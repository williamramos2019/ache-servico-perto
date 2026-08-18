import { createFileRoute } from "@tanstack/react-router";
import { parseSearchParams } from "@/features/jobs";
import { EmpregosPage } from "./empregos";

export const Route = createFileRoute("/empregos/")({
  validateSearch: parseSearchParams,
  component: EmpregosPage,
});
