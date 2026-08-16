import { phpGet, phpPost } from "@/lib/php-api";

export type EventRow = {
  id: string;
  slug: string;
  title: string;
  description: string | null;
  cover_image: string | null;
  location: string | null;
  city_id: string | null;
  start_at: string;
  end_at: string | null;
  status: string;
  event_type: string | null;
  category_id: string | null;
  ticket_url: string | null;
  price_min: number | null;
  price_max: number | null;
  created_by?: string | null;
};

export type ShowRow = {
  id: string;
  event_id: string;
  artist_name: string;
  description: string | null;
  start_at: string;
  end_at: string | null;
  stage: string | null;
  cover_image: string | null;
  ticket_url: string | null;
  ticket_price: number | null;
  sort: number;
};

export type EventCategory = { id: string; slug: string; name: string; icon: string | null; sort: number };

export async function fetchPublishedEvents(opts?: { citySlug?: string; categorySlug?: string; q?: string }) {
  const qs = new URLSearchParams({ op: "events" });
  if (opts?.q) qs.set("q", opts.q);
  if (opts?.categorySlug) qs.set("category", opts.categorySlug);
  const data = await phpGet<{ events: EventRow[] }>(`/api/content/index.php?${qs.toString()}`);
  return data.events ?? [];
}

export async function fetchEventBySlug(slug: string) {
  const data = await phpGet<{ event: EventRow | null }>(
    `/api/content/index.php?op=event&slug=${encodeURIComponent(slug)}`,
  );
  return data.event;
}

export async function fetchShowsForEvent(eventId: string) {
  const data = await phpGet<{ shows: ShowRow[] }>(
    `/api/content/index.php?op=shows&event_id=${encodeURIComponent(eventId)}`,
  );
  return data.shows ?? [];
}

export async function fetchEventCategories() {
  const data = await phpGet<{ categories: EventCategory[] }>("/api/content/index.php?op=event_categories");
  return data.categories ?? [];
}

export async function fetchAllEvents(): Promise<EventRow[]> {
  const data = await phpGet<{ events: EventRow[] }>("/api/content/index.php?op=events_admin");
  return data.events ?? [];
}

export async function saveEvent(payload: Partial<EventRow> & { title?: string }) {
  await phpPost("/api/content/index.php", { op: "event_save", ...payload });
}

export async function deleteEvent(id: string) {
  await phpPost("/api/content/index.php", { op: "event_delete", id });
}

export async function saveShow(payload: Partial<ShowRow> & { event_id: string; artist_name?: string; start_at?: string }) {
  await phpPost("/api/content/index.php", { op: "show_save", ...payload });
}

export async function deleteShow(id: string) {
  await phpPost("/api/content/index.php", { op: "show_delete", id });
}
