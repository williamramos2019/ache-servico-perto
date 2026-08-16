import { phpGet, phpPost, phpUpload } from "@/lib/php-api";

export type ListingStatus = "ativo" | "vendido" | "pausado" | "removido";
export type ListingCondition = "novo" | "seminovo" | "usado";

export type Listing = {
  id: string;
  slug: string;
  user_id: string;
  city_id: string | null;
  category_slug: string;
  title: string;
  description: string | null;
  price: number | null;
  condition: ListingCondition;
  neighborhood: string | null;
  contact_phone: string | null;
  images: string[];
  status: ListingStatus;
  views_count: number;
  created_at: string;
  updated_at: string;
};

export type ListingCategory = {
  slug: string;
  name: string;
  icon: string | null;
  sort_order: number;
};

export type ListingMessage = {
  id: string;
  listing_id: string;
  buyer_id: string;
  seller_id: string;
  sender_id: string;
  body: string;
  read_at: string | null;
  created_at: string;
};

export const CONDITION_LABEL: Record<ListingCondition, string> = {
  novo: "Novo",
  seminovo: "Seminovo",
  usado: "Usado",
};

export const STATUS_LABEL: Record<ListingStatus, string> = {
  ativo: "Ativo",
  vendido: "Vendido",
  pausado: "Pausado",
  removido: "Removido",
};

const IMG_ALLOWED = ["image/jpeg", "image/png", "image/webp"];
const IMG_MAX_BYTES = 5 * 1024 * 1024;
export const MAX_IMAGES = 6;

export function formatBRL(v: number | null | undefined): string {
  if (v === null || v === undefined) return "A combinar";
  return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

export function slugify(s: string): string {
  return s
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 60);
}

export function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const min = Math.floor(diff / 60000);
  if (min < 1) return "agora";
  if (min < 60) return `há ${min} min`;
  const h = Math.floor(min / 60);
  if (h < 24) return `há ${h}h`;
  const d = Math.floor(h / 24);
  if (d < 30) return `há ${d}d`;
  const mo = Math.floor(d / 30);
  return `há ${mo}mo`;
}

export async function uploadListingImage(_userId: string, file: File): Promise<string> {
  if (!IMG_ALLOWED.includes(file.type)) throw new Error("Use JPG, PNG ou WebP.");
  if (file.size > IMG_MAX_BYTES) throw new Error("Imagem acima de 5 MB.");
  const data = await phpUpload<{ url: string }>("/api/upload/image.php", file, { kind: "listing" });
  return data.url;
}

export async function fetchCategories(): Promise<ListingCategory[]> {
  const data = await phpGet<{ categories: ListingCategory[] }>("/api/listings/index.php?op=categories");
  return data.categories ?? [];
}

export function toListing(row: Record<string, unknown>): Listing {
  const rawImages = row.images;
  const images = Array.isArray(rawImages)
    ? (rawImages as unknown[]).filter((x): x is string => typeof x === "string")
    : [];
  return { ...(row as unknown as Listing), images };
}

export async function searchListings(params: {
  q?: string;
  category?: string;
  cityId?: string;
  condition?: string;
  sort?: string;
}): Promise<Listing[]> {
  const qs = new URLSearchParams({ op: "search" });
  if (params.q) qs.set("q", params.q);
  if (params.category && params.category !== "todas") qs.set("category", params.category);
  if (params.cityId && params.cityId !== "todas") qs.set("city_id", params.cityId);
  if (params.condition && params.condition !== "todas") qs.set("condition", params.condition);
  if (params.sort) qs.set("sort", params.sort);
  const data = await phpGet<{ listings: Listing[] }>(`/api/listings/index.php?${qs.toString()}`);
  return (data.listings ?? []).map((row) => toListing(row as unknown as Record<string, unknown>));
}

export async function fetchListingBySlug(slug: string): Promise<Listing | null> {
  const data = await phpGet<{ listing: Listing | null }>(
    `/api/listings/index.php?op=show&slug=${encodeURIComponent(slug)}`,
  );
  return data.listing ? toListing(data.listing as unknown as Record<string, unknown>) : null;
}

export async function fetchListingById(id: string): Promise<Listing | null> {
  const data = await phpGet<{ listing: Listing | null }>(
    `/api/listings/index.php?op=get&id=${encodeURIComponent(id)}`,
  );
  return data.listing ? toListing(data.listing as unknown as Record<string, unknown>) : null;
}

export async function fetchMyListings(): Promise<Listing[]> {
  const data = await phpGet<{ listings: Listing[] }>("/api/listings/index.php?op=mine");
  return (data.listings ?? []).map((row) => toListing(row as unknown as Record<string, unknown>));
}

export async function saveListing(input: {
  id?: string;
  title: string;
  description: string | null;
  price: number | null;
  condition: ListingCondition;
  category_slug: string;
  city_id: string;
  neighborhood: string | null;
  contact_phone: string | null;
  images: string[];
}) {
  await phpPost("/api/listings/index.php", {
    op: input.id ? "update" : "create",
    ...input,
  });
}

export async function updateListingStatus(id: string, status: ListingStatus) {
  await phpPost("/api/listings/index.php", { op: "status", id, status });
}

export async function deleteListing(id: string) {
  await phpPost("/api/listings/index.php", { op: "delete", id });
}

export async function fetchSellerProfile(userId: string) {
  const data = await phpGet<{ profile: { name: string | null; avatar_url: string | null } | null }>(
    `/api/listings/index.php?op=seller&user_id=${encodeURIComponent(userId)}`,
  );
  return data.profile;
}

export async function fetchOtherListings(userId: string, excludeId: string): Promise<Listing[]> {
  const data = await phpGet<{ listings: Listing[] }>(
    `/api/listings/index.php?op=other&user_id=${encodeURIComponent(userId)}&exclude_id=${encodeURIComponent(excludeId)}`,
  );
  return (data.listings ?? []).map((row) => toListing(row as unknown as Record<string, unknown>));
}

export async function sendListingMessage(input: {
  listing_id: string;
  body: string;
  buyer_id?: string;
}) {
  await phpPost("/api/listings/index.php", { op: "message", ...input });
}

export async function reportListing(input: { listing_id: string; reason: string; notes?: string | null }) {
  await phpPost("/api/listings/index.php", { op: "report", ...input });
}

export async function fetchListingMessages(): Promise<ListingMessage[]> {
  const data = await phpGet<{ messages: ListingMessage[] }>("/api/listings/index.php?op=messages");
  return data.messages ?? [];
}

export async function fetchListingThread(listingId: string, buyerId: string): Promise<ListingMessage[]> {
  const qs = new URLSearchParams({ op: "thread", listing_id: listingId, buyer_id: buyerId });
  const data = await phpGet<{ messages: ListingMessage[] }>(`/api/listings/index.php?${qs.toString()}`);
  return data.messages ?? [];
}
