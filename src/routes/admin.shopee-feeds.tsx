import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Download, ExternalLink, ShoppingBag } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { shopeeApi } from "@/lib/domain-api";

export const Route = createFileRoute("/admin/shopee-feeds")({
  head: () => ({
    meta: [
      { title: "Feeds Shopee Afiliados — Admin" },
      { name: "robots", content: "noindex" },
    ],
  }),
  component: ShopeeFeedsPage,
});

function ShopeeFeedsPage() {
  const feeds = useQuery({
    queryKey: ["admin-shopee-feeds"],
    queryFn: shopeeApi.feeds,
  });

  function copyUrl(url: string) {
    void navigator.clipboard.writeText(url).then(
      () => toast.success("Link copiado"),
      () => toast.error("Não foi possível copiar"),
    );
  }

  const rows = feeds.data?.feeds ?? [];

  return (
    <div className="space-y-6">
      <header>
        <h1 className="flex items-center gap-2 text-2xl font-bold">
          <ShoppingBag className="h-6 w-6 text-orange-500" />
          Feeds Shopee Afiliados
        </h1>
        <p className="text-sm text-muted-foreground">
          Os links ficam no servidor (`SHOPEE_FEED_1_URL`, `SHOPEE_FEED_2_URL`). Depois de baixar o CSV, importe com
          `php tools/shopee-import.php --csv=arquivo.csv`.
        </p>
      </header>

      {feeds.isLoading ? (
        <p className="text-sm text-muted-foreground">Carregando feeds…</p>
      ) : rows.length === 0 ? (
        <div className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
          Nenhum feed configurado. Defina as variáveis de ambiente no HostGator e recarregue esta página.
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {rows.map((feed) => (
            <Card key={feed.id} className="border-orange-500/20">
              <CardHeader>
                <CardTitle className="text-base">{feed.name}</CardTitle>
                <p className="text-xs text-muted-foreground">{feed.description}</p>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="break-all rounded-md border border-border bg-muted/40 p-2 font-mono text-[11px] text-muted-foreground">
                  {feed.url}
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button asChild size="sm" className="bg-orange-500 text-white hover:bg-orange-600">
                    <a href={feed.url} download target="_blank" rel="noopener noreferrer">
                      <Download className="mr-1 h-4 w-4" /> Baixar CSV
                    </a>
                  </Button>
                  <Button asChild size="sm" variant="outline">
                    <a href={feed.url} target="_blank" rel="noopener noreferrer">
                      <ExternalLink className="mr-1 h-4 w-4" /> Abrir em nova aba
                    </a>
                  </Button>
                  <Button size="sm" variant="ghost" onClick={() => copyUrl(feed.url)}>
                    Copiar link
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
