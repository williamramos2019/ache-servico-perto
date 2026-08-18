import { createFileRoute, Outlet } from "@tanstack/react-router";

export const Route = createFileRoute("/representantes")({
  head: () => ({ meta: [{ title: "Representantes — AgendaAqui" }, { name: "description", content: "Acompanhe atividades, projetos e presença dos representantes municipais." }] }),
  component: Outlet,
});
