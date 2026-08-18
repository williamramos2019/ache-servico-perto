import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { toast } from "sonner";
import { SiteLayout } from "@/components/site/SiteLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { authRecoveryApi } from "@/lib/domain-api";

export const Route = createFileRoute("/reset-password")({
  validateSearch: (search: Record<string, unknown>) => ({ token: typeof search.token === "string" ? search.token : "" }),
  head: () => ({ meta: [{ title: "Redefinir senha — AgendaAqui" }, { name: "robots", content: "noindex" }] }),
  component: ResetPasswordPage,
});

function ResetPasswordPage() {
  const { token } = Route.useSearch();
  const navigate = useNavigate();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [pending, setPending] = useState(false);
  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (password.length < 8) return toast.error("Use pelo menos 8 caracteres.");
    if (password !== confirm) return toast.error("As senhas não conferem.");
    setPending(true);
    try {
      await authRecoveryApi.confirm(token, password);
      toast.success("Senha atualizada.");
      navigate({ to: "/auth", replace: true });
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "O link é inválido ou expirou.");
    } finally {
      setPending(false);
    }
  }
  return (
    <SiteLayout>
      <main className="container mx-auto max-w-md px-4 py-16"><section className="rounded-2xl border bg-card p-7 shadow-sm"><h1 className="font-display text-2xl font-bold">Redefinir senha</h1><p className="mt-2 text-sm text-muted-foreground">Escolha uma nova senha para sua conta.</p>{!token ? <div className="mt-6"><p className="text-sm text-destructive">Este link não contém um token de recuperação válido.</p><Button className="mt-4 w-full" onClick={() => navigate({ to: "/auth" })}>Voltar ao login</Button></div> : <form onSubmit={submit} className="mt-6 space-y-4"><div><Label htmlFor="new-password">Nova senha</Label><Input id="new-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={8} required /></div><div><Label htmlFor="confirm-password">Confirmar senha</Label><Input id="confirm-password" type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} minLength={8} required /></div><Button className="w-full" disabled={pending}>{pending ? "Salvando…" : "Salvar nova senha"}</Button></form>}</section></main>
    </SiteLayout>
  );
}
