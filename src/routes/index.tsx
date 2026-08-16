import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, Siren, Sparkles } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { SearchBar } from "@/components/site/SearchBar";
import { CategoryIcon } from "@/components/site/CategoryIcon";
import { CompanyCard, toCompanyCardData } from "@/components/site/CompanyCard";
import { CitySwitch } from "@/components/site/CitySwitch";
import { Carousel, CarouselContent, CarouselItem } from "@/components/ui/carousel";
import { categoriesQueryOptions, featuredCompaniesQueryOptions, searchCompanies } from "@/lib/queries";
import { PUBLIC_SERVICE_CATEGORIES } from "@/lib/publicServices";
import { useSelectedCity, CITY_OPTIONS } from "@/hooks/useSelectedCity";
import { fetchPublishedEvents } from "@/lib/events";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "AgendaAqui — encontre empresas e serviços perto de você" },
      { name: "description", content: "Encontre empresas, serviços e oportunidades em Vespasiano e São José da Lapa. Avaliações reais, WhatsApp e cadastro grátis." },
      { property: "og:title", content: "AgendaAqui — empresas e serviços perto de você" },
      { property: "og:description", content: "Plataforma local de Vespasiano e São José da Lapa. Busque, compare e fale com empresas da sua cidade." },
      { property: "og:url", content: "/" },
    ],
    links: [{ rel: "canonical", href: "/" }],
  }),
  component: Home,
  loader: ({ context }) => {
    void context.queryClient.prefetchQuery(categoriesQueryOptions);
  },
});

type Category = { id: string; slug: string; name: string; icon?: string | null };

function CategoryCard({ category }: { category: Category }) {
  return (
    <Link
      to="/categoria/$slug"
      params={{ slug: category.slug }}
      className="group flex flex-col items-center gap-3 rounded-2xl border border-border bg-card p-5 text-center transition-all duration-300 ease-out hover:-translate-y-1 hover:border-primary/40 hover:shadow-[0_12px_32px_-12px_rgb(15_23_42/0.18)] focus-ring"
    >
      <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition-all duration-300 group-hover:scale-110 group-hover:bg-primary group-hover:text-primary-foreground">
        <CategoryIcon name={category.icon} className="h-6 w-6" />
      </div>
      <div className="text-sm font-medium text-foreground">{category.name}</div>
    </Link>
  );
}

function PublicServiceCard({ slug, label, icon, description }: { slug: string; label: string; icon: string; description: string }) {
  return (
    <Link
      to="/servicos-publicos"
      search={{ cat: slug }}
      className="group flex items-start gap-3 rounded-2xl border border-border bg-card p-4 transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 focus-ring"
    >
      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
        <CategoryIcon name={icon} className="h-5 w-5" />
      </div>
      <div className="min-w-0">
        <div className="font-medium text-foreground group-hover:text-primary">{label}</div>
        <div className="mt-0.5 text-xs leading-relaxed text-muted-foreground">{description}</div>
      </div>
    </Link>
  );
}

