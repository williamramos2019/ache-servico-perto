# Autenticação PHP + sessão

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 2.6  
**Schema:** `database/migrations/002_auth.sql`

Não há OAuth, JWT, refresh token nem migração de usuários do Supabase.

---

## 1. Arquitetura

```
GET  /api/auth/csrf.php
POST /api/auth/register.php
POST /api/auth/login.php
POST /api/auth/logout.php
GET  /api/auth/me.php
        ↓
api/bootstrap/auth.php
        ↓
sessão PHP (cookie HttpOnly, SameSite=Lax)
        ↓
db_pdo(false) → users / profiles / user_roles
```

Helpers: `require_auth()`, `has_role()`, `require_role()`, `auth_user_id()`.

---

## 2. Registro

`POST /api/auth/register.php`

JSON: `email`, `password`, `name`.

- e-mail normalizado (`trim` + minúsculas), `FILTER_VALIDATE_EMAIL`, máx. 255
- senha: 8–72 caracteres (limite do bcrypt); `password_hash(..., PASSWORD_DEFAULT)`
- `name` → `profiles.name` (campo comprovado; não existe `users.name`)
- transação: `users` + `profiles` (mesmo `id`) + `user_roles.role = user`
- role padrão `user`: regra da Fase 2.2 (`register default user`)
- `email_verified_at` fica NULL (confirmação por e-mail ainda não existe)
- `role` / `user_id` / `owner_id` / `company_id` / `admin` no JSON → 422 `unexpected_fields`
- 409 `email_taken` se o e-mail já existir
- 201 com usuário público (sem `password_hash`)
- não inicia sessão (login é passo separado)

---

## 3. Login

`POST /api/auth/login.php`

- busca por e-mail normalizado
- `password_verify`
- se o e-mail não existe, ainda assim chama `password_verify` contra um hash dummy (reduz timing)
- falha: 401 `invalid_credentials` — **a mesma mensagem** para e-mail ou senha errados
- sucesso: `session_regenerate_id(true)`, sessão só com `uid`
- não devolve `password_hash`

---

## 4. Logout

`POST /api/auth/logout.php`

Limpa `$_SESSION`, destrói a sessão, expira o cookie. Sem sessão prévia: 200 idempotente.

---

## 5. Sessão

Antes de `session_start()`:

- `session.use_strict_mode=1`
- só cookies, sem trans-sid
- HttpOnly
- Secure se HTTPS / porta 443
- SameSite=Lax
- nome: `SESSION_NAME` ou `agendaqui_sid`

Não guarda senha, hash, roles nem perfil na sessão.

---

## 6. `/me`

`GET /api/auth/me.php`

- sem `uid`: 401 `unauthenticated`
- com sessão: `id`, `email`, `email_verified`, `created_at`, `profile`, `roles` (lidas no banco)

---

## 7. Roles

Valores: `admin`, `company_owner`, `user`, `editor`, `publisher`.

Consultadas só em `user_roles`. Role no body/query/header **não** concede acesso. `has_role()` / `require_role()` ignoram valores fora da lista.

---

## 8. Autorização

Autenticado ≠ autorizado. Endpoints futuros: `require_auth()` e `require_role('admin')`. Sempre PDO + prepared statements.

---

## 9. Segurança

- sem enumeração no login
- prepared statements
- multi-statements desligado
- respostas sem senha, hash, DSN, SQL, stack (produção)
- `user_id` do cliente não autentica ninguém

---

## 10. CORS

Reutiliza `app.php` + `APP_ALLOWED_ORIGINS`. Sem `*`. Cookie exige origem permitida + credentials (já na fundação).

---

## 11. CSRF

Token na sessão + header `X-CSRF-Token`. Ver `docs/SECURITY-HARDENING.md`.

`GET /api/auth/csrf.php` emite o token. GET `/me` e login também devolvem `csrf_token`. Cookie sozinho não valida escrita.

---

## 12. Limitações / pendências de produção

1. Rate limit em filesystem (por host). Ver `docs/SECURITY-HARDENING.md`.
2. Sem e-mail de confirmação / reset (`password_resets` não está em `002_auth.sql`).
3. CSRF implementado; a SPA ainda não envia o header (integração futura).
4. Sem OAuth/Google.
5. Senhas do Supabase não migram — reset em massa na troca.
6. `Secure` só liga com HTTPS detectado no PHP (sem confiar em `X-Forwarded-Proto`).

---

## 13. HostGator

PHP 8 + PDO + sessões em arquivo do plano compartilhado. Sem Composer. Cookie `path=/`. Publicar `api/auth/` junto da SPA.

---

## 14. Testes

Ver relatório da Fase 2.6 (banco isolado + `php -S`).
