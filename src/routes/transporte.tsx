import { useState, type ReactNode } from "react";
import { createFileRoute, Link, Outlet, useNavigate } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { z } from "zod";
import { Bus, MapPin, Star, Search, Ticket, SlidersHorizontal, ChevronLeft, ChevronRight } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger, SheetFooter } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import { SiteLayout } from "@/components/site/SiteLayout";
import {
  fetchTransportLines,
  formatTransportDate,
  lineIsFav,
  readTransportFavs,
  toggleTransportFav,
  writeTransportFavs,
  TRANSPORT_STATUS_LABEL,
  TRANSPORT_TYPE_LABEL,
  type TransportLineCard,
} from "@/lib/transport";

const searchSchema = z.object({
  q: z.string().optional(),
  city: z.string().optional(),
  type: z.string().optional(),
  status: z.string().optional(),
  page: z.coerce.number().optional(),
});

export const Route = createFileRoute("/transporte")({
  validateSearch: searchSchema,
  head: () => ({
    meta: [
      { title: "Transporte Público — Vespasiano e São José da Lapa" },
      {
        name: "description",
        content:
          "Consulte linhas, horários e itinerários de ônibus em Vespasiano e São José da Lapa. Dados oficiais quando disponíveis.",
      },
    ],
  }),
  component: Outlet,
});

