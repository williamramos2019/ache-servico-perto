import { phpGet, phpPost } from "@/lib/php-api";

export type NavItem = { to: string; label: string; danger?: boolean };

export const DEFAULT_NAV_ITEMS: NavItem[] = [
  { to: "/", label: "Home" },
  { to: "/blog", label: "Notícias" },
  { to: "/buscar", label: "Empresas" },
  { to: "/eventos", label: "Eventos" },
  { to: "/o-que-fazer", label: "O que fazer" },
  { to: "/marketplace", label: "Marketplace" },
  { to: "/transporte", label: "Transporte" },
  { to: "/empregos", label: "Empregos" },
  { to: "/promocoes", label: "Promoções" },
  { to: "/ofertas-shopee", label: "Ofertas Shopee" },
  { to: "/agora", label: "Agora" },
  { to: "/representantes", label: "Representantes" },
  { to: "/transparencia", label: "Transparência" },
  { to: "/roteiro-turistico", label: "Turismo" },
  { to: "/vespasiano", label: "Vespasiano" },
];

export async function fetchNavItems(): Promise<NavItem[]> {
  try {
    const data = await phpGet<{ value: unknown }>("/api/content/index.php?op=setting&key=nav_items");
    if (!data.value || !Array.isArray(data.value)) return DEFAULT_NAV_ITEMS;
    const items = (data.value as NavItem[]).filter(
      (i) => i && typeof i.to === "string" && typeof i.label === "string",
    );
    return items.length ? items : DEFAULT_NAV_ITEMS;
  } catch {
    return DEFAULT_NAV_ITEMS;
  }
}

export async function saveNavItems(items: NavItem[]): Promise<void> {
  const clean = items
    .map((i) => ({
      to: String(i.to || "").trim(),
      label: String(i.label || "").trim(),
      ...(i.danger ? { danger: true } : {}),
    }))
    .filter((i) => i.to && i.label);
  await phpPost("/api/content/index.php", { op: "setting_save", key: "nav_items", value: clean });
}
