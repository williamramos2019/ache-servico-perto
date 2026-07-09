import { createFileRoute } from "@tanstack/react-router";
import { ComingSoon } from "@/components/site/ComingSoon";

export const Route = createFileRoute("/transporte")({
  head: () => ({
    meta: [
      { title: "Transporte Público — AgendaAqui" },
      { name: "description", content: "Horários de ônibus, linhas e pontos de embarque na sua cidade. Em breve." },
      { property: "og:title", content: "Transporte Público — AgendaAqui" },
      { property: "og:description", content: "Horários de ônibus, linhas e pontos de embarque na sua cidade." },
    ],
  }),
  component: TransportePage,
});

function TransportePage() {
  return (
    <ComingSoon
      emoji="🚌"
      title="Transporte Público"
      description="Consulte linhas, horários e pontos de embarque de Vespasiano, São José da Lapa e região direto no app."
    />
  );
}
