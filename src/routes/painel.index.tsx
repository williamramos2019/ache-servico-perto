import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useAdmin } from "@/hooks/use-admin";
import { panelStats, listMyCompanies } from "@/lib/panel";
import { Building2, Mail, Star, Eye, Heart, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/painel/")({
  component: PanelHome,
});

function PanelHome() {
  const { userId } = useAdmin();
  const stats = useQuery({ queryKey: ["panel-stats", userId], queryFn: () => panelStats(userId!), enabled: !!userId });
  const companies = useQuery({ queryKey: ["panel-companies", userId], queryFn: () => listMyCompanies(userId!), enabled: !!userId });
  const s = stats.data;

  const cards = [
    { label: "Empresas", value: s?.companyCount ?? 0, icon: Building2 },
    { label: "Visualizações", value: s?.totalViews ?? 0, icon: Eye },
    { label: "Leads totais", value: s?.totalLeads ?? 0, icon: Mail, hint: `${s?.leads7d ?? 0} nos últimos 7 dias` },
    { label: "Avaliações", value: s?.totalReviews ?? 0, icon: Star },
    { label: "Favoritos salvos", value: s?.favoritesCount ?? 0, icon: Heart },
  ];

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold">Visão geral</h1>
          <p className="text-sm text-muted-foreground">Resumo das suas empresas e resultados no AgendaAqui.</p>
        </div>
        <Link to="/painel/empresas/nova"><Button size="sm" className="gap-1"><Plus className="h-4 w-4" /> Nova empresa</Button></Link>
      </div>

      <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        {cards.map((c) => (
          <div key={c.label} className="rounded-xl border border-border bg-card p-4">
            <div className="flex items-center gap-2 text-muted-foreground"><c.icon className="h-4 w-4" /><span className="text-xs uppercase tracking-wider">{c.label}</span></div>
            <div className="mt-2 text-2xl font-bold">{stats.isLoading ? "—" : c.value}</div>
            {c.hint ? <div className="text-xs text-muted-foreground">{c.hint}</div> : null}
          </div>
        ))}
      </div>

      <div className="mt-8">
        <h2 className="font-display text-lg font-bold">Suas empresas</h2>
        <div className="mt-3 space-y-2">
          {companies.isLoading ? (
            <div className="text-sm text-muted-foreground">Carregando…</div>
          ) : (companies.data ?? []).length === 0 ? (
            <div className="rounded-xl border border-dashed border-border bg-card p-8 text-center">
              <p className="text-sm text-muted-foreground">Você ainda não cadastrou nenhuma empresa.</p>
              <Link to="/painel/empresas/nova"><Button className="mt-4 gap-1"><Plus className="h-4 w-4" /> Cadastrar empresa</Button></Link>
            </div>
          ) : (
            (companies.data ?? []).slice(0, 5).map((c) => (
              <Link key={c.id} to="/painel/empresas/$id" params={{ id: c.id }} className="flex items-center justify-between gap-3 rounded-lg border border-border bg-card p-3 hover:bg-muted/50">
                <div className="min-w-0">
                  <div className="truncate font-medium">{c.name}</div>
                  <div className="text-xs text-muted-foreground">Status: {c.status ?? "—"} · Plano: {c.plan ?? "free"} · {c.views_count ?? 0} visualizações</div>
                </div>
                <div className="text-xs text-muted-foreground">Editar →</div>
              </Link>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
