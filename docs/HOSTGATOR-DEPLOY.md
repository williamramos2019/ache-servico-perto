# Deploy HostGator (pacote e procedimento)

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 3.1

Este documento define **o que enviar**, **para onde**, e **como**.  
**Não** faz o deploy. **Não** liga ao MySQL de produção.

Arquitetura:

```
Internet
  → Apache (HTTPS)
    → SPA estática (dist/)
    → /api/*.php
      → PHP 8
        → PDO
          → MySQL HostGator
```

`tools/`, `database/migrations/` e `storage/rate-limit/` **não** devem ser servidos por HTTP.

---

## 1. O que construir localmente

Na máquina de desenvolvimento (Node **só aqui**):

```bash
npm ci
npx tsc --noEmit
npm run build
```

Resultado: pasta `dist/` (gitignored). A HostGator **não** precisa de `node_modules`, `npm`, `vite` nem TypeScript.

A SPA chama `/api/...` em **same-origin** (`src/lib/php-api.ts`: `VITE_API_BASE` vazio).  
Não definir `VITE_API_BASE` absoluto no build de produção.

---

## 2. Árvore de deploy

```
DEPLOY/
  public_html/                          ← web root do domínio
    .htaccess                           ← de dist/ (cópia de public/.htaccess)
    index.html
    assets/
    icons/
    manifest.webmanifest
    sw.js
    offline.html
    robots.txt
    alert.mp3
    api/                                ← CÓPIA de api/ do repositório
      .htaccess
      index.php
      health.php
      auth/
      users/
      companies/
      bootstrap/                        ← necessário ao PHP; bloqueado via HTTP

  fora_public_html/                     ← /home/USUARIO/agendaqui/
    api/                                ← cópia canónica (o runner exige isto)
    database/migrations/
      001_create_migrations.sql
      002_auth.sql
      003_companies.sql
      … até 012_transport.sql (quando essa fase estiver autorizada)
    tools/migrate.php
    tools/import-companies.php          ← CLI only; nunca pela web
    storage/imports/                    ← JSON/CSV locais; Apache Deny from all
    storage/rate-limit/                 ← vazia, gravável, 0700
      .htaccess
      .gitignore
    load-env.php                        ← CRIAR NO SERVIDOR; NUNCA no Git
```

`migrate.php` resolve `api/bootstrap/database.php` e `database/migrations` a partir da pasta do checkout (`dirname` de `tools/`), **não** a partir de `public_html`. Por isso existem duas cópias de `api/`: a HTTP e a do runner. Devem ser a **mesma versão**.

---

## 3. O QUE enviar para `public_html/`

| Origem local | Destino |
| --- | --- |
| Conteúdo de `dist/` (incluindo `.htaccess`) | `public_html/` |
| Pasta `api/` completa | `public_html/api/` |

Inclui `api/bootstrap/*.php` (o Apache deve **executar** PHP e o `.htaccess` da API deve devolver 403 em `/api/bootstrap/`).

---

## 4. O QUE NÃO enviar para a web

- `node_modules/`
- `.git/`
- `.env` / `.env.example` (o `.env` está versionado hoje; **não** copiar para `public_html`)
- `src/`
- `supabase/`
- `package.json`, `vite.config.ts`, `tsconfig.json`
- `tools/` (fica só em `fora_public_html`)
- `database/` (fica só em `fora_public_html`)
- dumps / datadir de testes MariaDB
- `docs/` (opcional no checkout privado; não na web)
- credenciais, `load-env.php`, senhas, dumps

Não criar `/api/migrate.php`.

---

## 5. Storage

Não colocar `storage/` dentro de `public_html` se for possível evitá-lo.

Definir:

```text
RATE_LIMIT_DIR=/home/USUARIO/agendaqui/storage/rate-limit
```

Sem esta variável, o PHP usa `{pai de api/}/storage/rate-limit`. Com `api/` só em `public_html`, isso vira `public_html/storage/rate-limit` (exposto a HTTP, a menos que se copiem os `.htaccess` deny-all).

Permissões alvo: diretório `0700`, ficheiros de limite `0600`. Conteúdo: JSON `{count, reset}` — sem senha, DSN ou cookie.

---

## 6. Configuração (problema conhecido)

`getenv()` **não** lê o `.env` do Vite. HostGator Apache em geral **não** define `DB_*` sozinha.

Mecanismo sem alterar `database.php`:

1. `load-env.php` acima de `public_html` (`putenv`).
2. `auto_prepend_file` no MultiPHP INI do domínio.
3. As mesmas variáveis no SSH antes de `php tools/migrate.php`.

Variáveis:

| Nome no código | Notas |
| --- | --- |
| `DB_HOST` | obrigatório |
| `DB_PORT` | opcional, `3306` |
| `DB_DATABASE` | obrigatório (**não** `DB_NAME`) |
| `DB_USERNAME` | obrigatório (**não** `DB_USER`) |
| `DB_PASSWORD` | opcional |
| `APP_ENV` | default `production` |
| `APP_ALLOWED_ORIGINS` | CSV; vazio = same-origin, sem `*` |
| `SESSION_NAME` | default `agendaqui_sid` |
| `RATE_LIMIT_DIR` | fortemente recomendado |

Banco: `docs/HOSTGATOR-DATABASE-SETUP.md`.

---

## 7. Ordem de publicação (quando a Fase seguinte autorizar)

1. Checkout em `/home/USUARIO/agendaqui/` (api, database, tools, storage).
2. Criar `load-env.php` (0600) e MultiPHP `auto_prepend_file`.
3. Criar MySQL vazio no cPanel (ainda sem apply até o operador mandar).
4. `npm run build` local → enviar **conteúdo** de `dist/` para `public_html/`.
5. Enviar `api/` para `public_html/api/` **e** manter `agendaqui/api/`.
6. PHP 8.x + `pdo_mysql` no MultiPHP do domínio.
7. Só então: health → status → dry-run → apply (ver setup de banco).
8. Checklist: `docs/HOSTGATOR-POST-DEPLOY-CHECKLIST.md`.

Rollback: `docs/HOSTGATOR-ROLLBACK.md`.

---

## 8. Apache (esperado, não medido no host)

`public/.htaccess`: ficheiros reais passam; `/api` não cai na SPA; resto → `index.html`.

`api/.htaccess`: sem listing; `bootstrap/` → 403; `.sql` `.md` `.log` `.ini` `.env` negados.

**TESTE NÃO EXECUTADO — Apache HostGator real.**

---

## 9. HTTPS e cookies

SPA e API no **mesmo** host HTTPS. Cookie `agendaqui_sid`: HttpOnly, SameSite=Lax, Secure se o PHP vir `HTTPS` ou porta 443.

Não misturar `http://` na SPA com `https://` na API.

---

## 10. Pacote mínimo (resumo)

Enviar à web: `dist/*` + `api/`.  
Fora da web: `database/`, `tools/`, `storage/rate-limit/`, `load-env.php`.  
Nunca: Node, `.env`, Git, dumps, migrate via HTTP.
