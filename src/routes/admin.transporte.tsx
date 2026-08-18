import { useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  adminCreateTransportLine,
  adminDeleteSchedule,
  adminDeleteStop,
  adminDeleteTransportLine,
  adminSaveSchedule,
  adminSaveStop,
  adminUpdateTransportLine,
  adminUpsertTransportSource,
  fetchTransportLines,
  fetchTransportSchedules,
  fetchTransportStops,
  TRANSPORT_STATUS_LABEL,
  TRANSPORT_TYPE_LABEL,
} from "@/lib/transport";
import { citiesQueryOptions } from "@/lib/queries";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export const Route = createFileRoute("/admin/transporte")({
  head: () => ({ meta: [{ title: "Transporte — Admin" }, { name: "robots", content: "noindex" }] }),
  component: AdminTransportePage,
});

function AdminTransportePage() {
  const qc = useQueryClient();
  const list = useQuery({
    queryKey: ["admin-transport-lines"],
    queryFn: () => fetchTransportLines({ limit: 50, page: 1 }),
  });
  const cities = useQuery(citiesQueryOptions);
  const lines = list.data?.lines ?? [];
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const selected = lines.find((l) => l.id === selectedId) ?? null;
  const schedulesQ = useQuery({
    queryKey: ["admin-transport-schedules", selectedId],
    queryFn: () => fetchTransportSchedules(selectedId!),
    enabled: !!selectedId,
  });
  const stopsQ = useQuery({
    queryKey: ["admin-transport-stops", selectedId],
    queryFn: () => fetchTransportStops(selectedId!),
    enabled: !!selectedId,
  });

  async function refresh() {
    await qc.invalidateQueries({ queryKey: ["admin-transport-lines"] });
    await qc.invalidateQueries({ queryKey: ["admin-transport-schedules"] });
    await qc.invalidateQueries({ queryKey: ["admin-transport-stops"] });
    await qc.invalidateQueries({ queryKey: ["transport-lines"] });
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-display text-2xl font-bold">Transporte público</h1>
        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
          Cadastro manual de linhas. Importação em lote só no servidor:{" "}
          <code className="rounded bg-muted px-1">php tools/transport-import.php</code>. Não há importação pela web.
        </p>
      </div>

      <CreateLineForm cityOptions={cities.data ?? []} onCreated={refresh} />

      <div className="overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Código</th>
              <th className="px-4 py-3">Nome</th>
              <th className="px-4 py-3">Cidade</th>
              <th className="px-4 py-3">Tipo</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Fonte</th>
            </tr>
          </thead>
          <tbody>
            {list.isLoading ? (
              <tr>
                <td className="px-4 py-6 text-muted-foreground" colSpan={6}>
                  Carregando…
                </td>
              </tr>
            ) : lines.length === 0 ? (
              <tr>
                <td className="px-4 py-6 text-muted-foreground" colSpan={6}>
                  Nenhuma linha cadastrada. O catálogo público permanece vazio até um import oficial ou cadastro aqui.
                </td>
              </tr>
            ) : (
              lines.map((l) => (
                <tr
                  key={l.id}
                  className={`cursor-pointer border-t border-border ${selectedId === l.id ? "bg-muted/40" : ""}`}
                  onClick={() => setSelectedId(l.id)}
                >
                  <td className="px-4 py-3 font-medium">{l.code}</td>
                  <td className="px-4 py-3">
                    <Link to="/transporte/$slug" params={{ slug: l.slug }} className="text-primary hover:underline" onClick={(e) => e.stopPropagation()}>
                      {l.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{l.city_name ?? "—"}</td>
                  <td className="px-4 py-3">{TRANSPORT_TYPE_LABEL[l.type] ?? l.type}</td>
                  <td className="px-4 py-3">{TRANSPORT_STATUS_LABEL[l.status]?.label ?? l.status}</td>
                  <td className="px-4 py-3 text-muted-foreground">{l.source?.name ?? "—"}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {selected ? (
        <EditLinePanel
          key={selected.id}
          line={selected}
          cityOptions={cities.data ?? []}
          schedules={schedulesQ.data ?? []}
          stops={stopsQ.data ?? []}
          onChange={refresh}
          onDelete={async () => {
            if (!confirm("Excluir esta linha e os horários/pontos ligados a ela?")) return;
            await adminDeleteTransportLine(selected.id);
            setSelectedId(null);
            toast.success("Linha excluída");
            await refresh();
          }}
        />
      ) : null}
    </div>
  );
}

function CreateLineForm({
  cityOptions,
  onCreated,
}: {
  cityOptions: Array<{ id: string; name: string }>;
  onCreated: () => Promise<void>;
}) {
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [type, setType] = useState("municipal");
  const [cityId, setCityId] = useState("");

  return (
    <form
      className="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
      onSubmit={async (e) => {
        e.preventDefault();
        try {
          await adminCreateTransportLine({
            code,
            name,
            type,
            city_id: cityId || null,
            status: "unknown",
          });
          setCode("");
          setName("");
          toast.success("Linha criada");
          await onCreated();
        } catch (err) {
          toast.error((err as Error).message);
        }
      }}
    >
      <div>
        <Label htmlFor="new-code">Código</Label>
        <Input id="new-code" value={code} onChange={(e) => setCode(e.target.value)} required />
      </div>
      <div>
        <Label htmlFor="new-name">Nome</Label>
        <Input id="new-name" value={name} onChange={(e) => setName(e.target.value)} required />
      </div>
      <div>
        <Label htmlFor="new-type">Tipo</Label>
        <select id="new-type" className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" value={type} onChange={(e) => setType(e.target.value)}>
          {Object.entries(TRANSPORT_TYPE_LABEL).map(([k, v]) => (
            <option key={k} value={k}>
              {v}
            </option>
          ))}
        </select>
      </div>
      <div>
        <Label htmlFor="new-city">Cidade</Label>
        <select id="new-city" className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" value={cityId} onChange={(e) => setCityId(e.target.value)}>
          <option value="">Não informada</option>
          {cityOptions.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </div>
      <div className="sm:col-span-2 lg:col-span-4">
        <Button type="submit">Criar linha</Button>
      </div>
    </form>
  );
}

function EditLinePanel({
  line,
  cityOptions,
  schedules,
  stops,
  onChange,
  onDelete,
}: {
  line: {
    id: string;
    code: string;
    name: string;
    type: string;
    status: string;
    fare: string | null;
    operator_name: string | null;
    city_id: string | null;
  };
  cityOptions: Array<{ id: string; name: string }>;
  schedules: Array<{ id?: string; direction: string; day_type: string; departure_time: string }>;
  stops: Array<{ id?: string; sequence: number; name: string; address: string | null; direction: string }>;
  onChange: () => Promise<void>;
  onDelete: () => Promise<void>;
}) {
  const [name, setName] = useState(line.name);
  const [status, setStatus] = useState(line.status);
  const [type, setType] = useState(line.type);
  const [fare, setFare] = useState(line.fare ?? "");
  const [operator, setOperator] = useState(line.operator_name ?? "");
  const [cityId, setCityId] = useState(line.city_id ?? "");
  const [sourceName, setSourceName] = useState("");
  const [sourceUrl, setSourceUrl] = useState("");
  const [time, setTime] = useState("06:00");
  const [day, setDay] = useState("weekday");
  const [dir, setDir] = useState("ida");
  const [stopName, setStopName] = useState("");
  const [stopSeq, setStopSeq] = useState("1");

  return (
    <section className="space-y-4 rounded-xl border border-border bg-card p-4">
      <h2 className="font-display text-lg font-semibold">
        Editar {line.code}
      </h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <div>
          <Label>Nome</Label>
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div>
          <Label>Tarifa</Label>
          <Input value={fare} onChange={(e) => setFare(e.target.value)} placeholder="Deixe vazio se a fonte não informar" />
        </div>
        <div>
          <Label>Operador</Label>
          <Input value={operator} onChange={(e) => setOperator(e.target.value)} />
        </div>
        <div>
          <Label>Status</Label>
          <select className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
            {Object.entries(TRANSPORT_STATUS_LABEL).map(([k, v]) => (
              <option key={k} value={k}>
                {v.label}
              </option>
            ))}
          </select>
        </div>
        <div>
          <Label>Tipo</Label>
          <select className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" value={type} onChange={(e) => setType(e.target.value)}>
            {Object.entries(TRANSPORT_TYPE_LABEL).map(([k, v]) => (
              <option key={k} value={k}>
                {v}
              </option>
            ))}
          </select>
        </div>
        <div>
          <Label>Cidade</Label>
          <select className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" value={cityId} onChange={(e) => setCityId(e.target.value)}>
            <option value="">Não informada</option>
            {cityOptions.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          onClick={async () => {
            try {
              await adminUpdateTransportLine(line.id, {
                name,
                status,
                type,
                fare: fare || null,
                operator_name: operator || null,
                city_id: cityId || null,
              });
              toast.success("Linha atualizada");
              await onChange();
            } catch (err) {
              toast.error((err as Error).message);
            }
          }}
        >
          Salvar linha
        </Button>
        <Button type="button" variant="destructive" onClick={() => void onDelete()}>
          Excluir linha
        </Button>
      </div>

      <div className="grid gap-2 sm:grid-cols-3">
        <Input placeholder="Nome da fonte" value={sourceName} onChange={(e) => setSourceName(e.target.value)} />
        <Input placeholder="https://fonte.gov.br" value={sourceUrl} onChange={(e) => setSourceUrl(e.target.value)} />
        <Button
          type="button"
          variant="outline"
          onClick={async () => {
            try {
              await adminUpsertTransportSource({ line_id: line.id, name: sourceName, url: sourceUrl, type: "other" });
              toast.success("Fonte registrada");
              await onChange();
            } catch (err) {
              toast.error((err as Error).message);
            }
          }}
        >
          Atualizar fonte
        </Button>
      </div>

      <h3 className="font-semibold">Horários</h3>
      <ul className="space-y-1 text-sm">
        {schedules.map((s) => (
          <li key={s.id ?? s.departure_time} className="flex items-center justify-between gap-2">
            <span>
              {s.direction} · {s.day_type} · {s.departure_time}
            </span>
            {s.id ? (
              <Button
                size="sm"
                variant="ghost"
                onClick={async () => {
                  await adminDeleteSchedule(s.id!);
                  await onChange();
                }}
              >
                Remover
              </Button>
            ) : null}
          </li>
        ))}
      </ul>
      <div className="flex flex-wrap gap-2">
        <Input className="w-28" value={time} onChange={(e) => setTime(e.target.value)} aria-label="Horário" />
        <select className="h-10 rounded-md border border-input bg-background px-2 text-sm" value={day} onChange={(e) => setDay(e.target.value)}>
          <option value="weekday">Úteis</option>
          <option value="saturday">Sábado</option>
          <option value="sunday">Domingo</option>
          <option value="holiday">Feriado</option>
        </select>
        <select className="h-10 rounded-md border border-input bg-background px-2 text-sm" value={dir} onChange={(e) => setDir(e.target.value)}>
          <option value="ida">Ida</option>
          <option value="volta">Volta</option>
        </select>
        <Button
          type="button"
          variant="outline"
          onClick={async () => {
            try {
              await adminSaveSchedule({ line_id: line.id, departure_time: time, day_type: day, direction: dir });
              toast.success("Horário adicionado");
              await onChange();
            } catch (err) {
              toast.error((err as Error).message);
            }
          }}
        >
          Adicionar horário
        </Button>
      </div>

      <h3 className="font-semibold">Pontos</h3>
      <ul className="space-y-1 text-sm">
        {stops.map((s) => (
          <li key={s.id ?? `${s.sequence}-${s.name}`} className="flex items-center justify-between gap-2">
            <span>
              {s.sequence}. {s.name} ({s.direction})
            </span>
            {s.id ? (
              <Button
                size="sm"
                variant="ghost"
                onClick={async () => {
                  await adminDeleteStop(s.id!);
                  await onChange();
                }}
              >
                Remover
              </Button>
            ) : null}
          </li>
        ))}
      </ul>
      <div className="flex flex-wrap gap-2">
        <Input className="w-20" value={stopSeq} onChange={(e) => setStopSeq(e.target.value)} aria-label="Sequência" />
        <Input placeholder="Nome do ponto" value={stopName} onChange={(e) => setStopName(e.target.value)} />
        <Button
          type="button"
          variant="outline"
          onClick={async () => {
            try {
              await adminSaveStop({ line_id: line.id, name: stopName, sequence: Number(stopSeq) || 0, direction: dir });
              setStopName("");
              toast.success("Ponto adicionado");
              await onChange();
            } catch (err) {
              toast.error((err as Error).message);
            }
          }}
        >
          Adicionar ponto
        </Button>
      </div>
    </section>
  );
}
