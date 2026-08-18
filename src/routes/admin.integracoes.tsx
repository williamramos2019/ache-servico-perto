import { createFileRoute } from "@tanstack/react-router";
import { AdapterStatusAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/integracoes")({ component: AdapterStatusAdmin });
