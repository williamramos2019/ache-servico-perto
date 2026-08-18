import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import { ShieldCheck, Clock, CheckCircle2, XCircle, Loader2 } from "lucide-react";
import { listMyClaims } from "@/lib/claims";
import { useAdmin } from "@/hooks/use-admin";
import { Badge } from "@/components/ui/badge";

export const Route = createFileRoute("/painel/reivindicacoes")({
  head: () => ({
    meta: [
      { title: "Minhas Reivindicações — AgendaAqui" },
      { name: "robots", content: "noindex" },
    ],
  }),
  component: MyClaimsPage,
});

const STATUS: Record<string, { label: string; className: string; icon: typeof Clock }> = {
  pending: { label: "Em análise", className: "bg-amber-500/10 text-amber-700 dark:text-amber-300", icon: Clock },
  approved: { label: "Aprovada", className: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300", icon: CheckCircle2 },
  rejected: { label: "Rejeitada", className: "bg-rose-500/10 text-rose-700 dark:text-rose-300", icon: XCircle },
};

function MyClaimsPage() {
  const { userId } = useAdmin();
  const { data, isLoading } = useQuery({
    queryKey: ["my-claims", userId],
    queryFn: () => listMyClaims(userId!),
    enabled: !!userId,
  });

  const rows = data ?? [];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="flex items-center gap-2 font-display text-2xl font-bold">
          <ShieldCheck className="h-6 w-6 text-primary" />
          Minhas reivindicações
        </h1>
        <p className="text-sm text-muted-foreground">
          Acompanhe as solicitações que você enviou para gerenciar empresas.
        </p>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : rows.length === 0 ? (
        <div className="rounded-lg border bg-card p-8 text-center">
          <ShieldCheck className="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
          <p className="font-medium">Você ainda não fez nenhuma reivindicação</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Encontre a empresa na busca e clique em "Reivindicar esta empresa".
          </p>
        </div>
      ) : (
        <ul className="space-y-3">
          {rows.map((r) => {
            const status = STATUS[r.status] ?? STATUS.pending;
            const Icon = status.icon;
            return (
              <li key={r.id} className="rounded-lg border bg-card p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div className="min-w-0">
                    <Link
                      to="/empresa/$slug"
                      params={{ slug: r.companies?.slug ?? "" }}
                      className="font-medium hover:underline"
                    >
                      {r.companies?.name ?? "Empresa"}
                    </Link>
                    <div className="mt-1 text-xs text-muted-foreground">
                      Enviada em {format(new Date(r.created_at), "dd/MM/yyyy 'às' HH:mm", { locale: ptBR })}
                    </div>
                  </div>
                  <Badge variant="secondary" className={`inline-flex items-center gap-1 ${status.className}`}>
                    <Icon className="h-3.5 w-3.5" />
                    {status.label}
                  </Badge>
                </div>
                {r.admin_notes ? (
                  <div className="mt-3 rounded-md bg-muted/50 p-3 text-sm">
                    <div className="text-xs font-medium uppercase text-muted-foreground">Resposta do admin</div>
                    <p className="mt-1 whitespace-pre-wrap">{r.admin_notes}</p>
                  </div>
                ) : null}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
