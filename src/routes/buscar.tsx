import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { z } from "zod";
import { SiteLayout } from "@/components/site/SiteLayout";
import { SearchBar } from "@/components/site/SearchBar";
import { CompanyCard } from "@/components/site/CompanyCard";
import { CategoryIcon } from "@/components/site/CategoryIcon";
import { fetchCategories, fetchCities, searchCompanies } from "@/lib/queries";

const searchSchema = z.object({
  q: z.string().optional(),
  city: z.string().optional(),
  category: z.string().optional(),
});

export const Route = createFileRoute("/buscar")({
  validateSearch: searchSchema,
  head: () => ({
    meta: [
      { title: "Buscar serviços e empresas — AgendaAqui" },
      { name: "description", content: "Pesquise empresas e profissionais verificados em Minas Gerais." },
      { property: "og:url", content: "/buscar" },
    ],
    links: [{ rel: "canonical", href: "/buscar" }],
  }),
  component: BuscarPage,
});

function BuscarPage() {
  const { q, city, category } = Route.useSearch();
  const cats = useQuery({ queryKey: ["categories"], queryFn: fetchCategories });
  const cities = useQuery({ queryKey: ["cities"], queryFn: fetchCities });
  const results = useQuery({
    queryKey: ["search", q ?? "", city ?? "", category ?? ""],
    queryFn: () => searchCompanies({ q, city, category }),
  });

  return (
    <SiteLayout>
      <section className="border-b border-border bg-surface">
        <div className="container mx-auto px-4 py-8">
          <h1 className="font-display text-2xl font-bold md:text-3xl">
            {q ? `Resultados para "${q}"` : "Todos os serviços"}
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {city ? `Em ${cities.data?.find((c) => c.slug === city)?.name ?? city}` : "Em todas as cidades atendidas"}
          </p>
          <div className="mt-5">
            <SearchBar defaultQ={q ?? ""} defaultCity={city ?? "todas"} />
          </div>
        </div>
      </section>

      <section className="container mx-auto grid gap-8 px-4 py-10 lg:grid-cols-[260px_1fr]">
        <aside className="space-y-6">
          <div>
            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Categorias</h3>
            <div className="space-y-1">
              <Link
                to="/buscar"
                search={{ q, city }}
                className={`flex items-center justify-between rounded-md px-3 py-2 text-sm hover:bg-muted ${!category ? "bg-muted font-semibold" : ""}`}
              >
                Todas
              </Link>
              {(cats.data ?? []).map((c) => (
                <Link
                  key={c.id}
                  to="/buscar"
                  search={{ q, city, category: c.slug }}
                  className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-muted ${category === c.slug ? "bg-muted font-semibold" : ""}`}
                >
                  <CategoryIcon name={c.icon} className="h-4 w-4 text-primary" />
                  {c.name}
                </Link>
              ))}
            </div>
          </div>
        </aside>

        <div>
          {results.isLoading ? (
            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="h-72 animate-pulse rounded-xl bg-muted" />
              ))}
            </div>
          ) : (results.data ?? []).length === 0 ? (
            <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center">
              <p className="text-lg font-semibold">Nenhuma empresa encontrada</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Tente ajustar a busca ou escolher outra categoria.
              </p>
            </div>
          ) : (
            <>
              <p className="mb-4 text-sm text-muted-foreground">
                {results.data?.length} resultado(s) encontrado(s)
              </p>
              <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                {(results.data ?? []).map((co) => (
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
            </>
          )}
        </div>
      </section>
    </SiteLayout>
  );
}
