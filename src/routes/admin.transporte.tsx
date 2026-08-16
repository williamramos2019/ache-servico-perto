import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { fetchTransportLines } from "@/lib/transport";

export const Route = createFileRoute("/admin/transporte")({
  head: () => ({ meta: [{ title: "Transporte — Admin" }, { name: "robots", content: "noindex" }] }),
  component: AdminTransportePage,
});

function AdminTransportePage() {
  const q = useQuery({
    queryKey: ["admin-transport-lines"],
    queryFn: () => fetchTransportLines(),
  });
  const lines = q.data ?? [];

  return (
    <div>
      <h1 className="font-display text-2xl font-bold">Transporte público</h1>
      <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
        Catálogo lido do banco. Inclusão em lote:{" "}
        <code className="rounded bg-muted px-1">php tools/transport-import.php</code> com{" "}
        <code className="rounded bg-muted px-1">--source-name</code> e <code className="rounded bg-muted px-1">--source-url</code>.
      </p>
      <div className="mt-4 overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Código</th>
              <th className="px-4 py-3">Nome</th>
              <th className="px-4 py-3">Cidade</th>
              <th className="px-4 py-3">Tipo</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Fonte</th>
            </tr>
          </thead>
          <tbody>
            {lines.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-muted-foreground" colSpan={6}>
                  Nenhuma linha importada.
                </td>
              </tr>
            ) : (
              lines.map((l) => (
                <tr key={l.id} className="border-t border-border">
                  <td className="px-4 py-3 font-medium">{l.code}</td>
                  <td className="px-4 py-3">
                    <Link to="/transporte/$slug" params={{ slug: l.slug }} className="text-primary hover:underline">
                      {l.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{l.city_name ?? "—"}</td>
                  <td className="px-4 py-3">{l.type}</td>
                  <td className="px-4 py-3">{l.status}</td>
                  <td className="px-4 py-3 text-muted-foreground">{l.source?.name ?? "—"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
