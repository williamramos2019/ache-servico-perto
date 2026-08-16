import { clearCsrf, phpGet, phpGetMe, phpPatch, phpPost, type PhpUser } from "@/lib/php-api";

export type { PhpUser };

type AuthListener = () => void;

const listeners = new Set<AuthListener>();

function notifyAuthChange(): void {
  listeners.forEach((fn) => fn());
}

export function onPhpAuthChange(listener: AuthListener): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function fetchCurrentUser(): Promise<PhpUser | null> {
  return phpGetMe();
}

export async function loginWithPassword(email: string, password: string): Promise<PhpUser> {
  await phpPost<{ user: PhpUser; csrf_token?: string }>("/api/auth/login.php", { email, password });
  const user = await phpGetMe();
  if (!user) {
    throw new Error("Não foi possível confirmar a sessão. Tente entrar de novo.");
  }
  notifyAuthChange();
  return user;
}

export async function registerAccount(input: {
  email: string;
  password: string;
  name: string;
}): Promise<void> {
  await phpPost("/api/auth/register.php", {
    email: input.email,
    password: input.password,
    name: input.name,
  });
}

export async function logoutSession(): Promise<void> {
  try {
    await phpPost("/api/auth/logout.php", {});
  } finally {
    clearCsrf();
    notifyAuthChange();
  }
}

export async function fetchMyProfile(): Promise<PhpUser> {
  const data = await phpGet<{ user: PhpUser; csrf_token?: string }>("/api/users/me.php");
  return data.user;
}

export async function patchMyProfile(patch: {
  name?: string;
  avatar_url?: string | null;
}): Promise<PhpUser> {
  const body: { name?: string; avatar_url?: string | null } = {};
  if (patch.name !== undefined) body.name = patch.name;
  if (patch.avatar_url !== undefined) body.avatar_url = patch.avatar_url;
  const data = await phpPatch<{ user: PhpUser; csrf_token?: string }>("/api/users/me.php", body);
  notifyAuthChange();
  return data.user;
}
