import { queryOptions } from "@tanstack/react-query";
import { phpGet } from "@/lib/php-api";

export type CompanyListItem = {
  id: string;
  slug: string;
  name: string;
  tagline: string | null;
  banner_url: string | null;
  logo_url: string | null;
  plan: string | null;
  featured: boolean | null;
  is_verified?: boolean | null;
  city: { name: string; slug: string } | null;
  rating: number;
  review_count: number;
  phone?: string | null;
  whatsapp?: string | null;
  open_now?: boolean | null;
  categories: { name: string; slug: string }[];
  origin?: string | null;
};

export const APP_CITY_SLUGS = ["vespasiano", "sao-jose-da-lapa"] as const;

export async function fetchCities() {
  const data = await phpGet<{ cities: { id: string; name: string; slug: string; state: string }[] }>(
    "/api/catalog/index.php?op=cities",
  );
  return data.cities ?? [];
}

export async function fetchCategories() {
  const data = await phpGet<{
    categories: {
      id: string;
      name: string;
      slug: string;
      icon: string | null;
      description: string | null;
      sort: number;
    }[];
  }>("/api/catalog/index.php?op=categories");
  return data.categories ?? [];
}

export async function fetchFeaturedCompanies(limit = 6, city?: string): Promise<CompanyListItem[]> {
  const qs = new URLSearchParams({ op: "featured", limit: String(limit) });
  if (city) qs.set("city", city);
  const data = await phpGet<{ companies: CompanyListItem[] }>(`/api/catalog/index.php?${qs.toString()}`);
  return data.companies ?? [];
}

export async function searchCompanies(params: {
  q?: string;
  city?: string;
  category?: string;
  minRating?: number;
  premiumOnly?: boolean;
  plan?: "free" | "premium" | "featured" | "all";
  sort?: "relevance" | "rating" | "name" | "newest" | "reviews";
  verified?: boolean;
  hasWhatsapp?: boolean;
  openNow?: boolean;
  hasReviews?: boolean;
  limit?: number;
  page?: number;
}): Promise<{ items: CompanyListItem[]; hasMore: boolean; total: number | null }> {
  const qs = new URLSearchParams({ op: "search" });
  if (params.q) qs.set("q", params.q);
  if (params.city) qs.set("city", params.city);
  if (params.category) qs.set("category", params.category);
  if (params.minRating) qs.set("minRating", String(params.minRating));
  if (params.premiumOnly) qs.set("premiumOnly", "1");
  if (params.plan && params.plan !== "all") qs.set("plan", params.plan);
  if (params.sort) qs.set("sort", params.sort);
  if (params.verified) qs.set("verified", "1");
  if (params.hasWhatsapp) qs.set("hasWhatsapp", "1");
  if (params.openNow) qs.set("openNow", "1");
  if (params.hasReviews) qs.set("hasReviews", "1");
  if (params.limit) qs.set("limit", String(params.limit));
  if (params.page) qs.set("page", String(params.page));
  return phpGet(`/api/catalog/index.php?${qs.toString()}`);
}

export async function suggestCompanies(
  q: string,
  city?: string,
): Promise<{ id: string; name: string; slug: string; logo_url: string | null; city_name: string | null }[]> {
  const safe = q.trim();
  if (safe.length < 2) return [];
  const qs = new URLSearchParams({ op: "suggest", q: safe });
  if (city) qs.set("city", city);
  const data = await phpGet<{
    companies: { id: string; name: string; slug: string; logo_url: string | null; city_name: string | null }[];
  }>(`/api/catalog/index.php?${qs.toString()}`);
  return data.companies ?? [];
}

export async function fetchCompanyBySlug(slug: string) {
  const data = await phpGet<{ company: Record<string, unknown> | null }>(
    `/api/catalog/index.php?op=company&slug=${encodeURIComponent(slug)}`,
  );
  return data.company;
}

export async function fetchCompanyReviews(companyId: string) {
  const data = await phpGet<{
    reviews: {
      id: string;
      rating: number;
      comment: string | null;
      created_at: string;
      user_id: string | null;
      author_name: string | null;
      source: string;
      review_date: string | null;
    }[];
  }>(`/api/catalog/index.php?op=reviews&company_id=${encodeURIComponent(companyId)}`);
  return data.reviews ?? [];
}

export async function fetchCitiesByIds(ids: string[]) {
  if (!ids || ids.length === 0) return [] as { id: string; name: string; state: string; slug: string }[];
  const data = await phpGet<{ cities: { id: string; name: string; state: string; slug: string }[] }>(
    `/api/catalog/index.php?op=cities_by_ids&ids=${encodeURIComponent(ids.join(","))}`,
  );
  return data.cities ?? [];
}

export async function fetchSimilarCompanies(opts: {
  excludeId: string;
  categoryIds: string[];
  cityId?: string | null;
  limit?: number;
}): Promise<CompanyListItem[]> {
  const qs = new URLSearchParams({
    op: "similar",
    exclude_id: opts.excludeId,
    limit: String(opts.limit ?? 6),
  });
  if (opts.categoryIds.length) qs.set("category_ids", opts.categoryIds.join(","));
  if (opts.cityId) qs.set("city_id", opts.cityId);
  const data = await phpGet<{ companies: CompanyListItem[] }>(`/api/catalog/index.php?${qs.toString()}`);
  return data.companies ?? [];
}

export const categoriesQueryOptions = queryOptions({
  queryKey: ["categories"],
  queryFn: fetchCategories,
  staleTime: 10 * 60_000,
  gcTime: 30 * 60_000,
});

export const citiesQueryOptions = queryOptions({
  queryKey: ["cities"],
  queryFn: fetchCities,
  staleTime: 10 * 60_000,
  gcTime: 30 * 60_000,
});

export const featuredCompaniesQueryOptions = (limit = 8, city?: string) =>
  queryOptions({
    queryKey: ["featured", limit, city ?? ""],
    queryFn: () => fetchFeaturedCompanies(limit, city),
    staleTime: 5 * 60_000,
    gcTime: 15 * 60_000,
  });

export const companyBySlugQueryOptions = (slug: string) =>
  queryOptions({
    queryKey: ["company", slug],
    queryFn: () => fetchCompanyBySlug(slug),
    staleTime: 2 * 60_000,
    gcTime: 10 * 60_000,
  });
