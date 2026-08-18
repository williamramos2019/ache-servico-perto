import { createFileRoute } from "@tanstack/react-router";
import { LiveFeedPage } from "@/components/site/LiveFeedPage";

export const Route = createFileRoute("/ao-vivo")({
  head: () => ({ meta: [{ title: "Acontecendo agora — AgendaAqui" }] }),
  component: () => <LiveFeedPage title="Acontecendo agora" />,
});
