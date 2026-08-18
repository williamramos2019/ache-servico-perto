import { phpDelete, phpGet, phpPatch, phpPost, phpUploadForm } from "@/lib/php-api";

type QueryValue = string | number | boolean | null | undefined;

export function buildQuery(path: string, values: Record<string, QueryValue>): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(values)) {
    if (value === undefined || value === null || value === "") continue;
    query.set(key, String(value));
  }
  const encoded = query.toString();
  return encoded ? `${path}?${encoded}` : path;
}

export type Paged<T> = {
  rows: T[];
  total: number;
  page: number;
  pageSize: number;
};

export type Job = {
  id: string;
  title: string;
  company_name: string | null;
  description?: string | null;
  location_city: string | null;
  location_state: string | null;
  is_remote: boolean;
  employment_type: string | null;
  experience_level: string | null;
  category: string | null;
  tags: string[];
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  apply_url: string | null;
  apply_email?: string | null;
  apply_whatsapp?: string | null;
  posted_at: string | null;
  expires_at?: string | null;
  is_premium: boolean;
  company_id?: string | null;
  company_logo_url?: string | null;
  company_size?: string | null;
  company_culture?: string | null;
  requirements?: string[];
  nice_to_have?: string[];
  benefits?: string[];
  responsibilities?: string[];
  workload?: string | null;
  application_deadline?: string | null;
  featured_until?: string | null;
  source_name?: string | null;
  source_slug?: string | null;
  is_active?: boolean;
  [key: string]: unknown;
};

export type JobFilters = {
  q?: string;
  city?: string;
  state?: string;
  category?: string;
  employment?: string;
  experience?: string;
  remote?: "all" | "yes" | "no";
  salaryMin?: number;
  sort?: "recent" | "salary_desc" | "salary_asc";
  page?: number;
  limit?: number;
};

export type JobSource = {
  id: string;
  slug: string;
  name: string;
  kind: "api" | "scrape" | "manual";
  config: Record<string, unknown>;
  is_active: boolean;
  sync_frequency_minutes: number;
  endpoint_url?: string | null;
};

export const jobsApi = {
  list: (filters: JobFilters = {}) =>
    phpGet<Paged<Job>>(buildQuery("/api/jobs/index.php", { op: "list", ...filters })),
  premium: (filters: JobFilters = {}) =>
    phpGet<Paged<Job>>(buildQuery("/api/jobs/index.php", { op: "premium", ...filters })),
  show: async (id: string) =>
    (await phpGet<{ job: Job | null }>(
      buildQuery("/api/jobs/index.php", { op: "show", id }),
    )).job,
  facets: () =>
    phpGet<{ employment: string[]; experience: string[]; category: string[] }>(
      "/api/jobs/index.php?op=facets",
    ),
  adminList: (filters: Record<string, QueryValue> = {}) =>
    phpGet<Paged<Job>>(buildQuery("/api/jobs/admin.php", { op: "list", ...filters })),
  sources: () =>
    phpGet<{ sources: JobSource[] }>("/api/jobs/admin.php?op=sources"),
  logs: () => phpGet<{ logs: Array<Record<string, unknown>> }>("/api/jobs/admin.php?op=logs"),
  save: (job: Partial<Job>) => phpPost<{ id: string }>("/api/jobs/admin.php", { op: "job_save", ...job }),
  toggle: (id: string, is_active: boolean) =>
    phpPatch<{ ok: boolean }>("/api/jobs/admin.php", { op: "job_toggle", id, is_active }),
  remove: (id: string) =>
    phpDelete<{ ok: boolean }>("/api/jobs/admin.php", { op: "job_delete", id }),
  sync: (source_id: string) =>
    phpPost<Record<string, unknown>>("/api/jobs/admin.php", { op: "sync", source_id }),
  saveSource: (source: Partial<JobSource>) =>
    phpPost<{ id: string }>("/api/jobs/admin.php", { op: "source_save", ...source }),
  removeSource: (id: string) =>
    phpDelete<{ ok: boolean }>("/api/jobs/admin.php", { op: "source_delete", id }),
};

export type Representative = {
  id: string;
  name: string;
  slug: string;
  role: string;
  city_id: string;
  city_name?: string;
  city_slug?: string;
  party: string | null;
  photo_url: string | null;
  email: string | null;
  phone: string | null;
  social_links: Record<string, string>;
  mandate_start: string | null;
  mandate_end: string | null;
  is_active: boolean;
  bio: string | null;
  activities?: RepresentativeFeedItem[];
  attendance?: Attendance[];
  [key: string]: unknown;
};

