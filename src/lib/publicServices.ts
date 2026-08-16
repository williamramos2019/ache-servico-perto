import { phpGet, phpPost } from "@/lib/php-api";

export type PublicServiceCategory =
  | "saude"
  | "educacao"
  | "seguranca"
  | "prefeitura"
  | "transporte"
  | "assistencia_social"
  | "emergencia"
  | "outros";

export type PublicService = {
  id: string;
  city_id: string;
  name: string;
  category: PublicServiceCategory;
  subtype: string | null;
  description: string | null;
  address: string | null;
  neighborhood: string | null;
  phone: string | null;
  phone_secondary: string | null;
  whatsapp: string | null;
  email: string | null;
  website: string | null;
  hours: string | null;
  lat: number | null;
  lng: number | null;
  featured: boolean;
  is_24h: boolean;
  active: boolean;
  created_at: string;
  updated_at: string;
  cities?: { name: string; slug: string } | null;
};

export type EmergencyContact = {
  id: string;
  city_id: string | null;
  name: string;
  phone: string;
  description: string | null;
  icon: string | null;
  sort_order: number;
  active: boolean;
  created_at: string;
  updated_at: string;
  cities?: { name: string; slug: string } | null;
};

export const PUBLIC_SERVICE_CATEGORIES: {
  slug: PublicServiceCategory;
  label: string;
  icon: string;
  description: string;
}[] = [
  { slug: "saude", label: "Saúde", icon: "HeartPulse", description: "Hospitais, UBS, farmácias 24h" },
  { slug: "educacao", label: "Educação", icon: "GraduationCap", description: "Escolas, creches, cursos" },
  { slug: "seguranca", label: "Segurança", icon: "Shield", description: "Delegacias, guarda municipal" },
  { slug: "prefeitura", label: "Prefeitura", icon: "Building2", description: "Secretarias e serviços" },
  { slug: "transporte", label: "Transporte", icon: "Bus", description: "Linhas, rodoviária, terminais" },
  { slug: "assistencia_social", label: "Assistência Social", icon: "HandHeart", description: "CRAS, CREAS, apoio" },
  { slug: "emergencia", label: "Emergência", icon: "Siren", description: "Serviços de urgência 24h" },
  { slug: "outros", label: "Outros", icon: "MoreHorizontal", description: "Demais serviços públicos" },
];

export function categoryLabel(slug: PublicServiceCategory | string | null | undefined): string {
  return PUBLIC_SERVICE_CATEGORIES.find((c) => c.slug === slug)?.label ?? "Outros";
}

export async function fetchPublicServices(opts?: {
  citySlug?: string | null;
  category?: PublicServiceCategory | null;
  limit?: number;
}): Promise<PublicService[]> {
  const qs = new URLSearchParams({ op: "public_services" });
  if (opts?.citySlug) qs.set("city", opts.citySlug);
  if (opts?.category) qs.set("category", opts.category);
  if (opts?.limit) qs.set("limit", String(opts.limit));
  const data = await phpGet<{ services: PublicService[] }>(`/api/content/index.php?${qs.toString()}`);
  return data.services ?? [];
}

export async function fetchPublicServiceById(id: string): Promise<PublicService | null> {
  const data = await phpGet<{ service: PublicService | null }>(
    `/api/content/index.php?op=public_service&id=${encodeURIComponent(id)}`,
  );
  return data.service;
}

export async function fetchEmergencyContacts(citySlug?: string | null): Promise<EmergencyContact[]> {
  const qs = new URLSearchParams({ op: "emergency" });
  if (citySlug) qs.set("city", citySlug);
  const data = await phpGet<{ contacts: EmergencyContact[] }>(`/api/content/index.php?${qs.toString()}`);
  return data.contacts ?? [];
}

export type PublicServiceInput = Partial<PublicService> & { name: string; city_id: string; category: string };

export async function adminUpsertPublicService(input: PublicServiceInput & { id?: string }) {
  const data = await phpPost<{ id: string }>("/api/content/index.php", { op: "service_save", ...input });
  return data.id;
}

export async function adminDeletePublicService(id: string) {
  await phpPost("/api/content/index.php", { op: "service_delete", id });
}

export type EmergencyContactInput = Partial<EmergencyContact> & { name: string; phone: string };

export async function adminUpsertEmergencyContact(input: EmergencyContactInput & { id?: string }) {
  const data = await phpPost<{ id: string }>("/api/content/index.php", { op: "emergency_save", ...input });
  return data.id;
}

export async function adminDeleteEmergencyContact(id: string) {
  await phpPost("/api/content/index.php", { op: "emergency_delete", id });
}

export async function adminListAllPublicServices(): Promise<PublicService[]> {
  const data = await phpGet<{ services: PublicService[] }>("/api/content/index.php?op=public_services_admin");
  return data.services ?? [];
}

export async function adminListAllEmergencyContacts(): Promise<EmergencyContact[]> {
  const data = await phpGet<{ contacts: EmergencyContact[] }>("/api/content/index.php?op=emergency_admin");
  return data.contacts ?? [];
}
