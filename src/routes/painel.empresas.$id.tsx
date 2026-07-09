import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useAdmin } from "@/hooks/use-admin";
import { getMyCompany, updateMyCompany, deleteMyCompany, listCities, type CompanyPatch } from "@/lib/panel";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { DIFFERENTIAL_OPTIONS } from "@/components/site/CompanyProfileSections";
import { ChevronLeft, Trash2, ExternalLink } from "lucide-react";

const CERT_FIELDS: { key: string; label: string }[] = [
  { key: "cnpj", label: "CNPJ validado" },
  { key: "cpf_responsavel", label: "CPF do responsável" },
  { key: "google_maps", label: "Google Maps" },
  { key: "crea", label: "CREA" },
  { key: "cau", label: "CAU" },
  { key: "nota_fiscal", label: "Emite nota fiscal" },
  { key: "garantia", label: "Oferece garantia" },
];

const BADGE_OPTIONS: { key: string; label: string }[] = [
  { key: "mais_contratado", label: "Mais contratado" },
  { key: "top_atendimento", label: "Top atendimento" },
  { key: "entrega_garantida", label: "Entrega garantida" },
  { key: "especialista", label: "Especialista" },
  { key: "parceiro", label: "Parceiro AgendaAqui" },
  { key: "top_10", label: "Top 10 da cidade" },
];

const QUALITY_FIELDS: { key: string; label: string }[] = [
  { key: "quality", label: "Qualidade" },
  { key: "punctuality", label: "Pontualidade" },
  { key: "service", label: "Atendimento" },
  { key: "cleanliness", label: "Limpeza" },
  { key: "value", label: "Custo-benefício" },
];

export const Route = createFileRoute("/painel/empresas/$id")({
  component: EditarEmpresa,
});

