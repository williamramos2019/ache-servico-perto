import { shopeeApi, type ShopeeProduct, type ShopeeQuery } from "@/lib/domain-api";

export type { ShopeeProduct, ShopeeQuery };

export async function fetchFeaturedShopee(limit = 12): Promise<ShopeeProduct[]> {
  const data = await shopeeApi.featured(limit);
  return data.items ?? [];
}

export async function fetchShopeeProducts(params: ShopeeQuery) {
  const data = await shopeeApi.list(params);
  return { items: data.items ?? [], total: data.total ?? 0 };
}

export async function fetchShopeeCategories(): Promise<string[]> {
  try {
    const data = await shopeeApi.categories();
    return data.categories ?? [];
  } catch {
    return [];
  }
}

export async function fetchStripProducts(hint: string | undefined, limit: number): Promise<ShopeeProduct[]> {
  const data = await shopeeApi.strip(hint, limit);
  return data.items ?? [];
}

export function formatBRL(v: number | null | undefined) {
  if (v == null || Number.isNaN(v)) return "";
  return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}
