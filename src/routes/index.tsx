import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, ShieldCheck, Sparkles, Clock } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { SearchBar } from "@/components/site/SearchBar";
import { CategoryIcon } from "@/components/site/CategoryIcon";
import { CompanyCard } from "@/components/site/CompanyCard";
import { Carousel, CarouselContent, CarouselItem } from "@/components/ui/carousel";
import { fetchCategories, fetchFeaturedCompanies } from "@/lib/queries";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "AgendaAqui — Encontre os melhores profissionais perto de você" },
      { name: "description", content: "Serviços, empresas e profissionais verificados em Vespasiano, São José da Lapa, Lagoa Santa e Belo Horizonte." },
      { property: "og:title", content: "AgendaAqui — Seu serviço certo, na hora certa." },
      { property: "og:description", content: "Encontre empresas e profissionais perto de você em Minas Gerais." },
      { property: "og:url", content: "/" },
    ],
    links: [{ rel: "canonical", href: "/" }],
  }),
  component: Home,
});

const CITIES = [
  { slug: "vespasiano", name: "Vespasiano" },
  { slug: "sao-jose-da-lapa", name: "São José da Lapa" },
  { slug: "lagoa-santa", name: "Lagoa Santa" },
  { slug: "belo-horizonte", name: "Belo Horizonte" },
];

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

function Home() {
  const cats = useQuery({ queryKey: ["categories"], queryFn: fetchCategories });
  const featured = useQuery({ queryKey: ["featured"], queryFn: () => fetchFeaturedCompanies(8) });

  return (
    <SiteLayout>
      {/* HERO */}
      <section className="relative overflow-hidden bg-gradient-to-br from-primary via-primary to-primary-dark text-primary-foreground">
        <div className="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:32px_32px]" />
        <div className="container relative mx-auto px-4 py-16 md:py-24">
          <div className="mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wider backdrop-blur">
              <Sparkles className="h-3.5 w-3.5" /> Marketplace regional · Minas Gerais
            </span>
            <h1 className="mt-5 font-display text-4xl font-extrabold leading-tight md:text-5xl lg:text-6xl">
              Encontre os melhores profissionais perto de você
            </h1>
            <p className="mt-4 text-base text-white/85 md:text-lg">
              Serviços, empresas e profissionais verificados. Seu serviço certo, na hora certa.
            </p>
          </div>
          <div className="mx-auto mt-8 max-w-3xl">
            <SearchBar />
          </div>
          <div className="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm text-white/80">
            <span>Cidades atendidas:</span>
            {CITIES.map((c) => (
              <Link
                key={c.slug}
                to="/cidades/$slug"
                params={{ slug: c.slug }}
                className="rounded-full bg-white/10 px-3 py-1 hover:bg-white/20"
              >
                {c.name}
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* CATEGORIES */}
      <section className="container mx-auto px-4 py-16">
        <div className="mb-8 flex items-end justify-between gap-4">
          <div>
            <h2 className="font-display text-2xl font-bold md:text-3xl">Explore por categoria</h2>
            <p className="mt-1 text-muted-foreground">12 categorias para encontrar o serviço ideal.</p>
          </div>
          <Link to="/buscar" className="hidden text-sm font-medium text-primary hover:underline md:inline-flex">
            Ver todas
          </Link>
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
          {(cats.data ?? []).map((c) => (
            <Link
              key={c.id}
              to="/categoria/$slug"
              params={{ slug: c.slug }}
              className="group flex flex-col items-center gap-3 rounded-xl border border-border bg-card p-5 text-center transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
            >
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                <CategoryIcon name={c.icon} className="h-6 w-6" />
              </div>
              <div className="text-sm font-medium text-foreground">{c.name}</div>
            </Link>
          ))}
        </div>
      </section>

      {/* FEATURED */}
      <section className="bg-surface py-16">
        <div className="container mx-auto px-4">
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
        </div>
      </section>

      {/* HOW IT WORKS */}
      <section className="container mx-auto px-4 py-16">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="font-display text-2xl font-bold md:text-3xl">Como funciona</h2>
          <p className="mt-2 text-muted-foreground">Encontrar o serviço certo nunca foi tão simples.</p>
        </div>
        <div className="mt-10 grid gap-6 md:grid-cols-3">
          {[
            { icon: Sparkles, title: "1. Busque", body: "Pesquise por categoria, cidade ou nome do serviço." },
            { icon: ShieldCheck, title: "2. Compare", body: "Veja avaliações reais, fotos e informações verificadas." },
            { icon: Clock, title: "3. Agende", body: "Fale direto pelo WhatsApp ou solicite um orçamento." },
          ].map((step) => (
            <div key={step.title} className="rounded-xl border border-border bg-card p-6">
              <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-accent/15 text-accent">
                <step.icon className="h-5 w-5" />
              </div>
              <h3 className="mt-4 font-display text-lg font-semibold">{step.title}</h3>
              <p className="mt-1 text-sm text-muted-foreground">{step.body}</p>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="container mx-auto px-4 pb-16">
        <div className="overflow-hidden rounded-2xl bg-gradient-to-r from-accent to-orange-500 p-8 text-accent-foreground md:p-12">
          <div className="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 className="font-display text-2xl font-bold md:text-3xl">Tem uma empresa ou presta serviços?</h3>
              <p className="mt-2 max-w-xl text-white/90">
                Cadastre-se grátis no AgendaAqui e seja encontrado por milhares de pessoas em Minas Gerais.
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
