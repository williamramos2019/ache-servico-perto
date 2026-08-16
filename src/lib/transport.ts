import { phpGet, phpPost } from "@/lib/php-api";

export type TransportSource = {
  name: string;
  url: string | null;
  type: string;
  collected_at: string | null;
};

export type TransportSchedule = {
  direction: string;
  day_type: string;
  departure_time: string;
  control_point: string | null;
  notes: string | null;
};

export type TransportStop = {
  sequence: number;
  name: string;
  address: string | null;
  lat: number | null;
  lng: number | null;
  direction: string;
};

export type TransportLine = {
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
  notes: string | null;
  source: TransportSource | null;
  schedules: TransportSchedule[];
  stops: TransportStop[];
  updated_at: string;
};

export async function fetchTransportLines(opts: { city?: string; q?: string; type?: string } = {}) {
  const qs = new URLSearchParams({ op: "list" });
  if (opts.city) qs.set("city", opts.city);
  if (opts.q) qs.set("q", opts.q);
  if (opts.type) qs.set("type", opts.type);
  const data = await phpGet<{ lines: TransportLine[] }>(`/api/transport/index.php?${qs.toString()}`);
  return data.lines ?? [];
}

export async function fetchTransportLine(slug: string) {
  const qs = new URLSearchParams({ op: "show", slug });
  const data = await phpGet<{ line: TransportLine | null }>(`/api/transport/index.php?${qs.toString()}`);
  return data.line;
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

export function schedulesByDay(line: TransportLine, day: string): string[] {
  return line.schedules
    .filter((s) => s.day_type === day)
    .map((s) => s.departure_time)
    .sort();
}
