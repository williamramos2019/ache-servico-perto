export type PhpUser = {
  id: string;
  email: string;
  email_verified: boolean;
  created_at: string;
  profile: { name: string | null; avatar_url: string | null };
  roles: string[];
};

export class PhpApiError extends Error {
  status: number;
  code: string;

  constructor(status: number, code: string, message: string) {
    super(message);
    this.name = "PhpApiError";
    this.status = status;
    this.code = code;
  }
}

type Envelope<T> =
  | { success: true; data: T }
  | { success: false; error: { code: string; message: string } };

const WRITE_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

let csrfToken: string | null = null;

function apiBase(): string {
  const raw = import.meta.env.VITE_API_BASE;
  if (typeof raw === "string" && raw.trim() !== "") {
    return raw.replace(/\/$/, "");
  }
  return "";
}

function apiUrl(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  return `${apiBase()}${normalized}`;
}

function rememberCsrf(data: unknown): void {
  if (!data || typeof data !== "object") return;
  const token = (data as { csrf_token?: unknown }).csrf_token;
  if (typeof token === "string" && token !== "") {
    csrfToken = token;
  }
}

function publicMessage(status: number, code: string, message: string): string {
  if (status === 401) return "Entre na sua conta para continuar.";
  if (status === 403 && code === "csrf_invalid")
    return "Não foi possível validar a requisição. Recarregue a página e tente de novo.";
  if (status === 403) return "Você não tem permissão para esta ação.";
  if (status === 409) return message || "Este dado já está em uso.";
  if (status === 422) return message || "Verifique os dados informados.";
  if (status === 429) return "Muitas tentativas. Tente novamente em instantes.";
  if (status >= 500) return "Não foi possível concluir. Tente de novo.";
  return message || "Não foi possível concluir.";
}

export function clearCsrf(): void {
  csrfToken = null;
}

export async function ensureCsrf(): Promise<string> {
  if (csrfToken) return csrfToken;
  const data = await phpRequest<{ csrf_token: string }>("GET", "/api/auth/csrf.php");
  rememberCsrf(data);
  if (!csrfToken) {
    throw new PhpApiError(
      500,
      "csrf_missing",
      "Não foi possível validar a requisição. Recarregue a página e tente de novo.",
    );
  }
  return csrfToken;
}

async function parseEnvelope<T>(res: Response): Promise<T> {
  const text = await res.text();
  let parsed: Envelope<T> | null = null;
  try {
    parsed = text ? (JSON.parse(text) as Envelope<T>) : null;
  } catch {
    parsed = null;
  }

  if (parsed && parsed.success === true) {
    rememberCsrf(parsed.data);
    return parsed.data;
  }

  const code = parsed && parsed.success === false ? parsed.error.code : "http_error";
  const rawMessage =
    parsed && parsed.success === false ? parsed.error.message : "Não foi possível concluir.";
  throw new PhpApiError(res.status, code, publicMessage(res.status, code, rawMessage));
}

async function phpRequest<T>(
  method: string,
  path: string,
  body?: unknown,
  retryCsrf = true,
): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json" };
  if (WRITE_METHODS.has(method)) {
    headers["X-CSRF-Token"] = await ensureCsrf();
    headers["Content-Type"] = "application/json";
  }

  const res = await fetch(apiUrl(path), {
    method,
    credentials: "include",
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  try {
    return await parseEnvelope<T>(res);
  } catch (err) {
    if (
      retryCsrf &&
      WRITE_METHODS.has(method) &&
      err instanceof PhpApiError &&
      err.status === 403 &&
      err.code === "csrf_invalid"
    ) {
      clearCsrf();
      await ensureCsrf();
      return phpRequest<T>(method, path, body, false);
    }
    throw err;
  }
}

export function phpGet<T>(path: string): Promise<T> {
  return phpRequest<T>("GET", path);
}

export function phpPost<T>(path: string, body?: unknown): Promise<T> {
  return phpRequest<T>("POST", path, body ?? {});
}

export function phpPatch<T>(path: string, body?: unknown): Promise<T> {
  return phpRequest<T>("PATCH", path, body ?? {});
}

export function phpDelete<T>(path: string, body?: unknown): Promise<T> {
  return phpRequest<T>("DELETE", path, body ?? {});
}

export async function phpUpload<T>(
  path: string,
  file: File | Blob,
  fields?: Record<string, string>,
  retryCsrf = true,
): Promise<T> {
  const token = await ensureCsrf();
  const form = new FormData();
  const filename = file instanceof File ? file.name : "upload.bin";
  form.append("file", file, filename);
  if (fields) {
    for (const [key, value] of Object.entries(fields)) {
      form.append(key, value);
    }
  }
  const res = await fetch(apiUrl(path), {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json", "X-CSRF-Token": token },
    body: form,
  });
  try {
    return await parseEnvelope<T>(res);
  } catch (err) {
    if (
      retryCsrf &&
      err instanceof PhpApiError &&
      err.status === 403 &&
      err.code === "csrf_invalid"
    ) {
      clearCsrf();
      await ensureCsrf();
      return phpUpload<T>(path, file, fields, false);
    }
    throw err;
  }
}

export async function phpGetMe(): Promise<PhpUser | null> {
  try {
    const data = await phpGet<{ user: PhpUser; csrf_token?: string }>("/api/auth/me.php");
    rememberCsrf(data);
    return data.user;
  } catch (err) {
    if (err instanceof PhpApiError && err.status === 401) {
      return null;
    }
    throw err;
  }
}
