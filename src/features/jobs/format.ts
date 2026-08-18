import type { SearchState } from "./types";
import { DEFAULT_SEARCH } from "./constants";

export function formatSalary(
  min: number | null | undefined,
  max: number | null | undefined,
  currency: string | null | undefined,
): string | null {
  if (!min && !max) return null;
  const c = currency === "USD" ? "US$" : "R$";
  const fmt = (n: number) => (n >= 1000 ? `${(n / 1000).toFixed(0)}k` : `${n}`);
  if (min && max) return `${c} ${fmt(min)}–${fmt(max)}`;
  return `${c} ${fmt((min ?? max)!)}`;
}

export function formatPostedDate(iso: string | null | undefined): string {
  if (!iso) return "Recente";
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
  if (days <= 0) return "Hoje";
  if (days === 1) return "Ontem";
  if (days < 30) return `${days}d atrás`;
  return new Date(iso).toLocaleDateString("pt-BR");
}

export function parseSearchParams(s: Record<string, unknown>): SearchState {
  const remote = s.remote === "yes" || s.remote === "no" ? s.remote : DEFAULT_SEARCH.remote;
  const sort =
    s.sort === "salary_desc" || s.sort === "salary_asc" ? s.sort : DEFAULT_SEARCH.sort;
  const page = Number(s.page);
  const salaryMin = Number(s.salaryMin);
  return {
    q: typeof s.q === "string" ? s.q : DEFAULT_SEARCH.q,
    city: typeof s.city === "string" ? s.city : DEFAULT_SEARCH.city,
    remote,
    employment: typeof s.employment === "string" ? s.employment : DEFAULT_SEARCH.employment,
    experience: typeof s.experience === "string" ? s.experience : DEFAULT_SEARCH.experience,
    salaryMin: Number.isFinite(salaryMin) && salaryMin > 0 ? salaryMin : DEFAULT_SEARCH.salaryMin,
    sort,
    page: Number.isInteger(page) && page > 0 ? page : DEFAULT_SEARCH.page,
  };
}
