import { useMemo, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Bus, Clock, MapPin, Star, Search, Route as RouteIcon } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/transporte")({
  head: () => ({
    meta: [
      { title: "Transporte Público — Vespasiano e São José da Lapa" },
      {
        name: "description",
        content:
          "Consulte linhas de ônibus, horários e pontos de embarque em Vespasiano, São José da Lapa e região metropolitana.",
      },
      { property: "og:title", content: "Transporte Público — AgendaAqui" },
      {
        property: "og:description",
        content:
          "Linhas, horários e paradas de ônibus em Vespasiano e São José da Lapa em um só lugar.",
      },
    ],
  }),
  component: TransportePage,
});

type Cidade = "vespasiano" | "sao-jose-da-lapa" | "intermunicipal";
type Tipo = "expressa" | "paradora" | "circular";

interface Linha {
  numero: string;
  nome: string;
  cidade: Cidade;
  tipo: Tipo;
  operadora: string;
  status: "em-operacao" | "atrasada" | "encerrada";
  horarios: { util: string[]; sabado: string[]; domingo: string[] };
  pontos: string[];
}

const LINHAS: Linha[] = [
  {
    numero: "5280",
    nome: "Vespasiano / BH (via Cristiano Machado)",
    cidade: "intermunicipal",
    tipo: "paradora",
    operadora: "Saritur",
    status: "em-operacao",
    horarios: {
      util: ["04:45", "05:20", "06:00", "06:40", "07:30", "12:00", "17:15", "19:00", "22:10"],
      sabado: ["05:30", "07:00", "09:00", "12:30", "17:00", "21:00"],
      domingo: ["06:00", "09:00", "13:00", "18:00", "21:30"],
    },
    pontos: [
      "Rodoviária de Vespasiano",
      "Av. Prefeito Sebastião Fernandes",
      "BR-381 - Trevo do Morro Alto",
      "Terminal Rodoviário de BH",
    ],
  },
  {
    numero: "5815",
    nome: "São José da Lapa / BH (Serra Verde)",
    cidade: "intermunicipal",
    tipo: "expressa",
    operadora: "Saritur",
    status: "em-operacao",
    horarios: {
      util: ["05:00", "05:45", "06:30", "07:20", "17:00", "18:30", "20:00"],
      sabado: ["06:00", "09:30", "14:00", "18:00"],
      domingo: ["07:00", "12:00", "18:00"],
    },
    pontos: [
      "Praça Central - São José da Lapa",
      "Av. Cel. Juventino Dias",
      "Estação Metrô Vilarinho",
      "Estação Serra Verde",
    ],
  },
  {
    numero: "101",
    nome: "Centro / Nova Pampulha",
    cidade: "vespasiano",
    tipo: "circular",
    operadora: "TransVespasiano",
    status: "em-operacao",
    horarios: {
      util: ["05:30", "06:10", "06:50", "07:30", "12:00", "17:30", "18:20", "19:10"],
      sabado: ["06:00", "08:00", "12:00", "17:00", "20:00"],
      domingo: ["07:00", "12:00", "18:00"],
    },
    pontos: [
      "Rodoviária de Vespasiano",
      "Praça da Matriz",
      "Bairro Nova Pampulha",
      "Escola Estadual",
    ],
  },
  {
    numero: "202",
    nome: "Caieiras / Bandeirantes",
    cidade: "vespasiano",
    tipo: "paradora",
    operadora: "TransVespasiano",
    status: "atrasada",
    horarios: {
      util: ["05:45", "06:30", "07:15", "12:15", "17:45", "18:40"],
      sabado: ["06:30", "10:00", "14:00", "18:00"],
      domingo: ["08:00", "14:00", "19:00"],
    },
    pontos: [
      "Bairro Caieiras",
      "Av. Brasília",
      "Praça de Bandeirantes",
      "Terminal Central",
    ],
  },
  {
    numero: "303",
    nome: "Lapa Centro / Cachoeirinha",
    cidade: "sao-jose-da-lapa",
    tipo: "paradora",
    operadora: "ViaLapa",
    status: "em-operacao",
    horarios: {
      util: ["05:20", "06:00", "06:45", "07:30", "12:00", "17:00", "18:30"],
      sabado: ["06:00", "09:00", "13:00", "18:00"],
      domingo: ["07:30", "13:00", "18:30"],
    },
    pontos: [
      "Praça Central da Lapa",
      "Rua Sete de Setembro",
      "Bairro Cachoeirinha",
      "UBS Cachoeirinha",
    ],
  },
  {
    numero: "404",
    nome: "Lapa / Nova União",
    cidade: "sao-jose-da-lapa",
    tipo: "circular",
    operadora: "ViaLapa",
    status: "em-operacao",
    horarios: {
      util: ["05:40", "06:30", "07:20", "12:30", "17:30", "19:00"],
      sabado: ["06:30", "10:00", "15:00", "19:00"],
      domingo: ["08:00", "14:00", "19:30"],
    },
    pontos: ["Praça Central da Lapa", "Rodovia MG-010", "Bairro Nova União"],
  },
  {
    numero: "5290",
    nome: "Vespasiano / São José da Lapa",
    cidade: "intermunicipal",
    tipo: "paradora",
    operadora: "Saritur",
    status: "em-operacao",
    horarios: {
      util: ["05:15", "06:15", "07:15", "12:00", "17:00", "18:30", "20:00"],
      sabado: ["06:30", "10:00", "14:00", "19:00"],
      domingo: ["08:00", "14:00", "19:00"],
    },
    pontos: [
      "Rodoviária de Vespasiano",
      "MG-010",
      "Praça Central - São José da Lapa",
    ],
  },
];

