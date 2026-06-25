import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Phone, MessageCircle, Share2, MapPin, Globe, Instagram, Facebook, Star, BadgeCheck } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ReviewsSection } from "@/components/site/ReviewsSection";
import { QuoteDialog } from "@/components/site/QuoteDialog";
import { fetchCompanyBySlug, fetchCompanyReviews } from "@/lib/queries";
import { FavoriteButton } from "@/components/site/FavoriteButton";
import { telUrl, waUrl } from "@/lib/format";

export const Route = createFileRoute("/empresa/$slug")({
  head: ({ params }) => ({
    meta: [
      { title: `${params.slug} — AgendaAqui` },
      { name: "description", content: `Veja avaliações, fotos e contato de ${params.slug} no AgendaAqui.` },
      { property: "og:url", content: `/empresa/${params.slug}` },
      { property: "og:type", content: "profile" },
    ],
    links: [{ rel: "canonical", href: `/empresa/${params.slug}` }],
  }),
  component: CompanyPage,
});

type Media = { id: string; url: string; type: string; sort: number };
type CatLink = { categories: { name: string; slug: string; icon: string | null } | null };
type Company = {
  id: string; slug: string; name: string; tagline: string | null; description: string | null;
  phone: string | null; whatsapp: string | null; email: string | null;
  address: string | null; zip: string | null; lat: number | null; lng: number | null;
  website: string | null; instagram: string | null; facebook: string | null;
  hours: Record<string, string> | null; logo_url: string | null; banner_url: string | null;
  plan: string | null; featured: boolean | null;
  cities: { name: string; slug: string; state: string } | null;
  company_categories: CatLink[];
  company_media: Media[];
};

