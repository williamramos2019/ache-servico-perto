import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { Check, X, ExternalLink, Clock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { adminListClaims, adminApproveClaim, adminRejectClaim, type ClaimStatus } from "@/lib/claims";

export const Route = createFileRoute("/admin/reivindicacoes")({
  component: AdminClaims,
});

const STATUS_LABEL: Record<string, string> = {
  pending: "Pendente",
  approved: "Aprovada",
  rejected: "Recusada",
};

function AdminClaims() {
  const qc = useQueryClient();
  const [status, setStatus] = useState<"all" | ClaimStatus>("pending");
  const [notes, setNotes] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState<string | null>(null);

  const list = useQuery({
    queryKey: ["admin-claims", status],
    queryFn: () => adminListClaims(status),
  });

  async function act(id: string, kind: "approve" | "reject") {
    if (kind === "reject" && !confirm("Recusar esta reivindicação?")) return;
    setBusy(id);
    try {
      if (kind === "approve") await adminApproveClaim(id, notes[id]);
      else await adminRejectClaim(id, notes[id]);
      toast.success(kind === "approve" ? "Reivindicação aprovada — empresa transferida" : "Reivindicação recusada");
      qc.invalidateQueries({ queryKey: ["admin-claims"] });
      qc.invalidateQueries({ queryKey: ["admin-companies"] });
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  const rows = list.data ?? [];

  return (
    <div>
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <h1 className="font-display text-2xl font-bold">Reivindicações de empresas</h1>
          <p className="text-sm text-muted-foreground">Aprove para transferir a empresa ao solicitante.</p>
        </div>
        <Select value={status} onValueChange={(v: "all" | ClaimStatus) => setStatus(v)}>
          <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="pending">Pendentes</SelectItem>
            <SelectItem value="approved">Aprovadas</SelectItem>
            <SelectItem value="rejected">Recusadas</SelectItem>
            <SelectItem value="all">Todas</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="mt-6 space-y-4">
        {list.isLoading ? (
          <p className="text-muted-foreground">Carregando…</p>
        ) : rows.length === 0 ? (
          <div className="rounded-xl border border-border bg-card p-10 text-center text-muted-foreground">
            Nenhuma reivindicação {status === "all" ? "" : STATUS_LABEL[status]?.toLowerCase()} no momento.
          </div>
        ) : rows.map((c) => {
          const company = c.companies as { name: string; slug: string; owner_id: string | null } | null;
          return (
            <article key={c.id} className="rounded-xl border border-border bg-card p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-display text-lg font-bold">{company?.name ?? "Empresa removida"}</h2>
                    <Badge variant={c.status === "pending" ? "secondary" : c.status === "approved" ? "default" : "outline"}>
                      {STATUS_LABEL[c.status] ?? c.status}
                    </Badge>
                    {company?.owner_id ? <Badge variant="outline">Já possui dono</Badge> : null}
                  </div>
                  <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                    <Clock className="h-3 w-3" /> {new Date(c.created_at).toLocaleString("pt-BR")}
                  </p>
                </div>
                {company?.slug && (
                  <Link to="/empresa/$slug" params={{ slug: company.slug }} target="_blank">
                    <Button variant="ghost" size="sm" className="gap-1"><ExternalLink className="h-4 w-4" /> Ver perfil</Button>
                  </Link>
                )}
              </div>

              <dl className="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div><dt className="text-xs uppercase text-muted-foreground">Solicitante</dt><dd>{c.full_name}{c.role_in_company ? ` — ${c.role_in_company}` : ""}</dd></div>
                <div><dt className="text-xs uppercase text-muted-foreground">Documento</dt><dd>{c.document || "—"}</dd></div>
                <div><dt className="text-xs uppercase text-muted-foreground">Telefone</dt><dd>{c.phone}</dd></div>
                <div><dt className="text-xs uppercase text-muted-foreground">E-mail</dt><dd className="break-all">{c.email}</dd></div>
                <div className="sm:col-span-2">
                  <dt className="text-xs uppercase text-muted-foreground">Comprovação</dt>
                  <dd className="break-all">
                    {c.proof_url ? <a href={c.proof_url} target="_blank" rel="noreferrer" className="text-primary underline">{c.proof_url}</a> : "—"}
                  </dd>
                </div>
                {c.message && (
                  <div className="sm:col-span-2">
                    <dt className="text-xs uppercase text-muted-foreground">Mensagem</dt>
                    <dd className="whitespace-pre-wrap">{c.message}</dd>
                  </div>
                )}
                {c.admin_notes && (
                  <div className="sm:col-span-2">
                    <dt className="text-xs uppercase text-muted-foreground">Observação do admin</dt>
                    <dd>{c.admin_notes}</dd>
                  </div>
                )}
              </dl>

              {c.status === "pending" && (
                <div className="mt-4 space-y-2">
                  <Textarea
                    rows={2}
                    placeholder="Observação (enviada ao usuário na notificação)"
                    value={notes[c.id] ?? ""}
                    onChange={(e) => setNotes((n) => ({ ...n, [c.id]: e.target.value }))}
                  />
                  <div className="flex flex-wrap gap-2">
                    <Button size="sm" className="gap-1" disabled={busy === c.id} onClick={() => act(c.id, "approve")}>
                      <Check className="h-4 w-4" /> Aprovar e transferir
                    </Button>
                    <Button size="sm" variant="outline" className="gap-1" disabled={busy === c.id} onClick={() => act(c.id, "reject")}>
                      <X className="h-4 w-4" /> Recusar
                    </Button>
                  </div>
                </div>
              )}
            </article>
          );
        })}
      </div>
    </div>
  );
}
