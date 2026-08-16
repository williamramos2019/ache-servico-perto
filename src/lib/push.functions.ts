import { z } from "zod";
import { phpGet, phpPost } from "@/lib/php-api";
import { requireUser } from "@/lib/spa-auth";

const SubscribeSchema = z.object({
  endpoint: z.string().url(),
  p256dh: z.string().min(1),
  auth: z.string().min(1),
  user_agent: z.string().nullish(),
  platform: z.string().nullish(),
  is_pwa: z.boolean().default(false),
});

export async function subscribePush(opts: { data: unknown }) {
  const data = SubscribeSchema.parse(opts.data);
  await requireUser();
  await phpPost("/api/ops/index.php", { op: "subscribe", ...data });
  return { ok: true };
}

export async function unsubscribePush(opts: { data: unknown }) {
  const data = z.object({ endpoint: z.string().url() }).parse(opts.data);
  await requireUser();
  await phpPost("/api/ops/index.php", { op: "unsubscribe", endpoint: data.endpoint });
  return { ok: true };
}

const PrefsSchema = z.object({
  promocoes: z.boolean().optional(),
  novidades: z.boolean().optional(),
  eventos: z.boolean().optional(),
  atualizacoes: z.boolean().optional(),
  empresas: z.boolean().optional(),
  blog: z.boolean().optional(),
  marketplace: z.boolean().optional(),
  som: z.boolean().optional(),
  vibracao: z.boolean().optional(),
  quiet_hours_enabled: z.boolean().optional(),
  quiet_start: z.number().int().min(0).max(23).optional(),
  quiet_end: z.number().int().min(0).max(23).optional(),
});

export async function getMyPreferences(_opts?: { data?: unknown }) {
  await requireUser();
  const data = await phpGet<{ prefs: Record<string, unknown> | null }>("/api/ops/index.php?op=prefs");
  return data.prefs ?? null;
}

export async function savePreferences(opts: { data: unknown }) {
  const data = PrefsSchema.parse(opts.data);
  await requireUser();
  await phpPost("/api/ops/index.php", { op: "prefs_save", ...data });
  return { ok: true };
}

export async function listMyInbox(opts?: { data?: unknown }) {
  const data = z
    .object({
      tab: z.enum(["all", "unread", "read", "favorites", "archived"]).default("all"),
      q: z.string().optional(),
      limit: z.number().int().min(1).max(100).default(50),
    })
    .parse(opts?.data ?? {});
  await requireUser();
  const qs = new URLSearchParams({
    op: "inbox",
    tab: data.tab,
    limit: String(data.limit),
  });
  if (data.q) qs.set("q", data.q);
  const res = await phpGet<{ inbox: unknown[] }>(`/api/ops/index.php?${qs.toString()}`);
  return res.inbox ?? [];
}

export async function unreadInboxCount(_opts?: { data?: unknown }) {
  await requireUser();
  const data = await phpGet<{ count: number }>("/api/ops/index.php?op=inbox_count");
  return { count: data.count ?? 0 };
}

const InboxActionSchema = z.object({
  id: z.number().int(),
  action: z.enum(["read", "unread", "favorite", "unfavorite", "archive", "unarchive", "delete"]),
});

export async function inboxAction(opts: { data: unknown }) {
  const data = InboxActionSchema.parse(opts.data);
  await requireUser();
  await phpPost("/api/ops/index.php", { op: "inbox_action", ...data });
  return { ok: true };
}

export async function markAllRead(_opts?: { data?: unknown }) {
  await requireUser();
  await phpPost("/api/ops/index.php", { op: "inbox_read_all" });
  return { ok: true };
}