function CompanyPage() {
  const { slug } = Route.useParams();
  const q = useQuery({ queryKey: ["company", slug], queryFn: () => fetchCompanyBySlug(slug) });
  const company = q.data as Company | null | undefined;
  const reviews = useQuery({
    queryKey: ["reviews", company?.id ?? ""],
    queryFn: () => fetchCompanyReviews(company!.id),
    enabled: !!company?.id,
  });

  if (q.isSuccess && !company) throw notFound();
  if (!company) {
    return (
      <SiteLayout>
        <div className="container mx-auto p-10">
          <div className="h-64 animate-pulse rounded-xl bg-muted" />
        </div>
      </SiteLayout>
    );
  }

  const ratings = (reviews.data ?? []).map((r) => r.rating);
  const avg = ratings.length ? ratings.reduce((a, b) => a + b, 0) / ratings.length : 0;
  const mapSrc = company.lat && company.lng
    ? `https://www.google.com/maps?q=${company.lat},${company.lng}&z=15&output=embed`
    : null;

  return (
    <SiteLayout>
      {/* Banner */}
      <div className="relative h-56 w-full overflow-hidden bg-muted md:h-72">
        {company.banner_url ? (
          <img src={company.banner_url} alt="" className="h-full w-full object-cover" />
        ) : (
          <div className="h-full w-full bg-gradient-to-br from-primary to-primary-dark" />
        )}
      </div>

      <div className="container mx-auto px-4">
        {/* Header card */}
        <div className="relative -mt-16 rounded-2xl border border-border bg-card p-5 shadow-lg md:p-8">
          <div className="flex flex-col gap-5 md:flex-row md:items-start">
            <img
              src={company.logo_url ?? company.banner_url ?? ""}
              alt={company.name}
              className="h-24 w-24 rounded-xl border border-border bg-muted object-cover md:h-28 md:w-28"
            />
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="font-display text-2xl font-bold md:text-3xl">{company.name}</h1>
                {company.plan === "premium" ? (
                  <Badge className="bg-primary text-primary-foreground hover:bg-primary">
                    <BadgeCheck className="mr-1 h-3 w-3" /> Premium
                  </Badge>
                ) : null}
                {company.featured ? (
                  <Badge className="bg-accent text-accent-foreground hover:bg-accent">Destaque</Badge>
                ) : null}
              </div>
              {company.tagline ? <p className="mt-1 text-muted-foreground">{company.tagline}</p> : null}
              <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                <span className="inline-flex items-center gap-1">
                  <Star className="h-4 w-4 fill-accent text-accent" />
                  <strong className="text-foreground">{avg ? avg.toFixed(1) : "—"}</strong>
                  <span>({ratings.length} avaliações)</span>
                </span>
                {company.cities ? (
                  <Link
                    to="/cidades/$slug"
                    params={{ slug: company.cities.slug }}
                    className="inline-flex items-center gap-1 hover:text-foreground"
                  >
                    <MapPin className="h-4 w-4" />
                    {company.cities.name} · {company.cities.state}
                  </Link>
                ) : null}
              </div>
              <div className="mt-3 flex flex-wrap gap-2">
                {company.company_categories.map((cc) =>
                  cc.categories ? (
                    <Link
                      key={cc.categories.slug}
                      to="/categoria/$slug"
                      params={{ slug: cc.categories.slug }}
                      className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-foreground/80 hover:bg-primary hover:text-primary-foreground"
                    >
                      {cc.categories.name}
                    </Link>
                  ) : null,
                )}
              </div>
            </div>
            <div className="grid w-full shrink-0 gap-2 md:w-56">
              {company.whatsapp ? (
                <a href={waUrl(company.whatsapp, `Olá! Vi sua empresa no AgendaAqui.`)} target="_blank" rel="noreferrer">
                  <Button className="w-full bg-[#25D366] text-white hover:bg-[#1ebe5d]">
                    <MessageCircle className="mr-2 h-4 w-4" /> WhatsApp
                  </Button>
                </a>
              ) : null}
              {company.phone ? (
                <a href={telUrl(company.phone)}>
                  <Button variant="outline" className="w-full">
                    <Phone className="mr-2 h-4 w-4" /> Ligar
                  </Button>
                </a>
              ) : null}
              <QuoteDialog companyId={company.id} companyName={company.name} />
              <Button
                variant="ghost"
                className="w-full"
                onClick={() => {
                  if (typeof navigator !== "undefined" && navigator.share) {
                    navigator.share({ title: company.name, url: window.location.href }).catch(() => {});
                  } else {
                    navigator.clipboard?.writeText(window.location.href);
                  }
                }}
              >
                <Share2 className="mr-2 h-4 w-4" /> Compartilhar
              </Button>
              <div className="flex justify-center pt-1">
                <FavoriteButton companyId={company.id} className="relative" />
              </div>
            </div>
          </div>
        </div>

        {/* Body */}
        <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_320px]">
          <div className="space-y-8">
            {company.description ? (
              <section className="rounded-xl border border-border bg-card p-6">
                <h2 className="font-display text-xl font-bold">Sobre</h2>
                <p className="mt-3 whitespace-pre-line text-foreground/90">{company.description}</p>
              </section>
            ) : null}

            {company.company_media.length > 0 ? (
              <section className="rounded-xl border border-border bg-card p-6">
                <h2 className="font-display text-xl font-bold">Galeria</h2>
                <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                  {company.company_media
                    .sort((a, b) => a.sort - b.sort)
                    .map((m) => (
                      <img
                        key={m.id}
                        src={m.url}
                        alt=""
                        loading="lazy"
                        className="aspect-square w-full rounded-lg object-cover"
                      />
                    ))}
                </div>
              </section>
            ) : null}

            <ReviewsSection companyId={company.id} />
          </div>

          <aside className="space-y-6">
            <section className="rounded-xl border border-border bg-card p-6">
              <h3 className="font-display text-lg font-bold">Contato</h3>
              <ul className="mt-3 space-y-2 text-sm">
                {company.address ? (
                  <li className="flex gap-2"><MapPin className="mt-0.5 h-4 w-4 text-primary" /> {company.address}</li>
                ) : null}
                {company.phone ? (
                  <li className="flex gap-2"><Phone className="mt-0.5 h-4 w-4 text-primary" /> {company.phone}</li>
                ) : null}
                {company.website ? (
                  <li className="flex gap-2"><Globe className="mt-0.5 h-4 w-4 text-primary" />
                    <a href={company.website} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                      {company.website.replace(/^https?:\/\//, "")}
                    </a>
                  </li>
                ) : null}
                {company.instagram ? (
                  <li className="flex gap-2"><Instagram className="mt-0.5 h-4 w-4 text-primary" />
                    <a href={`https://instagram.com/${company.instagram}`} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                      @{company.instagram}
                    </a>
                  </li>
                ) : null}
                {company.facebook ? (
                  <li className="flex gap-2"><Facebook className="mt-0.5 h-4 w-4 text-primary" /> {company.facebook}</li>
                ) : null}
              </ul>
            </section>

            {company.hours ? (
              <section className="rounded-xl border border-border bg-card p-6">
                <h3 className="font-display text-lg font-bold">Horário</h3>
                <ul className="mt-3 space-y-1 text-sm text-foreground/85">
                  {Object.entries(company.hours).map(([k, v]) => (
                    <li key={k} className="flex justify-between border-b border-border py-1 last:border-0">
                      <span className="capitalize">{k.replace("-", " a ")}</span>
                      <span className="font-medium">{v}</span>
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}

            {mapSrc ? (
              <section className="overflow-hidden rounded-xl border border-border bg-card">
                <h3 className="border-b border-border px-6 py-4 font-display text-lg font-bold">Localização</h3>
                <iframe
                  title="Mapa"
                  src={mapSrc}
                  className="h-64 w-full"
                  loading="lazy"
                  referrerPolicy="no-referrer-when-downgrade"
                />
              </section>
            ) : null}
          </aside>
        </div>
      </div>
    </SiteLayout>
  );
}