function EditarEmpresa() {
  const { id } = Route.useParams();
  const { userId, loading } = useAdmin();
  const nav = useNavigate();
  const qc = useQueryClient();
  const cities = useQuery({ queryKey: ["cities"], queryFn: listCities });
  const company = useQuery({
    queryKey: ["panel-company", id, userId],
    queryFn: () => getMyCompany(userId!, id),
    enabled: !!userId && !!id,
  });

  const [form, setForm] = useState<CompanyPatch>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (company.data) {
      const c = company.data as Record<string, unknown>;
      setForm({
        name: c.name as string, tagline: (c.tagline as string) ?? "", description: (c.description as string) ?? "",
        phone: (c.phone as string) ?? "", whatsapp: (c.whatsapp as string) ?? "", email: (c.email as string) ?? "",
        address: (c.address as string) ?? "", zip: (c.zip as string) ?? "", city_id: (c.city_id as string) ?? "",
        website: (c.website as string) ?? "", instagram: (c.instagram as string) ?? "", facebook: (c.facebook as string) ?? "",
        tiktok: (c.tiktok as string) ?? "", youtube: (c.youtube as string) ?? "",
        logo_url: (c.logo_url as string) ?? "", banner_url: (c.banner_url as string) ?? "", video_url: (c.video_url as string) ?? "",
        status: (c.status as string) ?? "pending",
        founded_year: (c.founded_year as number) ?? null,
        response_time_minutes: (c.response_time_minutes as number) ?? null,
        response_rate: (c.response_rate as number) ?? null,
        services_completed: (c.services_completed as number) ?? null,
        clients_served: (c.clients_served as number) ?? null,
        price_range: (c.price_range as number) ?? null,
        tour_360_url: (c.tour_360_url as string) ?? "",
        catalog_url: (c.catalog_url as string) ?? "",
        pricebook_url: (c.pricebook_url as string) ?? "",
        portfolio_pdf_url: (c.portfolio_pdf_url as string) ?? "",
        coverage_cities: (c.coverage_cities as string[]) ?? [],
        differentials: (c.differentials as string[]) ?? [],
        badges: (c.badges as string[]) ?? [],
        certifications: (c.certifications as Record<string, boolean>) ?? {},
        quality_scores: (c.quality_scores as Record<string, number>) ?? {},
        promotions: (c.promotions as unknown[]) ?? [],
        financing_info: (c.financing_info as Record<string, unknown>) ?? {},
      });
    }
  }, [company.data]);

  if (loading || company.isLoading) return <div className="text-sm text-muted-foreground">Carregando…</div>;
  if (!company.data) {
    return (
      <div className="rounded-xl border border-dashed p-8 text-center">
        <p className="text-sm text-muted-foreground">Empresa não encontrada ou você não tem acesso.</p>
        <Link to="/painel/empresas"><Button variant="ghost" className="mt-3">Voltar</Button></Link>
      </div>
    );
  }

  function set<K extends keyof CompanyPatch>(k: K, v: CompanyPatch[K]) {
    setForm((f) => ({ ...f, [k]: v }));
  }

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    try {
      await updateMyCompany(id, form);
      toast.success("Alterações salvas");
      qc.invalidateQueries({ queryKey: ["panel-company", id] });
      qc.invalidateQueries({ queryKey: ["panel-companies"] });
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (!confirm("Excluir esta empresa permanentemente?")) return;
    try {
      await deleteMyCompany(id);
      toast.success("Empresa excluída");
      qc.invalidateQueries({ queryKey: ["panel-companies"] });
      nav({ to: "/painel/empresas" });
    } catch (err) {
      toast.error((err as Error).message);
    }
  }

  const slug = (company.data as { slug: string }).slug;

  return (
    <div className="max-w-3xl">
      <Link to="/painel/empresas" className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ChevronLeft className="h-4 w-4" /> Voltar</Link>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold">{form.name || "Empresa"}</h1>
          <p className="text-xs text-muted-foreground">/empresa/{slug}</p>
        </div>
        <div className="flex gap-2">
          <Link to="/empresa/$slug" params={{ slug }} target="_blank"><Button variant="outline" size="sm" className="gap-1"><ExternalLink className="h-4 w-4" /> Ver</Button></Link>
          <Button variant="ghost" size="sm" className="gap-1 text-destructive" onClick={remove}><Trash2 className="h-4 w-4" /> Excluir</Button>
        </div>
      </div>

      <form onSubmit={save} className="mt-6 space-y-5 rounded-xl border border-border bg-card p-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <Label>Nome *</Label>
            <Input value={form.name ?? ""} onChange={(e) => set("name", e.target.value)} required />
          </div>
          <div className="sm:col-span-2">
            <Label>Slogan</Label>
            <Input value={form.tagline ?? ""} onChange={(e) => set("tagline", e.target.value)} />
          </div>
          <div className="sm:col-span-2">
            <Label>Descrição</Label>
            <Textarea rows={5} value={form.description ?? ""} onChange={(e) => set("description", e.target.value)} />
          </div>
          <div>
            <Label>Status</Label>
            <Select value={form.status ?? "pending"} onValueChange={(v) => set("status", v)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="active">Ativa</SelectItem>
                <SelectItem value="pending">Pendente</SelectItem>
                <SelectItem value="paused">Pausada</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Cidade</Label>
            <Select value={form.city_id ?? ""} onValueChange={(v) => set("city_id", v)}>
              <SelectTrigger><SelectValue placeholder="Selecione…" /></SelectTrigger>
              <SelectContent>
                {(cities.data ?? []).map((c) => <SelectItem key={c.id} value={c.id}>{c.name} — {c.state}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="sm:col-span-2">
            <Label>Endereço</Label>
            <Input value={form.address ?? ""} onChange={(e) => set("address", e.target.value)} />
          </div>
          <div>
            <Label>CEP</Label>
            <Input value={form.zip ?? ""} onChange={(e) => set("zip", e.target.value)} />
          </div>
          <div>
            <Label>Telefone</Label>
            <Input value={form.phone ?? ""} onChange={(e) => set("phone", e.target.value)} />
          </div>
          <div>
            <Label>WhatsApp</Label>
            <Input value={form.whatsapp ?? ""} onChange={(e) => set("whatsapp", e.target.value)} />
          </div>
          <div>
            <Label>E-mail</Label>
            <Input type="email" value={form.email ?? ""} onChange={(e) => set("email", e.target.value)} />
          </div>
          <div>
            <Label>Website</Label>
            <Input value={form.website ?? ""} onChange={(e) => set("website", e.target.value)} placeholder="https://" />
          </div>
          <div>
            <Label>Instagram</Label>
            <Input value={form.instagram ?? ""} onChange={(e) => set("instagram", e.target.value)} />
          </div>
          <div>
            <Label>Facebook</Label>
            <Input value={form.facebook ?? ""} onChange={(e) => set("facebook", e.target.value)} />
          </div>
          <div>
            <Label>Logo (URL)</Label>
            <Input value={form.logo_url ?? ""} onChange={(e) => set("logo_url", e.target.value)} placeholder="https://" />
          </div>
          <div>
            <Label>Banner (URL)</Label>
            <Input value={form.banner_url ?? ""} onChange={(e) => set("banner_url", e.target.value)} placeholder="https://" />
          </div>
          <div className="sm:col-span-2">
            <Label>Vídeo (URL do YouTube)</Label>
            <Input value={form.video_url ?? ""} onChange={(e) => set("video_url", e.target.value)} placeholder="https://youtube.com/…" />
          </div>
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <Button type="submit" disabled={saving}>{saving ? "Salvando…" : "Salvar alterações"}</Button>
        </div>
      </form>
    </div>
  );
}
