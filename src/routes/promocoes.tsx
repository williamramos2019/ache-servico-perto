import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { BadgePercent, Ticket } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState, PromotionCard } from "@/components/site/DomainWidgets";
import { Input } from "@/components/ui/input";
import { promotionsApi, type Promotion } from "@/lib/domain-api";
import { mergeUniqueRows } from "@/lib/frontend-domain-helpers";

export const Route = createFileRoute("/promocoes")({
  head: () => ({
    meta: [
      { title: "Promoções — AgendaAqui" },
      { name: "description", content: "Ofertas, descontos e cupons das empresas da sua cidade." },
      { property: "og:title", content: "Promoções — AgendaAqui" },
      { property: "og:description", content: "Ofertas e descontos das empresas da sua cidade." },
    ],
  }),
  component: PromocoesPage,
});

function PromocoesPage() {
  const [search, setSearch] = useState("");
  const promotions = usePagedPromotions("promotions");
  const coupons = usePagedPromotions("coupons");
  const filter = <T extends { title: string; description: string | null; company_name?: string | null }>(rows: T[] = []) => {
    const term = search.trim().toLowerCase();
    return term ? rows.filter((row) => `${row.title} ${row.description ?? ""} ${row.company_name ?? ""}`.toLowerCase().includes(term)) : rows;
  };
  const promotionRows = filter(promotions.rows);
  const couponRows = filter(coupons.rows);
  return (
    <SiteLayout>
      <header className="border-b bg-gradient-to-br from-primary/10 via-background to-accent/10"><div className="container mx-auto px-4 py-12"><p className="text-sm font-semibold uppercase tracking-wider text-primary">Promoções & cupons</p><h1 className="mt-2 font-display text-4xl font-extrabold">Economize perto de você</h1><p className="mt-3 max-w-2xl text-muted-foreground">Ofertas publicadas por empresas de Vespasiano, São José da Lapa e região.</p><Input value={search} onChange={(e) => setSearch(e.target.value)} className="mt-6 max-w-md" placeholder="Buscar oferta ou empresa" /></div></header>
      <main className="container mx-auto space-y-12 px-4 py-10">
        <section><h2 className="mb-4 font-display text-2xl font-bold"><BadgePercent className="mr-2 inline h-5 w-5 text-primary" />Promoções</h2><DataState loading={promotions.isLoading} error={promotions.error} empty={!promotionRows.length}><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{promotionRows.map((item) => <PromotionCard key={item.id} item={item} />)}</div>{promotions.hasMore && <div className="mt-6 text-center"><button className="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted" disabled={promotions.isFetching} onClick={promotions.loadMore}>{promotions.isFetching ? "Carregando…" : "Carregar mais promoções"}</button></div>}</DataState></section>
        <section><h2 className="mb-4 font-display text-2xl font-bold"><Ticket className="mr-2 inline h-5 w-5 text-primary" />Cupons</h2><DataState loading={coupons.isLoading} error={coupons.error} empty={!couponRows.length}><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{couponRows.map((item) => <PromotionCard key={item.id} item={item} coupon />)}</div>{coupons.hasMore && <div className="mt-6 text-center"><button className="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted" disabled={coupons.isFetching} onClick={coupons.loadMore}>{coupons.isFetching ? "Carregando…" : "Carregar mais cupons"}</button></div>}</DataState></section>
      </main>
    </SiteLayout>
  );
}

function usePagedPromotions(entity: "promotions" | "coupons") {
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<Promotion[]>([]);
  const query = useQuery({
    queryKey: [entity, page],
    queryFn: () => promotionsApi.list(entity, { page, limit: 12 }),
  });
  useEffect(() => {
    if (query.data) setRows((current) => mergeUniqueRows(current, query.data.rows));
  }, [query.data]);
  return {
    ...query,
    rows,
    hasMore: !!query.data && rows.length < query.data.total,
    loadMore: () => setPage((current) => current + 1),
  };
}
