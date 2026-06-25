import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { z } from "zod";
import { Star } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { SearchBar } from "@/components/site/SearchBar";
import { CompanyCard } from "@/components/site/CompanyCard";
import { CategoryIcon } from "@/components/site/CategoryIcon";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { fetchCategories, fetchCities, searchCompanies } from "@/lib/queries";

const searchSchema = z.object({
  q: z.string().optional(),
  city: z.string().optional(),
  category: z.string().optional(),
  sort: z.enum(["relevance", "rating", "name", "newest"]).optional(),
  minRating: z.coerce.number().min(0).max(5).optional(),
  premium: z.coerce.boolean().optional(),
});

export const Route = createFileRoute("/buscar")({
  validateSearch: searchSchema,
  head: () => ({
    meta: [
      { title: "Buscar serviços e empresas — AgendaAqui" },
      { name: "description", content: "Pesquise empresas e profissionais verificados em Minas Gerais com filtros por avaliação, categoria e cidade." },
      { property: "og:url", content: "/buscar" },
    ],
    links: [{ rel: "canonical", href: "/buscar" }],
  }),
  component: BuscarPage,
});

function BuscarPage() {
  const search = Route.useSearch();
  const { q, city, category, sort = "relevance", minRating = 0, premium = false } = search;
  const navigate = Route.useNavigate();

  const cats = useQuery({ queryKey: ["categories"], queryFn: fetchCategories });
  const cities = useQuery({ queryKey: ["cities"], queryFn: fetchCities });
  const results = useQuery({
    queryKey: ["search", q ?? "", city ?? "", category ?? "", sort, minRating, premium],
    queryFn: () => searchCompanies({ q, city, category, sort, minRating, premiumOnly: premium }),
  });

  function setParam<K extends keyof typeof search>(k: K, v: typeof search[K]) {
    navigate({ to: "/buscar", search: { ...search, [k]: v } });
  }

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
            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Ordenar por</h3>
            <Select value={sort} onValueChange={(v) => setParam("sort", v as typeof sort)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="relevance">Relevância</SelectItem>
                <SelectItem value="rating">Melhor avaliados</SelectItem>
                <SelectItem value="name">Nome (A–Z)</SelectItem>
                <SelectItem value="newest">Mais recentes</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div>
            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Avaliação mínima</h3>
            <div className="space-y-1">
              {[0, 3, 4, 4.5].map((r) => (
                <button
                  key={r}
                  onClick={() => setParam("minRating", r === 0 ? undefined : r)}
                  className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-muted ${minRating === r ? "bg-muted font-semibold" : ""}`}
                >
                  {r === 0 ? (
                    "Qualquer"
                  ) : (
                    <>
                      <Star className="h-4 w-4 fill-accent text-accent" />
                      {r}+ estrelas
                    </>
                  )}
                </button>
              ))}
            </div>
          </div>

          <div className="flex items-center gap-2 rounded-md border border-border bg-card p-3">
            <Checkbox
              id="premium"
              checked={premium}
              onCheckedChange={(v) => setParam("premium", v ? true : undefined)}
            />
            <Label htmlFor="premium" className="cursor-pointer text-sm">Somente Premium</Label>
          </div>

          <div>
            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Categorias</h3>
            <div className="space-y-1">
              <Link
                to="/buscar"
                search={{ q, city, sort, minRating: minRating || undefined, premium: premium || undefined }}
                className={`flex items-center justify-between rounded-md px-3 py-2 text-sm hover:bg-muted ${!category ? "bg-muted font-semibold" : ""}`}
              >
                Todas
              </Link>
              {(cats.data ?? []).map((c) => (
                <Link
                  key={c.id}
                  to="/buscar"
                  search={{ q, city, category: c.slug, sort, minRating: minRating || undefined, premium: premium || undefined }}
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
                Tente ajustar a busca, ordenação ou filtros.
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
