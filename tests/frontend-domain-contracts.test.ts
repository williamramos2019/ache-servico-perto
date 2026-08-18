import { afterEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import {
  buildQuery,
  jobsApi,
  normalizeRepresentativeFeed,
  promotionsApi,
  tourismApi,
  type RepresentativeFeedItem,
} from "../src/lib/domain-api";
import {
  matchesRoutePattern,
  isCampaignTargeted,
  mergeUniqueRows,
  parseLoadedBlacklist,
  selectWeightedCampaign,
} from "../src/lib/frontend-domain-helpers";

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("frontend PHP domain contracts", () => {
  it("omits empty query values and encodes supported filters", () => {
    expect(
      buildQuery("/api/jobs/index.php", {
        op: "list",
        q: "auxiliar geral",
        city: "",
        page: 2,
        remote: "yes",
        unused: undefined,
      }),
    ).toBe("/api/jobs/index.php?op=list&q=auxiliar+geral&page=2&remote=yes");
  });

  it("maps flat representative feed rows to the presentation shape", () => {
    const row = {
      id: "activity-1",
      representative_id: "rep-1",
      city_id: "city-1",
      kind: "projeto_lei",
      title: "Projeto publicado",
      description: null,
      status: "publicado",
      source_url: null,
      source_name: "Câmara",
      occurred_at: "2026-08-16T12:00:00Z",
      representative_name: "Maria Silva",
      representative_slug: "maria-silva",
      representative_role: "vereadora",
      city_slug: "vespasiano",
    } as RepresentativeFeedItem;

    expect(normalizeRepresentativeFeed(row).representative).toEqual({
      id: "rep-1",
      name: "Maria Silva",
      slug: "maria-silva",
      role: "vereadora",
    });
  });

  it("uses the same-origin jobs and coupon endpoint contracts", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            success: true,
            data: { rows: [], total: 0, page: 1, pageSize: 20 },
          }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ success: true, data: { rows: [] } }), {
          status: 200,
        }),
      );
    vi.stubGlobal("fetch", fetchMock);

    await jobsApi.list({ q: "dev", page: 1 });
    await promotionsApi.list("coupons", { category: "alimentacao" });

    expect(fetchMock.mock.calls[0][0]).toContain(
      "/api/jobs/index.php?op=list&q=dev&page=1",
    );
    expect(fetchMock.mock.calls[1][0]).toContain(
      "/api/promotions/index.php?op=list&entity=coupons&category=alimentacao",
    );
  });

  it("keeps parent routes as layouts and exact pages as index routes", () => {
    const root = resolve(import.meta.dirname, "..", "src", "routes");
    const jobsParent = readFileSync(resolve(root, "empregos.tsx"), "utf8");
    const jobsIndex = readFileSync(resolve(root, "empregos.index.tsx"), "utf8");
    const transportParent = readFileSync(resolve(root, "transporte.tsx"), "utf8");
    const transportIndex = readFileSync(resolve(root, "transporte.index.tsx"), "utf8");

    expect(jobsParent).toContain("component: Outlet");
    expect(jobsIndex).toContain('createFileRoute("/empregos/")');
    expect(transportParent).toContain("component: Outlet");
    expect(transportIndex).toContain('createFileRoute("/transporte/")');
  });

  it("matches exact, wildcard and global ad route patterns", () => {
    expect(matchesRoutePattern("/empregos/123", "/empregos/*")).toBe(true);
    expect(matchesRoutePattern("/empregos", "/empregos")).toBe(true);
    expect(matchesRoutePattern("/blog/noticia", "*")).toBe(true);
    expect(matchesRoutePattern("/admin", "/empregos/*")).toBe(false);
  });

  it("rejects ads with unsupported placements or unmatched routes", () => {
    expect(
      isCampaignTargeted(
        { route_patterns: ["/empregos/*"], placement: "bottom-right" },
        "/empregos/123",
      ),
    ).toBe(true);
    expect(
      isCampaignTargeted(
        { route_patterns: ["/empregos/*"], placement: "sidebar" },
        "/empregos/123",
      ),
    ).toBe(false);
  });

  it("selects targeted ads according to positive weights", () => {
    const campaigns = [
      { id: "a", weight: 1 },
      { id: "b", weight: 3 },
    ];
    expect(selectWeightedCampaign(campaigns, 0)?.id).toBe("a");
    expect(selectWeightedCampaign(campaigns, 0.99)?.id).toBe("b");
  });

  it("refuses blacklist edits until existing terms are loaded", () => {
    expect(parseLoadedBlacklist(null, "novo")).toBeNull();
    expect(parseLoadedBlacklist(["antigo"], "antigo\nnovo")).toEqual(["antigo", "novo"]);
  });

  it("merges paginated rows without duplicating records", () => {
    expect(
      mergeUniqueRows(
        [{ id: "1", title: "Primeiro" }],
        [
          { id: "1", title: "Atualizado" },
          { id: "2", title: "Segundo" },
        ],
      ),
    ).toEqual([
      { id: "1", title: "Atualizado" },
      { id: "2", title: "Segundo" },
    ]);
  });

  it("sends page and limit for promotions and tourism", async () => {
    const fetchMock = vi
      .fn()
      .mockImplementation(async () =>
        new Response(
          JSON.stringify({
            success: true,
            data: { rows: [], total: 0, page: 2, pageSize: 12 },
          }),
          { status: 200 },
        ),
      );
    vi.stubGlobal("fetch", fetchMock);

    await promotionsApi.list("promotions", { page: 2, limit: 12 });
    await tourismApi.list({ page: 2, limit: 12 });

    expect(fetchMock.mock.calls[0][0]).toContain("page=2&limit=12");
    expect(fetchMock.mock.calls[1][0]).toContain("page=2&limit=12");
  });

  it("contains no Lovable or Supabase dependency in local auth", () => {
    const auth = readFileSync(
      resolve(import.meta.dirname, "..", "src", "routes", "auth.tsx"),
      "utf8",
    );
    expect(auth).not.toMatch(/lovable|supabase|signInWithOAuth/i);
  });

  it("uses the same-origin Shopee catalog contract", async () => {
    const fetchMock = vi.fn().mockImplementation(async () =>
      new Response(
        JSON.stringify({
          success: true,
          data: { items: [], total: 0, page: 1, pageSize: 24 },
        }),
        { status: 200 },
      ),
    );
    vi.stubGlobal("fetch", fetchMock);

    const { shopeeApi } = await import("../src/lib/domain-api");
    await shopeeApi.list({ q: "fone", page: 2, minDiscount: 20, sort: "discount" });
    await shopeeApi.featured(12);
    await shopeeApi.categories();

    expect(String(fetchMock.mock.calls[0][0])).toContain("/api/shopee/index.php?");
    const listUrl = new URL(String(fetchMock.mock.calls[0][0]), "https://agendaqui.local");
    expect(listUrl.searchParams.get("op")).toBe("list");
    expect(listUrl.searchParams.get("q")).toBe("fone");
    expect(listUrl.searchParams.get("page")).toBe("2");
    expect(listUrl.searchParams.get("minDiscount")).toBe("20");
    expect(listUrl.searchParams.get("sort")).toBe("discount");
    expect(String(fetchMock.mock.calls[1][0])).toContain(
      "/api/shopee/index.php?op=featured&limit=12",
    );
    expect(String(fetchMock.mock.calls[2][0])).toContain(
      "/api/shopee/index.php?op=categories",
    );
  });

  it("keeps close-by-helper Shopee and landing routes", () => {
    const root = resolve(import.meta.dirname, "..", "src", "routes");
    expect(readFileSync(resolve(root, "ofertas-shopee.tsx"), "utf8")).toContain(
      'createFileRoute("/ofertas-shopee")',
    );
    expect(readFileSync(resolve(root, "cadastre-sua-empresa.tsx"), "utf8")).toContain(
      'createFileRoute("/cadastre-sua-empresa")',
    );
    expect(readFileSync(resolve(root, "admin.shopee-feeds.tsx"), "utf8")).toContain(
      'createFileRoute("/admin/shopee-feeds")',
    );
    expect(readFileSync(resolve(root, "painel.reivindicacoes.tsx"), "utf8")).toContain(
      'createFileRoute("/painel/reivindicacoes")',
    );
    expect(readFileSync(resolve(root, "transporte.linhas.tsx"), "utf8")).toContain(
      'createFileRoute("/transporte/linhas")',
    );
  });

  it("ports jobs and live-feed UI onto PHP adapters without Supabase realtime", () => {
    const jobsPage = readFileSync(
      resolve(import.meta.dirname, "..", "src", "routes", "empregos.tsx"),
      "utf8",
    );
    expect(jobsPage).toContain("jobsApi.list");
    expect(jobsPage).not.toContain("useServerFn");
    expect(jobsPage).not.toContain("supabase");
    const jobsIndex = readFileSync(
      resolve(import.meta.dirname, "..", "src", "routes", "empregos.index.tsx"),
      "utf8",
    );
    expect(jobsIndex).toContain("parseSearchParams");

    const liveHook = readFileSync(
      resolve(import.meta.dirname, "..", "src", "features", "live-feed", "useLiveFeed.ts"),
      "utf8",
    );
    expect(liveHook).toContain("liveFeedApi.list");
    expect(liveHook).toContain("refetchInterval: 30_000");
    expect(liveHook).not.toContain("supabase");
    expect(liveHook).not.toContain("postgres_changes");

    const sw = readFileSync(resolve(import.meta.dirname, "..", "public", "sw.js"), "utf8");
    expect(sw).toContain("/api/public/push/track");
    expect(sw).toContain("/api/public/push/resubscribe");
  });

  it("keeps civic modules in default nav and polls listing messages", () => {
    const nav = readFileSync(
      resolve(import.meta.dirname, "..", "src", "lib", "navItems.ts"),
      "utf8",
    );
    expect(nav).toContain('to: "/empregos"');
    expect(nav).toContain('to: "/representantes"');
    expect(nav).toContain('to: "/transparencia"');
    expect(nav).toContain('to: "/agora"');

    const messages = readFileSync(
      resolve(import.meta.dirname, "..", "src", "routes", "painel.mensagens.tsx"),
      "utf8",
    );
    expect(messages).toContain("fetchListingMessages");
    expect(messages).toContain("refetchInterval: 15_000");
    expect(messages).not.toContain("supabase");
  });
});
