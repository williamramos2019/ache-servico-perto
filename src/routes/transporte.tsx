import { useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Bus, Clock, MapPin, Star, Search, Ticket, Building2, ExternalLink } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { SiteLayout } from "@/components/site/SiteLayout";
import { fetchTransportLines, schedulesByDay, type TransportLine } from "@/lib/transport";

export const Route = createFileRoute("/transporte")({
  head: () => ({
    meta: [
      { title: "Transporte Público — Vespasiano e São José da Lapa" },
      {
        name: "description",
        content:
          "Linhas municipais e metropolitanas em Vespasiano e São José da Lapa: horários, tarifas e pontos de embarque.",
      },
    ],
  }),
  component: TransportePage,
});

type Tab = "todas" | "vespasiano" | "sao-jose-da-lapa" | "intermunicipal";

const FAV_KEY = "transporte_favoritos";

function readFavs(): string[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(FAV_KEY);
    return raw ? (JSON.parse(raw) as string[]) : [];
  } catch {
    return [];
  }
}

const STATUS_STYLES: Record<string, { label: string; className: string }> = {
  active: { label: "Em operação", className: "bg-emerald-100 text-emerald-800 border-emerald-200" },
  unknown: { label: "A confirmar", className: "bg-muted text-muted-foreground border-border" },
  inactive: { label: "Encerrada", className: "bg-muted text-muted-foreground border-border" },
};

const TYPE_LABEL: Record<string, string> = {
  municipal: "Municipal",
  metropolitana: "Metropolitana",
  intermunicipal: "Intermunicipal",
  "tarifa-zero": "Tarifa zero",
};

