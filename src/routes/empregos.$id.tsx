import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Building2, Check, ExternalLink, Mail, MapPin, MessageCircle, Wifi } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState, formatSalary } from "@/components/site/DomainWidgets";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { jobsApi } from "@/lib/domain-api";
import { DEFAULT_SEARCH } from "@/features/jobs";

export const Route = createFileRoute("/empregos/$id")({
  head: () => ({ meta: [{ title: "Detalhes da vaga — AgendaAqui" }] }),
  component: JobDetailPage,
});

function JobDetailPage() {
  const { id } = Route.useParams();
  const query = useQuery({ queryKey: ["job", id], queryFn: () => jobsApi.show(id) });
  const job = query.data;
  return (
    <SiteLayout>
      <main className="container mx-auto max-w-5xl px-4 py-10">
        <Link to="/empregos" search={DEFAULT_SEARCH} className="text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="mr-1 inline h-4 w-4" />Todas as vagas</Link>
        <div className="mt-6">
          <DataState loading={query.isLoading} error={query.error} empty={!job}>
            {job && (
              <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                <article className="space-y-5">
                  <section className={`rounded-2xl border bg-card p-6 ${job.is_premium ? "border-amber-400/50" : ""}`}>
                    {job.is_premium && <Badge className="mb-3 bg-amber-500 text-white">Vaga em destaque</Badge>}
                    <h1 className="font-display text-3xl font-extrabold">{job.title}</h1>
                    <p className="mt-2 text-lg text-muted-foreground"><Building2 className="mr-2 inline h-5 w-5" />{job.company_name || "Empresa não informada"}</p>
                    <div className="mt-4 flex flex-wrap gap-3 text-sm text-muted-foreground">
                      {(job.location_city || job.location_state) && <span><MapPin className="mr-1 inline h-4 w-4" />{[job.location_city, job.location_state].filter(Boolean).join(" · ")}</span>}
                      {job.is_remote && <span className="text-emerald-600"><Wifi className="mr-1 inline h-4 w-4" />Remoto</span>}
                      {job.employment_type && <span>{job.employment_type}</span>}
                      {formatSalary(job) && <strong className="text-primary">{formatSalary(job)}</strong>}
                    </div>
                  </section>
                  {job.description && <Section title="Sobre a vaga"><p className="whitespace-pre-wrap text-sm leading-7">{job.description}</p></Section>}
                  <BulletSection title="Responsabilidades" items={job.responsibilities} />
                  <BulletSection title="Requisitos" items={job.requirements} />
                  <BulletSection title="Diferenciais" items={job.nice_to_have} />
                  <BulletSection title="Benefícios" items={job.benefits} />
                  {job.company_culture && <Section title={`Sobre ${job.company_name || "a empresa"}`}><p className="whitespace-pre-wrap text-sm leading-7">{job.company_culture}</p></Section>}
                </article>
                <aside className="lg:sticky lg:top-24 lg:self-start">
                  <div className="rounded-2xl border bg-card p-5">
                    <h2 className="font-semibold">Candidatar-se</h2>
                    <div className="mt-4 space-y-2">
                      {job.apply_url && <a href={job.apply_url} target="_blank" rel="noreferrer"><Button className="w-full">Abrir candidatura <ExternalLink className="ml-2 h-4 w-4" /></Button></a>}
                      {job.apply_email && <a href={`mailto:${job.apply_email}?subject=${encodeURIComponent(`Candidatura: ${job.title}`)}`}><Button variant="outline" className="w-full"><Mail className="mr-2 h-4 w-4" />Enviar e-mail</Button></a>}
                      {job.apply_whatsapp && <a href={`https://wa.me/${job.apply_whatsapp.replace(/\D/g, "")}`} target="_blank" rel="noreferrer"><Button variant="outline" className="w-full"><MessageCircle className="mr-2 h-4 w-4" />WhatsApp</Button></a>}
                    </div>
                    {!job.apply_url && !job.apply_email && !job.apply_whatsapp && <p className="mt-3 text-sm text-muted-foreground">O canal de candidatura não foi informado.</p>}
                    {job.application_deadline && <p className="mt-4 text-xs text-muted-foreground">Prazo: {new Date(job.application_deadline).toLocaleDateString("pt-BR")}</p>}
                    {job.source_name && <p className="mt-2 text-xs text-muted-foreground">Fonte: {job.source_name}</p>}
                  </div>
                </aside>
              </div>
            )}
          </DataState>
        </div>
      </main>
    </SiteLayout>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="rounded-2xl border bg-card p-6"><h2 className="font-display text-xl font-bold">{title}</h2><div className="mt-3">{children}</div></section>;
}

function BulletSection({ title, items }: { title: string; items?: string[] }) {
  if (!items?.length) return null;
  return <Section title={title}><ul className="space-y-2">{items.map((item) => <li key={item} className="flex gap-2 text-sm"><Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />{item}</li>)}</ul></Section>;
}
