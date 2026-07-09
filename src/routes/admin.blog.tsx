import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Pencil, Plus, Trash2, Eye, EyeOff, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Switch } from "@/components/ui/switch";
import { supabase } from "@/integrations/supabase/client";

export const Route = createFileRoute("/admin/blog")({
  head: () => ({ meta: [{ title: "Blog — Admin AgendaAqui" }, { name: "robots", content: "noindex" }] }),
  component: AdminBlog,
});

type Post = {
  id: string;
  slug: string;
  title: string;
  excerpt: string | null;
  content: string | null;
  cover_url: string | null;
  author_name: string | null;
  published: boolean;
  published_at: string | null;
  created_at: string;
};

function slugify(s: string) {
  return s
    .toLowerCase()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim().replace(/\s+/g, "-").slice(0, 80);
}

async function fetchAll(): Promise<Post[]> {
  const { data, error } = await supabase
    .from("blog_posts_legacy")
    .select("id, slug, title, excerpt, content, cover_url, author_name, published, published_at, created_at")
    .order("created_at", { ascending: false });
  if (error) throw error;
  return (data ?? []) as Post[];
}

function AdminBlog() {
  const qc = useQueryClient();
  const { data: posts, isLoading } = useQuery({ queryKey: ["admin-blog-posts"], queryFn: fetchAll });
  const [editing, setEditing] = useState<Partial<Post> | null>(null);

  async function save(p: Partial<Post>) {
    const payload = {
      slug: p.slug || slugify(p.title || ""),
      title: p.title,
      excerpt: p.excerpt || null,
      content: p.content || null,
      cover_url: p.cover_url || null,
      author_name: p.author_name || "Equipe AgendaAqui",
      published: !!p.published,
      published_at: p.published ? (p.published_at ?? new Date().toISOString()) : null,
    };
    if (!payload.title || !payload.slug) return toast.error("Título e slug são obrigatórios");
    const q = p.id
      ? supabase.from("blog_posts_legacy").update(payload).eq("id", p.id)
      : supabase.from("blog_posts_legacy").insert(payload);
    const { error } = await q;
    if (error) return toast.error(error.message);
    toast.success(p.id ? "Post atualizado" : "Post criado");
    setEditing(null);
    qc.invalidateQueries({ queryKey: ["admin-blog-posts"] });
    qc.invalidateQueries({ queryKey: ["blog-posts"] });
  }

  async function togglePublish(p: Post) {
    const { error } = await supabase.from("blog_posts_legacy").update({
      published: !p.published,
      published_at: !p.published ? (p.published_at ?? new Date().toISOString()) : p.published_at,
    }).eq("id", p.id);
    if (error) return toast.error(error.message);
    toast.success(!p.published ? "Publicado" : "Despublicado");
    qc.invalidateQueries({ queryKey: ["admin-blog-posts"] });
    qc.invalidateQueries({ queryKey: ["blog-posts"] });
  }

  async function remove(p: Post) {
    if (!confirm(`Excluir "${p.title}"?`)) return;
    const { error } = await supabase.from("blog_posts_legacy").delete().eq("id", p.id);
    if (error) return toast.error(error.message);
    toast.success("Post excluído");
    qc.invalidateQueries({ queryKey: ["admin-blog-posts"] });
    qc.invalidateQueries({ queryKey: ["blog-posts"] });
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="font-display text-2xl font-bold">Blog</h1>
          <p className="text-sm text-muted-foreground">Crie, edite e publique artigos.</p>
        </div>
        <Button onClick={() => setEditing({ published: false, author_name: "Equipe AgendaAqui" })} className="gap-1">
          <Plus className="h-4 w-4" /> Novo post
        </Button>
      </div>

      <div className="mt-6 overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Título</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Publicado em</th>
              <th className="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr><td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">Carregando…</td></tr>
            ) : !posts?.length ? (
              <tr><td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">Nenhum post ainda.</td></tr>
            ) : posts.map((p) => (
              <tr key={p.id} className="border-t border-border">
                <td className="px-4 py-3">
                  <div className="font-medium truncate max-w-[420px]">{p.title}</div>
                  <div className="text-xs text-muted-foreground truncate max-w-[420px]">/{p.slug}</div>
                </td>
                <td className="px-4 py-3">
                  <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${p.published ? "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200" : "bg-muted text-muted-foreground"}`}>
                    {p.published ? "Publicado" : "Rascunho"}
                  </span>
                </td>
                <td className="px-4 py-3 text-muted-foreground">
                  {p.published_at ? new Date(p.published_at).toLocaleDateString("pt-BR") : "—"}
                </td>
                <td className="px-4 py-3">
                  <div className="flex justify-end gap-1">
                    {p.published && (
                      <a href={`/blog/${p.slug}`} target="_blank" rel="noreferrer">
                        <Button variant="ghost" size="icon" title="Ver"><ExternalLink className="h-4 w-4" /></Button>
                      </a>
                    )}
                    <Button variant="ghost" size="icon" title={p.published ? "Despublicar" : "Publicar"} onClick={() => togglePublish(p)}>
                      {p.published ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </Button>
                    <Button variant="ghost" size="icon" title="Editar" onClick={() => setEditing(p)}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" title="Excluir" onClick={() => remove(p)}><Trash2 className="h-4 w-4 text-destructive" /></Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Dialog open={!!editing} onOpenChange={(o) => !o && setEditing(null)}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editing?.id ? "Editar post" : "Novo post"}</DialogTitle>
          </DialogHeader>
          {editing && (
            <div className="space-y-4">
              <div>
                <Label>Título</Label>
                <Input value={editing.title ?? ""} onChange={(e) => setEditing({ ...editing, title: e.target.value, slug: editing.id ? editing.slug : slugify(e.target.value) })} />
              </div>
              <div>
                <Label>Slug</Label>
                <Input value={editing.slug ?? ""} onChange={(e) => setEditing({ ...editing, slug: slugify(e.target.value) })} />
              </div>
              <div>
                <Label>Resumo</Label>
                <Textarea rows={2} value={editing.excerpt ?? ""} onChange={(e) => setEditing({ ...editing, excerpt: e.target.value })} />
              </div>
              <div>
                <Label>Conteúdo (Markdown)</Label>
                <Textarea rows={12} value={editing.content ?? ""} onChange={(e) => setEditing({ ...editing, content: e.target.value })} />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label>URL da capa</Label>
                  <Input value={editing.cover_url ?? ""} onChange={(e) => setEditing({ ...editing, cover_url: e.target.value })} />
                </div>
                <div>
                  <Label>Autor</Label>
                  <Input value={editing.author_name ?? ""} onChange={(e) => setEditing({ ...editing, author_name: e.target.value })} />
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Switch checked={!!editing.published} onCheckedChange={(v) => setEditing({ ...editing, published: v })} />
                <Label className="!m-0">Publicado</Label>
              </div>
            </div>
          )}
          <DialogFooter>
            <Button variant="ghost" onClick={() => setEditing(null)}>Cancelar</Button>
            <Button onClick={() => editing && save(editing)}>Salvar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