export type RepresentativeFeedItem = {
  id: string;
  representative_id: string | null;
  city_id: string;
  kind: string;
  title: string;
  description: string | null;
  status: string | null;
  source_url: string | null;
  source_name: string | null;
  occurred_at: string;
  representative_name?: string | null;
  representative_slug?: string | null;
  representative_role?: string | null;
  city_slug?: string | null;
  representative?: { id: string; name: string; slug: string; role: string } | null;
};

export type Attendance = {
  id: string;
  session_date: string;
  session_type: string | null;
  present: boolean;
  notes?: string | null;
};

export type RankingRow = {
  representative: Representative;
  activities_count: number;
  sessions_count: number;
  absences_count: number;
  attendance_rate: number;
};

export function normalizeRepresentativeFeed(
  row: RepresentativeFeedItem,
): RepresentativeFeedItem {
  if (row.representative || !row.representative_id || !row.representative_name) return row;
  return {
    ...row,
    representative: {
      id: row.representative_id,
      name: row.representative_name,
      slug: row.representative_slug ?? row.representative_id,
      role: row.representative_role ?? "representante",
    },
  };
}

export const representativesApi = {
  list: (filters: { city?: string; role?: string; page?: number; limit?: number } = {}) =>
    phpGet<Paged<Representative>>(
      buildQuery("/api/representatives/index.php", { op: "list", ...filters }),
    ),
  show: async (id: string) =>
    (await phpGet<{ representative: Representative | null }>(
      buildQuery("/api/representatives/index.php", { op: "show", id }),
    )).representative,
  feed: async (
    filters: {
      city?: string;
      kind?: string;
      status?: string;
      sinceDays?: number;
      limit?: number;
      cursor?: string;
    } = {},
  ) => {
    const result = await phpGet<{ rows: RepresentativeFeedItem[]; cursor: string | null }>(
      buildQuery("/api/representatives/index.php", { op: "feed", ...filters }),
    );
    return { ...result, rows: result.rows.map(normalizeRepresentativeFeed) };
  },
  ranking: (city: string) =>
    phpGet<{ rows: RankingRow[] }>(
      buildQuery("/api/representatives/index.php", { op: "ranking", city }),
    ),
};

export type LiveFeedItem = {
  id: string;
  kind: string;
  source: string;
  source_id: string;
  title: string;
  subtitle: string | null;
  image: string | null;
  href: string;
  timestamp: string;
  city_slug: string | null;
};

export const liveFeedApi = {
  list: (filters: { city?: string; limit?: number; cursor?: string } = {}) =>
    phpGet<{ items: LiveFeedItem[]; cursor: string | null }>(
      buildQuery("/api/live-feed/index.php", { op: "list", ...filters }),
    ),
  hidden: () =>
    phpGet<{ rows: Array<Record<string, unknown>> }>("/api/live-feed/index.php?op=hidden"),
  blacklist: () =>
    phpGet<{ terms: string[] }>("/api/live-feed/index.php?op=blacklist"),
  hide: (source: string, source_id: string, reason?: string) =>
    phpPost<{ ok: boolean }>("/api/live-feed/index.php", { op: "hide", source, source_id, reason }),
  unhide: (source: string, source_id: string) =>
    phpDelete<{ ok: boolean }>("/api/live-feed/index.php", { op: "unhide", source, source_id }),
  saveBlacklist: (terms: string[]) =>
    phpPost<{ ok: boolean }>("/api/live-feed/index.php", { op: "blacklist_save", terms }),
};

export type Attraction = {
  id: string;
  title: string;
  slug: string;
  description: string;
  category: string;
  city_id: string | null;
  city_name?: string | null;
  city_slug?: string | null;
  image_url: string | null;
  link_url: string | null;
  meta: string | null;
  tag: string | null;
  sort_order: number;
  is_active: boolean;
};

export const tourismApi = {
  list: (filters: { city?: string; category?: string; page?: number; limit?: number } = {}) =>
    phpGet<Paged<Attraction>>(
      buildQuery("/api/tourism/index.php", { op: "list", ...filters }),
    ),
  admin: (page = 1, limit = 100) =>
    phpGet<Paged<Attraction>>(
      buildQuery("/api/tourism/index.php", { op: "admin", page, limit }),
    ),
  save: (item: Partial<Attraction>) =>
    phpPost<{ id: string }>("/api/tourism/index.php", { op: "save", ...item }),
  remove: (id: string) =>
    phpDelete<{ ok: boolean }>("/api/tourism/index.php", { op: "delete", id }),
};

