import { createFileRoute } from "@tanstack/react-router";
import { AdsAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/anuncios")({ component: AdsAdmin });
