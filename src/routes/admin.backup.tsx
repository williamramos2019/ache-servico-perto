import { createFileRoute } from "@tanstack/react-router";
import { BackupAdmin } from "@/components/admin/DomainAdmin";
export const Route = createFileRoute("/admin/backup")({ component: BackupAdmin });
