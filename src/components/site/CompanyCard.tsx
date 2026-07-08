import { Link } from "@tanstack/react-router";
import { MapPin, Star, BadgeCheck, Crown } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { FavoriteButton } from "@/components/site/FavoriteButton";
import { getPlanLimits } from "@/lib/plans";

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
};

export function CompanyCard({ company }: { company: CompanyCardData }) {
  const limits = getPlanLimits(company.plan);
  const isPremium = limits.cardVariant !== "default";
  const isFeatured = limits.cardVariant === "featured";

  return (
    <Link
      to="/empresa/$slug"
      params={{ slug: company.slug }}
      className={`group relative flex flex-col overflow-hidden rounded-2xl border bg-card transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_20px_40px_-16px_rgb(15_23_42/0.22)] ${
        isFeatured
          ? "border-accent/60 ring-2 ring-accent/30 hover:ring-accent/60"
          : isPremium
          ? "border-primary/40 ring-1 ring-primary/20"
          : "border-border"
      }`}
    >
      {isPremium ? (
        <div className="absolute right-0 top-0 z-10 rounded-bl-xl bg-gradient-to-r from-accent to-orange-500 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white shadow-md">
          Patrocinado
        </div>
      ) : null}
      <div className={`relative overflow-hidden bg-muted ${isFeatured ? "aspect-[16/9]" : "aspect-[16/10]"}`}>
        {company.banner_url ? (
          <img
            src={company.banner_url}
            alt={company.name}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
            onError={(e) => { (e.currentTarget as HTMLImageElement).style.display = "none"; }}
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
        {isFeatured ? (
          <Badge className="absolute left-3 top-3 bg-accent text-accent-foreground hover:bg-accent">
            <Crown className="mr-1 h-3 w-3" /> Destaque
          </Badge>
        ) : isPremium ? (
          <Badge className="absolute left-3 top-3 bg-primary text-primary-foreground hover:bg-primary">
            <BadgeCheck className="mr-1 h-3 w-3" /> Premium
          </Badge>
        ) : (
          <Badge variant="outline" className="absolute left-3 top-3 bg-background/80 backdrop-blur text-muted-foreground">
            Grátis
          </Badge>
        )}
        {company.id ? <FavoriteButton companyId={company.id} className="absolute bottom-3 right-3" /> : null}
      </div>
      <div className="flex flex-1 flex-col gap-2 p-4">
        <div className="flex items-start gap-3">
          {company.logo_url ? (
            <img
              src={company.logo_url}
              alt=""
              className="h-12 w-12 shrink-0 rounded-lg border border-border object-cover"
              loading="lazy"
            />
          ) : (
            <div className="h-12 w-12 shrink-0 rounded-lg bg-primary/10" />
          )}
          <div className="min-w-0 flex-1">
            <h3 className={`truncate font-display font-semibold text-foreground ${isFeatured ? "text-lg" : "text-base"}`}>
              {company.name}
              {company.is_verified ? (
                <BadgeCheck className="ml-1 inline h-4 w-4 text-primary" aria-label="Verificada" />
              ) : null}
            </h3>
            {company.tagline ? (
              <p className="line-clamp-1 text-sm text-muted-foreground">{company.tagline}</p>
            ) : null}
          </div>
        </div>
        <div className="mt-auto flex items-center justify-between pt-2 text-xs text-muted-foreground">
          {company.city_name ? (
            <span className="inline-flex items-center gap-1">
              <MapPin className="h-3.5 w-3.5" />
              {company.city_name}
            </span>
          ) : <span />}
          <span className="inline-flex items-center gap-1 font-medium text-foreground">
            <Star className="h-3.5 w-3.5 fill-accent text-accent" />
            {company.rating ? company.rating.toFixed(1) : "—"}
            <span className="font-normal text-muted-foreground">
              ({company.review_count ?? 0})
            </span>
          </span>
        </div>
      </div>
    </Link>
  );
}
