import { createFileRoute } from "@tanstack/react-router";
import { ComingSoon } from "@/components/site/ComingSoon";

export const Route = createFileRoute("/marketplace")({
  head: () => ({
    meta: [
      { title: "Marketplace — AgendaAqui" },
      { name: "description", content: "Compre e venda produtos e serviços na sua cidade. Em breve no AgendaAqui." },
      { property: "og:title", content: "Marketplace — AgendaAqui" },
      { property: "og:description", content: "Compre e venda produtos e serviços na sua cidade." },
    ],
  }),
  component: MarketplacePage,
});

function MarketplacePage() {
  return (
    <ComingSoon
      emoji="🛍"
      title="Marketplace da sua cidade"
      description="Estamos preparando um espaço para você comprar e vender perto de casa, com segurança e sem taxas escondidas."
    />
  );
}
