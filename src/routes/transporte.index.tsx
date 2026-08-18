import { createFileRoute } from "@tanstack/react-router";
import { TransportePage } from "./transporte";

export const Route = createFileRoute("/transporte/")({
  component: TransportePage,
});
