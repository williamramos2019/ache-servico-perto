import { phpGet } from "@/lib/php-api";

async function nearestCity(lat: number, lng: number) {
  return phpGet<{ slug: string | null; name: string | null; distance_km?: number }>(
    `/api/catalog/index.php?op=nearest&lat=${encodeURIComponent(String(lat))}&lng=${encodeURIComponent(String(lng))}`,
  );
}

export async function detectCityByGPS(opts: { data: { lat: number; lng: number } }) {
  const { lat, lng } = opts.data;
  if (typeof lat !== "number" || typeof lng !== "number") {
    throw new Error("lat/lng required");
  }
  const nearest = await nearestCity(lat, lng);
  if (!nearest.slug) return { slug: null as string | null, name: null as string | null };
  return { slug: nearest.slug, name: nearest.name, distance_km: nearest.distance_km };
}

/** Client-side IP geo via ipapi.co (same provider as the old server function). */
export async function detectCityByIP() {
  try {
    const resp = await fetch("https://ipapi.co/json/", {
      headers: { "User-Agent": "AgendaAqui/1.0" },
    });
    if (!resp.ok) return { slug: null as string | null, name: null as string | null };
    const geo = (await resp.json()) as { latitude?: number; longitude?: number };
    if (typeof geo.latitude !== "number" || typeof geo.longitude !== "number") {
      return { slug: null, name: null };
    }
    const nearest = await nearestCity(geo.latitude, geo.longitude);
    if (!nearest.slug) return { slug: null, name: null };
    return { slug: nearest.slug, name: nearest.name };
  } catch {
    return { slug: null, name: null };
  }
}
