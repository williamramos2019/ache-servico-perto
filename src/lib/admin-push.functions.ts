import { z } from "zod";
import { phpGet, phpPost } from "@/lib/php-api";
import { requireAdmin } from "@/lib/spa-auth";

const AudienceSchema = z.object({
  kind: z
    .enum([
      "all",
      "users",
      "companies",
      "premium",
      "free",
      "admins",
      "city",
      "state",
      "category",
      "pwa",
      "recent30",
      "inactive",
    ])
    .default("all"),
  city_id: z.string().uuid().nullish(),
  state: z.string().nullish(),
  category_id: z.string().uuid().nullish(),
});

const ButtonSchema = z.object({ label: z.string().min(1).max(24), url: z.string().url() });

const ComposeSchema = z.object({
  title: z.string().min(1).max(120),
  body: z.string().min(1).max(400),
  icon_url: z.string().url().nullish(),
  image_url: z.string().url().nullish(),
  url: z.string().url().nullish(),
  category: z.string().default("geral"),
  priority: z.enum(["low", "normal", "high"]).default("normal"),
  color: z.string().nullish(),
  emoji: z.string().nullish(),
  buttons: z.array(ButtonSchema).max(2).nullish(),
  audience: AudienceSchema,
  template_id: z.string().uuid().nullish(),
});

export async function sendPushNow(opts: { data: unknown }): Promise<{ id: string; sent: number }> {
  ComposeSchema.parse(opts.data);
  await requireAdmin();
  throw new Error(
    "O disparo de Web Push precisa de um servidor (chave VAPID privada). Nesta fase o envio está desativado. Histórico e estatísticas continuam disponíveis.",
  );
}

export async function listAdminPush(opts?: { data?: unknown }) {
  const data = z
    .object({ limit: z.number().int().min(1).max(200).default(50) })
    .parse(opts?.data ?? {});
  await requireAdmin();
  const res = await phpGet<{ rows: unknown[] }>(
    `/api/ops/index.php?op=push_list&limit=${encodeURIComponent(String(data.limit))}`,
  );
  return res.rows ?? [];
}

export async function getAdminPush(opts: { data: unknown }) {
  const data = z.object({ id: z.string().uuid() }).parse(opts.data);
  await requireAdmin();
  return phpGet<{
    notification: Record<string, unknown>;
    byDevice: Record<string, number>;
    byBrowser: Record<string, number>;
    totalDeliveries: number;
  }>(`/api/ops/index.php?op=push_get&id=${encodeURIComponent(data.id)}`);
}

export async function deleteAdminPush(opts: { data: unknown }) {
  const data = z.object({ id: z.string().uuid() }).parse(opts.data);
  await requireAdmin();
  await phpPost("/api/ops/index.php", { op: "push_delete", id: data.id });
  return { ok: true };
}

export async function pushDashboardStats(_opts?: { data?: unknown }) {
  await requireAdmin();
  const stats = await phpGet<{
    uniqueSubscribers: number;
    pwaSubscribers: number;
    companiesTotal: number;
    companiesPremium: number;
    companiesFree: number;
    notifications: Array<{
      sent_at: string | null;
      created_at: string;
      sent_count: number | null;
      clicked_count: number | null;
      failed_count: number | null;
      unsubscribed_count: number | null;
      opened_count: number | null;
    }>;
    lastSent: { id: string; title: string; sent_at: string | null } | null;
    nextScheduled: { id: string; title: string; scheduled_at: string | null } | null;
  }>("/api/ops/index.php?op=push_stats");

  const totals = (stats.notifications ?? []).reduce(
    (a, n) => ({
      sent: a.sent + (n.sent_count ?? 0),
      opened: a.opened + (n.opened_count ?? 0),
      clicked: a.clicked + (n.clicked_count ?? 0),
    }),
    { sent: 0, opened: 0, clicked: 0 },
  );
  const openRate = totals.sent > 0 ? Math.round((totals.opened / totals.sent) * 1000) / 10 : 0;
  const clickRate = totals.sent > 0 ? Math.round((totals.clicked / totals.sent) * 1000) / 10 : 0;

  const days: Array<{ date: string; sent: number; clicked: number; failed: number; unsub: number }> = [];
  for (let i = 13; i >= 0; i--) {
    const d = new Date(Date.now() - i * 86400_000);
    days.push({ date: d.toISOString().slice(0, 10), sent: 0, clicked: 0, failed: 0, unsub: 0 });
  }
  (stats.notifications ?? []).forEach((n) => {
    const key = (n.sent_at ?? n.created_at ?? "").slice(0, 10);
    const day = days.find((d) => d.date === key);
    if (day) {
      day.sent += n.sent_count ?? 0;
      day.clicked += n.clicked_count ?? 0;
      day.failed += n.failed_count ?? 0;
      day.unsub += n.unsubscribed_count ?? 0;
    }
  });

  return {
    subscribers: stats.uniqueSubscribers,
    subscriptions: stats.uniqueSubscribers,
    pwaInstalls: stats.pwaSubscribers,
    companies: stats.companiesTotal,
    premium: stats.companiesPremium,
    free: stats.companiesFree,
    openRate,
    clickRate,
    lastSent: stats.lastSent,
    nextScheduled: stats.nextScheduled,
    days,
  };
}
