import { createFileRoute } from "@tanstack/react-router";
import { JobsAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/empregos")({ component: JobsAdmin });
