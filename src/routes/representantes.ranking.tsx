import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { CalendarCheck, Trophy, TrendingDown, TrendingUp } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState, RepresentativeAvatar } from "@/components/site/DomainWidgets";
import { useSelectedCity } from "@/hooks/useSelectedCity";
import { representativesApi, type RankingRow } from "@/lib/domain-api";

export const Route = createFileRoute("/representantes/ranking")({ component: RankingPage });

function RankingPage() {
  const { city } = useSelectedCity();
  const query = useQuery({ queryKey: ["representative-ranking", city], queryFn: () => representativesApi.ranking(city) });
  const rows = query.data?.rows ?? [];
  return (
    <SiteLayout>
      <header className="border-b"><div className="container mx-auto px-4 py-9"><Link to="/representantes" className="text-sm text-muted-foreground">← Representantes</Link><h1 className="mt-3 font-display text-3xl font-extrabold"><Trophy className="mr-2 inline h-7 w-7 text-amber-500" />Ranking do mês</h1><p className="mt-2 text-muted-foreground">Indicadores públicos baseados nos registros disponíveis.</p></div></header>
      <main className="container mx-auto px-4 py-8">
        <DataState loading={query.isLoading} error={query.error} empty={!rows.length}><div className="grid gap-5 lg:grid-cols-3"><Ranking title="Mais ativos" icon={<TrendingUp className="h-5 w-5 text-emerald-500" />} rows={[...rows].sort((a,b) => b.activities_count-a.activities_count)} value={(r) => `${r.activities_count} atividades`} /><Ranking title="Mais faltas" icon={<TrendingDown className="h-5 w-5 text-red-500" />} rows={[...rows].sort((a,b) => b.absences_count-a.absences_count)} value={(r) => `${r.absences_count} faltas`} /><Ranking title="Melhor assiduidade" icon={<CalendarCheck className="h-5 w-5 text-blue-500" />} rows={[...rows].sort((a,b) => b.attendance_rate-a.attendance_rate)} value={(r) => `${r.attendance_rate}%`} /></div></DataState>
      </main>
    </SiteLayout>
  );
}

function Ranking({ title, icon, rows, value }: { title: string; icon: React.ReactNode; rows: RankingRow[]; value: (row: RankingRow) => string }) {
  return <section className="rounded-xl border bg-card p-5"><h2 className="flex items-center gap-2 font-semibold">{icon}{title}</h2><ol className="mt-4 space-y-2">{rows.slice(0,10).map((row,index) => <li key={row.representative.id}><Link to="/representantes/$id" params={{ id: row.representative.slug }} className="flex items-center gap-3 rounded-lg p-2 hover:bg-muted"><span className="w-5 font-bold text-muted-foreground">{index+1}</span><RepresentativeAvatar representative={row.representative} className="h-9 w-9 text-xs" /><span className="min-w-0 flex-1 truncate text-sm font-medium">{row.representative.name}</span><span className="text-xs text-muted-foreground">{value(row)}</span></Link></li>)}</ol></section>;
}
