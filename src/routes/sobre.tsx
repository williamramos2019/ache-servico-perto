import { createFileRoute } from "@tanstack/react-router";
import { SiteLayout } from "@/components/site/SiteLayout";

export const Route = createFileRoute("/sobre")({
  head: () => ({
    meta: [
      { title: "Sobre — AgendaAqui" },
      { name: "description", content: "Conheça o AgendaAqui, marketplace regional de serviços e empresas em Minas Gerais." },
      { property: "og:url", content: "/sobre" },
    ],
    links: [{ rel: "canonical", href: "/sobre" }],
  }),
  component: () => (
    <SiteLayout>
      <div className="container mx-auto max-w-3xl px-4 py-16">
        <h1 className="font-display text-3xl font-bold md:text-4xl">Sobre o AgendaAqui</h1>
        <p className="mt-4 text-lg text-muted-foreground">Seu serviço certo, na hora certa.</p>
        <div className="prose mt-6 max-w-none text-foreground/90">
          <p>O AgendaAqui nasceu para conectar pessoas a empresas e profissionais de confiança em Minas Gerais. Começamos focados em Vespasiano, São José da Lapa, Lagoa Santa e Belo Horizonte, com planos de expansão para todo o estado e, em seguida, todo o país.</p>
          <p className="mt-4">Aqui você encontra prestadores de serviços de construção civil, higienização, transportes, assistência técnica, alimentação, saúde, tecnologia e muito mais — todos verificados pela nossa comunidade.</p>
          <p className="mt-4">É grátis para usuários e grátis para começar a anunciar. Cadastre sua empresa hoje mesmo e seja encontrado por milhares de pessoas.</p>
        </div>
      </div>
    </SiteLayout>
  ),
});
