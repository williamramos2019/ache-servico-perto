import { z } from "zod";
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
    "O disparo de Web Push precisa de um servidor (chave VAPID privada). Nesta fase SPA o envio está desativado. Histórico e estatísticas continuam disponíveis. Será migrado para PHP.",
  );
}

export async function listAdminPush(opts?: { data?: unknown }) {
  const data = z
    .object({ limit: z.number().int().min(1).max(200).default(50) })
    .parse(opts?.data ?? {});
  const { supabase } = await requireAdmin();
  const { data: rows, error } = await supabase
    .from("push_notifications")
    .select(
      "id, title, body, category, status, sent_at, created_at, sent_count, delivered_count, opened_count, clicked_count, failed_count, audience",
    )
    .order("created_at", { ascending: false })
    .limit(data.limit);
  if (error) throw new Error(error.message);
  return rows ?? [];
}

export async function getAdminPush(opts: { data: unknown }) {
  const data = z.object({ id: z.string().uuid() }).parse(opts.data);
  const { supabase } = await requireAdmin();
  const { data: notif } = await supabase
    .from("push_notifications")
    .select("*")
    .eq("id", data.id)
    .maybeSingle();
  if (!notif) throw new Error("Envio não encontrado.");
  const { data: deliveries } = await supabase
    .from("push_deliveries")
    .select("status, device, browser")
    .eq("notification_id", data.id);
  const byDevice: Record<string, number> = {};
  const byBrowser: Record<string, number> = {};
  (deliveries ?? []).forEach((d) => {
    const dev = d.device ?? "unknown";
    const br = d.browser ?? "unknown";
    byDevice[dev] = (byDevice[dev] ?? 0) + 1;
    byBrowser[br] = (byBrowser[br] ?? 0) + 1;
  });
  return { notification: notif, byDevice, byBrowser, totalDeliveries: deliveries?.length ?? 0 };
}

export async function deleteAdminPush(opts: { data: unknown }) {
  const data = z.object({ id: z.string().uuid() }).parse(opts.data);
  const { supabase } = await requireAdmin();
  const { error } = await supabase.from("push_notifications").delete().eq("id", data.id);
  if (error) throw new Error(error.message);
  return { ok: true };
}

export async function pushDashboardStats(_opts?: { data?: unknown }) {
  const { supabase } = await requireAdmin();

  const [subs, pwaSubs, companiesTotal, companiesPrem, companiesFree, notifs, lastSent, nextSched] =
    await Promise.all([
      supabase.from("push_subscriptions").select("user_id", { count: "exact", head: false }),
      supabase
        .from("push_subscriptions")
        .select("id", { count: "exact", head: true })
        .eq("is_pwa", true),
      supabase.from("companies").select("id", { count: "exact", head: true }),
      supabase.from("companies").select("id", { count: "exact", head: true }).eq("plan", "premium"),
      supabase.from("companies").select("id", { count: "exact", head: true }).eq("plan", "free"),
      supabase
        .from("push_notifications")
        .select(
          "id, sent_at, sent_count, delivered_count, opened_count, clicked_count, failed_count, unsubscribed_count, created_at",
        )
        .order("created_at", { ascending: false })
        .limit(30),
      supabase
        .from("push_notifications")
        .select("id, title, sent_at")
        .eq("status", "sent")
        .order("sent_at", { ascending: false })
        .limit(1)
        .maybeSingle(),
      supabase
        .from("push_notifications")
        .select("id, title, scheduled_at")
        .eq("status", "scheduled")
        .order("scheduled_at", { ascending: true })
        .limit(1)
        .maybeSingle(),
    ]);

  const uniqueSubscribers = new Set((subs.data ?? []).map((s) => s.user_id as string)).size;
  const totals = (notifs.data ?? []).reduce(
    (a, n) => ({
      sent: a.sent + (n.sent_count ?? 0),
      opened: a.opened + (n.opened_count ?? 0),
      clicked: a.clicked + (n.clicked_count ?? 0),
    }),
    { sent: 0, opened: 0, clicked: 0 },
  );
  const openRate = totals.sent > 0 ? Math.round((totals.opened / totals.sent) * 1000) / 10 : 0;
  const clickRate = totals.sent > 0 ? Math.round((totals.clicked / totals.sent) * 1000) / 10 : 0;

  const days: Array<{
    date: string;
    sent: number;
    clicked: number;
    failed: number;
    unsub: number;
  }> = [];
  for (let i = 13; i >= 0; i--) {
    const d = new Date(Date.now() - i * 86400_000);
    const key = d.toISOString().slice(0, 10);
    days.push({ date: key, sent: 0, clicked: 0, failed: 0, unsub: 0 });
  }
  (notifs.data ?? []).forEach((n) => {
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
    subscribers: uniqueSubscribers,
    subscriptions: subs.data?.length ?? 0,
    pwaInstalls: pwaSubs.count ?? 0,
    companies: companiesTotal.count ?? 0,
    premium: companiesPrem.count ?? 0,
    free: companiesFree.count ?? 0,
    openRate,
    clickRate,
    lastSent: lastSent.data ?? null,
    nextScheduled: nextSched.data ?? null,
    days,
  };
}
