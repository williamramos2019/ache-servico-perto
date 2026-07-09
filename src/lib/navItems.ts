import { supabase } from "@/integrations/supabase/client";

export type NavItem = { to: string; label: string; danger?: boolean };

export const DEFAULT_NAV_ITEMS: NavItem[] = [
  { to: "/", label: "Início" },
  { to: "/servicos-publicos", label: "Serviços Públicos" },
  { to: "/emergencia", label: "Emergência", danger: true },
  { to: "/buscar", label: "Empresas" },
  { to: "/eventos", label: "Eventos" },
  { to: "/blog", label: "Blog" },
  { to: "/sobre", label: "Sobre" },
];

export async function fetchNavItems(): Promise<NavItem[]> {
  const { data, error } = await supabase
    .from("system_settings")
    .select("value")
    .eq("key", "nav_items")
    .maybeSingle();
  if (error || !data?.value || !Array.isArray(data.value)) return DEFAULT_NAV_ITEMS;
  const items = (data.value as unknown as NavItem[]).filter(
    (i) => i && typeof i.to === "string" && typeof i.label === "string",
  );
  return items.length ? items : DEFAULT_NAV_ITEMS;
}

export async function saveNavItems(items: NavItem[]): Promise<void> {
  const clean = items
    .map((i) => ({
      to: String(i.to || "").trim(),
      label: String(i.label || "").trim(),
      ...(i.danger ? { danger: true } : {}),
    }))
    .filter((i) => i.to && i.label);
  const { error } = await supabase
    .from("system_settings")
    .upsert(
      { key: "nav_items", value: clean as never, is_public: true, updated_at: new Date().toISOString() },
      { onConflict: "key" },
    );
  if (error) throw error;
}
