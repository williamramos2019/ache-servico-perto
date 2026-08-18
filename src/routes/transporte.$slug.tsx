import { useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Bus, ArrowLeft, Clock, MapPin, Ticket, ExternalLink, Star, Share2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { SiteLayout } from "@/components/site/SiteLayout";
import { cn } from "@/lib/utils";
import {
  fetchTransportLine,
  fetchTransportSchedules,
  fetchTransportStops,
  formatTransportDate,
  lineIsFav,
  readTransportFavs,
  shareTransportLine,
  toggleTransportFav,
  writeTransportFavs,
  TRANSPORT_DAY_LABEL,
  TRANSPORT_STATUS_LABEL,
  TRANSPORT_TYPE_LABEL,
  type TransportSchedule,
  type TransportStop,
} from "@/lib/transport";

export const Route = createFileRoute("/transporte/$slug")({
  head: ({ params }) => ({
    meta: [
      { title: `Linha ${params.slug} — Transporte público` },
      {
        name: "description",
        content: `Horários, ida, volta e pontos da linha ${params.slug} em Vespasiano e São José da Lapa.`,
      },
    ],
  }),
  component: TransporteDetalhePage,
});

const DAYS = ["weekday", "saturday", "sunday", "holiday", "vacation", "atypical"] as const;

function TransporteDetalhePage() {
  const { slug } = Route.useParams();
  const [favs, setFavs] = useState(() => readTransportFavs());
  const q = useQuery({
    queryKey: ["transport-line", slug],
    queryFn: () => fetchTransportLine(slug),
  });
  const line = q.data;
  const schedulesQ = useQuery({
    queryKey: ["transport-schedules", line?.id],
    queryFn: () => fetchTransportSchedules(line!.id),
    enabled: !!line?.id,
  });
  const stopsQ = useQuery({
    queryKey: ["transport-stops", line?.id],
    queryFn: () => fetchTransportStops(line!.id),
    enabled: !!line?.id,
  });

  if (q.isLoading) {
    return (
      <SiteLayout>
        <div className="container mx-auto px-4 py-16 text-center text-muted-foreground" role="status">
          Carregando linha…
        </div>
      </SiteLayout>
    );
  }
  if (q.isError) {
    return (
      <SiteLayout>
        <div className="container mx-auto max-w-lg px-4 py-16 text-center">
          <h1 className="font-display text-2xl font-bold">Não foi possível carregar</h1>
          <p className="mt-2 text-muted-foreground">Tente de novo em instantes.</p>
          <Link to="/transporte" className="mt-4 inline-block text-primary hover:underline">
            Voltar ao transporte
          </Link>
        </div>
      </SiteLayout>
    );
  }
  if (!line) {
    return (
      <SiteLayout>
        <div className="container mx-auto max-w-lg px-4 py-16 text-center">
          <h1 className="font-display text-2xl font-bold">Linha não encontrada</h1>
          <p className="mt-2 text-muted-foreground">Este itinerário não está no catálogo ou ainda não foi importado.</p>
          <Link to="/transporte" className="mt-4 inline-block text-primary hover:underline">
            Voltar ao transporte
          </Link>
        </div>
      </SiteLayout>
    );
  }

  const status = TRANSPORT_STATUS_LABEL[line.status] ?? TRANSPORT_STATUS_LABEL.unknown;
  const isFav = lineIsFav(line, favs);
  const schedules = schedulesQ.data ?? [];
  const stops = stopsQ.data ?? [];
  const updated = formatTransportDate(line.updated_at);
  const sourceDate = formatTransportDate(line.source?.collected_at ?? null);

  return (
    <SiteLayout>
      <div className="sticky top-16 z-20 border-b border-border bg-background/90 backdrop-blur">
        <div className="container mx-auto flex max-w-3xl items-center justify-between gap-2 px-4 py-3">
          <Link to="/transporte" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft className="h-4 w-4" /> Voltar
          </Link>
          <div className="flex gap-2">
            <Button
              size="sm"
              variant="outline"
              className="gap-1.5"
              aria-pressed={isFav}
              onClick={() => {
                const next = toggleTransportFav(line, favs);
                writeTransportFavs(next);
                setFavs(next);
              }}
            >
              <Star className={cn("h-4 w-4", isFav && "fill-amber-400 text-amber-400")} />
              {isFav ? "Favoritado" : "Favoritar"}
            </Button>
            <Button size="sm" variant="outline" className="gap-1.5" onClick={() => void shareTransportLine(line)}>
              <Share2 className="h-4 w-4" />
              Compartilhar
            </Button>
          </div>
        </div>
      </div>

      <div className="container mx-auto max-w-3xl space-y-6 px-4 py-8">
        <header className="flex items-start gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <Bus className="h-6 w-6" aria-hidden />
          </div>
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <span className="rounded-lg bg-primary/10 px-2 py-0.5 font-display text-sm font-bold text-primary">{line.code}</span>
              <Badge variant="outline">{TRANSPORT_TYPE_LABEL[line.type] ?? line.type}</Badge>
              <Badge variant="outline" className={status.className}>
                {status.label}
              </Badge>
            </div>
            <h1 className="mt-1 font-display text-2xl font-bold">{line.name}</h1>
            <p className="text-sm text-muted-foreground">
              {line.city_name ?? "Cidade não informada"}
              {line.operator_name ? ` · ${line.operator_name}` : ""}
            </p>
          </div>
        </header>

        <div className="grid gap-3 sm:grid-cols-2">
          <Card>
            <CardContent className="flex items-center gap-2 p-4 text-sm">
              <Ticket className="h-4 w-4 text-primary" />
              {line.fare ?? "Tarifa não informada"}
            </CardContent>
          </Card>
          <Card>
            <CardContent className="flex items-center gap-2 p-4 text-sm">
              <MapPin className="h-4 w-4 text-primary" />
              {line.stop_count > 0 ? `${line.stop_count} pontos` : "Pontos não informados"}
            </CardContent>
          </Card>
        </div>

        <p className="text-xs text-muted-foreground">
          {updated ? `Atualizado em: ${updated}` : "Data de atualização não informada pela fonte."}
        </p>

        <section>
          <h2 className="mb-3 flex items-center gap-2 font-display text-lg font-semibold">
            <Clock className="h-4 w-4" /> Horários
          </h2>
          {schedulesQ.isLoading ? (
            <p className="text-sm text-muted-foreground" role="status">
              Carregando horários…
            </p>
          ) : schedules.length === 0 ? (
            <p className="text-sm text-muted-foreground">Horário não informado pela fonte.</p>
          ) : (
            <Tabs defaultValue="ida">
              <TabsList>
                <TabsTrigger value="ida">Ida</TabsTrigger>
                <TabsTrigger value="volta">Volta</TabsTrigger>
              </TabsList>
              <TabsContent value="ida" className="mt-3">
                <ScheduleBlock schedules={schedules} direction="ida" />
              </TabsContent>
              <TabsContent value="volta" className="mt-3">
                <ScheduleBlock schedules={schedules} direction="volta" />
              </TabsContent>
            </Tabs>
          )}
        </section>

        <section>
          <h2 className="mb-3 flex items-center gap-2 font-display text-lg font-semibold">
            <MapPin className="h-4 w-4" /> Pontos
          </h2>
          {stopsQ.isLoading ? (
            <p className="text-sm text-muted-foreground" role="status">
              Carregando pontos…
            </p>
          ) : stops.length === 0 ? (
            <p className="text-sm text-muted-foreground">Itinerário não informado pela fonte.</p>
          ) : (
            <Tabs defaultValue="ida">
              <TabsList>
                <TabsTrigger value="ida">Ida</TabsTrigger>
                <TabsTrigger value="volta">Volta</TabsTrigger>
              </TabsList>
              <TabsContent value="ida" className="mt-3">
                <StopsList stops={stops.filter((s) => s.direction === "ida" || s.direction === "circular")} />
              </TabsContent>
              <TabsContent value="volta" className="mt-3">
                <StopsList stops={stops.filter((s) => s.direction === "volta")} />
              </TabsContent>
            </Tabs>
          )}
        </section>

        <section className="space-y-1 text-xs text-muted-foreground">
          <p>
            Fonte:{" "}
            {line.source?.url ? (
              <a href={line.source.url} className="inline-flex items-center gap-1 text-primary hover:underline" target="_blank" rel="noopener noreferrer">
                {line.source.name} <ExternalLink className="h-3 w-3" /> Consultar fonte oficial
              </a>
            ) : (
              line.source?.name ?? "Não informada"
            )}
          </p>
          <p>{sourceDate ? `Coletado em: ${sourceDate}` : "Data de atualização não informada pela fonte."}</p>
          <p>Horários sujeitos a alterações. Consulte a fonte/operador responsável.</p>
        </section>

        {line.notes ? <p className="text-sm text-muted-foreground">{line.notes}</p> : null}
      </div>
    </SiteLayout>
  );
}

function ScheduleBlock({ schedules, direction }: { schedules: TransportSchedule[]; direction: string }) {
  const days = DAYS.map((day) => ({
    day,
    times: schedules
      .filter((s) => s.day_type === day && s.direction === direction)
      .map((s) => s.departure_time)
      .sort(),
  })).filter((d) => d.times.length > 0);
  if (days.length === 0) {
    return <p className="text-sm text-muted-foreground">Horário não informado pela fonte.</p>;
  }
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {days.map((d) => (
        <div key={d.day}>
          <div className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{TRANSPORT_DAY_LABEL[d.day]}</div>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {d.times.map((h) => (
              <span key={h} className="rounded-md bg-background px-2 py-1 text-xs font-medium shadow-sm ring-1 ring-border">
                {h}
              </span>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

function StopsList({ stops }: { stops: TransportStop[] }) {
  if (stops.length === 0) {
    return <p className="text-sm text-muted-foreground">Pontos não informados neste sentido.</p>;
  }
  const ordered = [...stops].sort((a, b) => a.sequence - b.sequence);
  return (
    <ol className="space-y-2 text-sm">
      {ordered.map((s) => (
        <li key={`${s.direction}-${s.sequence}-${s.name}`} className="flex gap-3" data-lat={s.lat ?? undefined} data-lng={s.lng ?? undefined}>
          <span className="w-6 shrink-0 text-muted-foreground">{s.sequence}</span>
          <span>
            {s.name}
            {s.address ? <span className="block text-xs text-muted-foreground">{s.address}</span> : null}
            <span className="block text-[11px] uppercase tracking-wide text-muted-foreground">{s.direction}</span>
          </span>
        </li>
      ))}
    </ol>
  );
}
