# Fundação PHP da API (HostGator)

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 2.4  
**Fontes:** `docs/MIGRATION-AUDIT.md`, `docs/MYSQL-SCHEMA-DESIGN.md`, `docs/MIGRATION-RUNNER.md`

Esta fase cria só a fundação HTTP. Não há autenticação, schema de negócio nem deploy.

---

## 1. Objetivo

Permitir que, nas fases seguintes, a SPA estática chame:

```
Browser → React SPA → Apache → /api/*.php → PDO → MySQL/MariaDB
```

na HostGator compartilhada, com PHP 8 + PDO, sem Node e sem framework.

---

## 2. Arquitetura

```
public_html/          (conteúdo de dist/ + pasta api/)
  index.html
  assets/
  api/
    index.php
    health.php
    .htaccess
    bootstrap/        (bloqueado via HTTP)
      app.php
      database.php    (reutilizado; não alterado nesta fase)

tools/migrate.php     (CLI only — fora do public_html)
database/migrations/  (fora do public_html)
```

Não existe `/api/migrate.php`.

---

## 3. Estrutura

| Arquivo | Papel |
| --- | --- |
| `api/bootstrap/database.php` | PDO (Fase 2.3). Multi-statements **desligado** por padrão |
| `api/bootstrap/app.php` | HTTP: UTC, JSON, CORS, erros, headers |
| `api/index.php` | Ponto de entrada mínimo |
| `api/health.php` | Saúde da API + estado do banco |
| `api/.htaccess` | Sem listagem; bloqueia `bootstrap/` |

---

## 4. Bootstrap

`app_start(['GET', 'OPTIONS'])`:

1. `date_default_timezone_set('UTC')`
2. Headers de segurança
3. Handlers de erro/exceção
4. CORS
5. `OPTIONS` → 204
6. Método fora da lista do endpoint → 405 JSON

Sem Composer. Sem roteador complexo.

---

## 5. PDO

Reutiliza `db_pdo(false)` de `database.php`:

- PHP 8 + PDO MySQL
- exceptions
- prepared statements (`ATTR_EMULATE_PREPARES = false`)
- `utf8mb4`
- `time_zone = '+00:00'`

A API **nunca** chama `db_pdo(true)`. Isso fica só no runner CLI.

A mensagem interna de falha do PDO (host/user) **não** é enviada ao cliente. `health.php` traduz qualquer falha para `database = unavailable`.

---

## 6. Configuração

Somente variáveis de ambiente. **Não** foram alterados `.env` / `.env.example`.

| Variável | Obrigatória | Padrão | Uso |
| --- | --- | --- | --- |
| `DB_HOST` | para conectar | — | host MySQL |
| `DB_PORT` | não | `3306` | porta |
| `DB_DATABASE` | para conectar | — | database |
| `DB_USERNAME` | para conectar | — | usuário |
| `DB_PASSWORD` | não | vazio | senha |
| `APP_ENV` | não | `production` | `development` ou `production` |
| `APP_ALLOWED_ORIGINS` | não | vazio | lista CSV de origens CORS |

Valores reais não são versionados. Não inventar domínio.

Na HostGator: definir no ambiente do PHP/cron ou no painel. Preferir o repositório **acima** de `public_html` e publicar `dist/` + `api/`.

---

## 7. CORS

A auditoria (Fase 2.1) define **mesma origem**: o domínio serve a SPA e `/api`.

- Sem `APP_ALLOWED_ORIGINS`: **não** envia `Access-Control-Allow-Origin: *`. `OPTIONS` com `Origin` → 403 `cors_not_configured`.
- Com lista: só origens **exatas** da lista recebem ACAO + `Allow-Credentials: true` (cookie de sessão futuro).
- Origem presente e fora da lista: `OPTIONS` → 403 `cors_forbidden`.
- Pedido sem `Origin` (curl, same-origin clássico): segue sem cabeçalhos CORS.

Não há domínio hardcoded.

---

## 8. JSON

Sucesso:

```json
{ "success": true, "data": { } }
```

Erro:

```json
{ "success": false, "error": { "code": "...", "message": "..." } }
```

`health.php` em banco indisponível usa HTTP 503, o objeto `error` e um `data` com `status` / `database` para o cliente distinguir degradação sem stack trace.

---

## 9. Tratamento de erros

| `APP_ENV` | Corpo HTTP |
| --- | --- |
| `production` (padrão) | `Unexpected error.` — sem stack, path, SQL, DSN, senha |
| `development` | mensagem da exceção, **exceto** se parecer senha/DSN |

`error_log` no servidor registra só `API exception`, sem despejar credenciais no corpo HTTP.

---

## 10. Health check

`GET /api/health.php`

- Banco ok: HTTP 200, `success: true`, `data.status = ok`, `data.database = ok`
- Banco indisponível: HTTP 503, `success: false`, `error.code = database_unavailable`, `data.database = unavailable`

Não autentica. Não mostra host, user, senha ou DSN.

---

## 11. API index

`GET /api/index.php` (ou `/api/` se o Apache mapear `DirectoryIndex`)

```json
{ "success": true, "data": { "service": "api", "status": "ok" } }
```

Sem rotas de negócio.

---

## 12. Segurança

- `Content-Type: application/json; charset=utf-8`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: no-referrer`
- `Cache-Control: no-store`
- Sem CSP nesta fase (evita quebrar a SPA)
- `bootstrap/` inacessível via HTTP
- Runner continua CLI
- Métodos reconhecidos: GET, POST, PUT, PATCH, DELETE, OPTIONS
- Endpoint atual: só GET e OPTIONS; resto → 405

---

## 13. Apache

`public/.htaccess` da SPA **não foi alterado**. Já contém `RewriteRule ^api(/|$) - [L]`.

`api/.htaccess` (novo):

- `Options -Indexes`
- `RewriteRule ^bootstrap/ - [F,L]`
- bloqueia `.sql` `.md` `.log` `.ini` `.env` dentro de `api/`

Sem rewrite de front controller.

---

## 14. HostGator

- PHP 8.x + `pdo_mysql`
- Sem Composer, Laravel, Redis, daemon
- Publicar `api/` ao lado da SPA em `public_html`
- Manter `tools/` e `database/` fora da web root
- Criar o MySQL no cPanel; definir as variáveis de ambiente
- Padrão `APP_ENV=production` se a variável não existir

---

## 15. Testes

Ver relatório da Fase 2.4. Localmente: `php -S 127.0.0.1:8091 -t api` e `GET /index.php`, `GET /health.php`.

---

## 16. Limitações

1. Sem roteamento por path (`/api/companies` ainda não existe).
2. Sem autenticação, CSRF, rate limit ou sessões.
3. CORS extra só se `APP_ALLOWED_ORIGINS` for definido; o caso previsto é same-origin.
4. Health 503 inclui `data` além de `error` (extensão documentada).
5. `database.php` ainda menciona host/user na *exceção interna*; a API não repassa isso ao cliente.
6. O servidor built-in do PHP não aplica `.htaccess` (só Apache).

---

## 17. O que ainda não foi implementado

Auth, users, companies, reviews, leads, claims, blog, events, marketplace, QA, push, uploads, ranking, admin, schema de negócio, dump do Supabase, deploy.
