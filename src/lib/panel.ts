import { supabase } from "@/integrations/supabase/client";

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

export async function listMyCompanies(userId: string): Promise<PanelCompany[]> {
  const { data, error } = await supabase
    .from("companies")
    .select("id, slug, name, tagline, plan, status, is_verified, featured, views_count, rating, review_count, logo_url, city_id, created_at")
    .eq("owner_id", userId)
    .order("created_at", { ascending: false });
  if (error) throw error;
  return (data ?? []) as PanelCompany[];
}

export async function panelStats(userId: string) {
  const [companies, leads, favs] = await Promise.all([
    supabase.from("companies").select("id, views_count, review_count", { count: "exact" }).eq("owner_id", userId),
    supabase.from("leads").select("id, created_at, company_id, companies!inner(owner_id)").eq("companies.owner_id", userId).order("created_at", { ascending: false }).limit(500),
    supabase.from("favorites").select("company_id", { count: "exact", head: true }).eq("user_id", userId),
  ]);
  const cs = companies.data ?? [];
  const ls = leads.data ?? [];
  const cutoff = Date.now() - 7 * 86400000;
  return {
    companyCount: companies.count ?? 0,
    totalViews: cs.reduce((s, c) => s + (c.views_count ?? 0), 0),
    totalReviews: cs.reduce((s, c) => s + (c.review_count ?? 0), 0),
    totalLeads: ls.length,
    leads7d: ls.filter((l) => new Date(l.created_at).getTime() >= cutoff).length,
    favoritesCount: favs.count ?? 0,
  };
}

export async function getMyCompany(userId: string, id: string) {
  const { data, error } = await supabase
    .from("companies")
    .select("*")
    .eq("id", id)
    .eq("owner_id", userId)
    .maybeSingle();
  if (error) throw error;
  return data;
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
  logo_url: string | null;
  banner_url: string | null;
  video_url: string | null;
  status: string;
}>;

export async function updateMyCompany(id: string, patch: CompanyPatch) {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { error } = await supabase.from("companies").update(patch as any).eq("id", id);
  if (error) throw error;
}

export async function deleteMyCompany(id: string) {
  const { error } = await supabase.from("companies").delete().eq("id", id);
  if (error) throw error;
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

export async function createMyCompany(userId: string, input: { name: string; tagline?: string; description?: string; city_id?: string | null; phone?: string; whatsapp?: string; email?: string; address?: string }) {
  const base = slugify(input.name) || `empresa-${Date.now()}`;
  let slug = base;
  for (let i = 0; i < 5; i++) {
    const { data: exists } = await supabase.from("companies").select("id").eq("slug", slug).maybeSingle();
    if (!exists) break;
    slug = `${base}-${Math.random().toString(36).slice(2, 6)}`;
  }
  const { data, error } = await supabase
    .from("companies")
    .insert({
      owner_id: userId,
      name: input.name,
      slug,
      tagline: input.tagline || null,
      description: input.description || null,
      city_id: input.city_id || null,
      phone: input.phone || null,
      whatsapp: input.whatsapp || null,
      email: input.email || null,
      address: input.address || null,
      plan: "free",
      status: "pending",
    })
    .select("id, slug")
    .single();
  if (error) throw error;
  return data;
}

export async function listMyLeads(userId: string) {
  const { data, error } = await supabase
    .from("leads")
    .select("id, name, phone, email, message, created_at, company_id, companies!inner(id, name, slug, owner_id)")
    .eq("companies.owner_id", userId)
    .order("created_at", { ascending: false })
    .limit(300);
  if (error) throw error;
  return data ?? [];
}

export async function listMyReviews(userId: string) {
  const { data, error } = await supabase
    .from("reviews")
    .select("id, rating, comment, created_at, user_id, company_id, companies!inner(id, name, slug, owner_id), profiles(name, avatar_url)")
    .eq("companies.owner_id", userId)
    .order("created_at", { ascending: false })
    .limit(300);
  if (error) throw error;
  return data ?? [];
}

export async function listCities() {
  const { data, error } = await supabase.from("cities").select("id, name, state").order("name");
  if (error) throw error;
  return data ?? [];
}

export async function getMyProfile(userId: string) {
  const { data, error } = await supabase.from("profiles").select("*").eq("id", userId).maybeSingle();
  if (error) throw error;
  return data;
}

export async function upsertMyProfile(userId: string, patch: { name?: string; avatar_url?: string | null }) {
  const { error } = await supabase.from("profiles").upsert({ id: userId, ...patch }, { onConflict: "id" });
  if (error) throw error;
}
