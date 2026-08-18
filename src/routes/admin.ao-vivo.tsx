import { createFileRoute } from "@tanstack/react-router";
import { LiveModerationAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/ao-vivo")({ component: LiveModerationAdmin });