export function TransportePage() {
  const search = Route.useSearch();
  const navigate = useNavigate({ from: "/transporte" });
  const q = search.q ?? "";
  const city = search.city ?? "todas";
  const type = search.type ?? "";
  const status = search.status ?? "";
  const page = search.page && search.page > 0 ? search.page : 1;
  const [favs, setFavs] = useState<string[]>(() => readTransportFavs());
  const [draftQ, setDraftQ] = useState(q);

  const linesQuery = useQuery({
    queryKey: ["transport-lines", city, q, type, status, page],
    queryFn: () =>
      fetchTransportLines({
        city: city === "todas" ? undefined : city,
        q: q || undefined,
        type: type || undefined,
        status: status || undefined,
        page,
        limit: 24,
      }),
    staleTime: 60_000,
  });
  const data = linesQuery.data;
  const lines = data?.lines ?? [];
  const facets = data?.facets ?? { cities: {}, types: {}, statuses: {} };
  const total = data?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / (data?.limit ?? 24)));

  function setSearch(patch: Partial<z.infer<typeof searchSchema>>) {
    void navigate({
      search: (prev) => ({
        ...prev,
        ...patch,
        page: patch.page ?? 1,
      }),
    });
  }

  const toggleFav = (line: TransportLineCard) => {
    setFavs((prev) => {
      const next = toggleTransportFav(line, prev);
      writeTransportFavs(next);
      return next;
    });
  };

  const favoritas = lines.filter((l) => lineIsFav(l, favs));
  const showVesp = (facets.cities["vespasiano"] ?? 0) > 0;
  const showSjl = (facets.cities["sao-jose-da-lapa"] ?? 0) > 0;
  const showInter = (facets.types["intermunicipal"] ?? 0) > 0;
  const typeFilters = Object.entries(facets.types).filter(([, n]) => n > 0);
  const statusFilters = Object.entries(facets.statuses).filter(([, n]) => n > 0);
  const hasAnyLine = Object.values(facets.cities).some((n) => n > 0) || Object.values(facets.types).some((n) => n > 0);

  return (
    <SiteLayout>
      <div className="min-h-screen bg-background pb-16">
        <section className="relative overflow-hidden bg-gradient-to-br from-primary to-primary-dark text-primary-foreground">
          <div className="container mx-auto px-4 py-10 sm:py-14">
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                <Bus className="h-6 w-6" aria-hidden />
              </div>
              <div>
                <h1 className="font-display text-2xl font-extrabold sm:text-4xl">Transporte público</h1>
                <p className="mt-1 max-w-xl text-sm text-white/85 sm:text-base">
                  Consulte linhas, horários e itinerários de Vespasiano e São José da Lapa. Só publicamos o que a fonte oficial informar.
                </p>
              </div>
            </div>
            <form
              className="mt-6"
              onSubmit={(e) => {
                e.preventDefault();
                setSearch({ q: draftQ.trim() || undefined, page: 1 });
              }}
            >
              <label htmlFor="linha-search" className="sr-only">
                Buscar por linha, destino, bairro ou ponto
              </label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" aria-hidden />
                <Input
                  id="linha-search"
                  value={draftQ}
                  onChange={(e) => setDraftQ(e.target.value)}
                  placeholder="Busque por linha, destino, bairro ou ponto..."
                  className="h-14 rounded-2xl border-0 bg-white pl-12 pr-4 text-base text-foreground shadow-lg placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-accent"
                  inputMode="search"
                  autoComplete="off"
                />
              </div>
            </form>
          </div>
        </section>

        <div className="container mx-auto space-y-6 px-4 py-6">
          <section aria-labelledby="favs-title">
            <div className="mb-3 flex items-center gap-2">
              <Star className="h-5 w-5 text-amber-500" aria-hidden />
              <h2 id="favs-title" className="text-lg font-bold">
                Minhas linhas favoritas
              </h2>
            </div>
            {favoritas.length === 0 ? (
              <Card className="border-dashed">
                <CardContent className="p-4 text-sm text-muted-foreground">
                  Toque na estrela de uma linha para fixá-la aqui. Os favoritos ficam neste aparelho.
                </CardContent>
              </Card>
            ) : (
              <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2">
                {favoritas.map((l) => (
                  <Link
                    key={l.id}
                    to="/transporte/$slug"
                    params={{ slug: l.slug }}
                    className="shrink-0 rounded-2xl border border-border bg-card px-4 py-3 text-left shadow-sm transition hover:border-primary hover:shadow-md"
                  >
                    <div className="flex items-center gap-2">
                      <span className="rounded-lg bg-primary/10 px-2 py-0.5 text-sm font-bold text-primary">{l.code}</span>
                      <Star className="h-4 w-4 fill-amber-400 text-amber-400" aria-hidden />
                    </div>
                    <div className="mt-1 max-w-[200px] truncate text-xs text-muted-foreground">{l.name}</div>
                  </Link>
                ))}
              </div>
            )}
          </section>

          <section aria-labelledby="linhas-title">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <h2 id="linhas-title" className="text-lg font-bold">
                Linhas {total > 0 ? `(${total})` : ""}
              </h2>
              <Sheet>
                <SheetTrigger asChild>
                  <Button variant="outline" size="sm" className="gap-2 lg:hidden">
                    <SlidersHorizontal className="h-4 w-4" aria-hidden />
                    Filtrar
                  </Button>
                </SheetTrigger>
                <SheetContent side="bottom" className="rounded-t-2xl">
                  <SheetHeader>
                    <SheetTitle>Filtros</SheetTitle>
                  </SheetHeader>
                  <FilterFields
                    city={city}
                    type={type}
                    status={status}
                    showVesp={showVesp}
                    showSjl={showSjl}
                    showInter={showInter}
                    typeFilters={typeFilters}
                    statusFilters={statusFilters}
                    hasAnyLine={hasAnyLine}
                    onCity={(v) => setSearch({ city: v, page: 1 })}
                    onType={(v) => setSearch({ type: v || undefined, page: 1 })}
                    onStatus={(v) => setSearch({ status: v || undefined, page: 1 })}
                  />
                  <SheetFooter className="mt-4">
                    <Button variant="ghost" className="w-full" onClick={() => setSearch({ city: "todas", type: undefined, status: undefined, page: 1 })}>
                      Limpar
                    </Button>
                  </SheetFooter>
                </SheetContent>
              </Sheet>
            </div>

            <div className="mb-4 hidden flex-wrap gap-2 lg:flex">
              <FilterFields
                city={city}
                type={type}
                status={status}
                showVesp={showVesp}
                showSjl={showSjl}
                showInter={showInter}
                typeFilters={typeFilters}
                statusFilters={statusFilters}
                hasAnyLine={hasAnyLine}
                onCity={(v) => setSearch({ city: v, page: 1 })}
                onType={(v) => setSearch({ type: v || undefined, page: 1 })}
                onStatus={(v) => setSearch({ status: v || undefined, page: 1 })}
              />
            </div>

            {linesQuery.isLoading ? (
              <p className="text-sm text-muted-foreground" role="status">
                Carregando linhas…
              </p>
            ) : linesQuery.isError ? (
              <Card className="border-dashed">
                <CardContent className="p-4 text-sm text-muted-foreground">
                  Não foi possível carregar o catálogo de transporte. Tente de novo em instantes.
                </CardContent>
              </Card>
            ) : lines.length === 0 ? (
              <Card className="border-dashed">
                <CardContent className="space-y-2 p-4 text-sm text-muted-foreground">
                  <p className="font-medium text-foreground">Nenhuma linha cadastrada ainda.</p>
                  <p>
                    Os horários entram no site só depois de um import com fonte identificada (prefeitura, DER-MG ou
                    consórcio). Não usamos listas genéricas nem dados inventados.
                  </p>
                </CardContent>
              </Card>
            ) : (
              <div className="space-y-3">
                {lines.map((l) => (
                  <LineCard key={l.id} line={l} isFav={lineIsFav(l, favs)} onToggleFav={() => toggleFav(l)} />
                ))}
              </div>
            )}

            {totalPages > 1 ? (
              <div className="mt-4 flex items-center justify-center gap-3">
                <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setSearch({ page: page - 1 })}>
                  <ChevronLeft className="h-4 w-4" /> Anterior
                </Button>
                <span className="text-sm text-muted-foreground">
                  Página {page} de {totalPages}
                </span>
                <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setSearch({ page: page + 1 })}>
                  Próxima <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            ) : null}
          </section>

          <Card className="border-dashed">
            <CardContent className="space-y-2 p-4 text-xs text-muted-foreground">
              <p className="font-semibold text-foreground">Sobre estes dados</p>
              <p>
                Horários sujeitos a alterações. Consulte a fonte/operador responsável. Consulta metropolitana:{" "}
                <a
                  className="text-primary hover:underline"
                  href="http://www.consultas.der.mg.gov.br/grgx/sgtm/consulta_linha.xhtml"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  consultas.der.mg.gov.br
                </a>
                .
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </SiteLayout>
  );
}

