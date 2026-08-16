# Checklist pós-deploy HostGator

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 3.1

Usar **depois** do primeiro upload. Nesta fase **nenhum** item foi marcado como feito em produção.

Marcar só com evidência (status HTTP + corpo). Não inventar.

---

## Pré-condição

- [ ] Banco de produção criado no cPanel
- [ ] `load-env.php` fora de `public_html` + `auto_prepend_file`
- [ ] `RATE_LIMIT_DIR` fora da web
- [ ] Conteúdo de `dist/` em `public_html/`
- [ ] `api/` em `public_html/api/`
- [ ] Checkout com `tools/` + `database/migrations/` fora da web

BANCO DE PRODUÇÃO HOSTGATOR: tratar como **NÃO INSTALADO** até o cPanel o mostrar.  
MIGRATIONS EM PRODUÇÃO: tratar como **NÃO EXECUTADAS** até `--status` listar 001–003 applied.

---

## SPA e Apache

- [ ] SPA abre (`GET /` → HTML 200)
- [ ] Refresh em `/auth` não dá 404 Apache
- [ ] Refresh em `/painel/perfil` não dá 404 Apache
- [ ] Refresh em `/buscar` não dá 404 Apache
- [ ] `/api/...` não é reescrito para `index.html`

**TESTE NÃO EXECUTADO — Apache HostGator real** (até haver domínio).

---

## API e banco

- [ ] `/api/index.php` responde JSON `success: true`, `data.service = api`
- [ ] `/api/health.php` responde
- [ ] banco conectado (`health` → `data.database = ok`, HTTP 200; não 503)
- [ ] migration status correto (`php tools/migrate.php --status` → 001, 002, 003 applied, pending none)

---

## Autenticação

- [ ] CSRF: `GET /api/auth/csrf.php` → JSON com `csrf_token` (não logar o token)
- [ ] registro: `POST /api/auth/register.php` com token (sem `role` / `user_id`)
- [ ] login: `POST /api/auth/login.php`
- [ ] `/me`: `GET /api/auth/me.php` autenticado → 200
- [ ] logout: `POST /api/auth/logout.php`
- [ ] `/me` após logout → 401 (SPA não deve quebrar)
- [ ] sessão persiste entre requests (`credentials: include`)
- [ ] cookie `agendaqui_sid` (ou `SESSION_NAME`): HttpOnly, SameSite=Lax
- [ ] cookie Secure em HTTPS
- [ ] POST/PATCH **sem** `X-CSRF-Token` → 403
- [ ] POST/PATCH com token inválido → 403
- [ ] POST/PATCH com token válido → operação permitida

---

## Perfil e empresas

- [ ] perfil GET `/api/users/me.php`
- [ ] perfil PATCH (só `name` / `avatar_url`)
- [ ] criação de empresa `POST /api/companies/create.php` (sem `owner_id`)
- [ ] listagem `GET /api/companies/mine.php`
- [ ] detalhe `GET /api/companies/show.php?id=`
- [ ] edição de empresa `PATCH /api/companies/update.php?id=`
- [ ] utilizador B não altera empresa de A (403)
- [ ] utilizador B não vê empresa inativa de A (404)

---

## Rate limit e HTTPS

- [ ] rate limit: logins inválidos repetidos → 429 `rate_limited` + `Retry-After`
- [ ] ficheiros em `RATE_LIMIT_DIR` graváveis; conteúdo só `count`/`reset`
- [ ] HTTPS no domínio (SPA e API)
- [ ] sem mixed content
- [ ] `credentials: include` same-origin

---

## Ficheiros sensíveis (não devem descarregar)

- [ ] arquivos sensíveis bloqueados
- [ ] bootstrap não acessível (`/api/bootstrap/` e `*.php` → 403)
- [ ] SQL não acessível (não publicar `database/`; `*.sql` em `api/` negado)
- [ ] `.env` não acessível (não publicar; 404)
- [ ] storage protegido (fora da web ou deny-all)
- [ ] `/tools/` não existe na web
- [ ] directory listing desligado

---

## O que este checklist **não** cobre

Diretório público (`/buscar`, `/empresa/$slug`), Google OAuth, favoritos, reviews, admin, push, etc. ainda no Supabase — ver `docs/SUPABASE-MIGRATION-STATUS.md`.
