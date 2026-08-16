import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { phpGet } from "@/lib/php-api";

export const Route = createFileRoute("/admin/imports")({
  head: () => ({ meta: [{ title: "Importações — Admin" }, { name: "robots", content: "noindex" }] }),
  component: AdminImportsPage,
});

type ImportRun = {
  id: string;
  city_slug: string;
  source: string;
  status: string;
  started_at: string;
  finished_at: string | null;
  total_collected: number;
  total_valid: number;
  total_inserted: number;
  total_updated: number;
  total_duplicates: number;
  total_rejected: number;
  error_message: string | null;
  errors: Array<{ error_type: string; error_message: string; company_name: string | null; created_at: string }>;
};

function AdminImportsPage() {
  const q = useQuery({
    queryKey: ["admin-imports"],
    queryFn: async () => {
      const data = await phpGet<{ runs: ImportRun[]; not_migrated?: boolean }>("/api/admin/index.php?op=imports");
      return data;
    },
  });
  const runs = q.data?.runs ?? [];

  return (
    <div>
      <h1 className="font-display text-2xl font-bold">Importações de empresas</h1>
      <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
        Somente leitura. Novas cargas rodam no servidor com{" "}
        <code className="rounded bg-muted px-1">php tools/import-companies.php</code>. Não há importação pela web.
      </p>
      {q.data?.not_migrated ? (
        <p className="mt-4 text-sm text-muted-foreground">Aplique a migration 011 para ver o histórico.</p>
      ) : null}
      <div className="mt-4 overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Início</th>
              <th className="px-4 py-3">Cidade</th>
              <th className="px-4 py-3">Fonte</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Inseridas</th>
              <th className="px-4 py-3">Atualizadas</th>
              <th className="px-4 py-3">Duplicatas</th>
              <th className="px-4 py-3">Rejeitadas</th>
            </tr>
          </thead>
          <tbody>
            {runs.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-muted-foreground" colSpan={8}>
                  Nenhuma execução registrada.
                </td>
              </tr>
            ) : (
              runs.map((r) => (
                <tr key={r.id} className="border-t border-border align-top">
                  <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                    {new Date(r.started_at).toLocaleString("pt-BR")}
                  </td>
                  <td className="px-4 py-3">{r.city_slug}</td>
                  <td className="px-4 py-3">{r.source}</td>
                  <td className="px-4 py-3">{r.status}</td>
                  <td className="px-4 py-3">{r.total_inserted}</td>
                  <td className="px-4 py-3">{r.total_updated}</td>
                  <td className="px-4 py-3">{r.total_duplicates}</td>
                  <td className="px-4 py-3">
                    {r.total_rejected}
                    {r.errors[0] ? (
                      <div className="mt-1 text-xs text-muted-foreground">{r.errors[0].error_message}</div>
                    ) : null}
                    {r.error_message ? <div className="mt-1 text-xs text-destructive">{r.error_message}</div> : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
