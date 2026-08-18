import { createFileRoute } from "@tanstack/react-router";
import { LiveFeedPage } from "@/components/site/LiveFeedPage";

export const Route = createFileRoute("/agora")({
  head: () => ({ meta: [{ title: "Agora na sua cidade — AgendaAqui" }] }),
  component: LiveFeedPage,
});
