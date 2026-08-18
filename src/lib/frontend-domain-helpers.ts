export function matchesRoutePattern(pathname: string, pattern: string): boolean {
  const clean = pattern.trim();
  if (clean === "" || clean === "*") return true;
  if (!clean.includes("*")) return pathname === clean;
  const escaped = clean
    .split("*")
    .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
    .join(".*");
  return new RegExp(`^${escaped}$`).test(pathname);
}

const AD_PLACEMENTS = new Set(["bottom-right", "bottom-center", "center"]);

export function isCampaignTargeted(
  campaign: { route_patterns: string[]; placement: string },
  pathname: string,
): boolean {
  if (!AD_PLACEMENTS.has(campaign.placement)) return false;
  return (
    campaign.route_patterns.length === 0 ||
    campaign.route_patterns.some((pattern) => matchesRoutePattern(pathname, pattern))
  );
}

export function selectWeightedCampaign<T extends { weight: number }>(
  campaigns: T[],
  random = Math.random(),
): T | null {
  const eligible = campaigns.filter((campaign) => Number.isFinite(campaign.weight) && campaign.weight > 0);
  if (eligible.length === 0) return null;
  const total = eligible.reduce((sum, campaign) => sum + campaign.weight, 0);
  let cursor = Math.min(Math.max(random, 0), 0.999999999) * total;
  for (const campaign of eligible) {
    cursor -= campaign.weight;
    if (cursor < 0) return campaign;
  }
  return eligible.at(-1) ?? null;
}

export function parseLoadedBlacklist(
  loaded: string[] | null,
  draft: string,
): string[] | null {
  if (loaded === null) return null;
  return Array.from(
    new Set(
      draft
        .split(/\r?\n/)
        .map((term) => term.trim().toLocaleLowerCase("pt-BR"))
        .filter(Boolean),
    ),
  );
}

export function mergeUniqueRows<T extends { id: string }>(current: T[], next: T[]): T[] {
  const rows = new Map(current.map((row) => [row.id, row]));
  for (const row of next) rows.set(row.id, row);
  return Array.from(rows.values());
}
