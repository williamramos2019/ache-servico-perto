import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Calendar, ChevronLeft } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { fetchBlogPostBySlug } from "@/lib/blog";

export const Route = createFileRoute("/blog/$slug")({
  head: ({ params }) => ({
    meta: [
      { title: `${params.slug} — Blog AgendaAqui` },
      { name: "description", content: `Leia o artigo no blog do AgendaAqui.` },
      { property: "og:type", content: "article" },
      { property: "og:url", content: `/blog/${params.slug}` },
    ],
    links: [{ rel: "canonical", href: `/blog/${params.slug}` }],
  }),
  component: BlogPostPage,
});

function renderMarkdown(md: string) {
  // minimal markdown: ## headings, paragraphs, bullet lists
  const blocks = md.split(/\n\n+/);
  return blocks.map((b, i) => {
    if (b.startsWith("## ")) return <h2 key={i} className="mt-8 font-display text-2xl font-bold">{b.slice(3)}</h2>;
    if (b.startsWith("# ")) return <h1 key={i} className="mt-8 font-display text-3xl font-bold">{b.slice(2)}</h1>;
    if (b.startsWith("- ")) {
      return <ul key={i} className="mt-4 list-disc space-y-1 pl-6">{b.split("\n").map((l, j) => <li key={j}>{l.replace(/^- /, "")}</li>)}</ul>;
    }
    return <p key={i} className="mt-4 leading-relaxed text-foreground/90">{b}</p>;
  });
}

function BlogPostPage() {
  const { slug } = Route.useParams();
  const q = useQuery({ queryKey: ["blog-post", slug], queryFn: () => fetchBlogPostBySlug(slug) });
  if (q.isSuccess && !q.data) throw notFound();
  const p = q.data;

  return (
    <SiteLayout>
      <article className="container mx-auto max-w-3xl px-4 py-10">
        <Link to="/blog" className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
          <ChevronLeft className="h-4 w-4" /> Voltar ao blog
        </Link>
        {!p ? (
          <div className="mt-6 h-64 animate-pulse rounded-xl bg-muted" />
        ) : (
          <>
            <header className="mt-6">
              <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <Calendar className="h-3.5 w-3.5" />
                {new Date(p.published_at).toLocaleDateString("pt-BR")}
                {p.author_name && <span>· {p.author_name}</span>}
              </div>
              <h1 className="mt-2 font-display text-3xl font-extrabold md:text-4xl">{p.title}</h1>
              {p.excerpt && <p className="mt-3 text-lg text-muted-foreground">{p.excerpt}</p>}
            </header>
            {p.cover_url && (
              <img src={p.cover_url} alt={p.title} className="mt-6 aspect-[16/9] w-full rounded-xl object-cover" />
            )}
            <div className="prose prose-slate mt-2 max-w-none">
              {renderMarkdown(p.content)}
            </div>
          </>
        )}
      </article>
    </SiteLayout>
  );
}
