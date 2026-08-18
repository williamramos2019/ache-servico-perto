import { Link, useRouterState } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
import {
  BadgePercent,
  Briefcase,
  Building2,
  Copy,
  ExternalLink,
  MapPin,
  MessageCircle,
  Send,
  Ticket,
  Wifi,
  X,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  adsApi,
  requestsApi,
  whatsappApi,
  type AdCampaign,
  type Job,
  type Promotion,
  type Representative,
} from "@/lib/domain-api";
import { isCampaignTargeted, selectWeightedCampaign } from "@/lib/frontend-domain-helpers";
import { formatRoleParty, RepresentativeAvatar as FeatureAvatar, ROLE_LABEL } from "@/features/representatives";

export { LiveFeedCard, LiveFeedWidget } from "@/features/live-feed";

export function DataState({
  loading,
  error,
  empty,
  children,
}: {
  loading: boolean;
  error?: unknown;
  empty: boolean;
  children: ReactNode;
}) {
  if (loading) {
    return <div className="rounded-xl border bg-muted/30 p-10 text-center text-muted-foreground">Carregando…</div>;
  }
  if (error) {
    return (
      <div role="alert" className="rounded-xl border border-destructive/30 bg-destructive/5 p-8 text-center text-destructive">
        Não foi possível carregar estes dados. Tente novamente em instantes.
      </div>
    );
  }
  if (empty) {
    return <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">Nenhum resultado encontrado.</div>;
  }
  return <>{children}</>;
}

export function formatSalary(job: Job) {
  if (job.salary_min == null && job.salary_max == null) return null;
  const fmt = (value: number) =>
    value.toLocaleString("pt-BR", { style: "currency", currency: job.salary_currency || "BRL" });
  if (job.salary_min != null && job.salary_max != null) return `${fmt(job.salary_min)} – ${fmt(job.salary_max)}`;
  return fmt(job.salary_min ?? job.salary_max ?? 0);
}

export function JobCard({ job, premium = false }: { job: Job; premium?: boolean }) {
  const salary = formatSalary(job);
  return (
    <Link to="/empregos/$id" params={{ id: job.id }} className="group block">
      <article className={`h-full rounded-2xl border bg-card p-5 transition hover:-translate-y-0.5 hover:shadow-md ${premium ? "border-amber-400/50 bg-amber-50/30 dark:bg-amber-950/10" : ""}`}>
        <div className="flex items-start gap-3">
          <div className="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary/10 text-primary">
            {job.company_logo_url ? <img src={job.company_logo_url} alt="" className="h-full w-full object-cover" /> : <Building2 className="h-5 w-5" />}
          </div>
          <div className="min-w-0 flex-1">
            {premium && <Badge className="mb-2 bg-amber-500 text-white">Destaque</Badge>}
            <h3 className="line-clamp-2 font-display font-bold group-hover:text-primary">{job.title}</h3>
            <p className="mt-1 text-sm text-muted-foreground">{job.company_name || "Empresa não informada"}</p>
          </div>
        </div>
        <div className="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
          {(job.location_city || job.location_state) && <span><MapPin className="mr-1 inline h-3 w-3" />{[job.location_city, job.location_state].filter(Boolean).join(" · ")}</span>}
          {job.is_remote && <span className="text-emerald-600"><Wifi className="mr-1 inline h-3 w-3" />Remoto</span>}
          {job.employment_type && <span>{job.employment_type}</span>}
        </div>
        {salary && <p className="mt-3 text-sm font-semibold text-primary">{salary}</p>}
      </article>
    </Link>
  );
}

export function RepresentativeAvatar({ representative, className = "" }: { representative: Representative; className?: string }) {
  return (
    <FeatureAvatar
      name={representative.name}
      photoUrl={representative.photo_url}
      className={`h-14 w-14 text-sm ${className}`}
    />
  );
}

