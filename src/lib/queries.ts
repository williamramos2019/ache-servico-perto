import { supabase } from "@/integrations/supabase/client";

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
  categories: { name: string; slug: string }[];
};

type CompanyRow = {
  id: string;
  slug: string;
  name: string;
  tagline: string | null;
  banner_url: string | null;
  logo_url: string | null;
  plan: string | null;
  featured: boolean | null;
  is_verified: boolean | null;
  rating: number | null;
  review_count: number | null;
  cities: { name: string; slug: string } | null;
};

const SELECT = `id, slug, name, tagline, banner_url, logo_url, plan, featured, is_verified, rating, review_count,
  cities ( name, slug )`;

function mapCompany(c: CompanyRow): CompanyListItem {
  return {
    id: c.id,
    slug: c.slug,
    name: c.name,
    tagline: c.tagline,
    banner_url: c.banner_url,
    logo_url: c.logo_url,
    plan: c.plan,
    featured: c.featured,
    is_verified: c.is_verified,
    city: c.cities,
    rating: Number(c.rating ?? 0),
    review_count: c.review_count ?? 0,
    categories: [],
  };
}

export async function fetchCities() {
  const { data, error } = await supabase
    .from("cities")
    .select("id, name, slug, state")
    .order("name");
  if (error) throw error;
  return data ?? [];
}

export async function fetchCategories() {
  const { data, error } = await supabase
    .from("categories")
    .select("id, name, slug, icon, description, sort")
    .order("sort");
  if (error) throw error;
  return data ?? [];
}

export async function fetchFeaturedCompanies(limit = 6): Promise<CompanyListItem[]> {
  // Prefer companies that actually have a banner image first, so the home
  // showcase doesn't fill up with gradient placeholders.
  const withBanner = await supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active")
    .in("plan", ["premium", "featured"])
    .not("banner_url", "is", null)
    .order("featured", { ascending: false })
    .order("rating", { ascending: false })
    .order("review_count", { ascending: false })
    .limit(limit);
  if (withBanner.error) throw withBanner.error;
  const rows = (withBanner.data as CompanyRow[] | null) ?? [];
  if (rows.length >= limit) return rows.map(mapCompany);

  // Fallback: fill remaining slots. Prefer any active company with a banner
  // before falling back to premium/featured without images.
  const missing = limit - rows.length;
  const existingIds = rows.map((r) => r.id);
  const fillWithImg = await supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active")
    .not("banner_url", "is", null)
    .not("id", "in", `(${existingIds.length ? existingIds.join(",") : "00000000-0000-0000-0000-000000000000"})`)
    .order("rating", { ascending: false })
    .order("review_count", { ascending: false })
    .limit(missing);
  const filled = [...rows, ...(((fillWithImg.data as CompanyRow[] | null) ?? []))];
  if (filled.length >= limit) return filled.slice(0, limit).map(mapCompany);

  const stillMissing = limit - filled.length;
  const filledIds = filled.map((r) => r.id);
  const fillAny = await supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active")
    .in("plan", ["premium", "featured"])
    .not("id", "in", `(${filledIds.length ? filledIds.join(",") : "00000000-0000-0000-0000-000000000000"})`)
    .order("featured", { ascending: false })
    .order("rating", { ascending: false })
    .limit(stillMissing);
  return [...filled, ...(((fillAny.data as CompanyRow[] | null) ?? []))].slice(0, limit).map(mapCompany);
}