export type Procurement = {
  id: string;
  city_id: string;
  city_name: string;
  city_slug: string;
  source_url: string;
  source_site: string;
  external_id: string | null;
  process_number: string | null;
  modality: string | null;
  title: string;
  object: string | null;
  agency: string | null;
  status: string;
  publish_date: string | null;
  opening_date: string | null;
  deadline_date: string | null;
  estimated_value: number | null;
  files: Array<{ name?: string; url: string }>;
};

export const procurementsApi = {
  list: (filters: {
    q?: string;
    city?: string;
    modality?: string;
    status?: string;
    page?: number;
    limit?: number;
  } = {}) =>
    phpGet<{ items: Procurement[]; total: number; page: number; pageSize: number }>(
      buildQuery("/api/procurements/index.php", { op: "list", ...filters }),
    ),
};

export type Promotion = {
  id: string;
  company_id: string | null;
  company_name?: string | null;
  company_slug?: string | null;
  city_id: string | null;
  city_name?: string | null;
  city_slug?: string | null;
  title: string;
  description: string | null;
  code?: string;
  cover_image?: string | null;
  image_url: string | null;
  link_url: string | null;
  category: string | null;
  discount_percent: number | null;
  discount_label?: string | null;
  price_from?: number | null;
  price_to?: number | null;
  terms?: string | null;
  valid_from: string | null;
  valid_to: string | null;
  is_sponsored?: boolean;
  status: string;
};

export const promotionsApi = {
  list: (entity: "promotions" | "coupons", filters: Record<string, QueryValue> = {}) =>
    phpGet<Paged<Promotion>>(
      buildQuery("/api/promotions/index.php", { op: "list", entity, ...filters }),
    ),
  admin: (entity: "promotions" | "coupons") =>
    phpGet<Paged<Promotion>>(
      buildQuery("/api/promotions/index.php", { op: "admin", entity }),
    ),
  owner: (entity: "promotions" | "coupons", filters: Record<string, QueryValue> = {}) =>
    phpGet<Paged<Promotion>>(
      buildQuery("/api/promotions/index.php", { op: "owner", entity, ...filters }),
    ),
  save: (entity: "promotions" | "coupons", item: Partial<Promotion>) =>
    phpPost<{ id: string }>("/api/promotions/index.php", { op: "save", entity, ...item }),
  remove: (entity: "promotions" | "coupons", id: string) =>
    phpDelete<{ ok: boolean }>("/api/promotions/index.php", { op: "delete", entity, id }),
};

export type AdCampaign = {
  id: string;
  name: string;
  image_url: string;
  link_url: string;
  city_slug: string | null;
  placement: string;
  delay_seconds: number;
  scroll_trigger_percent: number;
  display_seconds: number;
  active: boolean;
  starts_at: string | null;
  ends_at: string | null;
  route_patterns: string[];
  impressions: number;
  clicks: number;
  weight: number;
};

export const adsApi = {
  list: (city?: string) =>
    phpGet<{ rows: AdCampaign[] }>(
      buildQuery("/api/ads/index.php", { op: "list", city }),
    ),
  admin: () => phpGet<{ rows: AdCampaign[] }>("/api/ads/index.php?op=admin"),
  save: (item: Partial<AdCampaign>) =>
    phpPost<{ id: string }>("/api/ads/index.php", { op: "save", ...item }),
  remove: (id: string) =>
    phpDelete<{ ok: boolean }>("/api/ads/index.php", { op: "delete", id }),
  track: (id: string, event: "impression" | "click") =>
    phpPost<{ ok: boolean }>("/api/ads/index.php", { op: "track", id, event }),
};

export type UserRequest = {
  id: string;
  request_number: string;
  category: string;
  subject: string;
  description: string;
  user_name: string | null;
  user_email: string | null;
  user_phone: string | null;
  status: string;
  priority: string;
  admin_response: string | null;
  created_at: string;
  [key: string]: unknown;
};