export function RepresentativeCard({ representative }: { representative: Representative }) {
  return (
    <Link to="/representantes/$id" params={{ id: representative.slug }} className="block">
      <article className="h-full rounded-xl border bg-card p-5 transition hover:shadow-md">
        <div className="flex gap-4">
          <RepresentativeAvatar representative={representative} />
          <div className="min-w-0">
            <h3 className="truncate font-semibold">{representative.name}</h3>
            <p className="text-sm capitalize text-muted-foreground">{ROLE_LABEL[representative.role] ?? formatRoleParty(representative.role, null)}</p>
            <div className="mt-2 flex gap-2">
              {representative.party && <Badge variant="secondary">{representative.party}</Badge>}
              {representative.city_name && <Badge variant="outline">{representative.city_name}</Badge>}
            </div>
          </div>
        </div>
        {representative.bio && <p className="mt-3 line-clamp-2 text-sm text-muted-foreground">{representative.bio}</p>}
      </article>
    </Link>
  );
}

export function WhatsAppSubscribeDialog({
  open,
  onOpenChange,
  defaultCity = "vespasiano",
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  defaultCity?: string;
}) {
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [city, setCity] = useState(defaultCity);
  const [pending, setPending] = useState(false);
  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    try {
      await whatsappApi.subscribe({ name, phone, city_slug: city, consent: true });
      toast.success("Inscrição confirmada.");
      onOpenChange(false);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Não foi possível concluir.");
    } finally {
      setPending(false);
    }
  }
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>Resumo semanal no WhatsApp</DialogTitle><DialogDescription>Receba atividades públicas da cidade. Você pode sair a qualquer momento.</DialogDescription></DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div><Label htmlFor="wa-name">Nome</Label><Input id="wa-name" value={name} onChange={(e) => setName(e.target.value)} minLength={2} maxLength={80} required /></div>
          <div><Label htmlFor="wa-phone">WhatsApp</Label><Input id="wa-phone" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="(31) 99999-9999" required /></div>
          <div><Label htmlFor="wa-city">Cidade</Label><select id="wa-city" value={city} onChange={(e) => setCity(e.target.value)} className="h-10 w-full rounded-md border bg-background px-3 text-sm"><option value="vespasiano">Vespasiano</option><option value="sao-jose-da-lapa">São José da Lapa</option></select></div>
          <Button type="submit" className="w-full" disabled={pending}><MessageCircle className="mr-2 h-4 w-4" />{pending ? "Inscrevendo…" : "Quero receber"}</Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function RequestDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const [subject, setSubject] = useState("");
  const [description, setDescription] = useState("");
  const [email, setEmail] = useState("");
  const [pending, setPending] = useState(false);
  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setPending(true);
    try {
      const result = await requestsApi.create({ category: "sugestao", subject, description, user_email: email || null, page_url: window.location.href });
      toast.success(`Solicitação ${result.request_number} enviada.`);
      onOpenChange(false);
      setSubject("");
      setDescription("");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Não foi possível enviar.");
    } finally {
      setPending(false);
    }
  }
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader><DialogTitle>Enviar solicitação</DialogTitle><DialogDescription>Conte o que você precisa. A equipe acompanhará pelo protocolo.</DialogDescription></DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div><Label htmlFor="request-subject">Assunto</Label><Input id="request-subject" value={subject} onChange={(e) => setSubject(e.target.value)} minLength={3} required /></div>
          <div><Label htmlFor="request-description">Descrição</Label><Textarea id="request-description" value={description} onChange={(e) => setDescription(e.target.value)} minLength={5} required /></div>
          <div><Label htmlFor="request-email">E-mail (opcional)</Label><Input id="request-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <Button className="w-full" disabled={pending}><Send className="mr-2 h-4 w-4" />{pending ? "Enviando…" : "Enviar"}</Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function PromotionCard({ item, coupon = false }: { item: Promotion; coupon?: boolean }) {
  const image = item.image_url || item.cover_image;
  async function copy() {
    if (!item.code) return;
    await navigator.clipboard.writeText(item.code);
    toast.success("Cupom copiado.");
  }
  return (
    <article className="overflow-hidden rounded-xl border bg-card">
      {image && <img src={image} alt="" className="aspect-video w-full object-cover" />}
      <div className="p-4">
        <div className="flex items-start justify-between gap-2">
          <h3 className="font-display text-lg font-bold">{item.title}</h3>
          {item.discount_percent != null && <Badge>-{item.discount_percent}%</Badge>}
        </div>
        {item.description && <p className="mt-1 line-clamp-3 text-sm text-muted-foreground">{item.description}</p>}
        {item.company_name && <p className="mt-2 text-sm font-medium text-primary">{item.company_name}</p>}
        {coupon && item.code && <button onClick={copy} className="mt-3 flex w-full items-center justify-between rounded-lg border-2 border-dashed border-primary/40 bg-primary/5 px-3 py-2 font-mono font-bold text-primary"><span>{item.code}</span><Copy className="h-4 w-4" /></button>}
        {item.link_url && <a href={item.link_url} target="_blank" rel="noreferrer" className="mt-3 inline-flex items-center text-sm font-medium text-primary hover:underline">Acessar oferta <ExternalLink className="ml-1 h-3 w-3" /></a>}
        {!coupon && !image && <BadgePercent className="mt-3 h-8 w-8 text-primary/50" />}
        {coupon && !item.code && <Ticket className="mt-3 h-8 w-8 text-primary/50" />}
      </div>
    </article>
  );
}

