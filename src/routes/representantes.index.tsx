import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { MessageCircle, Radio, Trophy, Users } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState, RepresentativeCard, WhatsAppSubscribeDialog } from "@/components/site/DomainWidgets";
import { Button } from "@/components/ui/button";
import { useSelectedCity } from "@/hooks/useSelectedCity";
import { representativesApi } from "@/lib/domain-api";

export const Route = createFileRoute("/representantes/")({ component: RepresentativesPage });

function RepresentativesPage() {
  const { city } = useSelectedCity();
  const [role, setRole] = useState("");
  const [subscribe, setSubscribe] = useState(false);
  const query = useQuery({ queryKey: ["representatives", city, role], queryFn: () => representativesApi.list({ city, role, limit: 100 }) });
  return (
    <SiteLayout>
      <header className="border-b bg-gradient-to-br from-primary/10 via-background to-background">
        <div className="container mx-auto px-4 py-12">
          <p className="text-sm font-semibold text-primary">Transparência pública</p>
          <h1 className="mt-2 font-display text-4xl font-extrabold">Seus representantes municipais</h1>
          <p className="mt-3 max-w-2xl text-muted-foreground">Projetos, indicações, decretos e presença em sessões, organizados em linguagem simples.</p>
          <div className="mt-6 flex flex-wrap gap-3"><Button asChild><Link to="/representantes/feed"><Radio className="mr-2 h-4 w-4" />Feed de atividades</Link></Button><Button asChild variant="outline"><Link to="/representantes/ranking"><Trophy className="mr-2 h-4 w-4" />Ranking</Link></Button><Button variant="secondary" onClick={() => setSubscribe(true)}><MessageCircle className="mr-2 h-4 w-4" />Resumo no WhatsApp</Button></div>
        </div>
      </header>
      <main className="container mx-auto px-4 py-10">
        <div className="mb-6 flex flex-wrap items-center gap-3"><h2 className="font-display text-2xl font-bold"><Users className="mr-2 inline h-5 w-5" />Representantes</h2><select value={role} onChange={(e) => setRole(e.target.value)} className="ml-auto h-10 rounded-md border bg-background px-3 text-sm"><option value="">Todos os cargos</option><option value="prefeito">Prefeito</option><option value="vice_prefeito">Vice-prefeito</option><option value="vereador">Vereador</option></select></div>
        <DataState loading={query.isLoading} error={query.error} empty={!query.data?.rows.length}><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{query.data?.rows.map((representative) => <RepresentativeCard key={representative.id} representative={representative} />)}</div></DataState>
      </main>
      <WhatsAppSubscribeDialog open={subscribe} onOpenChange={setSubscribe} defaultCity={city} />
    </SiteLayout>
  );
}
