import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { AlertTriangle, BarChart3, Database, Download, Plus, RefreshCw, Save, Trash2, Upload } from "lucide-react";
import { DataState } from "@/components/site/DomainWidgets";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  adsApi,
  backupApi,
  editorialApi,
  jobsApi,
  liveFeedApi,
  promotionsApi,
  requestsApi,
  tourismApi,
  type AdCampaign,
  type Attraction,
  type EditorialPost,
  type Job,
  type JobSource,
  type Promotion,
  type UserRequest,
} from "@/lib/domain-api";
import { parseLoadedBlacklist } from "@/lib/frontend-domain-helpers";

function Header({ title, description, children }: { title: string; description: string; children?: React.ReactNode }) {
  return <header className="mb-6 flex flex-wrap items-end justify-between gap-3"><div><h1 className="font-display text-2xl font-bold">{title}</h1><p className="mt-1 text-sm text-muted-foreground">{description}</p></div>{children}</header>;
}

function DeleteButton({ action, label = "Excluir" }: { action: () => Promise<unknown>; label?: string }) {
  const [pending, setPending] = useState(false);
  return <Button variant="destructive" size="sm" disabled={pending} onClick={async () => { if (!window.confirm(`${label} este registro?`)) return; setPending(true); try { await action(); toast.success("Registro removido."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao remover."); } finally { setPending(false); } }}><Trash2 className="mr-1 h-3 w-3" />{pending ? "Aguarde…" : label}</Button>;
}

export function JobsAdmin() {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ["admin-jobs"], queryFn: () => jobsApi.adminList({ limit: 100 }) });
  const sources = useQuery({ queryKey: ["admin-job-sources"], queryFn: jobsApi.sources });
  const logs = useQuery({ queryKey: ["admin-job-logs"], queryFn: jobsApi.logs });
  const [editing, setEditing] = useState<Job | null>(null);
  const [sourceEditing, setSourceEditing] = useState<JobSource | null>(null);
  const [syncing, setSyncing] = useState<string | null>(null);
  async function refreshJobs() {
    await Promise.all([
      client.invalidateQueries({ queryKey: ["admin-jobs"] }),
      client.invalidateQueries({ queryKey: ["admin-job-logs"] }),
    ]);
  }
  return <div><Header title="Empregos" description="Gerencie todos os campos das vagas, fontes, sincronizações e logs." />
    <JobEditor job={editing} sources={sources.data?.sources ?? []} onCancel={() => setEditing(null)} onSaved={async () => { setEditing(null); await refreshJobs(); }} />
    <section className="mb-6 rounded-xl border bg-card p-4"><div className="flex items-center justify-between gap-3"><h2 className="font-semibold">Fontes de vagas</h2><Button size="sm" variant="outline" onClick={() => setSourceEditing({ id: "", slug: "", name: "", kind: "manual", config: {}, is_active: true, sync_frequency_minutes: 60 })}><Plus className="mr-1 h-3 w-3" />Nova fonte</Button></div>{sourceEditing && <SourceEditor source={sourceEditing} onCancel={() => setSourceEditing(null)} onSaved={async () => { setSourceEditing(null); await client.invalidateQueries({ queryKey: ["admin-job-sources"] }); }} />}<DataState loading={sources.isLoading} error={sources.error} empty={!sources.data?.sources.length}><div className="mt-4 space-y-2">{sources.data?.sources.map((source) => <article key={source.id} className="flex flex-wrap items-center gap-3 rounded-lg border p-3"><div className="min-w-0 flex-1"><h3 className="font-medium">{source.name}</h3><p className="text-xs text-muted-foreground">{source.slug} · {source.kind} · a cada {source.sync_frequency_minutes} min</p></div><Badge variant={source.is_active ? "default" : "secondary"}>{source.is_active ? "Ativa" : "Inativa"}</Badge><Button size="sm" variant="outline" onClick={() => setSourceEditing(source)}>Editar</Button><Button size="sm" variant="outline" disabled={syncing === source.id || source.kind === "manual"} onClick={async () => { setSyncing(source.id); try { const result = await jobsApi.sync(source.id); toast.success(`Sincronização concluída: ${String(result.inserted ?? 0)} inseridas, ${String(result.updated ?? 0)} atualizadas.`); await refreshJobs(); } catch (error) { toast.error(error instanceof Error ? error.message : "Adaptador indisponível."); } finally { setSyncing(null); } }}><RefreshCw className={`mr-1 h-3 w-3 ${syncing === source.id ? "animate-spin" : ""}`} />Sincronizar</Button><DeleteButton action={async () => { await jobsApi.removeSource(source.id); await client.invalidateQueries({ queryKey: ["admin-job-sources"] }); }} /></article>)}</div></DataState></section>
    <section className="mb-6"><h2 className="mb-3 font-display text-lg font-bold">Vagas cadastradas</h2><DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="space-y-3">{query.data?.rows.map((job) => <article key={job.id} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h3 className="font-semibold">{job.title}</h3><p className="text-sm text-muted-foreground">{job.company_name || "Sem empresa"} · {job.location_city || "Sem cidade"}{job.is_premium ? " · Premium" : ""}</p></div><Badge variant={job.is_active ? "default" : "secondary"}>{job.is_active ? "Ativa" : "Inativa"}</Badge><Button variant="outline" size="sm" onClick={() => setEditing(job)}>Editar</Button><Button variant="outline" size="sm" onClick={async () => { try { await jobsApi.toggle(job.id, !job.is_active); await refreshJobs(); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao alterar status."); } }}>{job.is_active ? "Desativar" : "Ativar"}</Button><DeleteButton action={async () => { await jobsApi.remove(job.id); await refreshJobs(); }} /></article>)}</div></DataState></section>
    <section><h2 className="mb-3 font-display text-lg font-bold">Últimas sincronizações</h2><DataState loading={logs.isLoading} error={logs.error} empty={!logs.data?.logs.length}><div className="space-y-2">{logs.data?.logs.map((log) => <article key={String(log.id)} className="flex flex-wrap gap-3 rounded-lg border bg-card p-3 text-sm"><strong>{String(log.source_name ?? "Fonte removida")}</strong><Badge variant={String(log.status) === "success" ? "default" : "secondary"}>{String(log.status)}</Badge><span className="text-muted-foreground">{String(log.started_at ?? "")}</span><span className="ml-auto text-muted-foreground">{String(log.inserted ?? 0)} inseridas · {String(log.updated ?? 0)} atualizadas · {String(log.errors ?? 0)} erros</span></article>)}</div></DataState></section>
  </div>;
}

const emptyJob = {
  id: "", source_id: "", external_id: "", title: "", company_name: "", description: "",
  location_city: "", location_state: "MG", is_remote: false, employment_type: "",
  experience_level: "", salary_min: "", salary_max: "", salary_currency: "BRL",
  apply_url: "", category: "", tags: "", posted_at: "", expires_at: "", is_active: true,
  is_premium: false, company_id: "", company_logo_url: "", company_size: "",
  company_culture: "", requirements: "", nice_to_have: "", benefits: "",
  responsibilities: "", workload: "", apply_email: "", apply_whatsapp: "",
  application_deadline: "", featured_until: "",
};

function JobEditor({ job, sources, onCancel, onSaved }: { job: Job | null; sources: JobSource[]; onCancel: () => void; onSaved: () => Promise<void> }) {
  const [form, setForm] = useState(emptyJob);
  const [pending, setPending] = useState(false);
  useEffect(() => {
    if (!job) { setForm(emptyJob); return; }
    const text = (value: unknown) => Array.isArray(value) ? value.join("\n") : String(value ?? "");
    setForm({
      ...emptyJob,
      ...job,
      id: job.id,
      source_id: text(job.source_id),
      external_id: text(job.external_id),
      company_name: text(job.company_name),
      description: text(job.description),
      location_city: text(job.location_city),
      location_state: text(job.location_state),
      employment_type: text(job.employment_type),
      experience_level: text(job.experience_level),
      salary_min: text(job.salary_min),
      salary_max: text(job.salary_max),
      salary_currency: text(job.salary_currency || "BRL"),
      apply_url: text(job.apply_url),
      category: text(job.category),
      tags: text(job.tags),
      posted_at: text(job.posted_at).slice(0, 16),
      expires_at: text(job.expires_at).slice(0, 16),
      company_id: text(job.company_id),
      company_logo_url: text(job.company_logo_url),
      company_size: text(job.company_size),
      company_culture: text(job.company_culture),
      requirements: text(job.requirements),
      nice_to_have: text(job.nice_to_have),
      benefits: text(job.benefits),
      responsibilities: text(job.responsibilities),
      workload: text(job.workload),
      apply_email: text(job.apply_email),
      apply_whatsapp: text(job.apply_whatsapp),
      application_deadline: text(job.application_deadline).slice(0, 10),
      featured_until: text(job.featured_until).slice(0, 16),
      is_active: job.is_active ?? true,
      is_premium: job.is_premium,
      is_remote: job.is_remote,
    });
  }, [job]);
  const set = (key: keyof typeof emptyJob, value: string | boolean) => setForm((current) => ({ ...current, [key]: value }));
  async function save(event: React.FormEvent) {
    event.preventDefault();
    const salaryMin = form.salary_min === "" ? null : Number(form.salary_min);
    const salaryMax = form.salary_max === "" ? null : Number(form.salary_max);
    if (salaryMin !== null && salaryMax !== null && salaryMin > salaryMax) return toast.error("O salário mínimo não pode superar o máximo.");
    setPending(true);
    try {
      const lines = (value: string) => value.split(/\r?\n/).map((item) => item.trim()).filter(Boolean);
      await jobsApi.save({
        ...form,
        id: form.id || undefined,
        source_id: form.source_id || null,
        external_id: form.external_id || null,
        company_name: form.company_name || null,
        description: form.description || null,
        salary_min: salaryMin,
        salary_max: salaryMax,
        tags: lines(form.tags),
        requirements: lines(form.requirements),
        nice_to_have: lines(form.nice_to_have),
        benefits: lines(form.benefits),
        responsibilities: lines(form.responsibilities),
      });
      setForm(emptyJob);
      await onSaved();
      toast.success("Vaga salva.");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Falha ao salvar a vaga.");
    } finally {
      setPending(false);
    }
  }
  return <form onSubmit={save} className="mb-6 grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-3"><h2 className="font-semibold sm:col-span-2 lg:col-span-3">{form.id ? "Editar vaga" : "Nova vaga"}</h2><Input value={form.title} onChange={(e) => set("title", e.target.value)} placeholder="Título *" minLength={3} required /><Input value={form.company_name} onChange={(e) => set("company_name", e.target.value)} placeholder="Empresa" /><select value={form.source_id} onChange={(e) => set("source_id", e.target.value)} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="">Fonte manual/sem fonte</option>{sources.map((source) => <option key={source.id} value={source.id}>{source.name}</option>)}</select><Input value={form.external_id} onChange={(e) => set("external_id", e.target.value)} placeholder="ID externo" /><Input value={form.location_city} onChange={(e) => set("location_city", e.target.value)} placeholder="Cidade" /><Input value={form.location_state} maxLength={8} onChange={(e) => set("location_state", e.target.value)} placeholder="UF" /><Input value={form.employment_type} onChange={(e) => set("employment_type", e.target.value)} placeholder="Tipo de contrato" /><Input value={form.experience_level} onChange={(e) => set("experience_level", e.target.value)} placeholder="Experiência" /><Input value={form.category} onChange={(e) => set("category", e.target.value)} placeholder="Categoria" /><Input type="number" min={0} value={form.salary_min} onChange={(e) => set("salary_min", e.target.value)} placeholder="Salário mínimo" /><Input type="number" min={0} value={form.salary_max} onChange={(e) => set("salary_max", e.target.value)} placeholder="Salário máximo" /><Input value={form.salary_currency} onChange={(e) => set("salary_currency", e.target.value)} placeholder="Moeda" /><Input type="url" value={form.apply_url} onChange={(e) => set("apply_url", e.target.value)} placeholder="URL de candidatura" /><Input type="email" value={form.apply_email} onChange={(e) => set("apply_email", e.target.value)} placeholder="E-mail de candidatura" /><Input value={form.apply_whatsapp} onChange={(e) => set("apply_whatsapp", e.target.value)} placeholder="WhatsApp" /><Input value={form.workload} onChange={(e) => set("workload", e.target.value)} placeholder="Carga horária" /><Input type="datetime-local" value={form.posted_at} onChange={(e) => set("posted_at", e.target.value)} /><Input type="datetime-local" value={form.expires_at} onChange={(e) => set("expires_at", e.target.value)} /><Input type="date" value={form.application_deadline} onChange={(e) => set("application_deadline", e.target.value)} /><Input type="datetime-local" value={form.featured_until} onChange={(e) => set("featured_until", e.target.value)} /><Input value={form.company_id} onChange={(e) => set("company_id", e.target.value)} placeholder="ID da empresa vinculada" /><Input type="url" value={form.company_logo_url} onChange={(e) => set("company_logo_url", e.target.value)} placeholder="Logo da empresa" /><Input value={form.company_size} onChange={(e) => set("company_size", e.target.value)} placeholder="Porte da empresa" /><Textarea value={form.description} onChange={(e) => set("description", e.target.value)} placeholder="Descrição" className="sm:col-span-2 lg:col-span-3" /><Textarea value={form.company_culture} onChange={(e) => set("company_culture", e.target.value)} placeholder="Cultura da empresa" /><Textarea value={form.tags} onChange={(e) => set("tags", e.target.value)} placeholder="Tags, uma por linha" /><Textarea value={form.requirements} onChange={(e) => set("requirements", e.target.value)} placeholder="Requisitos, um por linha" /><Textarea value={form.nice_to_have} onChange={(e) => set("nice_to_have", e.target.value)} placeholder="Diferenciais, um por linha" /><Textarea value={form.benefits} onChange={(e) => set("benefits", e.target.value)} placeholder="Benefícios, um por linha" /><Textarea value={form.responsibilities} onChange={(e) => set("responsibilities", e.target.value)} placeholder="Responsabilidades, uma por linha" /><div className="flex flex-wrap gap-4 sm:col-span-2 lg:col-span-3"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_remote} onChange={(e) => set("is_remote", e.target.checked)} />Remota</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_premium} onChange={(e) => set("is_premium", e.target.checked)} />Premium</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_active} onChange={(e) => set("is_active", e.target.checked)} />Ativa</label></div><div className="flex gap-2 sm:col-span-2 lg:col-span-3"><Button disabled={pending}><Save className="mr-2 h-4 w-4" />{pending ? "Salvando…" : "Salvar vaga"}</Button>{form.id && <Button type="button" variant="outline" onClick={onCancel}>Cancelar</Button>}</div></form>;
}

function SourceEditor({ source, onCancel, onSaved }: { source: JobSource; onCancel: () => void; onSaved: () => Promise<void> }) {
  const [form, setForm] = useState({ ...source, configText: JSON.stringify(source.config ?? {}, null, 2) });
  const [pending, setPending] = useState(false);
  async function save(event: React.FormEvent) {
    event.preventDefault();
    let config: Record<string, unknown>;
    try {
      const parsed: unknown = JSON.parse(form.configText || "{}");
      if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) throw new Error();
      config = parsed as Record<string, unknown>;
    } catch {
      return toast.error("A configuração precisa ser um objeto JSON válido.");
    }
    setPending(true);
    try {
      await jobsApi.saveSource({ ...form, id: form.id || undefined, config });
      await onSaved();
      toast.success("Fonte salva.");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Falha ao salvar a fonte.");
    } finally {
      setPending(false);
    }
  }
  return <form onSubmit={save} className="mt-4 grid gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2"><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Nome *" required /><Input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} placeholder="Slug allowlisted *" required /><select value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value as JobSource["kind"] })} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="manual">Manual</option><option value="api">API allowlisted</option><option value="scrape">Scraper allowlisted</option></select><Input type="number" min={5} max={43200} value={form.sync_frequency_minutes} onChange={(e) => setForm({ ...form, sync_frequency_minutes: Number(e.target.value) })} /><Textarea value={form.configText} onChange={(e) => setForm({ ...form, configText: e.target.value })} className="sm:col-span-2" placeholder="Configuração JSON" /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />Fonte ativa</label><div className="flex gap-2"><Button size="sm" disabled={pending}><Save className="mr-1 h-3 w-3" />Salvar fonte</Button><Button type="button" size="sm" variant="outline" onClick={onCancel}>Cancelar</Button></div></form>;
}

export function TourismAdmin() {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ["admin-tourism"], queryFn: () => tourismApi.admin() });
  const [form, setForm] = useState({ title: "", description: "", category: "geral" });
  async function save(event: React.FormEvent) { event.preventDefault(); try { await tourismApi.save({ ...form, is_active: true, sort_order: 0 }); setForm({ title: "", description: "", category: "geral" }); await client.invalidateQueries({ queryKey: ["admin-tourism"] }); toast.success("Atração salva."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao salvar."); } }
  return <div><Header title="Turismo" description="Cadastre atrações exibidas no roteiro turístico." /><form onSubmit={save} className="mb-6 space-y-3 rounded-xl border bg-card p-4"><div className="grid gap-3 sm:grid-cols-2"><Input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Nome da atração" required /><Input value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} placeholder="Categoria" required /></div><Textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Descrição" required /><Button><Save className="mr-2 h-4 w-4" />Salvar</Button></form><DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="grid gap-3 md:grid-cols-2">{query.data?.rows.map((item: Attraction) => <article key={item.id} className="rounded-xl border bg-card p-4"><h2 className="font-semibold">{item.title}</h2><p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{item.description}</p><div className="mt-3"><DeleteButton action={async () => { await tourismApi.remove(item.id); await client.invalidateQueries({ queryKey: ["admin-tourism"] }); }} /></div></article>)}</div></DataState></div>;
}

export function PromotionsAdmin() {
  const client = useQueryClient();
  const [entity, setEntity] = useState<"promotions" | "coupons">("promotions");
  const query = useQuery({ queryKey: ["admin-promotions", entity], queryFn: () => promotionsApi.admin(entity) });
  const [form, setForm] = useState({ title: "", company_id: "", code: "", description: "" });
  async function save(event: React.FormEvent) { event.preventDefault(); try { await promotionsApi.save(entity, { ...form, status: "published" }); setForm({ title: "", company_id: "", code: "", description: "" }); await client.invalidateQueries({ queryKey: ["admin-promotions", entity] }); toast.success("Registro salvo."); } catch (error) { toast.error(error instanceof Error ? error.message : "Revise os campos obrigatórios."); } }
  return <div><Header title="Promoções e cupons" description="Gerencie ofertas vinculadas às empresas."><select value={entity} onChange={(e) => setEntity(e.target.value as typeof entity)} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="promotions">Promoções</option><option value="coupons">Cupons</option></select></Header><form onSubmit={save} className="mb-6 grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2"><Input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Título" required /><Input value={form.company_id} onChange={(e) => setForm({ ...form, company_id: e.target.value })} placeholder="ID da empresa" required />{entity === "coupons" && <Input value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} placeholder="Código do cupom" required />}<Input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Descrição" /><Button className="sm:col-span-2"><Save className="mr-2 h-4 w-4" />Salvar</Button></form><DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="space-y-3">{query.data?.rows.map((item: Promotion) => <article key={item.id} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h2 className="font-semibold">{item.title}</h2><p className="text-sm text-muted-foreground">{item.company_name || item.company_id || "Sem empresa"}{item.code ? ` · ${item.code}` : ""}</p></div><Badge>{item.status}</Badge><DeleteButton action={async () => { await promotionsApi.remove(entity, item.id); await client.invalidateQueries({ queryKey: ["admin-promotions", entity] }); }} /></article>)}</div></DataState></div>;
}

export function AdsAdmin({ analyticsOnly = false }: { analyticsOnly?: boolean }) {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ["admin-ads"], queryFn: adsApi.admin });
  const totals = useMemo(() => ({ impressions: query.data?.rows.reduce((sum, row) => sum + row.impressions, 0) ?? 0, clicks: query.data?.rows.reduce((sum, row) => sum + row.clicks, 0) ?? 0 }), [query.data]);
  const emptyForm = { id: "", name: "", image_url: "", link_url: "", city_slug: "", placement: "bottom-right", delay_seconds: 5, scroll_trigger_percent: 0, display_seconds: 7, weight: 1, starts_at: "", ends_at: "", route_patterns: "*", active: true };
  const [form, setForm] = useState(emptyForm);
  async function save(event: React.FormEvent) {
    event.preventDefault();
    try {
      await adsApi.save({
        ...form,
        id: form.id || undefined,
        city_slug: form.city_slug || null,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        route_patterns: form.route_patterns.split(/\r?\n|,/).map((value) => value.trim()).filter(Boolean),
      });
      setForm(emptyForm);
      await client.invalidateQueries({ queryKey: ["admin-ads"] });
      toast.success("Campanha salva.");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Falha ao salvar.");
    }
  }
  function edit(item: AdCampaign) {
    setForm({
      id: item.id,
      name: item.name,
      image_url: item.image_url,
      link_url: item.link_url,
      city_slug: item.city_slug ?? "",
      placement: item.placement,
      delay_seconds: item.delay_seconds,
      scroll_trigger_percent: item.scroll_trigger_percent,
      display_seconds: item.display_seconds,
      weight: item.weight,
      starts_at: item.starts_at?.slice(0, 16) ?? "",
      ends_at: item.ends_at?.slice(0, 16) ?? "",
      route_patterns: item.route_patterns.join("\n"),
      active: item.active,
    });
  }
  return <div><Header title={analyticsOnly ? "Analytics de anúncios" : "Anúncios"} description={analyticsOnly ? "Desempenho acumulado das campanhas." : "Campanhas exibidas no site."} />{analyticsOnly && <div className="mb-6 grid gap-4 sm:grid-cols-3"><Metric label="Impressões" value={totals.impressions} /><Metric label="Cliques" value={totals.clicks} /><Metric label="Taxa de clique" value={totals.impressions ? `${((totals.clicks/totals.impressions)*100).toFixed(2)}%` : "0%"} /></div>}{!analyticsOnly && <form onSubmit={save} className="mb-6 grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-3"><Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Nome" required /><Input value={form.image_url} onChange={(e) => setForm({ ...form, image_url: e.target.value })} placeholder="URL da imagem" required /><Input value={form.link_url} onChange={(e) => setForm({ ...form, link_url: e.target.value })} placeholder="URL de destino" required /><Input value={form.city_slug} onChange={(e) => setForm({ ...form, city_slug: e.target.value })} placeholder="Cidade (opcional)" /><select value={form.placement} onChange={(e) => setForm({ ...form, placement: e.target.value })} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="bottom-right">Inferior direito</option><option value="bottom-center">Inferior central</option><option value="center">Centro</option></select><Input type="number" min={1} max={100} value={form.weight} onChange={(e) => setForm({ ...form, weight: Number(e.target.value) })} placeholder="Peso" /><Input type="number" min={0} max={60} value={form.delay_seconds} onChange={(e) => setForm({ ...form, delay_seconds: Number(e.target.value) })} placeholder="Atraso (s)" /><Input type="number" min={0} max={100} value={form.scroll_trigger_percent} onChange={(e) => setForm({ ...form, scroll_trigger_percent: Number(e.target.value) })} placeholder="Rolagem (%)" /><Input type="number" min={3} max={60} value={form.display_seconds} onChange={(e) => setForm({ ...form, display_seconds: Number(e.target.value) })} placeholder="Exibição (s)" /><Input type="datetime-local" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} /><Input type="datetime-local" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.active} onChange={(e) => setForm({ ...form, active: e.target.checked })} /> Campanha ativa</label><Textarea value={form.route_patterns} onChange={(e) => setForm({ ...form, route_patterns: e.target.value })} placeholder="Rotas, uma por linha. Ex.: /empregos/*" className="sm:col-span-2 lg:col-span-3" /><div className="flex gap-2 sm:col-span-2 lg:col-span-3"><Button><Save className="mr-2 h-4 w-4" />{form.id ? "Atualizar campanha" : "Criar campanha"}</Button>{form.id && <Button type="button" variant="outline" onClick={() => setForm(emptyForm)}>Cancelar edição</Button>}</div></form>}<DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="space-y-3">{query.data?.rows.map((item: AdCampaign) => <article key={item.id} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h2 className="font-semibold">{item.name}</h2><p className="text-sm text-muted-foreground">{item.impressions} impressões · {item.clicks} cliques · {item.placement} · peso {item.weight}</p><p className="text-xs text-muted-foreground">{item.route_patterns.join(", ") || "Todas as rotas"}</p></div><Badge variant={item.active ? "default" : "secondary"}>{item.active ? "Ativa" : "Inativa"}</Badge>{!analyticsOnly && <><Button size="sm" variant="outline" onClick={() => edit(item)}>Editar</Button><DeleteButton action={async () => { await adsApi.remove(item.id); await client.invalidateQueries({ queryKey: ["admin-ads"] }); }} /></>}</article>)}</div></DataState></div>;
}

function Metric({ label, value }: { label: string; value: string | number }) { return <div className="rounded-xl border bg-card p-5"><p className="text-sm text-muted-foreground">{label}</p><p className="mt-2 text-3xl font-bold">{value}</p></div>; }

export function RequestsAdmin() {
  const client = useQueryClient();
  const query = useQuery({ queryKey: ["admin-requests"], queryFn: () => requestsApi.list() });
  return <div><Header title="Solicitações" description="Atenda pedidos enviados pelos formulários públicos." />{query.data?.stats && <div className="mb-6 grid gap-3 sm:grid-cols-4">{Object.entries(query.data.stats).map(([label,value]) => <Metric key={label} label={label} value={value} />)}</div>}<DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="space-y-3">{query.data?.rows.map((item: UserRequest) => <article key={item.id} className="rounded-xl border bg-card p-4"><div className="flex flex-wrap gap-2"><Badge>{item.status}</Badge><Badge variant="outline">{item.priority}</Badge><span className="ml-auto text-xs text-muted-foreground">{item.request_number}</span></div><h2 className="mt-2 font-semibold">{item.subject}</h2><p className="mt-1 text-sm text-muted-foreground">{item.description}</p><div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={async () => { await requestsApi.update({ id: item.id, status: "resolvido", priority: item.priority }); await client.invalidateQueries({ queryKey: ["admin-requests"] }); }}>Marcar resolvida</Button><DeleteButton action={async () => { await requestsApi.remove(item.id); await client.invalidateQueries({ queryKey: ["admin-requests"] }); }} /></div></article>)}</div></DataState></div>;
}

export function LiveModerationAdmin() {
  const client = useQueryClient();
  const visible = useQuery({ queryKey: ["admin-live-visible"], queryFn: () => liveFeedApi.list({ limit: 100 }) });
  const hidden = useQuery({ queryKey: ["admin-live-hidden"], queryFn: liveFeedApi.hidden });
  const blacklist = useQuery({ queryKey: ["admin-live-blacklist"], queryFn: liveFeedApi.blacklist });
  const [terms, setTerms] = useState("");
  const [loadedTerms, setLoadedTerms] = useState<string[] | null>(null);
  useEffect(() => {
    if (!blacklist.data) return;
    setLoadedTerms(blacklist.data.terms);
    setTerms(blacklist.data.terms.join("\n"));
  }, [blacklist.data]);
  async function refresh() {
    await Promise.all([
      client.invalidateQueries({ queryKey: ["admin-live-visible"] }),
      client.invalidateQueries({ queryKey: ["admin-live-hidden"] }),
    ]);
  }
  return <div><Header title="Moderação do Ao Vivo" description="Oculte itens visíveis, restaure itens moderados e mantenha termos bloqueados." /><section className="mb-6 rounded-xl border bg-card p-4"><Label htmlFor="blacklist">Termos bloqueados (um por linha)</Label>{blacklist.isLoading ? <p className="mt-2 text-sm text-muted-foreground">Carregando lista atual…</p> : blacklist.isError ? <p role="alert" className="mt-2 text-sm text-destructive">Não foi possível carregar a lista. O editor permanece bloqueado para não apagar valores existentes.</p> : <Textarea id="blacklist" value={terms} onChange={(e) => setTerms(e.target.value)} className="mt-2" />}<Button className="mt-3" disabled={loadedTerms === null} onClick={async () => { const next = parseLoadedBlacklist(loadedTerms, terms); if (next === null) return; try { await liveFeedApi.saveBlacklist(next); setLoadedTerms(next); setTerms(next.join("\n")); toast.success("Lista atualizada."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao salvar."); } }}><Save className="mr-2 h-4 w-4" />Salvar termos</Button></section><div className="grid gap-6 xl:grid-cols-2"><section><h2 className="mb-3 font-display text-lg font-bold">Itens visíveis</h2><DataState loading={visible.isLoading} error={visible.error} empty={!visible.data?.items.length}><div className="space-y-3">{visible.data?.items.map((item) => <article key={item.id} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h3 className="font-semibold">{item.title}</h3><p className="text-sm text-muted-foreground">{item.source} · {new Date(item.timestamp).toLocaleString("pt-BR")}</p></div><Button size="sm" variant="destructive" onClick={async () => { const reason = window.prompt("Motivo da ocultação (opcional)") ?? undefined; try { await liveFeedApi.hide(item.source, item.source_id, reason); await refresh(); toast.success("Item ocultado."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao ocultar."); } }}>Ocultar</Button></article>)}</div></DataState></section><section><h2 className="mb-3 font-display text-lg font-bold">Itens ocultos</h2><DataState loading={hidden.isLoading} error={hidden.error} empty={!hidden.data?.rows.length}><div className="space-y-3">{hidden.data?.rows.map((row) => <article key={String(row.id)} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h3 className="font-semibold">{String(row.source)} · {String(row.source_id)}</h3><p className="text-sm text-muted-foreground">{String(row.reason ?? "Sem motivo informado")}</p></div><Button size="sm" variant="outline" onClick={async () => { try { await liveFeedApi.unhide(String(row.source), String(row.source_id)); await refresh(); toast.success("Item restaurado."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao restaurar."); } }}>Restaurar</Button></article>)}</div></DataState></section></div></div>;
}

export function EditorialAdmin() {
  const client = useQueryClient();
  const month = new Date().toISOString().slice(0, 7);
  const query = useQuery({ queryKey: ["admin-editorial", month], queryFn: () => editorialApi.list(month) });
  const ai = useQuery({ queryKey: ["admin-editorial-ai"], queryFn: editorialApi.aiStatus });
  const [form, setForm] = useState({ publish_date: new Date().toISOString().slice(0,10), theme: "", caption: "", format: "Post" });
  async function save(event: React.FormEvent) { event.preventDefault(); try { await editorialApi.save({ ...form, status: "planejado", tags: [] }); setForm({ ...form, theme: "", caption: "" }); await client.invalidateQueries({ queryKey: ["admin-editorial", month] }); toast.success("Item planejado."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao salvar."); } }
  return <div><Header title="Calendário editorial" description="Planeje publicações por data, formato e campanha." />{ai.data && !ai.data.available && <div className="mb-6 flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/20 dark:text-amber-200"><AlertTriangle className="h-5 w-5 shrink-0" /><div><strong>Geração por IA indisponível.</strong><p>{ai.data.message}</p></div></div>}<form onSubmit={save} className="mb-6 grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2"><Input type="date" value={form.publish_date} onChange={(e) => setForm({ ...form, publish_date: e.target.value })} required /><Input value={form.format} onChange={(e) => setForm({ ...form, format: e.target.value })} placeholder="Formato" required /><Input value={form.theme} onChange={(e) => setForm({ ...form, theme: e.target.value })} placeholder="Tema" required /><Input value={form.caption} onChange={(e) => setForm({ ...form, caption: e.target.value })} placeholder="Legenda" required /><Button className="sm:col-span-2"><Save className="mr-2 h-4 w-4" />Planejar</Button></form><DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="space-y-3">{query.data?.rows.map((item: EditorialPost) => <article key={item.id} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h2 className="font-semibold">{item.theme}</h2><p className="text-sm text-muted-foreground">{new Date(`${item.publish_date}T12:00:00`).toLocaleDateString("pt-BR")} · {item.format}</p></div><Badge>{item.status}</Badge><DeleteButton action={async () => { await editorialApi.remove(item.id); await client.invalidateQueries({ queryKey: ["admin-editorial", month] }); }} /></article>)}</div></DataState></div>;
}

export function BackupAdmin() {
  const [file, setFile] = useState<File | null>(null);
  const [pending, setPending] = useState(false);
  return <div><Header title="Backup" description="Exporte e restaure somente as tabelas permitidas pelo servidor." /><div className="grid gap-5 md:grid-cols-2"><section className="rounded-xl border bg-card p-5"><Database className="h-8 w-8 text-primary" /><h2 className="mt-3 font-semibold">Exportar banco</h2><p className="mt-1 text-sm text-muted-foreground">O arquivo é gerado fora da área pública e validado pelo manifesto.</p><Button className="mt-4" disabled={pending} onClick={async () => { setPending(true); try { const result = await backupApi.export(); window.location.assign(result.download_url); toast.success("Backup gerado."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao exportar."); } finally { setPending(false); } }}><Download className="mr-2 h-4 w-4" />Gerar backup</Button></section><section className="rounded-xl border bg-card p-5"><Upload className="h-8 w-8 text-primary" /><h2 className="mt-3 font-semibold">Restaurar backup</h2><p className="mt-1 text-sm text-muted-foreground">Use apenas arquivos JSON exportados por esta instalação.</p><Input type="file" accept="application/json,.json" className="mt-4" onChange={(e) => setFile(e.target.files?.[0] ?? null)} /><Button className="mt-3" variant="destructive" disabled={!file || pending} onClick={async () => { if (!file || !window.confirm("Restaurar este backup? Os dados permitidos serão atualizados em transação.")) return; setPending(true); try { await backupApi.import(file); toast.success("Backup restaurado."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao restaurar."); } finally { setPending(false); } }}><Upload className="mr-2 h-4 w-4" />Restaurar</Button></section></div></div>;
}

export function AdapterStatusAdmin() {
  return (
    <div>
      <Header
        title="Scrapers e IA"
        description="Estas funções existiam no runtime Lovable (Node + TanStack Start). Na HostGator compartilhada elas não executam."
      />
      <div className="space-y-3">
        <Status
          title="Scrapers municipais"
          message="Não há crawler PHP. Não há Playwright/Node no cPanel. Fontes oficiais entram por CSV/JSON via tools/*-sync.php no cron, não por esta tela."
        />
        <Status
          title="Geração editorial por IA"
          message="O calendário editorial (CRUD) funciona em PHP. Chamadas a modelo de linguagem não estão ligadas neste servidor. editorialApi.ai_status responde unavailable até existir um provedor no load-env.php — hoje isso não dispara geração."
        />
      </div>
    </div>
  );
}

function Status({ title, message }: { title: string; message: string }) { return <section className="rounded-xl border bg-card p-5"><div className="flex items-center gap-2"><AlertTriangle className="h-5 w-5 text-amber-500" /><h2 className="font-semibold">{title}</h2><Badge variant="secondary">Indisponível</Badge></div><p className="mt-2 text-sm text-muted-foreground">{message}</p></section>; }

export function AnalyticsIcon() { return <BarChart3 className="h-4 w-4" />; }