export function AdSlot({ city }: { city?: string }) {
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const { data } = useQuery({ queryKey: ["ads", city], queryFn: () => adsApi.list(city), staleTime: 60_000 });
  const [campaign, setCampaign] = useState<AdCampaign | null>(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const eligible = (data?.rows ?? []).filter((row) => isCampaignTargeted(row, pathname));
    const next = selectWeightedCampaign(eligible);
    setCampaign(next);
    setVisible(false);
  }, [data, pathname]);
  useEffect(() => {
    if (!campaign) return;
    let delayReady = campaign.delay_seconds <= 0;
    let scrollReady = campaign.scroll_trigger_percent <= 0;
    let shown = false;
    let displayTimer: number | undefined;
    const showWhenReady = () => {
      if (shown || !delayReady || !scrollReady) return;
      shown = true;
      setVisible(true);
      void adsApi.track(campaign.id, "impression").catch(() => undefined);
      displayTimer = window.setTimeout(
        () => setVisible(false),
        Math.max(campaign.display_seconds, 3) * 1000,
      );
    };
    const delayTimer = window.setTimeout(() => {
      delayReady = true;
      showWhenReady();
    }, Math.max(campaign.delay_seconds, 0) * 1000);
    const onScroll = () => {
      const available = document.documentElement.scrollHeight - window.innerHeight;
      const percent = available <= 0 ? 100 : (window.scrollY / available) * 100;
      if (percent >= campaign.scroll_trigger_percent) {
        scrollReady = true;
        window.removeEventListener("scroll", onScroll);
        showWhenReady();
      }
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    showWhenReady();
    return () => {
      window.clearTimeout(delayTimer);
      if (displayTimer !== undefined) window.clearTimeout(displayTimer);
      window.removeEventListener("scroll", onScroll);
    };
  }, [campaign]);
  if (!campaign || !visible) return null;
  const placement =
    campaign.placement === "center"
      ? "fixed left-1/2 top-1/2 z-50 w-[min(92vw,480px)] -translate-x-1/2 -translate-y-1/2"
      : campaign.placement === "bottom-center"
        ? "fixed bottom-4 left-1/2 z-50 w-[min(92vw,640px)] -translate-x-1/2"
        : "fixed bottom-4 right-4 z-50 w-[min(92vw,380px)]";
  return (
    <aside className={`${placement} overflow-hidden rounded-xl border bg-card shadow-2xl`}>
      <div className="flex items-center justify-between px-3 py-1">
        <span className="text-[10px] uppercase tracking-wider text-muted-foreground">Publicidade</span>
        <button type="button" onClick={() => setVisible(false)} aria-label="Fechar anúncio" className="rounded p-1 hover:bg-muted"><X className="h-3 w-3" /></button>
      </div>
      <a href={campaign.link_url} target="_blank" rel="noreferrer sponsored" onClick={() => void adsApi.track(campaign.id, "click")}>
        <img src={campaign.image_url} alt={campaign.name} className="max-h-72 w-full object-cover" />
      </a>
    </aside>
  );
}

export function RequestButton() {
  const [open, setOpen] = useState(false);
  return <><Button variant="outline" onClick={() => setOpen(true)}><Briefcase className="mr-2 h-4 w-4" />Fazer solicitação</Button><RequestDialog open={open} onOpenChange={setOpen} /></>;
}