function Home() {
  const { city } = useSelectedCity();
  const cityName = CITY_OPTIONS.find((c) => c.slug === city)?.name ?? "sua cidade";
  const cats = useQuery(categoriesQueryOptions);
  const featured = useQuery(featuredCompaniesQueryOptions(8, city));
  const topRated = useQuery({
    queryKey: ["home-top-rated", city],
    queryFn: () => searchCompanies({ city, sort: "rating", hasReviews: true, limit: 8 }),
  });
  const discover = useQuery({
    queryKey: ["home-discover", city],
    queryFn: () => searchCompanies({ city, sort: "newest", limit: 8 }),
  });
  const events = useQuery({
    queryKey: ["home-events"],
    queryFn: () => fetchPublishedEvents(),
    staleTime: 5 * 60_000,
  });

  const popularCats = (cats.data ?? []).slice(0, 12);
  const featuredItems = featured.data ?? [];
  const ratedItems = (topRated.data?.items ?? []).filter((c) => (c.review_count ?? 0) > 0);
  const discoverItems = discover.data?.items ?? [];
  const upcomingEvents = (events.data ?? []).slice(0, 4);

  return (
    <SiteLayout>
      <section className="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-primary-dark text-primary-foreground">
        <div className="container relative mx-auto px-4 py-12 md:py-20">
          <div className="mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] ring-1 ring-white/20">
              <Sparkles className="h-3.5 w-3.5" /> {cityName} e região
            </span>
            <h1 className="mt-6 font-display text-3xl font-extrabold leading-[1.08] md:text-5xl lg:text-6xl">
              Encontre empresas e serviços perto de você
            </h1>
            <p className="mx-auto mt-4 max-w-2xl text-base text-white/85 md:text-lg">
              Busque, compare e fale direto no WhatsApp com quem atende em {cityName}.
            </p>
            <div className="mt-6 flex justify-center"><CitySwitch onDark /></div>
          </div>
          <div className="mx-auto mt-8 max-w-3xl">
            <SearchBar defaultCity={city} />
          </div>
        </div>
      </section>

      <section className="container relative z-10 mx-auto -mt-8 px-4 md:-mt-10">
        <Link
          to="/emergencia"
          className="group focus-ring flex flex-col items-start justify-between gap-4 rounded-2xl border border-border border-l-[6px] border-l-destructive bg-card p-5 shadow-elevated sm:flex-row sm:items-center"
        >
          <div className="flex items-center gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
              <Siren className="h-6 w-6" />
            </div>
            <div>
              <div className="font-display text-lg font-bold text-foreground">Plantão e Emergência 24h</div>
              <div className="mt-0.5 text-sm text-muted-foreground">SAMU, Bombeiros, Polícia e hospitais.</div>
            </div>
          </div>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-destructive px-4 py-2 text-sm font-semibold text-destructive-foreground">
            Ver números <ArrowRight className="h-4 w-4" />
          </span>
        </Link>
      </section>

      <section className="bg-surface py-14">
        <div className="container mx-auto px-4">
          <div className="mb-8 flex items-end justify-between gap-4">
            <div>
              <h2 className="font-display text-2xl font-bold md:text-3xl">Categorias populares</h2>
              <p className="mt-1 text-muted-foreground">O que as pessoas mais buscam em {cityName}.</p>
            </div>
            <Link to="/buscar" search={{ city }} className="hidden text-sm font-medium text-primary hover:underline md:inline-flex">
              Ver todas
            </Link>
          </div>
          {cats.isLoading ? (
            <div className="grid grid-cols-3 gap-3 md:grid-cols-6">
              {Array.from({ length: 6 }).map((_, i) => <div key={i} className="h-28 animate-pulse rounded-2xl bg-muted" />)}
            </div>
          ) : (
            <>
              <div className="reveal-grid hidden gap-3 md:grid md:grid-cols-4 lg:grid-cols-6">
                {popularCats.map((c) => <CategoryCard key={c.id} category={c} />)}
              </div>
              <div className="md:hidden">
                <Carousel opts={{ align: "start", dragFree: true }} className="w-full">
                  <CarouselContent className="-ml-3">
                    {popularCats.map((c) => (
                      <CarouselItem key={c.id} className="basis-[44%] pl-3">
                        <CategoryCard category={c} />
                      </CarouselItem>
                    ))}
                  </CarouselContent>
                </Carousel>
              </div>
            </>
          )}
        </div>
      </section>

      {featuredItems.length > 0 ? (
        <section className="container mx-auto px-4 py-14">
          <div className="mb-8 flex items-end justify-between gap-4">
            <div>
              <h2 className="font-display text-2xl font-bold md:text-3xl">Empresas em destaque</h2>
              <p className="mt-1 text-muted-foreground">Maior visibilidade em {cityName}. Identificadas no card.</p>
            </div>
            <Link to="/buscar" search={{ city, plan: "featured" }} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
              Ver tudo <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
          <div className="reveal-grid grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {featuredItems.map((co) => (
              <CompanyCard key={co.id} company={toCompanyCardData(co)} />
            ))}
          </div>
        </section>
      ) : null}

      {ratedItems.length > 0 ? (
        <section className="bg-surface py-14">
          <div className="container mx-auto px-4">
            <div className="mb-8">
              <h2 className="font-display text-2xl font-bold md:text-3xl">Mais bem avaliadas</h2>
              <p className="mt-1 text-muted-foreground">Somente empresas com avaliações reais em {cityName}.</p>
            </div>
            <div className="reveal-grid grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {ratedItems.map((co) => (
                <CompanyCard key={co.id} company={toCompanyCardData(co)} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {discoverItems.length > 0 ? (
        <section className="container mx-auto px-4 py-14">
          <div className="mb-8">
            <h2 className="font-display text-2xl font-bold md:text-3xl">Descubra em {cityName}</h2>
            <p className="mt-1 text-muted-foreground">Empresas da cidade selecionada, das mais recentes às já conhecidas.</p>
          </div>
          <div className="reveal-grid grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {discoverItems.map((co) => (
              <CompanyCard key={co.id} company={toCompanyCardData(co)} />
            ))}
          </div>
        </section>
      ) : null}

      {upcomingEvents.length > 0 ? (
        <section className="bg-surface py-14">
          <div className="container mx-auto px-4">
            <div className="mb-8 flex items-end justify-between">
              <h2 className="font-display text-2xl font-bold md:text-3xl">Eventos</h2>
              <Link to="/eventos" className="text-sm font-medium text-primary hover:underline">Ver agenda</Link>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {upcomingEvents.map((e) => (
                <Link
                  key={e.id}
                  to="/eventos/$slug"
                  params={{ slug: e.slug }}
                  className="overflow-hidden rounded-2xl border border-border bg-card focus-ring"
                >
                  {e.cover_image ? (
                    <img src={e.cover_image} alt="" className="aspect-[16/9] w-full object-cover" loading="lazy" />
                  ) : (
                    <div className="aspect-[16/9] bg-muted" />
                  )}
                  <div className="p-4">
                    <h3 className="font-display font-semibold line-clamp-2">{e.title}</h3>
                    {e.location ? <p className="mt-1 text-xs text-muted-foreground">{e.location}</p> : null}
                  </div>
                </Link>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      <section className="container mx-auto px-4 py-14">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 className="font-display text-2xl font-bold md:text-3xl">Utilidade local</h2>
            <p className="mt-1 text-muted-foreground">Saúde, educação, segurança e prefeitura.</p>
          </div>
          <Link to="/servicos-publicos" search={{}} className="hidden text-sm font-medium text-primary hover:underline md:inline-flex">
            Ver todos
          </Link>
        </div>
        <div className="reveal-grid grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {PUBLIC_SERVICE_CATEGORIES.filter((c) => c.slug !== "outros").map((c) => (
            <PublicServiceCard key={c.slug} {...c} />
          ))}
        </div>
      </section>

      <section className="container mx-auto px-4 pb-20">
        <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-accent via-orange-500 to-orange-600 p-8 text-accent-foreground md:p-14">
          <div className="relative">
            <h3 className="font-display text-2xl font-bold md:text-3xl">Tem uma empresa?</h3>
            <p className="mt-2 max-w-xl text-white/95">
              Cadastre gratuitamente sua empresa no AgendaAqui. Apareça para quem já procura seu serviço em {cityName}.
            </p>
            <Link
              to="/auth"
              className="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-6 py-3.5 font-semibold text-accent shadow-lg focus-ring"
            >
              Cadastrar empresa <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>
    </SiteLayout>
  );
}