const CIDADE_LABEL: Record<Cidade, string> = {
  vespasiano: "Vespasiano",
  "sao-jose-da-lapa": "São José da Lapa",
  intermunicipal: "Intermunicipal",
};

const TIPO_STYLES: Record<Tipo, string> = {
  expressa: "bg-blue-100 text-blue-800 border-blue-200",
  paradora: "bg-amber-100 text-amber-800 border-amber-200",
  circular: "bg-purple-100 text-purple-800 border-purple-200",
};

const STATUS_STYLES: Record<Linha["status"], { label: string; className: string }> = {
  "em-operacao": {
    label: "Em operação",
    className: "bg-emerald-100 text-emerald-800 border-emerald-200",
  },
  atrasada: {
    label: "Atrasada",
    className: "bg-orange-100 text-orange-900 border-orange-200",
  },
  encerrada: {
    label: "Encerrada",
    className: "bg-muted text-muted-foreground border-border",
  },
};

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

function TransportePage() {
  const [query, setQuery] = useState("");
  const [tab, setTab] = useState<"todas" | Cidade>("todas");
  const [favs, setFavs] = useState<string[]>(() => readFavs());

  const toggleFav = (numero: string) => {
    setFavs((prev) => {
      const next = prev.includes(numero)
        ? prev.filter((n) => n !== numero)
        : [...prev, numero];
      if (typeof window !== "undefined")
        window.localStorage.setItem(FAV_KEY, JSON.stringify(next));
      return next;
    });
  };

  const filtradas = useMemo(() => {
    const q = query.trim().toLowerCase();
    return LINHAS.filter((l) => {
      if (tab !== "todas" && l.cidade !== tab) return false;
      if (!q) return true;
      return (
        l.numero.toLowerCase().includes(q) ||
        l.nome.toLowerCase().includes(q) ||
        l.pontos.some((p) => p.toLowerCase().includes(q))
      );
    });
  }, [query, tab]);

  const favoritas = LINHAS.filter((l) => favs.includes(l.numero));

  return (
    <div className="min-h-screen bg-background pb-16">
      {/* Hero */}
      <section className="relative overflow-hidden bg-gradient-to-br from-primary to-primary-dark text-primary-foreground">
        <div className="container mx-auto px-4 py-10 sm:py-14">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
              <Bus className="h-6 w-6" aria-hidden />
            </div>
            <div>
              <h1 className="font-display text-2xl font-extrabold sm:text-4xl">
                Transporte Público
              </h1>
              <p className="mt-1 max-w-xl text-sm text-white/85 sm:text-base">
                Consulte linhas, horários e pontos de embarque de Vespasiano,
                São José da Lapa e região.
              </p>
            </div>
          </div>

          {/* Busca */}
          <div className="mt-6">
            <label htmlFor="linha-search" className="sr-only">
              Buscar linha ou destino
            </label>
            <div className="relative">
              <Search
                className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground"
                aria-hidden
              />
              <Input
                id="linha-search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder='Ex: "5815", "Belo Horizonte", "Serra Verde"'
                className="h-14 rounded-2xl border-0 bg-white pl-12 pr-4 text-base text-foreground shadow-lg placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-accent"
                inputMode="search"
                autoComplete="off"
              />
            </div>
          </div>
        </div>
      </section>

      <div className="container mx-auto px-4 py-6 space-y-6">
        {/* Favoritos */}
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
                Toque na estrela de uma linha para fixá-la aqui e acessá-la
                rapidamente.
              </CardContent>
            </Card>
          ) : (
            <div className="flex gap-2 overflow-x-auto pb-2 -mx-4 px-4">
              {favoritas.map((l) => (
                <button
                  key={l.numero}
                  type="button"
                  onClick={() =>
                    document
                      .getElementById(`linha-${l.numero}`)
                      ?.scrollIntoView({ behavior: "smooth", block: "center" })
                  }
                  className="shrink-0 rounded-2xl border border-border bg-card px-4 py-3 text-left shadow-sm transition hover:border-primary hover:shadow-md"
                >
                  <div className="flex items-center gap-2">
                    <span className="rounded-lg bg-primary/10 px-2 py-0.5 text-sm font-bold text-primary">
                      {l.numero}
                    </span>
                    <Star
                      className="h-4 w-4 fill-amber-400 text-amber-400"
                      aria-hidden
                    />
                  </div>
                  <div className="mt-1 max-w-[200px] truncate text-xs text-muted-foreground">
                    {l.nome}
                  </div>
                </button>
              ))}
            </div>
          )}
        </section>

        {/* Tabs cidade */}
        <section aria-labelledby="linhas-title">
          <div className="mb-3 flex items-center gap-2">
            <RouteIcon className="h-5 w-5 text-primary" aria-hidden />
            <h2 id="linhas-title" className="text-lg font-bold">
              Linhas disponíveis
            </h2>
          </div>

          <Tabs value={tab} onValueChange={(v) => setTab(v as typeof tab)}>
            <TabsList className="grid h-auto w-full grid-cols-2 gap-1 rounded-xl bg-muted p-1 sm:grid-cols-4">
              <TabsTrigger value="todas" className="rounded-lg py-2 text-xs sm:text-sm">
                Todas
              </TabsTrigger>
              <TabsTrigger value="vespasiano" className="rounded-lg py-2 text-xs sm:text-sm">
                Vespasiano
              </TabsTrigger>
              <TabsTrigger value="sao-jose-da-lapa" className="rounded-lg py-2 text-xs sm:text-sm">
                S. J. da Lapa
              </TabsTrigger>
              <TabsTrigger value="intermunicipal" className="rounded-lg py-2 text-xs sm:text-sm">
                Intermunicipais
              </TabsTrigger>
            </TabsList>

            <TabsContent value={tab} className="mt-4">
              {filtradas.length === 0 ? (
                <Card>
                  <CardContent className="p-6 text-center text-sm text-muted-foreground">
                    Nenhuma linha encontrada para "{query}".
                  </CardContent>
                </Card>
              ) : (
                <Accordion type="single" collapsible className="space-y-3">
                  {filtradas.map((l) => {
                    const isFav = favs.includes(l.numero);
                    const status = STATUS_STYLES[l.status];
                    return (
                      <AccordionItem
                        key={l.numero}
                        value={l.numero}
                        id={`linha-${l.numero}`}
                        className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                      >
                        <div className="flex items-center gap-2 pr-2">
                          <AccordionTrigger className="flex-1 px-4 py-4 hover:no-underline">
                            <div className="flex w-full items-start gap-3 text-left">
                              <div className="flex h-12 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <Bus className="h-4 w-4" aria-hidden />
                                <span className="text-sm font-black leading-tight">
                                  {l.numero}
                                </span>
                              </div>
                              <div className="min-w-0 flex-1">
                                <div className="truncate text-base font-bold text-foreground">
                                  {l.nome}
                                </div>
                                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                  <Badge
                                    variant="outline"
                                    className={cn("border text-[10px]", status.className)}
                                  >
                                    {status.label}
                                  </Badge>
                                  <Badge
                                    variant="outline"
                                    className={cn(
                                      "border text-[10px] capitalize",
                                      TIPO_STYLES[l.tipo],
                                    )}
                                  >
                                    {l.tipo}
                                  </Badge>
                                  <Badge
                                    variant="outline"
                                    className="border text-[10px] text-muted-foreground"
                                  >
                                    {CIDADE_LABEL[l.cidade]}
                                  </Badge>
                                </div>
                              </div>
                            </div>
                          </AccordionTrigger>
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              toggleFav(l.numero);
                            }}
                            aria-label={
                              isFav
                                ? `Remover linha ${l.numero} dos favoritos`
                                : `Favoritar linha ${l.numero}`
                            }
                            aria-pressed={isFav}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full hover:bg-muted"
                          >
                            <Star
                              className={cn(
                                "h-5 w-5 transition",
                                isFav
                                  ? "fill-amber-400 text-amber-400"
                                  : "text-muted-foreground",
                              )}
                            />
                          </button>
                        </div>

                        <AccordionContent className="border-t border-border bg-muted/30 px-4 pb-4 pt-4">
                          <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                              <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-foreground">
                                <Clock className="h-4 w-4 text-primary" aria-hidden />
                                Próximos horários
                              </div>
                              <HorariosBloco titulo="Dia útil" horarios={l.horarios.util} />
                              <HorariosBloco titulo="Sábado" horarios={l.horarios.sabado} />
                              <HorariosBloco titulo="Domingo" horarios={l.horarios.domingo} />
                            </div>

                            <div>
                              <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-foreground">
                                <MapPin className="h-4 w-4 text-primary" aria-hidden />
                                Principais pontos de embarque
                              </div>
                              <ol className="space-y-1.5">
                                {l.pontos.map((p, i) => (
                                  <li
                                    key={p}
                                    className="flex items-start gap-2 text-sm text-foreground/90"
                                  >
                                    <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                                      {i + 1}
                                    </span>
                                    <span>{p}</span>
                                  </li>
                                ))}
                              </ol>
                              <p className="mt-3 text-[11px] text-muted-foreground">
                                Operadora: <span className="font-medium">{l.operadora}</span>
                              </p>
                            </div>
                          </div>

                          <div className="mt-4 flex flex-wrap gap-2">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => toggleFav(l.numero)}
                              className="gap-1.5"
                            >
                              <Star
                                className={cn(
                                  "h-4 w-4",
                                  isFav && "fill-amber-400 text-amber-400",
                                )}
                              />
                              {isFav ? "Favoritado" : "Favoritar linha"}
                            </Button>
                          </div>
                        </AccordionContent>
                      </AccordionItem>
                    );
                  })}
                </Accordion>
              )}
            </TabsContent>
          </Tabs>
        </section>

        <p className="text-center text-[11px] text-muted-foreground">
          Horários informativos. Podem sofrer alterações. Confirme com a operadora antes de sua viagem.
        </p>
      </div>
    </div>
  );
}

function HorariosBloco({ titulo, horarios }: { titulo: string; horarios: string[] }) {
  return (
    <div className="mb-2">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
        {titulo}
      </div>
      <div className="mt-1 flex flex-wrap gap-1.5">
        {horarios.map((h) => (
          <span
            key={h}
            className="rounded-md bg-background px-2 py-1 text-xs font-medium text-foreground shadow-sm ring-1 ring-border"
          >
            {h}
          </span>
        ))}
      </div>
    </div>
  );
}