export async function searchCompanies(params: {
  q?: string;
  city?: string;
  category?: string;
  minRating?: number;
  premiumOnly?: boolean;
  plan?: "free" | "premium" | "featured" | "all";
  sort?: "relevance" | "rating" | "name" | "newest";
  limit?: number;
}): Promise<CompanyListItem[]> {
  const limit = params.limit ?? 60;

  // Resolve city/category to ids in parallel
  const [cityRes, catRes] = await Promise.all([
    params.city
      ? supabase.from("cities").select("id").eq("slug", params.city).maybeSingle()
      : Promise.resolve({ data: null as { id: string } | null }),
    params.category
      ? supabase.from("categories").select("id").eq("slug", params.category).maybeSingle()
      : Promise.resolve({ data: null as { id: string } | null }),
  ]);

  let companyIdsForCategory: string[] | null = null;
  if (params.category && catRes.data) {
    const { data: links } = await supabase
      .from("company_categories")
      .select("company_id")
      .eq("category_id", catRes.data.id)
      .limit(5000);
    companyIdsForCategory = (links ?? []).map((l) => l.company_id);
    if (companyIdsForCategory.length === 0) return [];
  }

  let query = supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active");

  if (params.premiumOnly) query = query.in("plan", ["premium", "featured"]);
  if (params.plan && params.plan !== "all") query = query.eq("plan", params.plan);
  if (cityRes.data) query = query.eq("city_id", cityRes.data.id);
  if (companyIdsForCategory) query = query.in("id", companyIdsForCategory.slice(0, 1000));
  if (params.minRating && params.minRating > 0) query = query.gte("rating", params.minRating);

  if (params.q) {
    const safe = params.q.replace(/[%,]/g, " ").trim();
    if (safe) query = query.or(`name.ilike.%${safe}%,tagline.ilike.%${safe}%`);
  }

  if (params.sort === "name") query = query.order("name");
  else if (params.sort === "newest") query = query.order("created_at", { ascending: false });
  else if (params.sort === "rating") {
    query = query.order("rating", { ascending: false }).order("review_count", { ascending: false });
  } else {
    // Relevance: featured/premium first (plan asc → featured,free,premium isn't ideal),
    // so we order by featured then rating; premium boost handled by featured flag + rating.
    query = query
      .order("featured", { ascending: false })
      .order("rating", { ascending: false })
      .order("review_count", { ascending: false });
  }

  const { data, error } = await query.limit(limit);
  if (error) throw error;
  return (data as CompanyRow[] | null ?? []).map(mapCompany);
}

export async function fetchCompanyBySlug(slug: string) {
  const { data, error } = await supabase
    .from("companies")
    .select(`*, cities ( name, slug, state ), company_categories ( categories ( id, name, slug, icon ) ), company_media ( id, url, type, caption, sort )`)
    .eq("slug", slug)
    .maybeSingle();
  if (error) throw error;
  return data;
}

export async function fetchCompanyReviews(companyId: string) {
  const { data, error } = await supabase
    .from("reviews")
    .select("id, rating, comment, created_at, user_id")
    .eq("company_id", companyId)
    .order("created_at", { ascending: false });
  if (error) throw error;
  return data ?? [];
}

export async function fetchSimilarCompanies(opts: {
  excludeId: string;
  categoryIds: string[];
  cityId?: string | null;
  limit?: number;
}): Promise<CompanyListItem[]> {
  const limit = opts.limit ?? 6;
  let companyIds: string[] = [];
  if (opts.categoryIds.length > 0) {
    const { data: links } = await supabase
      .from("company_categories")
      .select("company_id")
      .in("category_id", opts.categoryIds)
      .limit(500);
    companyIds = [...new Set((links ?? []).map((l) => l.company_id))].filter((id) => id !== opts.excludeId);
  }
  if (companyIds.length === 0 && opts.cityId) {
    const { data } = await supabase
      .from("companies")
      .select(SELECT)
      .eq("status", "active")
      .eq("city_id", opts.cityId)
      .neq("id", opts.excludeId)
      .order("rating", { ascending: false })
      .limit(limit);
    return (data as CompanyRow[] | null ?? []).map(mapCompany);
  }
  if (companyIds.length === 0) return [];
  let q = supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active")
    .in("id", companyIds.slice(0, 60))
    .order("featured", { ascending: false })
    .order("rating", { ascending: false })
    .limit(limit);
  if (opts.cityId) q = q.eq("city_id", opts.cityId);
  const { data, error } = await q;
  if (error) throw error;
  return (data as CompanyRow[] | null ?? []).map(mapCompany);
}
