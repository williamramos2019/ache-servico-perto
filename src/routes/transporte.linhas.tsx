import { createFileRoute } from "@tanstack/react-router";
import { TransportePage } from "./transporte";

export const Route = createFileRoute("/transporte/linhas")({
  head: () => ({ meta: [{ title: "Linhas de ônibus — AgendaAqui" }] }),
  component: TransportePage,
});
