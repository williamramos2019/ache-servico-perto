import { supabase } from "@/integrations/supabase/client";

export async function requireUser() {
  const { data, error } = await supabase.auth.getUser();
  if (error || !data.user) throw new Error("Unauthorized");
  return { supabase, userId: data.user.id };
}

export async function requireAdmin() {
  const ctx = await requireUser();
  const { data } = await ctx.supabase.rpc("has_role", {
    _user_id: ctx.userId,
    _role: "admin",
  });
  if (!data) throw new Error("Acesso restrito.");
  return ctx;
}
