import { useEffect, useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { toast } from "sonner";
import { SiteLayout } from "@/components/site/SiteLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { fetchCurrentUser, loginWithPassword, registerAccount } from "@/lib/php-auth";
import { PhpApiError } from "@/lib/php-api";
import { authRecoveryApi } from "@/lib/domain-api";

export const Route = createFileRoute("/auth")({
  head: () => ({
    meta: [
      { title: "Entrar ou criar conta — AgendaAqui" },
      {
        name: "description",
        content:
          "Acesse sua conta para avaliar empresas, salvar favoritos e gerenciar seu negócio no AgendaAqui.",
      },
      { property: "og:url", content: "/auth" },
    ],
    links: [{ rel: "canonical", href: "/auth" }],
  }),
  component: AuthPage,
});

function AuthPage() {
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    let active = true;
    fetchCurrentUser()
      .then((user) => {
        if (active && user) navigate({ to: "/" });
      })
      .catch(() => undefined);
    return () => {
      active = false;
    };
  }, [navigate]);

  async function signIn(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    try {
      await loginWithPassword(email, password);
      toast.success("Que bom te ver de volta!");
      navigate({ to: "/" });
    } catch (err) {
      toast.error(err instanceof PhpApiError ? err.message : "Não foi possível entrar.");
    } finally {
      setLoading(false);
    }
  }

  async function signUp(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    try {
      await registerAccount({ email, password, name });
      toast.success("Conta criada! Entre para continuar.");
      setPassword("");
    } catch (err) {
      toast.error(err instanceof PhpApiError ? err.message : "Não foi possível criar a conta.");
    } finally {
      setLoading(false);
    }
  }

  async function forgotPassword() {
    if (!email) return toast.error("Digite seu e-mail para receber o link de recuperação.");
    setLoading(true);
    try {
      await authRecoveryApi.request(email);
      toast.success("Se a conta existir, enviaremos as instruções de recuperação.");
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Não foi possível solicitar a recuperação.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <SiteLayout>
      <div className="container mx-auto flex max-w-md flex-col px-4 py-16">
        <div className="rounded-2xl border border-border bg-card p-6 shadow-sm md:p-8">
          <h1 className="font-display text-2xl font-bold">Bem-vindo ao AgendaAqui</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Entre para avaliar empresas, salvar seus favoritos e gerenciar o seu negócio.
          </p>

          <Tabs defaultValue="signin" className="mt-6">
            <TabsList className="grid grid-cols-2">
              <TabsTrigger value="signin">Já tenho conta</TabsTrigger>
              <TabsTrigger value="signup">Criar conta grátis</TabsTrigger>
            </TabsList>
            <TabsContent value="signin" className="mt-4">
              <form onSubmit={signIn} className="space-y-3">
                <div>
                  <Label htmlFor="email">E-mail</Label>
                  <Input
                    id="email"
                    type="email"
                    placeholder="voce@exemplo.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                  />
                </div>
                <div>
                  <div className="flex items-center justify-between">
                    <Label htmlFor="pw">Senha</Label>
                    <button
                      type="button"
                      onClick={forgotPassword}
                      className="text-xs text-primary hover:underline"
                    >
                      Esqueci a senha
                    </button>
                  </div>
                  <Input
                    id="pw"
                    type="password"
                    placeholder="Sua senha"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                  />
                </div>
                <Button type="submit" className="w-full" disabled={loading}>
                  {loading ? "Entrando..." : "Entrar na minha conta"}
                </Button>
              </form>
            </TabsContent>
            <TabsContent value="signup" className="mt-4">
              <form onSubmit={signUp} className="space-y-3">
                <div>
                  <Label htmlFor="n">Como podemos te chamar?</Label>
                  <Input
                    id="n"
                    placeholder="Seu nome"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    maxLength={100}
                  />
                </div>
                <div>
                  <Label htmlFor="e2">E-mail</Label>
                  <Input
                    id="e2"
                    type="email"
                    placeholder="voce@exemplo.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                  />
                </div>
                <div>
                  <Label htmlFor="p2">Crie uma senha</Label>
                  <Input
                    id="p2"
                    type="password"
                    placeholder="Mínimo 8 caracteres"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    minLength={8}
                  />
                </div>
                <Button type="submit" className="w-full" disabled={loading}>
                  {loading ? "Criando..." : "Criar minha conta grátis"}
                </Button>
                <p className="pt-1 text-center text-[11px] text-muted-foreground">
                  Grátis, sem cartão. Leva menos de 1 minuto.
                </p>
              </form>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </SiteLayout>
  );
}
