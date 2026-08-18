import { createFileRoute } from "@tanstack/react-router";
import { RequestsAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/solicitacoes")({ component: RequestsAdmin });