function TransportePage() {
  const [query, setQuery] = useState("");
  const [tab, setTab] = useState<Tab>("todas");
  const [favs, setFavs] = useState<string[]>(() => readFavs());
  const linesQuery = useQuery({
    queryKey: ["transport-lines"],
    queryFn: () => fetchTransportLines(),
    staleTime: 60_000,
  });
  const lines = linesQuery.data ?? [];

  const toggleFav = (code: string) => {
    setFavs((prev) => {
      const next = prev.includes(code) ? prev.filter((n) => n !== code) : [...prev, code];
      if (typeof window !== "undefined") window.localStorage.setItem(FAV_KEY, JSON.stringify(next));
      return next;
    });
  };

  const filtradas = useMemo(() => {
    const q = query.trim().toLowerCase();
    return lines.filter((l) => {
      if (tab === "intermunicipal" && l.type !== "intermunicipal") return false;
      if (tab === "vespasiano" || tab === "sao-jose-da-lapa") {
        if (l.city_slug !== tab) return false;
      }
      if (!q) return true;
      return (
        l.code.toLowerCase().includes(q) ||
        l.name.toLowerCase().includes(q) ||
        (l.operator_name ?? "").toLowerCase().includes(q) ||
        l.stops.some((p) => p.name.toLowerCase().includes(q))
      );
    });
  }, [lines, query, tab]);

  const favoritas = lines.filter((l) => favs.includes(l.code));

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
              <h1 className="font-display text-2xl font-extrabold sm:text-4xl">Transporte Público</h1>
              <p className="mt-1 max-w-xl text-sm text-white/85 sm:text-base">
                Consulte linhas, horários, tarifas e pontos de embarque de Vespasiano, São José da Lapa e região.
              </p>
            </div>
          </div>
          <div className="mt-6">
            <label htmlFor="linha-search" className="sr-only">
              Buscar linha, destino ou operadora
            </label>
            <div className="relative">
              <Search className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" aria-hidden />
              <Input
                id="linha-search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder='Ex: "5130", "Confins", "Vilarinho"'
                className="h-14 rounded-2xl border-0 bg-white pl-12 pr-4 text-base text-foreground shadow-lg placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-accent"
                inputMode="search"
                autoComplete="off"
              />
            </div>
          </div>
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
          <h2 id="linhas-title" className="mb-3 text-lg font-bold">
            Linhas {filtradas.length > 0 ? `(${filtradas.length})` : ""}
          </h2>
          <Tabs value={tab} onValueChange={(v) => setTab(v as Tab)}>
            <TabsList className="mb-4 flex h-auto w-full flex-wrap justify-start gap-1">
              <TabsTrigger value="todas">Todas</TabsTrigger>
              <TabsTrigger value="vespasiano">Vespasiano</TabsTrigger>
              <TabsTrigger value="sao-jose-da-lapa">São José da Lapa</TabsTrigger>
              <TabsTrigger value="intermunicipal">Intermunicipal</TabsTrigger>
            </TabsList>
            <TabsContent value={tab} className="mt-0">
              {linesQuery.isLoading ? (
                <p className="text-sm text-muted-foreground">Carregando linhas…</p>
              ) : linesQuery.isError ? (
                <Card className="border-dashed">
                  <CardContent className="p-4 text-sm text-muted-foreground">
                    Não foi possível carregar o catálogo de transporte. Tente de novo em instantes.
                  </CardContent>
                </Card>
              ) : filtradas.length === 0 ? (
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
                  {filtradas.map((l) => (
                    <LineCard key={l.id} line={l} isFav={favs.includes(l.code)} onToggleFav={() => toggleFav(l.code)} />
                  ))}
                </div>
              )}
            </TabsContent>
          </Tabs>
        </section>

        <Card className="border-dashed">
          <CardContent className="space-y-2 p-4 text-xs text-muted-foreground">
            <p className="font-semibold text-foreground">Sobre estes dados</p>
            <p>
              Cada linha publica a fonte usada no cadastro. Horários mudam — confirme com a operadora antes de viajar.
              Consulta metropolitana:{" "}
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

function LineCard({
  line,
  isFav,
  onToggleFav,
}: {
  line: TransportLine;
  isFav: boolean;
  onToggleFav: () => void;
}) {
  const status = STATUS_STYLES[line.status] ?? STATUS_STYLES.unknown;
  const util = schedulesByDay(line, "weekday");
  const sab = schedulesByDay(line, "saturday");
  const dom = schedulesByDay(line, "sunday");

  return (
    <article className="rounded-2xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-lg bg-primary/10 px-2 py-0.5 font-display text-sm font-bold text-primary">{line.code}</span>
            <Badge variant="outline">{TYPE_LABEL[line.type] ?? line.type}</Badge>
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
            {line.city_name ?? (line.type === "intermunicipal" ? "Intermunicipal" : "Região")}
            {line.operator_name ? ` · ${line.operator_name}` : ""}
          </p>
        </div>
        <Button size="sm" variant="outline" onClick={onToggleFav} className="gap-1.5">
          <Star className={cn("h-4 w-4", isFav && "fill-amber-400 text-amber-400")} />
          {isFav ? "Favoritado" : "Favoritar"}
        </Button>
      </div>
      <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
        <div className="flex items-center gap-2">
          <Ticket className="h-3.5 w-3.5 text-primary" aria-hidden />
          <span>{line.fare ?? "Tarifa não informada"}</span>
        </div>
        <div className="flex items-center gap-2">
          <Clock className="h-3.5 w-3.5 text-primary" aria-hidden />
          <span>{util.length ? `${util.length} partidas (úteis)` : "Horários a confirmar"}</span>
        </div>
        <div className="flex items-center gap-2">
          <MapPin className="h-3.5 w-3.5 text-primary" aria-hidden />
          <span>{line.stops.length ? `${line.stops.length} pontos` : "Itinerário a confirmar"}</span>
        </div>
      </div>
      {(util.length > 0 || sab.length > 0 || dom.length > 0) && (
        <p className="mt-3 text-xs text-muted-foreground">
          Úteis: {util.slice(0, 6).join(" · ") || "—"}
          {sab.length ? ` · Sáb: ${sab.slice(0, 3).join(" ")}` : ""}
          {dom.length ? ` · Dom: ${dom.slice(0, 3).join(" ")}` : ""}
        </p>
      )}
      {line.source?.url ? (
        <a
          href={line.source.url}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-3 inline-flex items-center gap-1 text-xs text-primary hover:underline"
        >
          <ExternalLink className="h-3.5 w-3.5" aria-hidden />
          Fonte: {line.source.name}
        </a>
      ) : null}
      <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
        <Building2 className="h-3.5 w-3.5" aria-hidden />
        <Link to="/transporte/$slug" params={{ slug: line.slug }} className="text-primary hover:underline">
          Ver horários e pontos
        </Link>
      </div>
    </article>
  );
}
