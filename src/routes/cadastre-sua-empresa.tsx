import { createFileRoute, Link } from "@tanstack/react-router";
import {
  ArrowRight,
  Building2,
  Gift,
  MessageCircle,
  Rocket,
  ShieldCheck,
  Sparkles,
  Star,
  UserPlus,
} from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { Button } from "@/components/ui/button";
import { useAdmin } from "@/hooks/use-admin";

export const Route = createFileRoute("/cadastre-sua-empresa")({
  head: () => ({
    meta: [
      { title: "Cadastre grátis seu perfil e sua empresa — AgendaAqui" },
      {
        name: "description",
        content:
          "Em menos de 2 minutos: crie sua conta, monte seu perfil e publique sua empresa em Vespasiano e São José da Lapa. Grátis, sem cartão, sem taxa.",
      },
      { property: "og:title", content: "Cadastre grátis seu perfil e sua empresa" },
    ],
  }),
  component: CadastreSuaEmpresa,
});

function CadastreSuaEmpresa() {
  const { userId } = useAdmin();
  const isAuthed = Boolean(userId);

  return (
    <SiteLayout>
      <section className="relative overflow-hidden border-b border-border bg-gradient-to-br from-primary/5 via-background to-accent/5">
        <div className="container mx-auto grid gap-10 px-4 py-14 md:grid-cols-[1.15fr_1fr] md:py-20">
          <div>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-accent/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-accent-foreground/90">
              <Sparkles className="h-3.5 w-3.5" /> Grátis · sem cartão · sem taxa
            </span>
            <h1 className="mt-4 font-display text-3xl font-extrabold leading-tight md:text-5xl">
              Cadastre <span className="text-primary">seu perfil</span> e coloque sua
              <span className="text-accent"> empresa no mapa</span>.
            </h1>
            <p className="mt-4 max-w-xl text-base text-muted-foreground md:text-lg">
              Em menos de 2 minutos você cria sua conta, monta seu perfil e publica sua empresa em
              Vespasiano e São José da Lapa — pronta para receber contatos direto no seu WhatsApp.
            </p>
            <div className="mt-7 flex flex-wrap gap-3">
              <Button asChild size="lg" className="btn-shine press-scale gap-2 rounded-full px-6">
                <Link to={isAuthed ? "/painel/empresas/nova" : "/auth"}>
                  <Building2 className="h-5 w-5" /> Cadastrar minha empresa grátis
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <Button asChild size="lg" variant="outline" className="gap-2 rounded-full px-6">
                <Link to={isAuthed ? "/painel/perfil" : "/auth"}>
                  <UserPlus className="h-5 w-5" /> Só criar meu perfil
                </Link>
              </Button>
            </div>
          </div>
          <div className="rounded-3xl border border-border bg-card p-6 shadow-lg">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Star className="h-6 w-6 fill-current" />
              </div>
              <div>
                <p className="text-sm font-semibold">O que os comerciantes ganham</p>
                <p className="text-xs text-muted-foreground">Recursos inclusos no plano grátis</p>
              </div>
            </div>
            <ul className="mt-5 space-y-3 text-sm">
              {[
                { icon: MessageCircle, t: "Botão de WhatsApp direto no seu perfil" },
                { icon: Star, t: "Avaliações e reputação dos seus clientes" },
                { icon: Gift, t: "Cupons e promoções para atrair novos clientes" },
                { icon: ShieldCheck, t: "Selo de empresa verificada" },
              ].map(({ icon: Icon, t }) => (
                <li key={t} className="flex items-start gap-2">
                  <Icon className="mt-0.5 h-4 w-4 text-primary" />
                  <span>{t}</span>
                </li>
              ))}
            </ul>
            <Link to="/planos" className="mt-5 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
              Ver planos Premium <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>

      <section className="container mx-auto px-4 py-14">
        <h2 className="text-center font-display text-2xl font-bold md:text-3xl">Como funciona</h2>
        <ol className="mt-8 grid gap-5 md:grid-cols-3">
          {[
            { icon: UserPlus, step: "01", title: "Crie sua conta", desc: "Com e-mail. Leva 30 segundos." },
            { icon: Building2, step: "02", title: "Cadastre sua empresa", desc: "Nome, cidade, WhatsApp e categoria. Pronto." },
            { icon: Rocket, step: "03", title: "Comece a receber clientes", desc: "Seu perfil aparece para quem já procura o que você faz." },
          ].map((s) => (
            <li key={s.step} className="relative rounded-2xl border border-border bg-card p-6">
              <span className="absolute right-4 top-3 font-display text-4xl font-black text-muted-foreground/15">{s.step}</span>
              <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <s.icon className="h-6 w-6" />
              </div>
              <h3 className="font-display text-lg font-bold">{s.title}</h3>
              <p className="mt-1 text-sm text-muted-foreground">{s.desc}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="container mx-auto px-4 pb-14">
        <div className="rounded-3xl bg-gradient-to-r from-primary to-primary-dark p-8 text-center text-primary-foreground md:p-12">
          <h2 className="font-display text-2xl font-bold md:text-3xl">Sua próxima venda pode estar te procurando agora.</h2>
          <Button asChild size="lg" variant="secondary" className="mt-6 gap-2 rounded-full px-6">
            <Link to={isAuthed ? "/painel/empresas/nova" : "/auth"}>
              <Building2 className="h-5 w-5" /> Cadastrar minha empresa
            </Link>
          </Button>
        </div>
      </section>
    </SiteLayout>
  );
}
