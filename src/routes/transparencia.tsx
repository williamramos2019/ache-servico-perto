import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Calendar, ExternalLink, FileText, Search } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState } from "@/components/site/DomainWidgets";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { procurementsApi } from "@/lib/domain-api";

export const Route = createFileRoute("/transparencia")({
  head: () => ({ meta: [{ title: "Editais e licitações — AgendaAqui" }] }),
  component: TransparencyPage,
});

function TransparencyPage() {
  const [q, setQ] = useState("");
  const [city, setCity] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ["procurements", q, city, status, page], queryFn: () => procurementsApi.list({ q, city, status, page, limit: 20 }) });
  return (
    <SiteLayout>
      <header className="border-b bg-gradient-to-br from-primary/10 via-background to-accent/5"><div className="container mx-auto px-4 py-12 text-center"><FileText className="mx-auto h-8 w-8 text-primary" /><h1 className="mt-3 font-display text-4xl font-extrabold">Editais e licitações</h1><p className="mt-3 text-muted-foreground">Publicações municipais reunidas com links para as fontes oficiais.</p><div className="mx-auto mt-7 grid max-w-3xl gap-2 rounded-2xl border bg-card p-3 sm:grid-cols-[1fr_180px_150px]"><div className="relative"><Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" /><Input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} className="pl-9" placeholder="Objeto, processo ou título" /></div><select value={city} onChange={(e) => { setCity(e.target.value); setPage(1); }} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="">Todas as cidades</option><option value="vespasiano">Vespasiano</option><option value="sao-jose-da-lapa">São José da Lapa</option></select><select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="all">Todos</option><option value="open">Abertos</option><option value="suspended">Suspensos</option><option value="finished">Encerrados</option></select></div></div></header>
      <main className="container mx-auto px-4 py-10"><DataState loading={query.isLoading} error={query.error} empty={!query.data?.items.length}><div className="space-y-4">{query.data?.items.map((item) => <article key={item.id} className="rounded-2xl border bg-card p-5"><div className="flex flex-wrap items-start gap-3"><div className="min-w-0 flex-1"><div className="flex flex-wrap gap-2"><Badge>{item.status}</Badge>{item.modality && <Badge variant="secondary">{item.modality.replaceAll("_", " ")}</Badge>}<span className="text-xs text-muted-foreground">{item.city_name}</span></div><h2 className="mt-2 font-display text-lg font-bold">{item.title}</h2>{item.object && <p className="mt-1 text-sm text-muted-foreground">{item.object}</p>}</div><a href={item.source_url} target="_blank" rel="noreferrer nofollow"><Button>Portal oficial <ExternalLink className="ml-2 h-4 w-4" /></Button></a></div><div className="mt-4 flex flex-wrap gap-4 border-t pt-3 text-xs text-muted-foreground">{item.publish_date && <span><Calendar className="mr-1 inline h-3 w-3" />Publicado: {new Date(item.publish_date).toLocaleDateString("pt-BR")}</span>}{item.deadline_date && <span>Prazo: {new Date(item.deadline_date).toLocaleDateString("pt-BR")}</span>}{item.estimated_value != null && <strong>{item.estimated_value.toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}</strong>}</div></article>)}</div><div className="mt-8 flex justify-center gap-3"><Button variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Anterior</Button><Button variant="outline" disabled={!query.data || page * query.data.pageSize >= query.data.total} onClick={() => setPage((p) => p + 1)}>Próxima</Button></div></DataState></main>
    </SiteLayout>
  );
}
