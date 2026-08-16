import { fetchCurrentUser } from "@/lib/php-auth";
import type { PhpUser } from "@/lib/php-api";

export async function requireUser(): Promise<{ userId: string; user: PhpUser }> {
  const user = await fetchCurrentUser();
  if (!user) throw new Error("Unauthorized");
  return { userId: user.id, user };
}

export async function requireAdmin(): Promise<{ userId: string; user: PhpUser }> {
  const ctx = await requireUser();
  if (!ctx.user.roles.includes("admin")) throw new Error("Acesso restrito.");
  return ctx;
}
