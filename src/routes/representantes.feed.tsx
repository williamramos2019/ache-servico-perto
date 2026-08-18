import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { ExternalLink, Radio, RefreshCw } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState } from "@/components/site/DomainWidgets";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useSelectedCity } from "@/hooks/useSelectedCity";
import { representativesApi } from "@/lib/domain-api";

export const Route = createFileRoute("/representantes/feed")({ component: RepresentativeFeedPage });

function RepresentativeFeedPage() {
  const { city } = useSelectedCity();
  const [kind, setKind] = useState("");
  const query = useQuery({ queryKey: ["representative-feed", city, kind], queryFn: () => representativesApi.feed({ city, kind, sinceDays: 60, limit: 100 }), refetchInterval: 120_000 });
  return (
    <SiteLayout>
      <header className="border-b"><div className="container mx-auto px-4 py-9"><Link to="/representantes" className="text-sm text-muted-foreground">← Representantes</Link><h1 className="mt-3 font-display text-3xl font-extrabold"><Radio className="mr-2 inline h-6 w-6 text-red-500" />Feed de atividades</h1><p className="mt-2 text-muted-foreground">Atualizado automaticamente a cada dois minutos.</p></div></header>
      <main className="container mx-auto px-4 py-8">
        <div className="mb-5 flex gap-3"><select value={kind} onChange={(e) => setKind(e.target.value)} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="">Todos os tipos</option><option value="projeto_lei">Projetos de lei</option><option value="indicacao">Indicações</option><option value="requerimento">Requerimentos</option><option value="obra">Obras</option></select><Button variant="outline" onClick={() => query.refetch()} disabled={query.isFetching}><RefreshCw className={`mr-2 h-4 w-4 ${query.isFetching ? "animate-spin" : ""}`} />Atualizar</Button></div>
        <DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="space-y-3">{query.data?.rows.map((item) => <article key={item.id} className="rounded-xl border bg-card p-5"><div className="flex flex-wrap gap-2"><Badge variant="secondary">{item.kind.replaceAll("_", " ")}</Badge>{item.status && <Badge variant="outline">{item.status.replaceAll("_", " ")}</Badge>}<span className="ml-auto text-xs text-muted-foreground">{new Date(item.occurred_at).toLocaleString("pt-BR")}</span></div><h2 className="mt-2 font-semibold">{item.title}</h2>{item.description && <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>}<div className="mt-3 flex gap-4 text-sm">{item.representative && <Link to="/representantes/$id" params={{ id: item.representative.slug }} className="font-medium text-primary">{item.representative.name}</Link>}{item.source_url && <a href={item.source_url} target="_blank" rel="noreferrer" className="text-muted-foreground">Fonte <ExternalLink className="inline h-3 w-3" /></a>}</div></article>)}</div></DataState>
      </main>
    </SiteLayout>
  );
}
