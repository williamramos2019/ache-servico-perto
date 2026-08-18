import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Clock, MapPin, Phone } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState, LiveFeedWidget, RequestButton } from "@/components/site/DomainWidgets";
import { InlineShopeeStrip } from "@/components/site/InlineShopeeStrip";
import { fetchPublicServices } from "@/lib/publicServices";

export const Route = createFileRoute("/vespasiano")({
  head: () => ({ meta: [{ title: "Vespasiano — A cidade inteira no seu bolso | AgendaAqui" }] }),
  component: VespasianoPage,
});

function VespasianoPage() {
  const query = useQuery({ queryKey: ["vespasiano-services"], queryFn: () => fetchPublicServices({ citySlug: "vespasiano" }) });
  return (
    <SiteLayout>
      <header className="border-b bg-gradient-to-br from-primary/10 via-background to-accent/5"><div className="container mx-auto px-4 py-14"><span className="text-sm font-semibold text-primary">Guia oficial da cidade</span><h1 className="mt-2 font-display text-5xl font-extrabold">Vespasiano</h1><p className="mt-3 max-w-2xl text-lg text-muted-foreground">Serviços públicos, oportunidades, eventos e informações locais reunidos em um só lugar.</p><div className="mt-6 flex gap-3"><Link to="/buscar" className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">Encontrar empresa</Link><RequestButton /></div></div></header>
      <main className="container mx-auto space-y-12 px-4 py-10">
        <LiveFeedWidget city="vespasiano" />
        <section><h2 className="mb-5 font-display text-2xl font-bold">Serviços públicos</h2><DataState loading={query.isLoading} error={query.error} empty={!query.data?.length}><div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">{query.data?.map((service) => <article key={service.id} className="rounded-xl border bg-card p-5"><h3 className="font-semibold">{service.name}</h3>{service.description && <p className="mt-2 text-sm text-muted-foreground">{service.description}</p>}<div className="mt-4 space-y-2 text-sm text-muted-foreground">{service.address && <p><MapPin className="mr-2 inline h-4 w-4" />{service.address}</p>}{service.hours && <p><Clock className="mr-2 inline h-4 w-4" />{service.hours}</p>}{service.phone && <a href={`tel:${service.phone.replace(/\D/g, "")}`} className="block text-primary"><Phone className="mr-2 inline h-4 w-4" />{service.phone}</a>}</div></article>)}</div></DataState></section>
        <InlineShopeeStrip hint="casa" title="Essenciais pra sua casa em Vespasiano" subtitle="Utilidades e ofertas selecionadas · links de parceiro" />
      </main>
    </SiteLayout>
  );
}
