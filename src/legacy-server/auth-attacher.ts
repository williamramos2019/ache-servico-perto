/**
 * Preserved Start middleware. Not used by the static SPA build.
 * Original path: src/integrations/supabase/auth-attacher.ts
 */
import { createMiddleware } from "@tanstack/react-start";
import { supabase } from "../integrations/supabase/client";

export const attachSupabaseAuth = createMiddleware({ type: "function" }).client(
  async ({ next }) => {
    const { data } = await supabase.auth.getSession();
    const token = data.session?.access_token;
    return next({
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    });
  },
);
