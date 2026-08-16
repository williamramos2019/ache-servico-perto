import { PhpApiError, phpGet, phpPatch, phpPost } from "@/lib/php-api";
import { fetchMyProfile, patchMyProfile } from "@/lib/php-auth";

export type PanelCompany = {
  id: string;
  slug: string;
  name: string;
  tagline: string | null;
  plan: string | null;
  status: string | null;
  is_verified: boolean | null;
  featured: boolean | null;
  views_count: number | null;
  rating: number | null;
  review_count: number | null;
  logo_url: string | null;
  city_id: string | null;
  created_at: string;
};

export async function listMyCompanies(_userId: string): Promise<PanelCompany[]> {
  const data = await phpGet<{ companies: PanelCompany[] }>("/api/companies/mine.php");
  return data.companies ?? [];
}

export async function panelStats(userId: string) {
  const companies = await listMyCompanies(userId);
  const activity = await phpGet<{
    leads: { created_at: string }[];
    favorites_count: number;
  }>("/api/panel/activity.php");
  const leads = activity.leads ?? [];
  const cutoff = Date.now() - 7 * 86400000;
  return {
    companyCount: companies.length,
    totalViews: companies.reduce((s, c) => s + (c.views_count ?? 0), 0),
    totalReviews: companies.reduce((s, c) => s + (c.review_count ?? 0), 0),
    totalLeads: leads.length,
    leads7d: leads.filter((l) => new Date(l.created_at).getTime() >= cutoff).length,
    favoritesCount: activity.favorites_count ?? 0,
  };
}

export async function getMyCompany(userId: string, id: string) {
  const data = await phpGet<{ company: Record<string, unknown> }>(
    `/api/companies/show.php?id=${encodeURIComponent(id)}`,
  );
  if (data.company.owner_id !== userId) {
    throw new PhpApiError(404, "not_found", "Empresa não encontrada.");
  }
  return data.company;
}

export type CompanyPatch = Partial<{
  name: string;
  tagline: string | null;
  description: string | null;
  phone: string | null;
  whatsapp: string | null;
  email: string | null;
  address: string | null;
  zip: string | null;
  city_id: string | null;
  website: string | null;
  instagram: string | null;
  facebook: string | null;
  tiktok: string | null;
  youtube: string | null;
  logo_url: string | null;
  banner_url: string | null;
  video_url: string | null;
  status: string;
  founded_year: number | null;
  response_time_minutes: number | null;
  response_rate: number | null;
  services_completed: number | null;
  clients_served: number | null;
  price_range: number | null;
  tour_360_url: string | null;
  catalog_url: string | null;
  pricebook_url: string | null;
  portfolio_pdf_url: string | null;
  coverage_cities: string[];
  differentials: string[];
  badges: string[];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  certifications: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  quality_scores: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  promotions: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  financing_info: any;
  hours: Record<string, string> | null;
}>;

const COMPANY_FORBIDDEN = new Set([
  "owner_id",
  "user_id",
  "role",
  "roles",
  "company_id",
  "plan",
  "featured",
  "is_verified",
  "rating",
  "review_count",
  "views_count",
  "reputation_score",
  "plan_expires_at",
]);

export async function updateMyCompany(id: string, patch: CompanyPatch) {
  const body: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(patch)) {
    if (COMPANY_FORBIDDEN.has(key)) continue;
    body[key] = value === "" ? null : value;
  }
  await phpPatch(`/api/companies/update.php?id=${encodeURIComponent(id)}`, body);
}

export async function deleteMyCompany(_id: string) {
  throw new Error("Exclusão de empresas ainda não está disponível.");
}

export function slugify(s: string) {
  return s
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "")
    .slice(0, 80);
}

export async function createMyCompany(
  _userId: string,
  input: {
    name: string;
    tagline?: string;
    description?: string;
    city_id?: string | null;
    phone?: string;
    whatsapp?: string;
    email?: string;
    address?: string;
  },
) {
  const base = slugify(input.name) || `empresa-${Date.now()}`;
  const payload: Record<string, unknown> = {
    name: input.name,
    tagline: input.tagline || null,
    description: input.description || null,
    phone: input.phone || null,
    whatsapp: input.whatsapp || null,
    email: input.email || null,
    address: input.address || null,
  };
  if (input.city_id) payload.city_id = input.city_id;

  let lastError: unknown = null;
  for (let i = 0; i < 6; i++) {
    const slug = i === 0 ? base : `${base}-${Math.random().toString(36).slice(2, 6)}`;
    try {
      const data = await phpPost<{ company: { id: string; slug: string } }>(
        "/api/companies/create.php",
        { ...payload, slug },
      );
      return { id: data.company.id, slug: data.company.slug };
    } catch (error) {
      lastError = error;
      if (error instanceof PhpApiError && error.code === "invalid_city_id" && payload.city_id) {
        delete payload.city_id;
        i -= 1;
        continue;
      }
      if (error instanceof PhpApiError && error.code === "slug_taken") continue;
      throw error;
    }
  }
  throw lastError ?? new Error("Não foi possível criar a empresa");
}

export async function listMyLeads(_userId: string) {
  const data = await phpGet<{
    leads: Array<{
      id: string;
      name: string;
      phone: string;
      email: string | null;
      message: string | null;
      created_at: string;
      company_id: string;
      companies: { id: string; name: string; slug: string; owner_id: string };
    }>;
  }>("/api/panel/activity.php");
  return data.leads ?? [];
}

export async function listMyReviews(_userId: string) {
  const data = await phpGet<{
    reviews: Array<{
      id: string;
      rating: number;
      comment: string | null;
      created_at: string;
      user_id: string | null;
      company_id: string;
      companies: { id: string; name: string; slug: string; owner_id: string };
      profile: { name: string | null; avatar_url: string | null } | null;
    }>;
  }>("/api/panel/activity.php");
  return data.reviews ?? [];
}

export async function listCities() {
  const data = await phpGet<{ cities: { id: string; name: string; state: string }[] }>(
    "/api/catalog/index.php?op=cities&all=1",
  );
  return data.cities ?? [];
}

export async function getMyProfile(_userId: string) {
  const user = await fetchMyProfile();
  return { name: user.profile.name, avatar_url: user.profile.avatar_url, email: user.email };
}

export async function upsertMyProfile(
  _userId: string,
  patch: { name?: string | null; avatar_url?: string | null },
) {
  const body: { name?: string; avatar_url?: string | null } = {};
  if (typeof patch.name === "string" && patch.name.trim() !== "") body.name = patch.name.trim();
  if (patch.avatar_url !== undefined) body.avatar_url = patch.avatar_url;
  if (body.name === undefined && body.avatar_url === undefined) {
    throw new Error("Informe um nome ou uma foto para salvar.");
  }
  await patchMyProfile(body);
}
