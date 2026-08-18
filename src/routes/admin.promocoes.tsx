import { createFileRoute } from "@tanstack/react-router";
import { PromotionsAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/promocoes")({ component: PromotionsAdmin });
