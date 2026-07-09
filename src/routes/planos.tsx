import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { z } from "zod";
import { toast } from "sonner";
import { Check, Star, Crown, Zap, Loader2 } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { supabase } from "@/integrations/supabase/client";

export const Route = createFileRoute("/planos")({
  head: () => ({
    meta: [
      { title: "Anuncie sua empresa — AgendaAqui" },
      { name: "description", content: "Coloque seu negócio na frente de quem já procura o seu serviço em Vespasiano e São José da Lapa. Cadastro grátis, sem cartão." },
      { property: "og:title", content: "Apareça no app da cidade — AgendaAqui" },
      { property: "og:url", content: "/planos" },
    ],
    links: [{ rel: "canonical", href: "/planos" }],
  }),
  component: PlanosPage,
});

const PLANS = [
  {
    id: "basico",
    name: "Começar",
    price: "Grátis",
    subtitle: "Para começar a receber clientes hoje",
    cta: "Criar meu perfil grátis",
    icon: Star,
    accent: false,
    features: [
      "Perfil completo no app da cidade",
      "Fotos, contato e localização no mapa",
      "Receba avaliações de clientes reais",
      "WhatsApp e ligação com 1 toque",
    ],
  },
  {
    id: "profissional",
    name: "Profissional",
    price: "R$ 49/mês",
    subtitle: "Para quem quer aparecer primeiro",
    cta: "Quero aparecer primeiro",
    icon: Zap,
    accent: true,
    features: [
      "Tudo do plano Grátis",
      "Selo Verificado (dobra a taxa de contato)",
      "Prioridade nas buscas da cidade",
      "Galeria de fotos ilimitada",
      "Estatísticas de visitas e cliques",
    ],
  },
  {
    id: "premium",
    name: "Premium",
    price: "R$ 149/mês",
    subtitle: "Para dominar a categoria na sua cidade",
    cta: "Falar com consultor",
    icon: Crown,
    accent: false,
    features: [
      "Tudo do Profissional",
      "Selo Premium em todas as listas",
      "Destaque fixo na home",
      "Consultor dedicado por WhatsApp",
      "Campanhas patrocinadas de divulgação",
    ],
  },
] as const;

const schema = z.object({
  company_name: z.string().trim().min(1).max(200),
  contact_name: z.string().trim().min(1).max(120),
  email: z.string().trim().email().max(255),
  phone: z.string().trim().max(40).optional(),
  city: z.string().trim().max(120).optional(),
  message: z.string().trim().max(1000).optional(),
});

function PlanosPage() {
  const [open, setOpen] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({ company_name: "", contact_name: "", email: "", phone: "", city: "", message: "" });

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!open) return;
    const parsed = schema.safeParse(form);
    if (!parsed.success) { toast.error(parsed.error.issues[0]?.message ?? "Dados inválidos"); return; }
    setLoading(true);
    const { error } = await supabase.from("leads_planos").insert({
      ...parsed.data,
      plan: open,
    });
    setLoading(false);
    if (error) { toast.error("Não conseguimos enviar agora. Tente de novo em instantes."); return; }
    toast.success("Recebido! Nossa equipe entra em contato em até 24h.");
    setOpen(null);
    setForm({ company_name: "", contact_name: "", email: "", phone: "", city: "", message: "" });
  }

  return (
    <SiteLayout>
      <section className="border-b border-border bg-gradient-to-br from-primary to-primary-dark text-primary-foreground">
        <div className="container mx-auto px-4 py-16 text-center">
          <h1 className="font-display text-4xl font-extrabold md:text-5xl">Anuncie sua empresa</h1>
          <p className="mx-auto mt-3 max-w-2xl text-white/90">
            Apareça para milhares de clientes em Minas Gerais. Escolha o plano que melhor se encaixa no seu negócio.
          </p>
        </div>
      </section>

      <section className="container mx-auto px-4 py-12">
        <div className="grid gap-6 md:grid-cols-3">
          {PLANS.map((p) => {
            const Icon = p.icon;
            return (
              <div
                key={p.id}
                className={`flex flex-col rounded-2xl border bg-card p-6 ${p.accent ? "border-accent shadow-lg ring-2 ring-accent/30" : "border-border"}`}
              >
                {p.accent && (
                  <div className="mb-3 inline-flex w-fit rounded-full bg-accent px-3 py-1 text-xs font-semibold text-accent-foreground">
                    Mais popular
                  </div>
                )}
                <div className="flex items-center gap-3">
                  <Icon className={`h-7 w-7 ${p.accent ? "text-accent" : "text-primary"}`} />
                  <h3 className="font-display text-xl font-bold">{p.name}</h3>
                </div>
                <div className="mt-4 text-3xl font-extrabold">{p.price}</div>
                <ul className="mt-6 flex-1 space-y-2 text-sm">
                  {p.features.map((f) => (
                    <li key={f} className="flex items-start gap-2">
                      <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" /> {f}
                    </li>
                  ))}
                </ul>
                <Button
                  className={`mt-6 ${p.accent ? "bg-accent text-accent-foreground hover:bg-accent/90" : ""}`}
                  variant={p.accent ? "default" : "outline"}
                  onClick={() => setOpen(p.id)}
                >
                  Quero este plano
                </Button>
              </div>
            );
          })}
        </div>

        <p className="mt-8 text-center text-sm text-muted-foreground">
          Dúvidas? Fale com nossa equipe pela página de contato.
        </p>
      </section>

      <Dialog open={!!open} onOpenChange={(o) => !o && setOpen(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Solicitar plano {open && PLANS.find((p) => p.id === open)?.name}</DialogTitle>
            <DialogDescription>Preencha seus dados e entraremos em contato em até 24h.</DialogDescription>
          </DialogHeader>
          <form onSubmit={submit} className="space-y-3">
            <div>
              <Label htmlFor="company_name">Nome da empresa *</Label>
              <Input id="company_name" required value={form.company_name} onChange={(e) => setForm({ ...form, company_name: e.target.value })} maxLength={200} />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <Label htmlFor="contact_name">Seu nome *</Label>
                <Input id="contact_name" required value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} maxLength={120} />
              </div>
              <div>
                <Label htmlFor="email">E-mail *</Label>
                <Input id="email" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} maxLength={255} />
              </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <Label htmlFor="phone">Telefone</Label>
                <Input id="phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} maxLength={40} />
              </div>
              <div>
                <Label htmlFor="city">Cidade</Label>
                <Input id="city" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} maxLength={120} />
              </div>
            </div>
            <div>
              <Label htmlFor="message">Mensagem (opcional)</Label>
              <Textarea id="message" value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} maxLength={1000} />
            </div>
            <Button type="submit" disabled={loading} className="w-full">
              {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null} Enviar solicitação
            </Button>
          </form>
        </DialogContent>
      </Dialog>
    </SiteLayout>
  );
}
