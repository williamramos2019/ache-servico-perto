/**
 * Preserved push tracking API. Not used by the static SPA build.
 * Original path: src/routes/api/public/push/track.ts
 *
 * The service worker still POSTs to /api/public/push/track and ignores failures.
 * Reimplement this as PHP in a later phase.
 */
import { z } from "zod";

export const TrackSchema = z.object({
  delivery_id: z.number().int().optional(),
  event: z.enum(["delivered", "opened", "clicked", "unsubscribed", "resubscribe", "failed"]),
  old_endpoint: z.string().optional(),
});

/**
 * Original handler logic (reference for the PHP port):
 *
 * 1. Parse JSON with TrackSchema
 * 2. Use service-role Supabase client
 * 3. resubscribe/unsubscribed: delete push_subscriptions by old_endpoint
 * 4. delivered/opened/clicked: update push_deliveries timestamps + increment counters on push_notifications
 */
export const PUSH_TRACK_NOTES = {
  method: "POST",
  path: "/api/public/push/track",
  usedBy: "public/sw.js",
  requires: "SUPABASE_SERVICE_ROLE_KEY",
};