export const requestsApi = {
  create: (request: Record<string, unknown>) =>
    phpPost<{ id: string; request_number: string }>("/api/requests/index.php", {
      op: "create",
      ...request,
    }),
  list: (filters: Record<string, QueryValue> = {}) =>
    phpGet<{ rows: UserRequest[]; stats: Record<string, number> }>(
      buildQuery("/api/requests/index.php", filters),
    ),
  update: (request: Partial<UserRequest> & { id: string }) =>
    phpPatch<{ ok: boolean }>("/api/requests/index.php", { op: "update", ...request }),
  remove: (id: string) =>
    phpDelete<{ ok: boolean }>("/api/requests/index.php", { op: "delete", id }),
};

export type EditorialPost = {
  id: string;
  publish_date: string;
  theme: string;
  format: string;
  caption: string;
  status: string;
  campaign: string | null;
  city: string | null;
  tags: string[];
  notes: string | null;
};

export const editorialApi = {
  list: (month: string) =>
    phpGet<{ rows: EditorialPost[] }>(
      buildQuery("/api/editorial/index.php", { op: "list", month }),
    ),
  aiStatus: () =>
    phpGet<{ available: boolean; configured: boolean; state: string; message: string }>(
      "/api/editorial/index.php?op=ai_status",
    ),
  save: (post: Partial<EditorialPost>) =>
    phpPost<{ id: string }>("/api/editorial/index.php", { op: "save", ...post }),
  remove: (id: string) =>
    phpDelete<{ ok: boolean }>("/api/editorial/index.php", { op: "delete", id }),
};

export const authRecoveryApi = {
  request: (email: string) =>
    phpPost<{ ok: boolean; message: string }>("/api/auth/reset-request.php", { email }),
  confirm: (token: string, password: string) =>
    phpPost<{ ok: boolean }>("/api/auth/reset-confirm.php", { token, password }),
};

export const whatsappApi = {
  subscribe: (payload: { name: string; phone: string; city_slug: string; consent: boolean }) =>
    phpPost<{
      ok: boolean;
      phone: string;
      city: string;
      idempotent: boolean;
      authorization_required?: boolean;
      welcome_status: string;
      opt_out_token: string | null;
    }>(
      "/api/whatsapp/subscribe.php",
      payload,
    ),
};

export const backupApi = {
  export: () =>
    phpPost<{ filename: string; download_url: string; manifest: Record<string, unknown> }>(
      "/api/admin/backup.php?op=export",
    ),
  import: (file: File) => {
    const form = new FormData();
    form.append("backup", file, file.name);
    return phpUploadForm<{ imported: Record<string, number> }>(
      "/api/admin/backup.php?op=import",
      form,
    );
  },
};

export type ShopeeProduct = {
  id: string;
  itemid: number;
  title: string;
  description: string | null;
  image_link: string | null;
  product_link: string;
  product_short_link: string | null;
  price: number | null;
  sale_price: number | null;
  discount_percentage: number | null;
  item_rating: number | null;
  global_category1: string | null;
  global_category2: string | null;
};

export type ShopeeQuery = {
  q?: string;
  category?: string | null;
  minDiscount?: number;
  minRating?: number;
  sort?: "discount" | "rating" | "price_asc" | "price_desc";
  page?: number;
  pageSize?: number;
};

export const shopeeApi = {
  list: (filters: ShopeeQuery = {}) =>
    phpGet<{ items: ShopeeProduct[]; total: number; page: number; pageSize: number }>(
      buildQuery("/api/shopee/index.php", {
        op: "list",
        q: filters.q,
        category: filters.category,
        minDiscount: filters.minDiscount,
        minRating: filters.minRating,
        sort: filters.sort,
        page: filters.page,
        limit: filters.pageSize,
      }),
    ),
  featured: (limit = 12) =>
    phpGet<{ items: ShopeeProduct[] }>(
      buildQuery("/api/shopee/index.php", { op: "featured", limit }),
    ),
  categories: () =>
    phpGet<{ categories: string[] }>("/api/shopee/index.php?op=categories"),
  feeds: () =>
    phpGet<{ feeds: Array<{ id: string; name: string; description: string; url: string }> }>(
      "/api/shopee/index.php?op=feeds",
    ),
  strip: (hint?: string, limit = 3) =>
    phpGet<{ items: ShopeeProduct[] }>(
      buildQuery("/api/shopee/index.php", { op: "strip", hint, limit }),
    ),
  toggle: (id: string, fields: { is_featured?: boolean; is_active?: boolean }) =>
    phpPost<{ ok: boolean }>("/api/shopee/index.php", { op: "toggle", id, ...fields }),
};

