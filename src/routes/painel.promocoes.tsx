import { createFileRoute } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { Save, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { promotionsApi, type Promotion } from "@/lib/domain-api";
import { listMyCompanies } from "@/lib/panel";

export const Route = createFileRoute("/painel/promocoes")({
  component: OwnerPromotionsPage,
});

const emptyForm = {
  id: "",
  company_id: "",
  title: "",
  description: "",
  code: "",
  category: "",
  discount_percent: "",
  image_url: "",
  link_url: "",
  valid_from: "",
  valid_to: "",
  status: "published",
};

function OwnerPromotionsPage() {
  const client = useQueryClient();
  const [entity, setEntity] = useState<"promotions" | "coupons">("promotions");
  const [form, setForm] = useState(emptyForm);
  const companies = useQuery({ queryKey: ["panel-companies-promotions"], queryFn: () => listMyCompanies("") });
  const records = useQuery({ queryKey: ["owner-promotions", entity], queryFn: () => promotionsApi.owner(entity, { limit: 100 }) });
  useEffect(() => {
    if (!form.company_id && companies.data?.[0]) {
      setForm((current) => ({ ...current, company_id: companies.data![0].id }));
    }
  }, [companies.data, form.company_id]);

  function edit(item: Promotion) {
    setForm({
      id: item.id,
      company_id: item.company_id ?? "",
      title: item.title,
      description: item.description ?? "",
      code: item.code ?? "",
      category: item.category ?? "",
      discount_percent: item.discount_percent == null ? "" : String(item.discount_percent),
      image_url: item.image_url ?? "",
      link_url: item.link_url ?? "",
      valid_from: item.valid_from?.slice(0, 16) ?? "",
      valid_to: item.valid_to?.slice(0, 16) ?? "",
      status: item.status,
    });
  }

  async function save(event: React.FormEvent) {
    event.preventDefault();
    if (!form.company_id) return toast.error("Cadastre ou selecione uma empresa.");
    try {
      await promotionsApi.save(entity, {
        ...form,
        id: form.id || undefined,
        description: form.description || null,
        category: form.category || null,
        discount_percent: form.discount_percent === "" ? null : Number(form.discount_percent),
        image_url: form.image_url || null,
        link_url: form.link_url || null,
        valid_from: form.valid_from || null,
        valid_to: form.valid_to || null,
      });
      setForm({ ...emptyForm, company_id: form.company_id });
      await client.invalidateQueries({ queryKey: ["owner-promotions", entity] });
      toast.success("Oferta salva.");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Não foi possível salvar.");
    }
  }

  if (companies.isLoading) return <p className="text-sm text-muted-foreground">Carregando suas empresas…</p>;
  if (companies.isError) return <p role="alert" className="text-sm text-destructive">Não foi possível carregar suas empresas.</p>;
  if (!companies.data?.length) return <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">Cadastre uma empresa antes de publicar promoções.</div>;

  return <div>
    <header className="mb-6"><h1 className="font-display text-2xl font-bold">Minhas promoções</h1><p className="mt-1 text-sm text-muted-foreground">Publique promoções e cupons somente para empresas da sua conta.</p></header>
    <div className="mb-4 flex gap-2"><Button type="button" variant={entity === "promotions" ? "default" : "outline"} onClick={() => { setEntity("promotions"); setForm({ ...emptyForm, company_id: form.company_id }); }}>Promoções</Button><Button type="button" variant={entity === "coupons" ? "default" : "outline"} onClick={() => { setEntity("coupons"); setForm({ ...emptyForm, company_id: form.company_id }); }}>Cupons</Button></div>
    <form onSubmit={save} className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2">
      <div><Label htmlFor="owner-company">Empresa</Label><select id="owner-company" value={form.company_id} onChange={(e) => setForm({ ...form, company_id: e.target.value })} className="mt-1 h-10 w-full rounded-md border bg-background px-3 text-sm">{companies.data.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select></div>
      <div><Label htmlFor="owner-title">Título</Label><Input id="owner-title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} minLength={2} required /></div>
      {entity === "coupons" && <div><Label htmlFor="owner-code">Código</Label><Input id="owner-code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} minLength={2} required /></div>}
      <div><Label htmlFor="owner-category">Categoria</Label><Input id="owner-category" value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} /></div>
      <div><Label htmlFor="owner-discount">Desconto (%)</Label><Input id="owner-discount" type="number" min={0} max={100} value={form.discount_percent} onChange={(e) => setForm({ ...form, discount_percent: e.target.value })} /></div>
      <div><Label htmlFor="owner-image">URL da imagem</Label><Input id="owner-image" type="url" value={form.image_url} onChange={(e) => setForm({ ...form, image_url: e.target.value })} /></div>
      <div><Label htmlFor="owner-link">URL de destino</Label><Input id="owner-link" type="url" value={form.link_url} onChange={(e) => setForm({ ...form, link_url: e.target.value })} /></div>
      <div><Label htmlFor="owner-from">Início</Label><Input id="owner-from" type="datetime-local" value={form.valid_from} onChange={(e) => setForm({ ...form, valid_from: e.target.value })} /></div>
      <div><Label htmlFor="owner-to">Fim</Label><Input id="owner-to" type="datetime-local" value={form.valid_to} onChange={(e) => setForm({ ...form, valid_to: e.target.value })} /></div>
      <div className="sm:col-span-2"><Label htmlFor="owner-description">Descrição</Label><Textarea id="owner-description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></div>
      <div className="flex gap-2 sm:col-span-2"><Button><Save className="mr-2 h-4 w-4" />{form.id ? "Atualizar" : "Publicar"}</Button>{form.id && <Button type="button" variant="outline" onClick={() => setForm({ ...emptyForm, company_id: form.company_id })}>Cancelar</Button>}</div>
    </form>
    <section className="mt-8"><h2 className="mb-3 font-display text-lg font-bold">Ofertas cadastradas</h2>{records.isLoading ? <p className="text-sm text-muted-foreground">Carregando…</p> : records.isError ? <p role="alert" className="text-sm text-destructive">Não foi possível carregar as ofertas.</p> : !records.data?.rows.length ? <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">Nenhuma oferta cadastrada.</p> : <div className="space-y-3">{records.data.rows.map((item) => <article key={item.id} className="flex flex-wrap items-center gap-3 rounded-xl border bg-card p-4"><div className="min-w-0 flex-1"><h3 className="font-semibold">{item.title}</h3><p className="text-sm text-muted-foreground">{item.company_name}{item.code ? ` · ${item.code}` : ""}</p></div><Badge>{item.status}</Badge><Button size="sm" variant="outline" onClick={() => edit(item)}>Editar</Button><Button size="sm" variant="destructive" onClick={async () => { if (!window.confirm("Excluir esta oferta?")) return; try { await promotionsApi.remove(entity, item.id); await client.invalidateQueries({ queryKey: ["owner-promotions", entity] }); toast.success("Oferta excluída."); } catch (error) { toast.error(error instanceof Error ? error.message : "Falha ao excluir."); } }}><Trash2 className="mr-1 h-3 w-3" />Excluir</Button></article>)}</div>}</section>
  </div>;
}
