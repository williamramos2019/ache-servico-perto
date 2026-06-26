import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { adminListCompanies, adminUpdateCompany, adminDeleteCompany } from "@/lib/admin";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import { Trash2, Eye } from "lucide-react";

export const Route = createFileRoute("/admin/empresas")({
  component: AdminEmpresas,
});

function AdminEmpresas() {
  const qc = useQueryClient();
  const [q, setQ] = useState("");
  const [plan, setPlan] = useState("all");
  const list = useQuery({
    queryKey: ["admin-companies", q, plan],
    queryFn: () => adminListCompanies({ q, plan }),
  });

  async function changePlan(id: string, newPlan: string) {
    try {
      await adminUpdateCompany(id, { plan: newPlan, featured: newPlan === "featured" });
      toast.success("Plano atualizado");
      qc.invalidateQueries({ queryKey: ["admin-companies"] });
    } catch (e) { toast.error((e as Error).message); }
  }
  async function toggleVerified(id: string, current: boolean) {
    try {
      await adminUpdateCompany(id, { is_verified: !current });
      toast.success(current ? "Removida verificação" : "Marcada como verificada");
      qc.invalidateQueries({ queryKey: ["admin-companies"] });
    } catch (e) { toast.error((e as Error).message); }
  }
  async function toggleFeatured(id: string, current: boolean) {
    try {
      await adminUpdateCompany(id, { featured: !current });
      qc.invalidateQueries({ queryKey: ["admin-companies"] });
    } catch (e) { toast.error((e as Error).message); }
  }
  async function remove(id: string) {
    if (!confirm("Tem certeza que deseja excluir esta empresa?")) return;
    try {
      await adminDeleteCompany(id);
      toast.success("Empresa excluída");
      qc.invalidateQueries({ queryKey: ["admin-companies"] });
    } catch (e) { toast.error((e as Error).message); }
  }

  return (
    <div>
      <h1 className="font-display text-2xl font-bold">Empresas</h1>
      <div className="mt-4 flex flex-wrap gap-2">
        <Input placeholder="Buscar pelo nome…" value={q} onChange={(e) => setQ(e.target.value)} className="max-w-xs" />
        <Select value={plan} onValueChange={setPlan}>
          <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os planos</SelectItem>
            <SelectItem value="free">Grátis</SelectItem>
            <SelectItem value="premium">Premium</SelectItem>
            <SelectItem value="featured">Destaque</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div className="mt-6 overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Empresa</th>
              <th className="px-4 py-3">Cidade</th>
              <th className="px-4 py-3">Plano</th>
              <th className="px-4 py-3">Destaque</th>
              <th className="px-4 py-3">Verificada</th>
              <th className="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            {list.isLoading ? (
              <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={6}>Carregando…</td></tr>
            ) : (list.data ?? []).length === 0 ? (
              <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={6}>Nenhuma empresa encontrada</td></tr>
            ) : list.data!.map((c) => (
              <tr key={c.id} className="border-t border-border">
                <td className="px-4 py-3 font-medium">{c.name}</td>
                <td className="px-4 py-3 text-muted-foreground">{c.cities?.name ?? "—"}</td>
                <td className="px-4 py-3">
                  <Select value={c.plan ?? "free"} onValueChange={(v) => changePlan(c.id, v)}>
                    <SelectTrigger className="h-8 w-32"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="free">Grátis</SelectItem>
                      <SelectItem value="premium">Premium</SelectItem>
                      <SelectItem value="featured">Destaque</SelectItem>
                    </SelectContent>
                  </Select>
                </td>
                <td className="px-4 py-3">
                  <button onClick={() => toggleFeatured(c.id, !!c.featured)}>
                    <Badge variant={c.featured ? "default" : "outline"}>{c.featured ? "Sim" : "Não"}</Badge>
                  </button>
                </td>
                <td className="px-4 py-3">
                  <button onClick={() => toggleVerified(c.id, !!c.is_verified)}>
                    <Badge variant={c.is_verified ? "default" : "outline"}>{c.is_verified ? "Sim" : "Não"}</Badge>
                  </button>
                </td>
                <td className="px-4 py-3 text-right">
                  <div className="inline-flex gap-1">
                    <Link to="/empresa/$slug" params={{ slug: c.slug }} target="_blank">
                      <Button variant="ghost" size="icon"><Eye className="h-4 w-4" /></Button>
                    </Link>
                    <Button variant="ghost" size="icon" onClick={() => remove(c.id)}>
                      <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
