import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { Compass, ExternalLink, MapPin } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState } from "@/components/site/DomainWidgets";
import { InlineShopeeStrip } from "@/components/site/InlineShopeeStrip";
import { Badge } from "@/components/ui/badge";
import { tourismApi, type Attraction } from "@/lib/domain-api";
import { mergeUniqueRows } from "@/lib/frontend-domain-helpers";

export const Route = createFileRoute("/roteiro-turistico")({
  head: () => ({ meta: [{ title: "Roteiro turístico — AgendaAqui" }, { name: "description", content: "Descubra atrações e roteiros em Vespasiano e São José da Lapa." }] }),
  component: TourismPage,
});

function TourismPage() {
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<Attraction[]>([]);
  const query = useQuery({ queryKey: ["tourism", page], queryFn: () => tourismApi.list({ page, limit: 12 }) });
  useEffect(() => {
    if (query.data) setRows((current) => mergeUniqueRows(current, query.data.rows));
  }, [query.data]);
  return (
    <SiteLayout>
      <header className="border-b bg-gradient-to-br from-emerald-500/10 via-background to-primary/10"><div className="container mx-auto px-4 py-14"><p className="text-sm font-semibold text-emerald-700"><Compass className="mr-2 inline h-4 w-4" />Roteiro turístico</p><h1 className="mt-2 font-display text-4xl font-extrabold">Aventura, cultura e sabores mineiros</h1><p className="mt-3 max-w-2xl text-muted-foreground">Lugares recomendados para montar seu passeio em Vespasiano e São José da Lapa.</p></div></header>
      <main className="container mx-auto px-4 py-10"><DataState loading={query.isLoading} error={query.error} empty={!rows.length}><div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{rows.map((item) => <article key={item.id} className="overflow-hidden rounded-2xl border bg-card">{item.image_url && <img src={item.image_url} alt="" className="aspect-video w-full object-cover" />}<div className="p-5"><div className="flex flex-wrap gap-2"><Badge variant="secondary">{item.category}</Badge>{item.tag && <Badge variant="outline">{item.tag}</Badge>}</div><h2 className="mt-3 font-display text-xl font-bold">{item.title}</h2><p className="mt-2 text-sm text-muted-foreground">{item.description}</p>{item.meta && <p className="mt-3 text-xs text-muted-foreground"><MapPin className="mr-1 inline h-3 w-3" />{item.meta}</p>}{item.link_url && <a href={item.link_url} target="_blank" rel="noreferrer" className="mt-3 inline-flex text-sm font-medium text-primary">Saber mais <ExternalLink className="ml-1 h-3 w-3" /></a>}</div></article>)}</div>{query.data && rows.length < query.data.total && <div className="mt-8 text-center"><button className="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted" disabled={query.isFetching} onClick={() => setPage((current) => current + 1)}>{query.isFetching ? "Carregando…" : "Carregar mais atrações"}</button></div>}</DataState>
        <InlineShopeeStrip hint="camping" title="Kit essencial pra sua aventura" subtitle="Mochila, lanterna e mais · links de parceiro" />
      </main>
    </SiteLayout>
  );
}
