import { z } from "zod";
import { phpGet, phpPost } from "@/lib/php-api";
import { requireAdmin } from "@/lib/spa-auth";

const TypeEnum = z.enum([
  "erro",
  "bug",
  "info_incorreta",
  "empresa",
  "evento",
  "noticia",
  "layout",
  "lentidao",
  "funcionalidade",
  "sugestao",
  "outro",
]);
const StatusEnum = z.enum([
  "novo",
  "em_analise",
  "reproduzido",
  "em_desenvolvimento",
  "corrigido",
  "publicado",
  "fechado",
]);
const PriorityEnum = z.enum(["baixa", "media", "alta", "critica"]);

const CreateSchema = z.object({
  type: TypeEnum,
  description: z.string().trim().min(3).max(5000),
  page_url: z.string().max(2000).nullish(),
  page_title: z.string().max(300).nullish(),
  city_id: z.string().uuid().nullish(),
  device: z.record(z.string(), z.unknown()).default({}),
  console_logs: z.array(z.record(z.string(), z.unknown())).max(80).default([]),
  network_logs: z.array(z.record(z.string(), z.unknown())).max(60).default([]),
  screenshot_url: z.string().max(2000).nullish(),
  video_url: z.string().max(2000).nullish(),
  extra: z.record(z.string(), z.unknown()).default({}),
  user_name: z.string().max(120).nullish(),
  user_email: z.string().email().max(320).nullish(),
});

function fingerprintOf(input: {
  type: string;
  page_url?: string | null;
  description: string;
  console_logs: unknown[];
}) {
  const firstErr = (input.console_logs as Array<{ message?: string }> | undefined)?.find(
    (l) => typeof l?.message === "string",
  )?.message;
  const base = [
    input.type,
    (input.page_url ?? "").replace(/\?.*$/, ""),
    firstErr ?? input.description.slice(0, 60),
  ]
    .join("|")
    .toLowerCase();
  let hash = 0;
  for (let i = 0; i < base.length; i++) hash = (hash * 31 + base.charCodeAt(i)) | 0;
  return `fp_${(hash >>> 0).toString(36)}`;
}

export async function createQaTicket(opts: { data: unknown }) {
  const data = CreateSchema.parse(opts.data);
  const fingerprint = fingerprintOf(data);
  return phpPost<{ id: string; ticket_number: string }>("/api/ops/index.php", {
    op: "qa_create",
    ...data,
    fingerprint,
  });
}

export type QaTicketRow = {
  id: string;
  ticket_number: string;
  type: string;
  priority: string;
  status: string;
  description: string;
  page_url: string | null;
  page_title: string | null;
  user_name: string | null;
  user_email: string | null;
  device: unknown;
  screenshot_url: string | null;
  assigned_to: string | null;
  created_at: string;
  resolved_at: string | null;
  ip: string | null;
  city_id: string | null;
};

export async function listQaTickets(opts?: { data?: unknown }) {
  const data = z
    .object({
      status: StatusEnum.nullish(),
      type: TypeEnum.nullish(),
      priority: PriorityEnum.nullish(),
      search: z.string().max(200).nullish(),
      limit: z.number().int().min(1).max(200).default(100),
    })
    .parse(opts?.data ?? {});
  await requireAdmin();
  const qs = new URLSearchParams({ op: "qa_list", limit: String(data.limit) });
  if (data.status) qs.set("status", data.status);
  if (data.type) qs.set("type", data.type);
  if (data.priority) qs.set("priority", data.priority);
  if (data.search) qs.set("search", data.search);
  return phpGet<{ rows: QaTicketRow[]; stats: Record<string, number> }>(`/api/ops/index.php?${qs.toString()}`);
}

export async function getQaTicket(opts: { data: unknown }) {
  const data = z.object({ id: z.string().uuid() }).parse(opts.data);
  await requireAdmin();
  return phpGet<{
    ticket: QaTicketRow & { console_logs?: unknown; network_logs?: unknown; extra?: unknown; video_url?: string | null };
    comments: Array<{ id: string; body: string; created_at: string; author_id: string | null }>;
    events: Array<{ id: string; kind: string; created_at: string; payload: unknown }>;
    screenshotSignedUrl: string | null;
  }>(`/api/ops/index.php?op=qa_get&id=${encodeURIComponent(data.id)}`);
}

export async function updateQaTicket(opts: { data: unknown }) {
  const data = z
    .object({
      id: z.string().uuid(),
      status: StatusEnum.nullish(),
      priority: PriorityEnum.nullish(),
      assigned_to: z.string().uuid().nullable().optional(),
    })
    .parse(opts.data);
  await requireAdmin();
  await phpPost("/api/ops/index.php", { op: "qa_update", ...data });
  return { ok: true };
}

export async function addQaComment(opts: { data: unknown }) {
  const data = z
    .object({ ticket_id: z.string().uuid(), body: z.string().trim().min(1).max(4000) })
    .parse(opts.data);
  await requireAdmin();
  await phpPost("/api/ops/index.php", { op: "qa_comment", ...data });
  return { ok: true };
}
