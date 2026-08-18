import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Sparkles } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState } from "@/components/site/DomainWidgets";
import { PremiumJobCard, DEFAULT_SEARCH } from "@/features/jobs";
import { jobsApi } from "@/lib/domain-api";

export const Route = createFileRoute("/empregos/premium")({
  head: () => ({ meta: [{ title: "Vagas em destaque — AgendaAqui" }] }),
  component: PremiumJobsPage,
});

function PremiumJobsPage() {
  const query = useQuery({ queryKey: ["jobs-premium-all"], queryFn: () => jobsApi.premium({ limit: 30 }) });
  return (
    <SiteLayout>
      <header className="border-b bg-amber-500/10">
        <div className="container mx-auto px-4 py-10">
          <Link to="/empregos" search={DEFAULT_SEARCH} className="text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="mr-1 inline h-4 w-4" />Todas as vagas</Link>
          <h1 className="mt-4 font-display text-4xl font-extrabold"><Sparkles className="mr-2 inline h-8 w-8 text-amber-500" />Vagas em destaque</h1>
          <p className="mt-2 text-muted-foreground">Oportunidades com informações completas de requisitos, benefícios e candidatura.</p>
        </div>
      </header>
      <main className="container mx-auto px-4 py-10">
        <DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{query.data?.rows.map((job) => <PremiumJobCard key={job.id} job={job} />)}</div>
        </DataState>
      </main>
    </SiteLayout>
  );
}
