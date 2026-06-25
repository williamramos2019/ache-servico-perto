import { Link } from "@tanstack/react-router";
import { MapPin, Star, BadgeCheck } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { FavoriteButton } from "@/components/site/FavoriteButton";

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
};

export function CompanyCard({ company }: { company: CompanyCardData }) {
  return (
    <Link
      to="/empresa/$slug"
      params={{ slug: company.slug }}
      className="group flex flex-col overflow-hidden rounded-xl border border-border bg-card transition-shadow hover:shadow-lg"
    >
      <div className="relative aspect-[16/10] overflow-hidden bg-muted">
        {company.banner_url ? (
          <img
            src={company.banner_url}
            alt={company.name}
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
          />
        ) : null}
        {company.featured ? (
          <Badge className="absolute left-3 top-3 bg-accent text-accent-foreground hover:bg-accent">
            Destaque
          </Badge>
        ) : null}
        {company.plan === "premium" ? (
          <Badge className="absolute right-3 top-3 bg-primary text-primary-foreground hover:bg-primary">
            <BadgeCheck className="mr-1 h-3 w-3" /> Premium
          </Badge>
        ) : null}
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
            <h3 className="truncate font-display text-base font-semibold text-foreground">
              {company.name}
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
