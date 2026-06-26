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
  cities: { name: string; slug: string } | null;
  reviews: { rating: number }[];
  company_categories: { categories: { name: string; slug: string } | null }[];
};

const SELECT = `id, slug, name, tagline, banner_url, logo_url, plan, featured,
  cities ( name, slug ),
  reviews ( rating ),
  company_categories ( categories ( name, slug ) )`;

function mapCompany(c: CompanyRow): CompanyListItem {
  const ratings = (c.reviews ?? []).map((r) => r.rating);
  const rating = ratings.length ? ratings.reduce((a, b) => a + b, 0) / ratings.length : 0;
  return {
    id: c.id,
    slug: c.slug,
    name: c.name,
    tagline: c.tagline,
    banner_url: c.banner_url,
    logo_url: c.logo_url,
    plan: c.plan,
    featured: c.featured,
    city: c.cities,
    rating,
    review_count: ratings.length,
    categories: (c.company_categories ?? [])
      .map((cc) => cc.categories)
      .filter((x): x is { name: string; slug: string } => !!x),
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
  const { data, error } = await supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active")
    .in("plan", ["premium", "featured"])
    .order("featured", { ascending: false })
    .limit(limit);
  if (error) throw error;
  return (data as CompanyRow[] | null ?? []).map(mapCompany);
}

export async function searchCompanies(params: {
  q?: string;
  city?: string;
  category?: string;
  minRating?: number;
  premiumOnly?: boolean;
  plan?: "free" | "premium" | "featured" | "all";
  sort?: "relevance" | "rating" | "name" | "newest";
}): Promise<CompanyListItem[]> {
  let query = supabase
    .from("companies")
    .select(SELECT)
    .eq("status", "active");

  if (params.premiumOnly) query = query.in("plan", ["premium", "featured"]);
  if (params.plan && params.plan !== "all") query = query.eq("plan", params.plan);

  if (params.sort === "name") query = query.order("name");
  else if (params.sort === "newest") query = query.order("created_at", { ascending: false });

  if (params.q) {
    const safe = params.q.replace(/[%,]/g, " ").trim();
    if (safe) query = query.or(`name.ilike.%${safe}%,tagline.ilike.%${safe}%,description.ilike.%${safe}%`);
  }
  if (params.city) {
    const { data: city } = await supabase.from("cities").select("id").eq("slug", params.city).maybeSingle();
    if (city) query = query.eq("city_id", city.id);
  }
  if (params.category) {
    const { data: cat } = await supabase.from("categories").select("id").eq("slug", params.category).maybeSingle();
    if (cat) {
      const { data: links } = await supabase
        .from("company_categories")
        .select("company_id")
        .eq("category_id", cat.id);
      const ids = (links ?? []).map((l) => l.company_id);
      if (ids.length === 0) return [];
      query = query.in("id", ids);
    }
  }

  const { data, error } = await query.limit(200);
  if (error) throw error;
  let results = (data as CompanyRow[] | null ?? []).map(mapCompany);

  if (params.minRating && params.minRating > 0) {
    results = results.filter((r) => r.rating >= params.minRating!);
  }
  // Default: rank by plan first (premium/featured before free), then rating
  if (!params.sort || params.sort === "relevance") {
    const planRank = (p: string | null) => (p === "premium" || p === "featured" ? 0 : 2);
    results = [...results].sort((a, b) => {
      const pr = planRank(a.plan) - planRank(b.plan);
      if (pr !== 0) return pr;
      const fa = a.featured ? 0 : 1;
      const fb = b.featured ? 0 : 1;
      if (fa !== fb) return fa - fb;
      if (b.rating !== a.rating) return b.rating - a.rating;
      return b.review_count - a.review_count;
    });
  } else if (params.sort === "rating") {
    results = [...results].sort((a, b) => b.rating - a.rating || b.review_count - a.review_count);
  }
  return results;
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
      .in("category_id", opts.categoryIds);
    companyIds = [...new Set((links ?? []).map((l) => l.company_id))].filter((id) => id !== opts.excludeId);
  }
  if (companyIds.length === 0 && opts.cityId) {
    const { data } = await supabase
      .from("companies")
      .select(SELECT)
      .eq("status", "active")
      .eq("city_id", opts.cityId)
      .neq("id", opts.excludeId)
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
    .limit(limit);
  if (opts.cityId) q = q.eq("city_id", opts.cityId);
  const { data, error } = await q;
  if (error) throw error;
  let results = (data as CompanyRow[] | null ?? []).map(mapCompany);
  if (results.length < limit) {
    const have = new Set(results.map((r) => r.id));
    const fallbackIds = companyIds.filter((id) => !have.has(id)).slice(0, limit - results.length);
    if (fallbackIds.length) {
      const { data: extra } = await supabase
        .from("companies")
        .select(SELECT)
        .in("id", fallbackIds);
      results = results.concat((extra as CompanyRow[] | null ?? []).map(mapCompany));
    }
  }
  return results.slice(0, limit);
}
