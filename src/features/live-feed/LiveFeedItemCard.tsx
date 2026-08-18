import { Link } from "@tanstack/react-router";
import { formatDistanceToNow } from "date-fns";
import { ptBR } from "date-fns/locale";
import type { LiveFeedItem } from "@/lib/domain-api";
import { KIND_META } from "./types";

export function LiveFeedItemCard({ item, compact }: { item: LiveFeedItem; compact?: boolean }) {
  const meta = KIND_META[item.kind] ?? { icon: "•", label: item.kind };
  const timeAgo = item.timestamp
    ? formatDistanceToNow(new Date(item.timestamp), { addSuffix: true, locale: ptBR })
    : "";

  const inner = (
    <article
      className={`group flex gap-3 rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/40 ${
        compact ? "" : "sm:p-4"
      }`}
    >
      {item.image ? (
        <img src={item.image} alt="" className="h-10 w-10 shrink-0 rounded-md object-cover" />
      ) : (
        <div
          aria-hidden
          className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-lg"
        >
          {meta.icon}
        </div>
      )}
      <div className="min-w-0 flex-1">
        <div className="mb-1 flex flex-wrap items-center gap-2">
          <span className="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-secondary-foreground">
            {meta.label}
          </span>
          {timeAgo && <span className="text-xs text-muted-foreground">{timeAgo}</span>}
        </div>
        <h3 className={`line-clamp-2 font-medium text-foreground ${compact ? "text-sm" : "text-base"}`}>
          {item.title}
        </h3>
        {!compact && item.subtitle ? (
          <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{item.subtitle}</p>
        ) : null}
      </div>
    </article>
  );

  if (!item.href) return inner;
  if (item.href.startsWith("http")) {
    return (
      <a href={item.href} target="_blank" rel="noopener noreferrer">
        {inner}
      </a>
    );
  }
  return <Link to={item.href}>{inner}</Link>;
}

export function LiveFeedCard({ item }: { item: LiveFeedItem }) {
  return <LiveFeedItemCard item={item} />;
}
