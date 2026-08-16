import { Link } from "@tanstack/react-router";
import { MapPin, Star, BadgeCheck, Crown, MessageCircle, Phone, ChevronRight } from "lucide-react";
import { FavoriteButton } from "@/components/site/FavoriteButton";
import { getPlanLimits } from "@/lib/plans";
import { telUrl, waUrl } from "@/lib/format";
import type { CompanyListItem } from "@/lib/queries";

export type CompanyCardData = {
  id?: string;
  slug: string;
  name: string;
  tagline?: string | null;
  banner_url?: string | null;
  logo_url?: string | null;
  plan?: string | null;
  featured?: boolean | null;
  city_name?: string | null;
  rating?: number;
  review_count?: number;
  is_verified?: boolean | null;
  phone?: string | null;
  whatsapp?: string | null;
  open_now?: boolean | null;
  category_name?: string | null;
  origin?: string | null;
};

export function toCompanyCardData(co: CompanyListItem): CompanyCardData {
  return {
    id: co.id,
    slug: co.slug,
    name: co.name,
    tagline: co.tagline,
    banner_url: co.banner_url,
    logo_url: co.logo_url,
    plan: co.plan,
    featured: co.featured,
    city_name: co.city?.name ?? null,
    rating: co.rating,
    review_count: co.review_count,
    is_verified: co.is_verified,
    phone: co.phone,
    whatsapp: co.whatsapp,
    open_now: co.open_now,
    category_name: co.categories?.[0]?.name ?? null,
    origin: co.origin ?? null,
  };
}

function planBadge(plan: string | null | undefined, featured: boolean | null | undefined): {
  label: string;
  className: string;
  icon: "crown" | "check" | null;
} | null {
  const limits = getPlanLimits(plan);
  if (limits.cardVariant === "featured" || featured) {
    return { label: "Destaque", className: "bg-accent text-accent-foreground", icon: "crown" };
  }
  if (limits.cardVariant === "premium" || plan === "premium") {
    return { label: "Premium", className: "bg-primary text-primary-foreground", icon: "check" };
  }
  return null;
}

export function CompanyCard({ company }: { company: CompanyCardData }) {
  const badge = planBadge(company.plan, company.featured);
  const hasReviews = (company.review_count ?? 0) > 0 && (company.rating ?? 0) > 0;
  const cover = company.banner_url || company.logo_url;
  const meta = [company.category_name, company.city_name].filter(Boolean).join(" · ");

  return (
    <article className="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-[0_18px_36px_-16px_rgb(15_23_42/0.2)]">
      <div className="relative">
      <Link
        to="/empresa/$slug"
        params={{ slug: company.slug }}
        className="relative block overflow-hidden bg-muted aspect-[16/10] focus-ring"
        aria-label={company.name}
      >
        {cover ? (
          <img
            src={cover}
            alt=""
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            decoding="async"
            onError={(e) => {
              (e.currentTarget as HTMLImageElement).style.display = "none";
            }}
          />
        ) : (
          <div
            aria-hidden
            className="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/15 via-primary/5 to-accent/20"
          >
            <span className="font-display text-5xl font-bold text-primary/40">
              {company.name?.trim()?.charAt(0)?.toUpperCase() ?? "?"}
            </span>
          </div>
        )}
        {badge ? (
          <span className={`absolute left-3 top-3 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide ${badge.className}`}>
            {badge.icon === "crown" ? <Crown className="h-3 w-3" aria-hidden /> : <BadgeCheck className="h-3 w-3" aria-hidden />}
            {badge.label}
          </span>
        ) : null}
        {company.open_now === true ? (
          <span className="absolute left-3 bottom-3 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">
            Aberto agora
          </span>
        ) : null}
      </Link>
        {company.id ? <FavoriteButton companyId={company.id} className="absolute bottom-3 right-3 z-10" /> : null}
      </div>

      <div className="flex flex-1 flex-col gap-2 p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <h3 className="truncate font-display text-base font-semibold text-foreground">
              <Link to="/empresa/$slug" params={{ slug: company.slug }} className="hover:underline focus-ring rounded-sm">
                {company.name}
              </Link>
              {company.is_verified ? (
                <BadgeCheck className="ml-1 inline h-4 w-4 text-primary" aria-label="Verificada" />
              ) : null}
            </h3>
            <p className="mt-0.5 text-sm text-foreground/80">
              {hasReviews ? (
                <span className="inline-flex items-center gap-1">
                  <Star className="h-3.5 w-3.5 fill-accent text-accent" aria-hidden />
                  <span className="font-semibold">{company.rating!.toFixed(1)}</span>
                  <span className="text-muted-foreground">
                    ({company.review_count} {company.review_count === 1 ? "avaliação" : "avaliações"})
                  </span>
                </span>
              ) : (
                <span className="text-muted-foreground">Sem avaliações ainda</span>
              )}
            </p>
            {meta ? (
              <p className="mt-1 truncate text-xs text-muted-foreground">
                <MapPin className="mr-0.5 inline h-3 w-3" aria-hidden />
                {meta}
              </p>
            ) : null}
            {company.origin === "imported" ? (
              <p className="mt-1 text-[11px] text-muted-foreground">Cadastro público</p>
            ) : null}
          </div>
        </div>

        {company.tagline ? (
          <p className="line-clamp-1 text-sm text-muted-foreground">{company.tagline}</p>
        ) : null}

        <div className="mt-auto flex flex-wrap gap-2 pt-2">
          {company.whatsapp ? (
            <a
              href={waUrl(company.whatsapp, "Olá! Vi sua empresa no AgendaAqui.")}
              target="_blank"
              rel="noreferrer"
              className="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-full bg-[#25D366] px-3 text-sm font-semibold text-white hover:bg-[#1ebe5d] focus-ring"
            >
              <MessageCircle className="h-4 w-4" aria-hidden /> WhatsApp
            </a>
          ) : null}
          {company.phone ? (
            <a
              href={telUrl(company.phone)}
              className="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-full border border-border bg-background px-3 text-sm font-semibold text-foreground hover:bg-muted focus-ring"
            >
              <Phone className="h-4 w-4" aria-hidden /> Ligar
            </a>
          ) : null}
          <Link
            to="/empresa/$slug"
            params={{ slug: company.slug }}
            className="inline-flex min-h-10 items-center justify-center gap-1 rounded-full border border-border px-3 text-sm font-medium text-primary hover:bg-primary/5 focus-ring"
          >
            Ver empresa <ChevronRight className="h-4 w-4" aria-hidden />
          </Link>
        </div>
      </div>
    </article>
  );
}
