import { createFileRoute } from "@tanstack/react-router";
import { AdapterStatusAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/blog-ai")({ component: AdapterStatusAdmin });
