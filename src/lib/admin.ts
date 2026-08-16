import { fetchCurrentUser } from "@/lib/php-auth";
import { phpGet, phpPost } from "@/lib/php-api";

export async function checkIsAdmin(userId: string | undefined | null): Promise<boolean> {
  if (!userId) return false;
  try {
    const user = await fetchCurrentUser();
    return Boolean(user?.roles?.includes("admin"));
  } catch {
    return false;
  }
}

export async function adminStats() {
  return phpGet<{
    total: number;
    free: number;
    premium: number;
    featured: number;
    recent7d: number;
    views: number;
  }>("/api/admin/index.php?op=stats");
}

export async function adminListCompanies(
  opts: { q?: string; plan?: string; featured?: "all" | "yes" | "no"; page?: number; pageSize?: number } = {},
) {
  const page = Math.max(1, opts.page ?? 1);
  const pageSize = Math.min(200, Math.max(10, opts.pageSize ?? 50));
  const qs = new URLSearchParams({
    op: "companies",
    page: String(page),
    pageSize: String(pageSize),
  });
  if (opts.q) qs.set("q", opts.q);
  if (opts.plan && opts.plan !== "all") qs.set("plan", opts.plan);
  if (opts.featured && opts.featured !== "all") qs.set("featured", opts.featured);
  return phpGet<{
    rows: Array<{
      id: string;
      name: string;
      slug: string;
      plan: string | null;
      featured: boolean;
      is_verified: boolean;
      status: string | null;
      city_id: string | null;
      cities: { name: string } | null;
      created_at: string;
      email: string | null;
      phone: string | null;
    }>;
    total: number;
    page: number;
    pageSize: number;
  }>(`/api/admin/index.php?${qs.toString()}`);
}

export async function adminUpdateCompany(id: string, patch: Record<string, unknown>) {
  await phpPost("/api/admin/index.php", { op: "company_update", id, ...patch });
}

export async function adminDeleteCompany(id: string) {
  await phpPost("/api/admin/index.php", { op: "company_delete", id });
}

export async function fetchPlansConfig() {
  const data = await phpGet<{
    plans: Array<{
      slug: string;
      name: string;
      price_cents: number;
      duration_days: number;
      max_photos: number;
      features: unknown;
      sort: number;
      updated_at: string;
    }>;
  }>("/api/admin/index.php?op=plans");
  return data.plans ?? [];
}

export async function updatePlanConfig(slug: string, patch: Record<string, unknown>) {
  await phpPost("/api/admin/index.php", { op: "plan_update", slug, ...patch });
}

export async function fetchSystemSettings() {
  const data = await phpGet<{
    settings: Array<{ key: string; value: unknown; is_public: boolean; updated_at: string }>;
  }>("/api/admin/index.php?op=settings");
  return data.settings ?? [];
}

export async function updateSetting(key: string, value: unknown) {
  await phpPost("/api/admin/index.php", { op: "setting_update", key, value });
}

export async function adminListLeads() {
  const data = await phpGet<{
    leads: Array<{
      id: string;
      name: string;
      phone: string;
      email: string | null;
      message: string | null;
      created_at: string;
      companies: { name: string } | null;
    }>;
  }>("/api/admin/index.php?op=leads");
  return data.leads ?? [];
}

export async function adminListPlanLeads() {
  const data = await phpGet<{
    leads: Array<{
      id: string;
      company_name: string;
      contact_name: string;
      email: string;
      phone: string | null;
      city: string | null;
      plan: string;
      message: string | null;
      created_at: string;
    }>;
  }>("/api/admin/index.php?op=plan_leads");
  return data.leads ?? [];
}