function FilterFields({
  city,
  type,
  status,
  showVesp,
  showSjl,
  showInter,
  typeFilters,
  statusFilters,
  hasAnyLine,
  onCity,
  onType,
  onStatus,
}: {
  city: string;
  type: string;
  status: string;
  showVesp: boolean;
  showSjl: boolean;
  showInter: boolean;
  typeFilters: Array<[string, number]>;
  statusFilters: Array<[string, number]>;
  hasAnyLine: boolean;
  onCity: (v: string) => void;
  onType: (v: string) => void;
  onStatus: (v: string) => void;
}) {
  return (
    <div className="flex flex-wrap gap-2 pt-2">
      <FilterChip active={city === "todas" || city === ""} onClick={() => onCity("todas")}>
        Todas
      </FilterChip>
      {showVesp ? (
        <FilterChip active={city === "vespasiano"} onClick={() => onCity("vespasiano")}>
          Vespasiano
        </FilterChip>
      ) : null}
      {showSjl ? (
        <FilterChip active={city === "sao-jose-da-lapa"} onClick={() => onCity("sao-jose-da-lapa")}>
          São José da Lapa
        </FilterChip>
      ) : null}
      {showInter ? (
        <FilterChip active={city === "intermunicipal"} onClick={() => onCity("intermunicipal")}>
          Intermunicipais
        </FilterChip>
      ) : null}
      {hasAnyLine
        ? typeFilters.map(([t]) => (
            <FilterChip key={t} active={type === t} onClick={() => onType(type === t ? "" : t)}>
              {TRANSPORT_TYPE_LABEL[t] ?? t}
            </FilterChip>
          ))
        : null}
      {hasAnyLine
        ? statusFilters.map(([s]) => (
            <FilterChip key={s} active={status === s} onClick={() => onStatus(status === s ? "" : s)}>
              {TRANSPORT_STATUS_LABEL[s]?.label ?? s}
            </FilterChip>
          ))
        : null}
    </div>
  );
}

function FilterChip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        "rounded-full border px-3 py-1.5 text-sm",
        active ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card text-foreground",
      )}
    >
      {children}
    </button>
  );
}

function LineCard({
  line,
  isFav,
  onToggleFav,
}: {
  line: TransportLineCard;
  isFav: boolean;
  onToggleFav: () => void;
}) {
  const status = TRANSPORT_STATUS_LABEL[line.status] ?? TRANSPORT_STATUS_LABEL.unknown;
  const updated = formatTransportDate(line.updated_at);

  return (
    <article className="rounded-2xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-lg bg-primary/10 px-2 py-0.5 font-display text-sm font-bold text-primary">{line.code}</span>
            <Badge variant="outline">{TRANSPORT_TYPE_LABEL[line.type] ?? line.type}</Badge>
            <Badge variant="outline" className={status.className}>
              {status.label}
            </Badge>
          </div>
          <h3 className="mt-2 font-display text-base font-semibold">
            <Link to="/transporte/$slug" params={{ slug: line.slug }} className="hover:underline">
              {line.name}
            </Link>
          </h3>
          <p className="mt-1 text-sm text-muted-foreground">
            {line.city_name ?? (line.type === "intermunicipal" ? "Intermunicipal" : "Não informado")}
            {line.operator_name ? ` · ${line.operator_name}` : ""}
          </p>
        </div>
        <Button size="sm" variant="outline" onClick={onToggleFav} className="gap-1.5" aria-pressed={isFav} aria-label={isFav ? "Remover dos favoritos" : "Favoritar linha"}>
          <Star className={cn("h-4 w-4", isFav && "fill-amber-400 text-amber-400")} />
          {isFav ? "Favoritado" : "Favoritar"}
        </Button>
      </div>
      <div className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
        <div className="flex items-center gap-2">
          <Ticket className="h-3.5 w-3.5 text-primary" aria-hidden />
          <span>{line.fare ?? "Tarifa não informada"}</span>
        </div>
        <div className="flex items-center gap-2">
          <MapPin className="h-3.5 w-3.5 text-primary" aria-hidden />
          <span>{line.stop_count > 0 ? `${line.stop_count} pontos` : "Itinerário não informado"}</span>
        </div>
      </div>
      <p className="mt-3 text-xs text-muted-foreground">
        {updated ? `Atualizado em: ${updated}` : "Data de atualização não informada pela fonte."}
      </p>
      {line.source?.name ? (
        <p className="mt-1 text-xs text-muted-foreground">Fonte: {line.source.name}</p>
      ) : null}
      <Link to="/transporte/$slug" params={{ slug: line.slug }} className="mt-3 inline-block text-sm text-primary hover:underline">
        Ver horários e pontos
      </Link>
    </article>
  );
}
