import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { ExternalLink, Mail, Phone } from "lucide-react";
import { SiteLayout } from "@/components/site/SiteLayout";
import { DataState, RepresentativeAvatar } from "@/components/site/DomainWidgets";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { KIND_META, ROLE_LABEL, STATUS_LABEL, formatRoleParty } from "@/features/representatives";
import { representativesApi } from "@/lib/domain-api";

export const Route = createFileRoute("/representantes/$id")({
  head: () => ({ meta: [{ title: "Perfil do representante — AgendaAqui" }] }),
  component: RepresentativePage,
});

function RepresentativePage() {
  const { id } = Route.useParams();
  const query = useQuery({ queryKey: ["representative", id], queryFn: () => representativesApi.show(id) });
  const rep = query.data;
  const attendance = rep?.attendance ?? [];
  const present = attendance.filter((row) => row.present).length;
  const rate = attendance.length ? Math.round((present / attendance.length) * 100) : 0;
  return (
    <SiteLayout>
      <main className="container mx-auto max-w-4xl px-4 py-10">
        <Link to="/representantes" className="text-sm text-muted-foreground hover:text-foreground">← Representantes</Link>
        <div className="mt-6">
          <DataState loading={query.isLoading} error={query.error} empty={!rep}>
            {rep && <>
              <section className="flex flex-col gap-5 rounded-2xl border bg-card p-6 sm:flex-row">
                <RepresentativeAvatar representative={rep} className="h-24 w-24 text-xl" />
                <div><h1 className="font-display text-3xl font-extrabold">{rep.name}</h1><div className="mt-2 flex gap-2"><Badge className="capitalize">{ROLE_LABEL[rep.role] ?? formatRoleParty(rep.role, null)}</Badge>{rep.party && <Badge variant="secondary">{rep.party}</Badge>}</div>{rep.bio && <p className="mt-4 text-muted-foreground">{rep.bio}</p>}<div className="mt-4 flex flex-wrap gap-4 text-sm">{rep.email && <a href={`mailto:${rep.email}`}><Mail className="mr-1 inline h-4 w-4" />{rep.email}</a>}{rep.phone && <a href={`tel:${rep.phone}`}><Phone className="mr-1 inline h-4 w-4" />{rep.phone}</a>}</div></div>
              </section>
              <Tabs defaultValue="activities" className="mt-6">
                <TabsList><TabsTrigger value="activities">Atividades ({rep.activities?.length ?? 0})</TabsTrigger><TabsTrigger value="attendance">Assiduidade</TabsTrigger></TabsList>
                <TabsContent value="activities" className="space-y-3 pt-4">
                  {!rep.activities?.length ? <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">Ainda não há atividades registradas.</p> : rep.activities.map((activity) => <article key={activity.id} className="rounded-xl border bg-card p-4"><div className="flex flex-wrap gap-2"><Badge variant="secondary">{KIND_META[activity.kind]?.label ?? activity.kind.replaceAll("_", " ")}</Badge>{activity.status && <Badge variant="outline">{STATUS_LABEL[activity.status] ?? activity.status.replaceAll("_", " ")}</Badge>}<span className="ml-auto text-xs text-muted-foreground">{new Date(activity.occurred_at).toLocaleDateString("pt-BR")}</span></div><h2 className="mt-2 font-semibold">{activity.title}</h2>{activity.description && <p className="mt-1 text-sm text-muted-foreground">{activity.description}</p>}{activity.source_url && <a href={activity.source_url} target="_blank" rel="noreferrer" className="mt-2 inline-flex text-sm text-primary">Fonte oficial <ExternalLink className="ml-1 h-3 w-3" /></a>}</article>)}
                </TabsContent>
                <TabsContent value="attendance" className="pt-4"><div className="rounded-xl border bg-card p-6"><p className="text-4xl font-bold text-primary">{rate}%</p><p className="mt-1 text-sm text-muted-foreground">{present} presenças em {attendance.length} sessões registradas.</p></div></TabsContent>
              </Tabs>
            </>}
          </DataState>
        </div>
      </main>
    </SiteLayout>
  );
}
