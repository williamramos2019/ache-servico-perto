import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, Siren, Sparkles } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { SearchBar } from "@/components/site/SearchBar";
import { CategoryIcon } from "@/components/site/CategoryIcon";
import { CompanyCard } from "@/components/site/CompanyCard";
import { CitySwitch } from "@/components/site/CitySwitch";
import { Carousel, CarouselContent, CarouselItem } from "@/components/ui/carousel";
import { fetchCategories, fetchFeaturedCompanies } from "@/lib/queries";
import { PUBLIC_SERVICE_CATEGORIES } from "@/lib/publicServices";
import { useSelectedCity, CITY_OPTIONS } from "@/hooks/useSelectedCity";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "App da Cidade — Vespasiano & São José da Lapa" },
      { name: "description", content: "Serviços públicos, telefones de emergência e guia de empresas de Vespasiano e São José da Lapa em um só app." },
      { property: "og:title", content: "App da Cidade — Vespasiano & São José da Lapa" },
      { property: "og:description", content: "Tudo sobre sua cidade num só lugar: saúde, educação, segurança, prefeitura e empresas locais." },
      { property: "og:url", content: "/" },
    ],
    links: [{ rel: "canonical", href: "/" }],
  }),
  component: Home,
});

type Category = { id: string; slug: string; name: string; icon?: string | null };

function CategoryCard({ category }: { category: Category }) {
  return (
    <Link
      to="/categoria/$slug"
      params={{ slug: category.slug }}
      className="group flex flex-col items-center gap-3 rounded-xl border border-border bg-card p-5 text-center transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
    >
      <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
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
      className="group flex items-start gap-3 rounded-xl border border-border bg-card p-4 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
    >
      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
        <CategoryIcon name={icon} className="h-5 w-5" />
      </div>
      <div className="min-w-0">
        <div className="font-medium text-foreground">{label}</div>
        <div className="text-xs text-muted-foreground">{description}</div>
      </div>
    </Link>
  );
}

function Home() {
  const { city } = useSelectedCity();
  const cityName = CITY_OPTIONS.find((c) => c.slug === city)?.name ?? "sua cidade";
  const cats = useQuery({ queryKey: ["categories"], queryFn: fetchCategories });
  const featured = useQuery({ queryKey: ["featured"], queryFn: () => fetchFeaturedCompanies(8) });

  return (
    <SiteLayout>
      {/* HERO */}
      <section className="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-primary-dark text-primary-foreground">
        <div className="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:32px_32px]" />
        <div className="container relative mx-auto px-4 py-14 md:py-20">
          <div className="mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wider backdrop-blur">
              <Sparkles className="h-3.5 w-3.5" /> App da Cidade
            </span>
            <h1 className="mt-5 font-display text-4xl font-extrabold leading-tight md:text-5xl lg:text-6xl">
              Tudo sobre {cityName} num só app
            </h1>
            <p className="mt-4 text-base text-white/85 md:text-lg">
              Serviços públicos, telefones de emergência e o guia de empresas locais de Vespasiano e São José da Lapa.
            </p>
            <div className="mt-6 flex justify-center"><CitySwitch onDark /></div>
          </div>
          <div className="mx-auto mt-8 max-w-3xl">
            <SearchBar />
          </div>
        </div>
      </section>

      {/* EMERGENCY CTA */}
      <section className="container mx-auto -mt-6 px-4 md:-mt-8">
        <Link
          to="/emergencia"
          className="flex items-center justify-between gap-4 rounded-2xl border border-destructive/20 bg-destructive text-destructive-foreground px-5 py-4 shadow-lg transition hover:shadow-xl"
        >
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15">
              <Siren className="h-6 w-6" />
            </div>
            <div>
              <div className="font-display text-lg font-bold">Emergência 24h</div>
              <div className="text-xs text-destructive-foreground/85">SAMU, Bombeiros, Polícia e serviços de urgência</div>
            </div>
          </div>
          <ArrowRight className="h-5 w-5" />
        </Link>
      </section>

      {/* PUBLIC SERVICES */}
      <section className="container mx-auto px-4 py-14">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 className="font-display text-2xl font-bold md:text-3xl">Serviços Públicos</h2>
            <p className="mt-1 text-muted-foreground">Saúde, educação, segurança e serviços da prefeitura.</p>
          </div>
          <Link to="/servicos-publicos" search={{}} className="hidden text-sm font-medium text-primary hover:underline md:inline-flex">
            Ver todos
          </Link>
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {PUBLIC_SERVICE_CATEGORIES.filter((c) => c.slug !== "outros").map((c) => (
            <PublicServiceCard key={c.slug} {...c} />
          ))}
        </div>
      </section>

      {/* BUSINESS CATEGORIES */}
      <section className="bg-surface py-14">
        <div className="container mx-auto px-4">
          <div className="mb-8 flex items-end justify-between gap-4">
            <div>
              <h2 className="font-display text-2xl font-bold md:text-3xl">Guia de empresas</h2>
              <p className="mt-1 text-muted-foreground">{(cats.data ?? []).length} categorias de serviços na sua cidade.</p>
            </div>
            <Link to="/buscar" className="hidden text-sm font-medium text-primary hover:underline md:inline-flex">
              Ver todas
            </Link>
          </div>
          <div className="hidden gap-3 md:grid md:grid-cols-4 lg:grid-cols-6">
            {(cats.data ?? []).map((c) => (
              <CategoryCard key={c.id} category={c} />
            ))}
          </div>
          <div className="md:hidden">
            <Carousel opts={{ align: "start", dragFree: true }} className="w-full">
              <CarouselContent className="-ml-3">
                {(cats.data ?? []).map((c) => (
                  <CarouselItem key={c.id} className="basis-[44%] pl-3">
                    <CategoryCard category={c} />
                  </CarouselItem>
                ))}
              </CarouselContent>
            </Carousel>
          </div>
        </div>
      </section>

      {/* FEATURED */}
      <section className="container mx-auto px-4 py-14">
        <div className="mb-8 flex items-end justify-between gap-4">
          <div>
            <h2 className="font-display text-2xl font-bold md:text-3xl">Empresas em destaque</h2>
            <p className="mt-1 text-muted-foreground">Selecionadas pela comunidade.</p>
          </div>
          <Link to="/buscar" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
            Ver tudo <ArrowRight className="h-4 w-4" />
          </Link>
        </div>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {(featured.data ?? []).map((co) => (
            <CompanyCard
              key={co.id}
              company={{
                id: co.id,
                slug: co.slug,
                name: co.name,
                tagline: co.tagline,
                banner_url: co.banner_url,
                logo_url: co.logo_url,
                plan: co.plan,
                featured: co.featured,
                city_name: co.city?.name,
                rating: co.rating,
                review_count: co.review_count,
              }}
            />
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="container mx-auto px-4 pb-16">
        <div className="overflow-hidden rounded-2xl bg-gradient-to-r from-accent to-orange-500 p-8 text-accent-foreground md:p-12">
          <div className="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 className="font-display text-2xl font-bold md:text-3xl">Tem uma empresa na cidade?</h3>
              <p className="mt-2 max-w-xl text-white/90">
                Cadastre-se grátis e apareça para quem mora em Vespasiano e São José da Lapa.
              </p>
            </div>
            <Link
              to="/auth"
              className="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-accent shadow-md hover:bg-white/95"
            >
              Anunciar grátis <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </section>
    </SiteLayout>
  );
}
