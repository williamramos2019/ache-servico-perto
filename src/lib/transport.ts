import { phpGet, phpPost } from "@/lib/php-api";

export type TransportSource = {
  name: string;
  url: string | null;
  type: string;
  collected_at: string | null;
};

export type TransportSchedule = {
  id?: string;
  direction: string;
  day_type: string;
  departure_time: string;
  control_point: string | null;
  notes: string | null;
};

export type TransportStop = {
  id?: string;
  sequence: number;
  name: string;
  address: string | null;
  lat: number | null;
  lng: number | null;
  has_coords?: boolean;
  direction: string;
};

export type TransportLineCard = {
  id: string;
  city_id: string | null;
  city_slug: string | null;
  city_name: string | null;
  code: string;
  slug: string;
  name: string;
  type: string;
  status: string;
  fare: string | null;
  operator_name: string | null;
  source: TransportSource | null;
  schedule_count: number;
  stop_count: number;
  updated_at: string;
};

export type TransportLineDetail = TransportLineCard & {
  notes: string | null;
};

export type TransportFacets = {
  cities: Record<string, number>;
  types: Record<string, number>;
  statuses: Record<string, number>;
};

export type TransportListResult = {
  lines: TransportLineCard[];
  total: number;
  page: number;
  limit: number;
  facets: TransportFacets;
};

const FAV_KEY = "transporte_favoritos";

export function readTransportFavs(): string[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(FAV_KEY);
    return raw ? (JSON.parse(raw) as string[]) : [];
  } catch {
    return [];
  }
}

export function writeTransportFavs(ids: string[]): void {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(FAV_KEY, JSON.stringify(ids));
}

export function lineIsFav(line: { id: string; slug: string; code: string }, favs: string[]): boolean {
  return favs.includes(line.slug) || favs.includes(line.id) || favs.includes(line.code);
}

export function toggleTransportFav(line: { id: string; slug: string; code: string }, prev: string[]): string[] {
  const on = lineIsFav(line, prev);
  const without = prev.filter((x) => x !== line.slug && x !== line.id && x !== line.code);
  return on ? without : [...without, line.slug];
}

export async function fetchTransportLines(opts: {
  city?: string;
  q?: string;
  type?: string;
  status?: string;
  page?: number;
  limit?: number;
} = {}): Promise<TransportListResult> {
  const qs = new URLSearchParams({ op: "list" });
  if (opts.city) qs.set("city", opts.city);
  if (opts.q) qs.set("q", opts.q);
  if (opts.type) qs.set("type", opts.type);
  if (opts.status) qs.set("status", opts.status);
  if (opts.page) qs.set("page", String(opts.page));
  if (opts.limit) qs.set("limit", String(opts.limit));
  const data = await phpGet<TransportListResult>(`/api/transport/index.php?${qs.toString()}`);
  return {
    lines: data.lines ?? [],
    total: data.total ?? 0,
    page: data.page ?? 1,
    limit: data.limit ?? 24,
    facets: data.facets ?? { cities: {}, types: {}, statuses: {} },
  };
}

export async function fetchTransportLine(slug: string) {
  const qs = new URLSearchParams({ op: "show", slug });
  const data = await phpGet<{ line: TransportLineDetail | null }>(`/api/transport/index.php?${qs.toString()}`);
  return data.line;
}

export async function fetchTransportSchedules(lineId: string) {
  const qs = new URLSearchParams({ op: "schedules", line_id: lineId });
  const data = await phpGet<{ schedules: TransportSchedule[] }>(`/api/transport/index.php?${qs.toString()}`);
  return data.schedules ?? [];
}

export async function fetchTransportStops(lineId: string) {
  const qs = new URLSearchParams({ op: "stops", line_id: lineId });
  const data = await phpGet<{ stops: TransportStop[] }>(`/api/transport/index.php?${qs.toString()}`);
  return data.stops ?? [];
}

export async function adminCreateTransportLine(body: Record<string, unknown>) {
  return phpPost<{ id: string; slug: string }>("/api/transport/index.php", { op: "line_create", ...body });
}

export async function adminUpdateTransportLine(id: string, patch: Record<string, unknown>) {
  return phpPost("/api/transport/index.php", { op: "line_update", id, ...patch });
}

export async function adminDeleteTransportLine(id: string) {
  return phpPost("/api/transport/index.php", { op: "line_delete", id });
}

export async function adminUpsertTransportSource(body: Record<string, unknown>) {
  return phpPost<{ id: string }>("/api/transport/index.php", { op: "source_upsert", ...body });
}

export async function adminSaveSchedule(body: Record<string, unknown>) {
  return phpPost<{ id: string }>("/api/transport/index.php", { op: "schedule_save", ...body });
}

export async function adminDeleteSchedule(id: string) {
  return phpPost("/api/transport/index.php", { op: "schedule_delete", id });
}

export async function adminSaveStop(body: Record<string, unknown>) {
  return phpPost<{ id: string }>("/api/transport/index.php", { op: "stop_save", ...body });
}

export async function adminDeleteStop(id: string) {
  return phpPost("/api/transport/index.php", { op: "stop_delete", id });
}

export function schedulesByDay(schedules: TransportSchedule[], day: string, direction?: string): string[] {
  return schedules
    .filter((s) => s.day_type === day && (direction === undefined || s.direction === direction))
    .map((s) => s.departure_time)
    .sort();
}

export function formatTransportDate(iso: string | null | undefined): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.toLocaleDateString("pt-BR");
}

export async function shareTransportLine(line: { code: string; name: string; slug: string }): Promise<void> {
  const url = typeof window !== "undefined" ? `${window.location.origin}/transporte/${line.slug}` : "";
  const text = `Linha ${line.code} — ${line.name}\n${url}`;
  if (typeof navigator !== "undefined" && typeof navigator.share === "function") {
    try {
      await navigator.share({ title: `Linha ${line.code}`, text, url });
      return;
    } catch {
      /* user cancelled or fallback */
    }
  }
  const wa = `https://wa.me/?text=${encodeURIComponent(text)}`;
  if (typeof window !== "undefined") {
    window.open(wa, "_blank", "noopener,noreferrer");
  }
}

export const TRANSPORT_TYPE_LABEL: Record<string, string> = {
  municipal: "Municipal",
  metropolitana: "Metropolitano",
  intermunicipal: "Intermunicipal",
  "tarifa-zero": "Tarifa zero",
};

export const TRANSPORT_STATUS_LABEL: Record<string, { label: string; className: string }> = {
  active: { label: "Em operação", className: "bg-emerald-100 text-emerald-800 border-emerald-200" },
  unknown: { label: "A confirmar", className: "bg-muted text-muted-foreground border-border" },
  inactive: { label: "Encerrada", className: "bg-muted text-muted-foreground border-border" },
  suspended: { label: "Suspensa", className: "bg-amber-100 text-amber-900 border-amber-200" },
  temporary: { label: "Temporária", className: "bg-sky-100 text-sky-900 border-sky-200" },
};

export const TRANSPORT_DAY_LABEL: Record<string, string> = {
  weekday: "Dias úteis",
  saturday: "Sábado",
  sunday: "Domingo",
  holiday: "Feriados",
  vacation: "Férias",
  atypical: "Atípico",
};
