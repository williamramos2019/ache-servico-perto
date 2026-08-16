import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Bus, ArrowLeft, Clock, MapPin, Ticket, ExternalLink } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { SiteLayout } from "@/components/site/SiteLayout";
import { fetchTransportLine, schedulesByDay } from "@/lib/transport";

export const Route = createFileRoute("/transporte/$slug")({
  head: ({ params }) => ({
    meta: [
      { title: `Linha ${params.slug} — Transporte` },
      { name: "description", content: `Horários e pontos da linha ${params.slug} em Vespasiano e São José da Lapa.` },
    ],
  }),
  component: TransporteDetalhePage,
});

const DAY_LABEL: Record<string, string> = {
  weekday: "Dias úteis",
  saturday: "Sábado",
  sunday: "Domingo",
  holiday: "Feriado",
};

function TransporteDetalhePage() {
  const { slug } = Route.useParams();
  const q = useQuery({
    queryKey: ["transport-line", slug],
    queryFn: () => fetchTransportLine(slug),
  });
  const line = q.data;

  if (q.isLoading) {
    return (
      <SiteLayout>
        <div className="container mx-auto px-4 py-16 text-center text-muted-foreground">Carregando linha…</div>
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

  const days = ["weekday", "saturday", "sunday", "holiday"] as const;

  return (
    <SiteLayout>
    <div className="container mx-auto max-w-3xl space-y-6 px-4 py-8">
      <Link to="/transporte" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> Todas as linhas
      </Link>
      <div className="flex items-start gap-3">
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
          <Bus className="h-6 w-6" aria-hidden />
        </div>
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-lg bg-primary/10 px-2 py-0.5 font-display text-sm font-bold text-primary">{line.code}</span>
            <Badge variant="outline">{line.type}</Badge>
          </div>
          <h1 className="mt-1 font-display text-2xl font-bold">{line.name}</h1>
          <p className="text-sm text-muted-foreground">
            {line.city_name ?? "Região metropolitana"}
            {line.operator_name ? ` · ${line.operator_name}` : ""}
          </p>
        </div>
      </div>

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
            {line.stops.length ? `${line.stops.length} pontos` : "Pontos a confirmar"}
          </CardContent>
        </Card>
      </div>

      <section>
        <h2 className="mb-3 flex items-center gap-2 font-display text-lg font-semibold">
          <Clock className="h-4 w-4" /> Horários
        </h2>
        {line.schedules.length === 0 ? (
          <p className="text-sm text-muted-foreground">Horários ainda não cadastrados para esta linha.</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            {days.map((day) => {
              const times = schedulesByDay(line, day);
              if (times.length === 0) return null;
              return (
                <div key={day}>
                  <div className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{DAY_LABEL[day]}</div>
                  <div className="mt-1 flex flex-wrap gap-1.5">
                    {times.map((h) => (
                      <span key={h} className="rounded-md bg-background px-2 py-1 text-xs font-medium shadow-sm ring-1 ring-border">
                        {h}
                      </span>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      <section>
        <h2 className="mb-3 flex items-center gap-2 font-display text-lg font-semibold">
          <MapPin className="h-4 w-4" /> Pontos
        </h2>
        {line.stops.length === 0 ? (
          <p className="text-sm text-muted-foreground">Itinerário ainda não cadastrado.</p>
        ) : (
          <ol className="space-y-2 text-sm">
            {line.stops.map((s) => (
              <li key={`${s.direction}-${s.sequence}-${s.name}`} className="flex gap-3">
                <span className="w-6 shrink-0 text-muted-foreground">{s.sequence}</span>
                <span>
                  {s.name}
                  {s.address ? <span className="block text-xs text-muted-foreground">{s.address}</span> : null}
                </span>
              </li>
            ))}
          </ol>
        )}
      </section>

      {line.source ? (
        <p className="text-xs text-muted-foreground">
          Fonte: {line.source.url ? (
            <a href={line.source.url} className="inline-flex items-center gap-1 text-primary hover:underline" target="_blank" rel="noopener noreferrer">
              {line.source.name} <ExternalLink className="h-3 w-3" />
            </a>
          ) : (
            line.source.name
          )}
          {line.source.collected_at ? ` · coletado em ${new Date(line.source.collected_at).toLocaleDateString("pt-BR")}` : ""}
        </p>
      ) : null}
      {line.notes ? <p className="text-sm text-muted-foreground">{line.notes}</p> : null}
    </div>
    </SiteLayout>
  );
}
