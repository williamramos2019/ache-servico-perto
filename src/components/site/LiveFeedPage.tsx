import { useMemo, useState } from "react";
import { Radio, RefreshCw } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState } from "@/components/site/DomainWidgets";
import { Button } from "@/components/ui/button";
import { KIND_FILTERS, LiveFeedItemCard, useLiveFeed } from "@/features/live-feed";
import { useSelectedCity, CITY_OPTIONS } from "@/hooks/useSelectedCity";

export function LiveFeedPage({ title = "Agora na sua cidade" }: { title?: string }) {
  const { city } = useSelectedCity();
  const [kind, setKind] = useState("");
  const query = useLiveFeed({ city, limit: 80 });
  const items = useMemo(
    () => query.items.filter((item) => !kind || item.kind === kind),
    [query.items, kind],
  );
  const cityName = CITY_OPTIONS.find((option) => option.slug === city)?.name ?? city;
  return (
    <SiteLayout>
      <header className="border-b bg-gradient-to-br from-primary/10 via-background to-accent/5">
        <div className="container mx-auto px-4 py-12">
          <p className="text-sm font-semibold uppercase tracking-wider text-red-600">
            <Radio className="mr-2 inline h-4 w-4" />Ao vivo
          </p>
          <h1 className="mt-2 font-display text-4xl font-extrabold">{title}</h1>
          <p className="mt-3 max-w-2xl text-muted-foreground">
            Eventos, vagas, promoções e atividades dos vereadores em {cityName}, reunidos em um único feed.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <select
              value={kind}
              onChange={(e) => setKind(e.target.value)}
              className="h-10 rounded-md border bg-background px-3 text-sm"
            >
              {KIND_FILTERS.map((option) => (
                <option key={option.value || "all"} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <Button variant="outline" onClick={() => query.refetch()} disabled={query.isFetching}>
              <RefreshCw className={`mr-2 h-4 w-4 ${query.isFetching ? "animate-spin" : ""}`} />
              Atualizar
            </Button>
          </div>
        </div>
      </header>
      <main className="container mx-auto px-4 py-10">
        <DataState loading={query.isLoading} error={query.error} empty={!items.length}>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {items.map((item) => (
              <LiveFeedItemCard key={item.id} item={item} />
            ))}
          </div>
        </DataState>
      </main>
    </SiteLayout>
  );
}
