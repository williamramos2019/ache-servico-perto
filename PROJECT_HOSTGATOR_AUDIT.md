# PROJECT_HOSTGATOR_AUDIT.md

**Projeto:** AgendaAqui (repo `ache-servico-perto`)  
**Data da auditoria:** 17 de agosto de 2026  
**Passo:** 1 — somente diagnóstico (nenhuma alteração de código, banco, dependências, commit ou push, além deste arquivo)  
**Arquitetura-alvo declarada:** Browser → SPA/PWA Vite → API PHP same-origin → PDO → MySQL/MariaDB na HostGator. Sem Node em produção. Sem Supabase como backend.

---

## Como esta auditoria foi feita

Inspeção estática do repositório: árvore de pastas, `package.json`, Vite, rotas, `src/`, `api/`, `database/migrations/`, `tools/`, PWA, leftovers Supabase/TanStack Start, e documentação existente em `docs/`.

Não foi executado `npm install`, `npm run build`, migration, script destrutivo, nem acesso ao cPanel. O estado **ao vivo** da HostGator não foi revalidado neste passo.

---

# 1. ESTRUTURA DO PROJETO

O repositório já **não** é um app Lovable/TanStack Start puro. É um **SPA Vite + API PHP + MySQL**, com restos do runtime original.

| Pasta / arquivo | Finalidade |
| --- | --- |
| `src/` | Frontend React 19 + TypeScript. Entrada: `src/main.tsx` (CSR, `createRoot`). |
| `src/routes/` | Rotas TanStack Router (file-based). ~100 arquivos. Árvore gerada: `src/routeTree.gen.ts`. |
| `src/features/` | Módulos de UI: `jobs`, `representatives`, `live-feed`. |
| `src/components/` | shadcn/ui (`components/ui`), site, painel, admin, QA. |
| `src/lib/` | Clientes PHP (`php-api.ts`, `php-auth.ts`, `domain-api.ts`, `queries.ts`), PWA, push, helpers. |
| `src/hooks/` | `use-admin`, `useSelectedCity`, `useCityAutoDetect`, `use-mobile`. |
| `src/services/` | **Não existe.** |
| `src/integrations/supabase/` | Cliente/tipos **legado**. Rotas ativas **não** importam. |
| `src/integrations/lovable/` | OAuth Lovable **legado**. Não está no grafo das rotas. |
| `src/legacy-server/` | Start/Nitro/Supabase server **excluído do tsconfig e do Vite**. |
| `api/` | API PHP same-origin (PDO). Bootstrap em `api/bootstrap/` (bloqueado por `.htaccess`). |
| `api/importer/` | Importador de empresas (CLI + bibliotecas). Não é rota pública. |
| `database/migrations/` | Schema MySQL **001–019**. Fonte de verdade de produção. |
| `deploy-banco/` | Cópia das migrations + instalador para o pacote HostGator. |
| `tools/` | `migrate.php`, cron CLI, testes PHP, `build-release.php`. |
| `public/` | PWA (`sw.js`, manifest, ícones, `offline.html`), `.htaccess` SPA, `robots.txt`. |
| `supabase/` | 41 migrations Postgres **históricas**. Não aplicar na HostGator. |
| `scripts/` | **Não existe.** |
| `uploads/` | Destino de `api/upload/image.php` (gitignored, com `.htaccess` anti-PHP). |
| `index.html` | Shell SPA + metas PWA. |
| `vite.config.ts` | Build SPA; proxy `/api` só no `vite dev`. |
| `package.json` | Nome ainda `tanstack_start_ts`; scripts Vite/Vitest. |
| `load-env.example.php` | Modelo de secrets PHP (fora de `public_html`). |
| `atualizar-banco.php` / `instalar-banco.php` | Instalador one-click (raiz do repo). |
| `sitemap.php` | Sitemap XML via MySQL (não SSR Node). |
| `VERSION.txt` | `1.1.0` |
| `docs/` | Deploys, schema, segurança, matrizes de migração. |

**Atenção:** `tsconfig.json` inclui `src/integrations/**` (clientes Supabase ainda compilam se importados). `src/legacy-server` está em `exclude`.

---

# 2. TECNOLOGIAS UTILIZADAS

| Tecnologia | Versão (package / evidência) | Onde é usada | Necessária em produção HostGator | Funciona na HostGator |
| --- | --- | --- | --- | --- |
| React | ^19.2.0 | `src/` | Sim (bundle JS) | Sim, estático |
| TypeScript | ^5.8.3 | Build | Só no build | N/A no servidor |
| Vite | ^8.0.16 | `npm run build` → `dist/` | Só no build | N/A |
| TanStack Router | ^1.168.25 | Rotas SPA | Sim (bundle) | Sim |
| TanStack Query | ^5.83.0 | Fetch/cache | Sim (bundle) | Sim |
| TanStack Start | **não** está em `package.json` | Só `src/legacy-server/` | Não | Não usar |
| Nitro / Vinxi / SSR | Ausentes do runtime atual | leftovers `.output`/`.vinxi` no gitignore | Não | Não |
| Tailwind CSS | ^4.2.1 + `@tailwindcss/vite` | Estilos | Sim (CSS gerado) | Sim |
| shadcn / Radix | vários `@radix-ui/*` | UI | Sim (bundle) | Sim |
| react-hook-form + zod | ^7.71.2 / ^3.24.2 | Formulários / validação cliente | Sim | Sim |
| sonner | ^2.0.7 | Toasts | Sim | Sim |
| lucide-react | ^0.575.0 | Ícones | Sim | Sim |
| recharts | ^2.15.4 | Gráficos admin ads | Sim | Sim |
| date-fns | ^4.1.0 | Datas | Sim | Sim |
| html2canvas | ^1.4.1 | QA screenshots | Sim | Sim |
| PWA (custom SW) | `public/sw.js` v1.0.1 | Offline + push receive | Sim | Sim (Apache) |
| PHP | 8.1+ (uso de `array_is_list`, typed) | `api/`, `tools/`, `sitemap.php` | Sim | Sim (MultiPHP 8.1/8.2) |
| PDO / MySQL / MariaDB | InnoDB utf8mb4 | Dados | Sim | Sim (MariaDB 10.2+ p/ JSON) |
| Apache + `.htaccess` | `public/.htaccess`, `api/.htaccess` | SPA fallback, bloqueios | Sim | Sim (mod_rewrite) |
| Node.js | engines implícitos (Vite 8) | **Somente máquina de build** | Não em runtime | Não como processo |
| `@supabase/supabase-js` | ^2.108.2 | leftovers em `src/integrations` | **Não** (alvo) | Tecnicamente sim, **fora do alvo** |
| `@lovable.dev/cloud-auth-js` | ^1.1.2 | `src/integrations/lovable` | Não | Não necessário |
| `web-push` | ^3.6.7 | `src/lib/push-send.server.ts` (não importado pelas rotas) | Não neste PHP | Node-only |
| Biblioteca de mapas | **não** há Leaflet/Mapbox | Só campo `google_maps` em perfil + env Vite opcional | Não | N/A |
| OneSignal | Não encontrado | — | Não | N/A |

---

# 3. PACKAGE.JSON

Scripts atuais: `dev` = `vite dev`; `build` = `vite build`; `preview` = `vite preview`; `test` = `vitest run`.

### FRONTEND (vão para o bundle se importados)

React, React DOM, TanStack Router/Query, Tailwind, Radix, lucide, sonner, zod, react-hook-form, date-fns, recharts, cmdk, embla, vaul, class-variance-authority, fontes `@fontsource/*`, etc.

### BUILD (só na máquina que gera `dist/`)

`vite`, `@vitejs/plugin-react`, `@tanstack/router-plugin`, `@tailwindcss/vite`, `vite-tsconfig-paths`, TypeScript, ESLint, Prettier, Vitest, `@types/*`.

### BACKEND/NODE (problema se alguém ligar em produção)

- `web-push` — envio VAPID em Node. **Não há processo Node na HostGator.**
- `@supabase/supabase-js` e `@lovable.dev/cloud-auth-js` — ainda em `dependencies`. Rotas ativas não importam; risco de reativação acidental e de o Vite embutir se alguém importar.

### SSR / TANSTACK START / NITRO

Nenhuma dependência Start/Nitro no `package.json`. Código legado em `src/legacy-server/` (excluído).

### SUPABASE

`@supabase/supabase-js` ^2.108.2 + lockfile (`auth-js`, `postgrest-js`, `realtime-js`, `storage-js`, `functions-js`).

**O que pode atrapalhar Apache+PHP sem Node:** tentar servir o app com `vite preview` / `node`; importar `web-push` ou `client.server.ts` no SPA; depender de Edge Functions; esquecer o `dist/` + `api/` no `public_html`.

**Este passo não removeu nenhuma dependência.**

---

# 4. BUILD

| Item | Achado |
| --- | --- |
| Install | `npm install` (local / CI). Não roda na HostGator. |
| Dev | `npm run dev` (Vite) + proxy `/api` → `VITE_PHP_PROXY` ou `http://127.0.0.1:8098`. |
| Build | `npm run build` → Vite SPA. `outDir` padrão **`dist/`**. |
| Preview | `npm run preview` — servidor Node local; **não** é produção. |
| SSR | **Não** no build atual (`main.tsx` usa `createRoot`). |
| Processo persistente | **Não** para o frontend. PHP é request/response + cron CLI. |
| `public_html` | Conteúdo de `dist/` + pasta `api/` + `.htaccess` + `sitemap.php` + `uploads/`. Pacote oficial: `tools/build-release.php` → ZIP `AgendaAqui-hostgator-v*.zip`. |

Plugin Vite `excludeOldInstaller` tenta apagar `instalar.php` / `atualizar-banco.php` / `instalar-banco.php` **depois** do bundle, se existirem em `dist/`.

### COMPATÍVEL COM HOSTGATOR

- SPA estática (`index.html` + assets hashed).
- `.htaccess` fallback para TanStack Router.
- API PHP same-origin (`VITE_API_BASE` vazio em produção).
- PWA (`sw.js`, manifest, ícones).
- Cron PHP CLI / HTTP com `CRON_SHARED_SECRET`.

### PRECISA DE ADAPTAÇÃO

- Envio Web Push (hoje lança erro no admin; helper Node não entra no PHP).
- IA editorial (`ai_generate` → 503; `available: false`).
- Scrapers municipais (telas admin = placeholder “indisponível”).
- Dependências npm legado (Supabase, Lovable auth, `web-push`) ainda no `package.json`.
- SEO de páginas dinâmicas sem SSR (ver §19).
- Instaladores em `public/` vs cópia Vite (ver riscos).

### NÃO COMPATÍVEL (se alguém tentar usar como no Lovable)

- TanStack Start / Nitro / Node server.
- Supabase Auth, Realtime, Storage, Edge Functions como backend.
- `npm run dev` / `preview` como “produção”.
- Playwright / crawler Node no cPanel compartilhado.

---

# 5. SUPABASE — AUDITORIA COMPLETA

## Runtime do frontend (rotas e features)

**Nenhuma rota em `src/routes/` importa `@/integrations/supabase`.**  
Não há `supabase.from(`, `.rpc(`, `functions.invoke`, `channel(`, `auth.signIn` nas telas ativas.  
Testes em `tests/frontend-domain-contracts.test.ts` **exigem** que páginas-chave **não** contenham `supabase`.

Substituição efetiva: `phpGet` / `phpPost` em `src/lib/php-api.ts` (cookie de sessão + CSRF).

## Arquivos que ainda falam com Supabase (não são o backend da SPA)

| Arquivo | Função | Recurso | Substituição PHP | Status |
| --- | --- | --- | --- | --- |
| `src/integrations/supabase/client.ts` | `createClient` anon | Auth/DB client | `php-api.ts` + sessão PHP | Legado; Proxy lazy |
| `src/integrations/supabase/client.server.ts` | `createClient` **service role** | Admin bypass RLS | Nunca expor; PHP `require_role('admin')` | Legado perigoso se importado |
| `src/integrations/supabase/types.ts` | Tipos gerados Postgres | Schema histórico | migrations MySQL 001–019 | Referência |
| `src/legacy-server/auth-middleware.ts` | JWT `getClaims` | Auth | `api/bootstrap/auth.php` | Fora do build |
| `src/legacy-server/auth-attacher.ts` | `getSession` | Auth | `php-auth.ts` | Fora do build |
| `src/legacy-server/sitemap.xml.ts` | `from(cities/categories/companies)` | Database | `sitemap.php` | Fora do build |
| `src/integrations/lovable/index.ts` | `setSession` após OAuth Lovable | Auth | Não no alvo HostGator | Legado |
| `supabase/migrations/*.sql` (41 arquivos) | Schema Postgres + RLS | Database | **Não migrar à HostGator** | Arquivo histórico |
| `.env.example` | `VITE_SUPABASE_*` | Config | `load-env.php` MySQL | Template |
| `package.json` | `@supabase/supabase-js` | Lib | Remover depois (passo futuro) | Ainda listado |

## DATABASE (histórico vs atual)

O schema **de produção pretendido** está em `database/migrations/001–019` (MySQL), não nas 41 migrations Supabase.

Tabelas cobertas no MySQL (inventário §10): auth, catálogo, claims, engagement, content, listings, ops/push/QA, promotions órfãs + 013–019 cívico/ads/jobs/reps/whatsapp/transport/shopee.

**SELECT/INSERT/UPDATE/DELETE no frontend atual:** via PHP, não PostgREST.

**RPC histórico (não usado nas rotas):** `has_role` (docs antigas). Hoje: `user.roles` em `/api/auth/me.php` / `user_roles`.

**Realtime:** não há `channel`/`subscribe` no frontend ativo. “Ao vivo” = polling 30s (`useLiveFeed`).

**Storage:** uploads vão para disco `uploads/{kind}/` + tabela `media`. Sem buckets Supabase no código ativo.

**Functions / Edge:** pasta `supabase/functions` **não encontrada**. Nenhum `functions.invoke` no `src/` ativo.

## AUTH (Supabase)

Existia no produto original (`signInWithPassword`, JWT localStorage). **Fluxo ativo:** e-mail/senha PHP (§12). OAuth Google/Lovable **não** está ligado nas rotas `/auth`.

## Conclusão Supabase

| Uso | Quantidade no runtime SPA |
| --- | --- |
| Chamadas `supabase.from` em rotas | **0** |
| Auth Supabase em `/auth` | **0** |
| Realtime | **0** |
| Storage | **0** |
| Edge Functions | **0** |
| Dependência npm ainda presente | **1** (`@supabase/supabase-js`) |
| Arquivos de integração restantes | **4** em `src/integrations/supabase` + Lovable |
| Migrations Postgres | **41** (não usar no MySQL) |

---

# 6. SERVER FUNCTIONS

Busca por `createServerFn`, `"use server"`, `server-only`: **0 ocorrências** no código ativo.

O runtime TanStack Start foi desligado. O que resta:

### A) Wrappers client-side (nome antigo `*.functions.ts`) — já falam PHP

| Arquivo | Nomes | Finalidade | Auth | Tabelas / API | Node? | Conversão PHP |
| --- | --- | --- | --- | --- | --- | --- |
| `src/lib/push.functions.ts` | `subscribePush`, `unsubscribePush`, prefs, inbox | Push do usuário | `requireUser()` (cliente) + sessão PHP | `push_*` via `/api/ops/index.php` | Não | **Já PHP** |
| `src/lib/admin-push.functions.ts` | `listAdminPush`, `getAdminPush`, `deleteAdminPush`, `pushDashboardStats`, **`sendPushNow`** | Admin push | `requireAdmin()` | `push_notifications` | `sendPushNow` **ainda lança erro** | Listagem PHP; **envio falta** |
| `src/lib/qa.functions.ts` | create/list/update QA | Central de qualidade | user/admin | `qa_*` via ops | Não | Já PHP |
| `src/lib/duplicates.functions.ts` | scan duplicados | Admin duplicados | admin | posts/empresas/eventos via content | Não (heurística no browser) | Já PHP leitura |
| `src/lib/cityDetect.functions.ts` | GPS + IP (`ipapi.co`) | Cidade mais próxima | público | `/api/catalog/?op=nearest` | Não | Já PHP + geo IP **no browser** |

### B) Helper Node não ligado ao SPA

| Arquivo | Nome | Secrets | Node | Conversão |
| --- | --- | --- | --- | --- |
| `src/lib/push-send.server.ts` | `sendWebPush` | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Sim (`web-push`) | Reescrever em PHP (openssl + cURL) |

**Não importado** por nenhuma rota (`grep` sem hits). Risco: se importado, o Vite pode tentar empacotar `web-push`.

### C) Legado Start (fora do build)

`src/legacy-server/start.ts`, `server.ts`, `auth-*.ts`, `sitemap.xml.ts`, `push-track.ts`.  
`tsconfig` exclude + README: não entram no `dist/`.

### Contagem

| Categoria | Qtd |
| --- | --- |
| `createServerFn` ativos | **0** |
| Wrappers `*.functions.ts` | **5 arquivos** |
| Helpers Node órfãos | **1** (`push-send.server.ts`) |
| Arquivos legacy-server | **7** |

---

# 7. API

API PHP em `api/`. Envelope JSON `{ success, data }` / `{ success, false, error }`. CSRF em writes. Sessão cookie `agendaqui_sid`.

Abaixo: **arquivos HTTP** (não `bootstrap/`, não classes do importer). Muitos usam `?op=` / `body.op`.

### Auth e usuário

| URL | Métodos | Auth | Finalidade | Classificação |
| --- | --- | --- | --- | --- |
| `/api/auth/csrf.php` | GET | público | Token CSRF | PHP direto |
| `/api/auth/register.php` | POST | público + rate limit | Registro | PHP direto |
| `/api/auth/login.php` | POST | público | Login | PHP direto |
| `/api/auth/logout.php` | POST | sessão | Logout | PHP direto |
| `/api/auth/me.php` | GET | sessão | Usuário + roles | PHP direto |
| `/api/auth/reset-request.php` | POST | público | Pedido reset (e-mail) | PHP direto |
| `/api/auth/reset-confirm.php` | POST | token | Nova senha | PHP direto |
| `/api/users/me.php` | GET, PATCH | sessão | Perfil | PHP direto |

### Catálogo e conteúdo público

| URL | Métodos | Auth | Ops / notas | Classificação |
| --- | --- | --- | --- | --- |
| `/api/health.php` | GET | público | `status` + `database` | PHP direto |
| `/api/index.php` | GET | público | Índice API | PHP direto |
| `/api/catalog/index.php` | GET | público | `cities`, `categories`, `featured`, `search`, `suggest`, `company`, `reviews`, `similar`, `cities_by_ids`, `nearest` | PHP direto |
| `/api/content/index.php` | GET público / POST admin | misto | events, posts, public_services, emergency, settings | PHP direto |
| `/api/companies/index.php` | GET | público | lista | PHP direto |
| `/api/companies/show.php` | GET | público | detalhe | PHP direto |
| `/api/views/hit.php` | POST | público | views | PHP direto |
| `/api/newsletter/subscribe.php` | POST | público | newsletter | PHP direto |
| `/api/leads/create.php` | POST | público | lead empresa | PHP direto |
| `/api/leads/planos.php` | POST | público | lead planos | PHP direto |

### Empresas autenticadas

| URL | Métodos | Auth | Classificação |
| --- | --- | --- | --- |
| `/api/companies/create.php` | POST | user + CSRF | PHP direto |
| `/api/companies/update.php` | PATCH | **owner** + CSRF | PHP direto |
| `/api/companies/mine.php` | GET | user | PHP direto |
| `/api/upload/image.php` | POST | user + CSRF; kinds `company\|listing\|qa\|generic`; max 5 MB | PHP direto |
| `/api/reviews/upsert.php` | POST | user | PHP direto |
| `/api/favorites/index.php` | GET/POST/DELETE | user | PHP direto |
| `/api/claims/index.php` | GET/POST | user / admin approve-reject | PHP direto |
| `/api/listings/index.php` | GET/POST/PATCH/DELETE | público search; user write | PHP direto |
| `/api/panel/activity.php` | GET | user | PHP direto |

### Domínios de produto

| URL | Métodos | Auth | Ops principais | Classificação |
| --- | --- | --- | --- | --- |
| `/api/jobs/index.php` | GET | público | `list`, `show`, `facets`, `premium` | PHP direto |
| `/api/jobs/admin.php` (+ `admin/index.php`) | GET/POST/PATCH/DELETE | admin | sources, logs, CRUD, `sync` | PHP direto |
| `/api/representatives/index.php` | GET | público | `list`, `show`, `feed`, `ranking` | PHP direto |
| `/api/representatives/admin.php` | GET/POST/PATCH/DELETE | admin | CRUD, import, attendance | PHP direto |
| `/api/live-feed/index.php` | GET/POST/DELETE | público list; admin hide/blacklist | PHP direto |
| `/api/tourism/index.php` | GET/POST/PATCH/DELETE | público / admin | PHP direto |
| `/api/procurements/index.php` | GET/POST/PATCH/DELETE | público / admin | PHP direto |
| `/api/promotions/index.php` | GET/POST/PATCH/DELETE | público; owner; admin; entity `promotions\|coupons` | PHP direto |
| `/api/ads/index.php` | GET/POST/PATCH/DELETE | público list/track; admin CRUD | PHP direto |
| `/api/requests/index.php` | GET/POST/PATCH/DELETE | user create; admin resto | PHP direto |
| `/api/editorial/index.php` | GET/POST/PATCH/DELETE | admin | CRUD ok; **`ai_generate` 503** | Precisa adaptação (IA) |
| `/api/transport/index.php` | GET público / POST admin | linhas, horários, paradas | PHP direto |
| `/api/shopee/index.php` | GET/POST/PATCH | público feeds; admin toggle | PHP direto |
| `/api/whatsapp/subscribe.php` | POST | público | PHP direto |
| `/api/whatsapp/opt-out.php` | POST | token HMAC ou cron secret | PHP direto |
| `/api/whatsapp/index.php` | GET/POST | despacha subscribe/opt-out | PHP direto |

### Ops, admin, cron, push público

| URL | Métodos | Auth | Classificação |
| --- | --- | --- | --- |
| `/api/ops/index.php` | GET/POST/PATCH/DELETE | user (inbox/sub); admin (push list/stats/delete, QA) | PHP; **sem `push_send`** |
| `/api/admin/index.php` | GET/POST/PATCH/DELETE | admin | stats, companies, cities, plans, settings, leads |
| `/api/admin/backup.php` | GET/POST | `require_role('admin')` | export/import JSON allowlisted |
| `/api/cron/index.php` | POST | `X-Cron-Secret` | PHP direto |
| `/api/public/push/track.php` | POST | HMAC `PUSH_TRACK_SECRET` | PHP direto |
| `/api/public/push/resubscribe.php` | POST | endpoint allowlisted | PHP direto |

### Pode ser PHP diretamente

Quase toda a superfície acima **já é PHP**.

### Precisa adaptação

- Editorial AI (`ai_status` força `available: false`; `ai_generate` 503).
- Disparo Web Push (não há op de send; `sendPushNow` throw).
- Scrapers (não há `api/scraper/`).
- WhatsApp envio: só se `WHATSAPP_BOT_URL` + token no env (HTTP outbound). Sem isso, welcome/digest não enviam.

### Depende de Node

Nenhum endpoint PHP exige Node. O **envio push Node** (`web-push`) **não está exposto** como rota.

**Contagem:** ~**51 arquivos PHP de endpoint** (sem bootstrap/importer). Dezenas de `op` internas (content, ops, catalog, admin, transport).

---

# 8. WEBHOOKS

Não há webhooks de pagamento (Stripe), GitHub, Supabase DB webhooks, nem pasta `webhooks/`.

O que se **parece** com callback externo:

| Superfície | Método | Origem | Auth (sem valores) | Payload | Processamento |
| --- | --- | --- | --- | --- | --- |
| `/api/cron/index.php` | POST | Cron HostGator / operador | Header `X-Cron-Secret` vs env `CRON_SHARED_SECRET` (vazio = 403) | JSON `op` | jobs, reps, bus, whatsapp, scheduled-hooks |
| `/api/whatsapp/opt-out.php` | POST | Link assinado / cron | HMAC `WHATSAPP_OPTOUT_SECRET` **ou** cron secret | telefone / token | baixa opt-in |
| `/api/public/push/track.php` | POST | Service worker / browser | HMAC `PUSH_TRACK_SECRET` (não é o secret do cron) | `event`, `delivery_id`, `token` | contadores de entrega |
| `/api/public/push/resubscribe.php` | POST | Browser (endpoint rotacionado) | validação de URL allowlist FCM/Mozilla/Apple/WNS | subscription keys | UPDATE endpoint |

**Webhooks nomeados de terceiros:** **0**.  
Secrets: só nomes de variáveis em `load-env.example.php` / getenv — valores **não** reproduzidos aqui.

---

# 9. CRON / JOBS / WORKERS

Não há `pg_cron`, filas Redis, nem worker Node.

| Nome | Arquivo | Frequência (docs) | Finalidade | Node? | PHP Cron HostGator |
| --- | --- | --- | --- | --- | --- |
| scheduled-hooks | `tools/scheduled-hooks.php` | sugerido periódico | jobs due + WhatsApp welcome/digest + cleanup tokens | Não | Sim (CLI only; recusa HTTP) |
| jobs-sync | `tools/jobs-sync.php` + `cron_jobs_due` | por `sync_frequency_minutes` da fonte | HTTP GET HTTPS allowlisted → upsert `jobs` | Não | Sim |
| representatives-sync | `tools/representatives-sync.php` | sob demanda / cron | ingestão JSON itens | Não | Sim |
| bus-sync | `tools/bus-sync.php` | sob demanda | horários ônibus | Não | Sim |
| shopee-import | `tools/shopee-import.php` | sob demanda | CSV feeds env `SHOPEE_FEED_*_URL` | Não | Sim |
| transport-import | `tools/transport-import.php` | sob demanda | GTFS/CSV | Não | Sim |
| migrate | `tools/migrate.php` | deploy | aplica 001–019 | Não | Sim (uma vez) |
| HTTP dispatcher | `api/cron/index.php` | cPanel “wget/curl” | mesmos ops | Não | Sim se secret configurado |
| Live feed poll | `useLiveFeed` `refetchInterval: 30000` | browser | **não é cron** | Não | N/A |
| Push scheduler | — | — | **não implementado em PHP** | Node legado | Falta |

WhatsApp digest: só sexta UTC 08–18 (`cron_digest_is_due`), se bot env configurado.

---

# 10. BANCO DE DADOS

**Fonte de verdade HostGator:** `database/migrations/*.sql` (espelho em `deploy-banco/database/migrations/`).

Runner: tabela `migrations` (001) + `tools/migrate.php`. `CREATE TABLE IF NOT EXISTS`, seeds `INSERT IGNORE`. Sem procedures/triggers/views encontradas nas 001–019. JSON em várias colunas. FKs InnoDB.

### 001–012 — PRESERVAR / NÃO ALTERAR

| Nº | Arquivo | Tabelas / papel | Dependências |
| --- | --- | --- | --- |
| 001 | `001_create_migrations.sql` | `migrations` | — |
| 002 | `002_auth.sql` | `users`, `profiles`, `user_roles` | — |
| 003 | `003_companies.sql` | `cities`, `categories`, `companies`, `company_categories` | 002 |
| 004 | `004_engagement.sql` | `company_media`, `company_views`, `reviews`, `leads`, `leads_planos`, `favorites`, `newsletter_subscribers` | 003 |
| 005 | `005_claims.sql` | `company_claims` | 003 |
| 006 | `006_content.sql` | `event_categories`, `events`, `shows`, `posts`, `post_categories`, `public_services`, `emergency_contacts` | 003 |
| 007 | `007_listings.sql` | `listing_categories`, `listings`, `listing_messages`, `listing_reports`, `media` | 002/003 |
| 008 | `008_ops.sql` | settings, plans, notifications, push_*, qa_* | 002 |
| 009 | `009_orphans.sql` | `promotions`, `appointments`, `banners`, `analytics_events`, `marketplace_items`, `company_projects`, `company_faqs`, `blog_posts_legacy` | 003 |
| 010 | `010_seed_public.sql` | seed catálogo (não schema) | 001–009 |
| 011 | `011_company_import.sql` | `company_import_runs`, `company_sources`, `company_import_errors` | 003 |
| 012 | `012_transport.sql` | `transport_sources`, `transport_lines`, `transport_schedules`, `transport_stops` | — |

### 013–019 — aditivas (já no repo; não reescrever 001–012)

| Nº | Arquivo | Tabelas / alterações | Finalidade |
| --- | --- | --- | --- |
| 013 | `013_content_civic.sql` | `password_reset_tokens`, `blog_categories`, `editorial_posts`, `event_sync_logs`, `tourist_attractions`, `procurements`; ALTER `users.session_version` | civic + reset |
| 014 | `014_engagement_ads.sql` | `coupons`, `ad_campaigns`, `user_requests`, `live_feed_hidden` | ads/cupons/solicitações |
| 015 | `015_jobs.sql` | `job_sources`, `jobs`, `job_sync_logs` | empregos |
| 016 | `016_representatives_whatsapp.sql` | `representatives*`, `whatsapp_subscribers`, `scheduled_hook_runs` | reps + WA |
| 017 | `017_transport_compatibility.sql` | `bus_sync_logs` | logs ônibus |
| 018 | `018_shopee.sql` | `shopee_products` | afiliados |
| 019 | `019_reference_seeds.sql` | seeds turismo/civic `INSERT IGNORE` | não copia empresas |

**Postgres `supabase/migrations/` (41 arquivos):** inventário histórico. **Não aplicar** no MySQL. RLS/policies Postgres não existem no PHP (autorização na API).

Órfãs 009 (`appointments`, `banners`, `analytics_events`, `blog_posts_legacy`): schema presente; **frontend ativo não usa** (só `types.ts` legado).

---

# 11. DADOS EXISTENTES

Nenhum script de limpeza/import foi executado nesta auditoria.

Do **seed 010** (comentários no próprio SQL, export anon REST):

| Entidade | Qtd aproximada no seed |
| --- | --- |
| users / profiles | 7 (stubs; **não logam**; hashes dummy; e-mails `migrated-…@invalid.local`) |
| cities | 6 (Vespasiano, SJL, BH, Lagoa Santa, Pedro Leopoldo, Confins) |
| categories | 27 |
| companies | 209 |
| company_categories | 1760 |
| company_media | 48 |
| reviews | 26 |
| posts | 18 |
| public_services | 155 |
| emergency_contacts | 6 |
| system_settings | 5 |

019: atrações turísticas de referência (UUIDs estáveis), sem empresas.

**Produção HostGator:** o MySQL “já existente” pode ter **mais** dados que o seed. Este passo não consultou o servidor. Documentação de deploy alerta: backup antes de `migrate.php`; sem `DROP TABLE`.

Bairros: campo `neighborhood` em serviços/empresas, não tabela dedicada.

---

# 12. AUTENTICAÇÃO

### Fluxo atual (produção pretendida)

```
LOGIN (POST /api/auth/login.php)
  → password_verify + rate limit
  → session_regenerate_id
  → cookie HttpOnly, SameSite=Lax, Secure se HTTPS
  → SESSION uid + session_version
SESSÃO
  → GET /api/auth/me.php  ou  phpGetMe()
USUÁRIO
  → users + profiles
ROLE
  → user_roles (admin, company_owner, user, editor, publisher)
AUTORIZAÇÃO
  → require_auth / require_role('admin') / companies_require_owner
  → CSRF em writes (X-CSRF-Token)
```

Idle: 8 h (`AUTH_IDLE_SECONDS`). Cookie name: `SESSION_NAME` ou `agendaqui_sid`.

Registro: whitelist email/password/name; role **não** aceita do cliente.

Reset: tabela `password_reset_tokens` + e-mail SMTP (`api/bootstrap/mail.php`) se `MAIL_*` configurado.

Frontend: `src/lib/php-auth.ts`, rota `/auth`, `/reset-password`. `useAdmin()` lê `user.roles.includes("admin")`.

### O que permanece no PHP

**Tudo o que é sessão, hash, CSRF, roles e cookies.** Não voltar JWT Supabase.

### O que não está no alvo

OAuth Lovable / Google (`cloud-auth-js`). Confirmação de e-mail: coluna `email_verified_at` existe; fluxo de confirmação não foi mapeado como endpoint dedicado além do reset.

---

# 13. AUTORIZAÇÃO

Roles conhecidas (`AUTH_KNOWN_ROLES`): `admin`, `company_owner`, `user`, `editor`, `publisher`.

Na prática o código **enforcement** usa sobretudo:

| Perfil | Pode |
| --- | --- |
| Anônimo | Catálogo, busca, empregos, reps, transporte, blog, promoções, live-feed list, Shopee, health, cadastro/login, leads, newsletter, track ads, sitemap |
| `user` (autenticado) | Painel, favoritos, reviews, claims criar, listings, upload, empresas criar, empresas **próprias** (owner_id), promoções owner, ads do dono, inbox push, WhatsApp já coberto no público |
| Owner (`companies.owner_id` = uid) | PATCH empresa, mídia, promoções/cupons da empresa — mesmo sem role `admin` |
| `admin` | Todo `/admin/*` (UI) + APIs `persist_require_admin` / `require_role('admin')`: empresas globais, seed settings, backup, jobs admin, reps admin, editorial, scrapers UI, QA, push stats/delete, transport write, shopee admin, requests, tourism, procurements, live-feed hide |
| `editor` / `publisher` | Declarados no PHP; **pouco/nenhum** gate específico encontrado além de estarem na lista |

Não há matriz RBAC por permissão fina (ACL). Admin é binário na UI (`src/routes/admin.tsx`).

Cron: não usa sessão; usa secret compartilhado.

---

# 14. FRONTEND — ROTAS

SPA TanStack Router. Auth de página: checagens no componente (admin layout / painel), não middleware Start.

Legenda API: PHP = `php-api` / `domain-api` / `queries`. SF = server function Start (**nenhuma**). SB = Supabase nas rotas (**nenhum**).

### Públicas (amostra completa por arquivo de rota)

| URL | Arquivo | Feature | Auth | Dados / API |
| --- | --- | --- | --- | --- |
| `/` | `index.tsx` | home | não | catalog featured/cities |
| `/buscar` | `buscar.tsx` | busca empresas | não | catalog search |
| `/empresa/$slug` | `empresa.$slug.tsx` | ficha | não | catalog company + reviews |
| `/categoria/$slug` | `categoria.$slug.tsx` | categoria | não | catalog |
| `/cidades/$slug` | `cidades.$slug.tsx` | cidade | não | catalog |
| `/vespasiano` | `vespasiano.tsx` | landing cidade | não | conteúdo/catalog |
| `/auth` | `auth.tsx` | login/registro | não | `/api/auth/*` |
| `/reset-password` | `reset-password.tsx` | reset | não | reset-confirm |
| `/empregos` | `empregos.tsx` + layout | vagas | não | jobs API |
| `/empregos/$id` | `empregos.$id.tsx` | vaga | não | jobs show |
| `/empregos/premium` | `empregos.premium.tsx` | destaque | não | jobs premium |
| `/representantes` | layout + `index` | civico | não | representatives |
| `/representantes/$id` | `representantes.$id.tsx` | perfil | não | show |
| `/representantes/feed` | feed | atividades | não | feed |
| `/representantes/ranking` | ranking | ranking | não | ranking |
| `/agora` `/ao-vivo` | `agora.tsx` `ao-vivo.tsx` | live | não | live-feed 30s |
| `/roteiro-turistico` | turismo | turismo | não | tourism API |
| `/transparencia` | editais | civic | não | procurements |
| `/promocoes` | promoções+cupons | ofertas | não | promotions API |
| `/transporte` `/$slug` `/linhas` | transport | ônibus | não | transport API |
| `/ofertas-shopee` | Shopee | afiliados | não | shopee API |
| `/marketplace` `/$slug` | classificados | listings | não | listings API |
| `/blog` `/blog/$slug` | blog | posts | não | content posts |
| `/eventos` `/eventos/$slug` | eventos | events | não | content events |
| `/servicos-publicos` | serviços | public_services | não | content |
| `/emergencia` | SOS | emergency | não | content |
| `/favoritos` | favoritos | precisa login efetivo | favorites API |
| `/planos` `/cadastre-sua-empresa` `/contato` `/sobre` `/reputacao` `/o-que-fazer` | marketing / institucional | misto | leads / content |

### Painel (`/painel/*`) — sessão user/owner

Empresas, nova/editar, anúncios, promoções, favoritos, avaliações, leads, mensagens (listings), reivindicações, notificações + preferências, perfil, ranking.

APIs: `companies/mine`, listings, ads, promotions owner, claims, panel/activity, ops inbox/prefs.

### Admin (`/admin/*`) — `useAdmin()`

Dashboard, cidades, serviços públicos, emergência, empresas, imports, transporte, empregos, turismo, promoções, Shopee, anúncios, analytics, solicitações, ao-vivo, calendário editorial, backup, **integracoes / scrapers / blog-ai (placeholder)**, reivindicações, eventos, blog, duplicados, push, QA, menu, textos, planos, leads, configurações.

**Scrapers e blog-ai** hoje renderizam `AdapterStatusAdmin` (“não executam na HostGator”).

Meta `robots: noindex` em várias rotas admin/painel.

---

# 15. COMPONENTES E FEATURES

### `src/features`

| Módulo | Tipo |
| --- | --- |
| `jobs/*` | Dependente de API PHP (`jobsApi` / queries) |
| `representatives/*` | Dependente de API PHP |
| `live-feed/*` | Dependente de API PHP + poll 30s (substitui Realtime) |

### `src/components`

| Grupo | Tipo |
| --- | --- |
| `components/ui/*` | **Puros** (Radix/shadcn) |
| `site/*` (Header, Footer, cards, PWA, Shopee, live page…) | Puros ou **API PHP** |
| `panel/*` | **API PHP** (upload, hours, listings) |
| `admin/DomainAdmin.tsx` | **API PHP**; scrapers/IA = placeholder |
| `qa/BugReportButton` | **API PHP** (ops QA + upload) |

### `src/hooks`

| Hook | Tipo |
| --- | --- |
| `use-mobile` | Puro |
| `useSelectedCity` | localStorage + catalog |
| `useCityAutoDetect` | GPS/IP + catalog nearest |
| `use-admin` | **API PHP** `/auth/me` |

### `src/lib`

Quase todo **dependente de API PHP**.  
`utils.ts`, `format.ts`, `seasonalThemes.ts`, `frontend-domain-helpers.ts`: puros.  
`push-config.ts`: chave VAPID **pública** (expor no browser é o desenho Web Push).  
`push-send.server.ts`: **Node**, não no grafo SPA.  
`integrations/supabase`: **legado**, não usado pelas rotas.

**Dependentes de server function Start:** nenhum no grafo ativo.  
**Dependentes de Supabase nas rotas:** nenhum.

---

# 16. PWA

| Item | Situação |
| --- | --- |
| Manifest | `public/manifest.webmanifest` (standalone, atalhos SOS/buscar/públicos/favoritos) |
| Service worker | `public/sw.js` VERSION `v1.0.1`; precache shell; **não cacheia `/api/`** |
| Ícones | `public/icons/` 192, 512, maskable, apple-touch, badge |
| Offline | `public/offline.html` + fallback navigate |
| Instalação | `src/lib/pwa.ts` + `PWAInstallPrompt` |
| Push receive | SW escuta `push` / `notificationclick` (arquivo SW) |
| Background sync | não mapeado como sync API dedicada |

`index.html` declara manifest e apple-touch. Apache: `Cache-Control: no-cache` para `sw.js` e webmanifest.

| Classe | Itens |
| --- | --- |
| **FUNCIONA NA HOSTGATOR** | install, cache estático, offline shell, ícones, receive push **se** o push chegar |
| **PRECISA DE PHP** | subscribe (`/api/ops` op=subscribe), track, resubscribe |
| **PRECISA DE ADAPTAÇÃO** | **envio** (VAPID privado no PHP); chave pública hardcoded vs chave no servidor |

---

# 17. PUSH / NOTIFICAÇÕES

| Camada | Onde | Backend |
| --- | --- | --- |
| Subscribe browser | `push-client.ts` + `VAPID_PUBLIC_KEY` | PHP `ops` subscribe |
| Preferências / inbox | `push.functions.ts` | PHP ops |
| Admin list/stats/delete | `admin-push.functions.ts` | PHP ops GET/POST delete |
| **Admin send** | `sendPushNow` | **Lança erro** (“fase desativada”) |
| Node helper | `push-send.server.ts` | **Não usado** |
| Track | SW → `/api/public/push/track.php` | HMAC |
| Resubscribe | `/api/public/push/resubscribe.php` | allowlist hosts |
| Tabelas | `push_subscriptions`, `push_notifications`, `push_deliveries`, `push_inbox`, `notification_*` | MySQL 008 |
| Cron send | **não existe** | — |
| OneSignal | não | — |
| Supabase | não no fluxo ativo | — |

Env esperado (exemplo, sem valores): `PUSH_TRACK_SECRET`, futuro `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT`.  
`load-env.example.php` ainda comenta que disparo VAPID **não** roda neste PHP compartilhado.

---

# 18. UPLOADS / ARQUIVOS

| Tipo | Destino | Serviço | Supabase Storage? | HostGator |
| --- | --- | --- | --- | --- |
| Imagens empresa/listing/QA | `uploads/{kind}/{uuid}.ext` | `api/upload/image.php` | Não | Sim (disco + `.htaccess` nega PHP) |
| Metadados | tabela `media` | MySQL | Não | Sim |
| Backup JSON | `api/admin/backup.php` (fora da web, manifesto) | PHP | Não | Sim |
| Imports CSV empresas | `storage/imports/` | CLI importer | Não | Sim, **fora** de `public_html` |
| Avatar | `profiles.avatar_url` via URL de upload | PHP | Não | Sim |

Limite 5 MB; JPEG/PNG/WebP; QA pode WebM. Rate 30/h/user.

---

# 19. SEO

| Recurso | Estado | SSR/Node? |
| --- | --- | --- |
| `public/robots.txt` | `Allow: /` | Não |
| `sitemap.php` | URLs estáticas + slugs MySQL (empresas, eventos, posts, jobs, reps) | PHP, **não** Node |
| Meta / OG | `index.html` genérico + `head()` TanStack em várias rotas | SPA: crawler sem JS vê pouco |
| Canonical | não mapeado de forma central | — |
| Páginas dinâmicas | `/empresa/$slug`, `/blog/$slug`, etc. | Sem HTML pré-render |

**Dependem de SSR/Node:** nenhum no build atual. **Limitação:** SEO de fichas depende de JS ou de crawlers que executam JS. `docs/SEO-MIGRATION.md` existe como histórico.

Admin/painel: `noindex` no `head()`.

---

# 20. SEGURANÇA

**Nenhum valor secreto é reproduzido neste relatório.**

| Arquivo | Tipo do segredo | Risco | Recomendação (futuro; não feito agora) |
| --- | --- | --- | --- |
| `.env` (gitignored; presente no workspace) | URL/keys Supabase Vite; possíveis outras | Alto se commitado ou copiado ao `public_html` | Manter gitignore; não ir no ZIP |
| `.env.example` | nomes `VITE_SUPABASE_*`, `SUPABASE_SERVICE_ROLE_KEY` | Baixo (vazio) | Separar do runtime PHP |
| `load-env.php` (gitignored) | DB, SMTP, cron, push HMAC | Crítico | Só `/home/USUARIO/agendaqui/`, permissão 0600 |
| `load-env.example.php` | placeholders | Baixo | OK no repo |
| `src/integrations/supabase/client.server.ts` | lê **service role** do env | Crítico se bundlado | Não importar no SPA; remover depois |
| `src/lib/push-config.ts` | VAPID **público** | Baixo (público por desenho) | Casar com privado no servidor |
| `src/lib/push-send.server.ts` | espera VAPID privado no env Node | Médio se executado | Não usar Node na HostGator |
| `database/migrations/010_seed_public.sql` | hashes bcrypt dummy + nomes de perfil | Médio (PII seed) | Stubs não logam; não tratar como senhas reais |
| `atualizar-banco.php` | grava DB no `load-env.php` | Alto se ficar no docroot | Apagar após instalar; htaccess já bloqueia alguns nomes |
| `public/instalar*.php` (listados no glob) | instalador | Alto se Vite copiar ao `dist/` | Conferir se entram no release; plugin Vite tenta apagar |
| `docs/*` / ZIP release | histórico de contas | Médio | Scanner `build-release.php` recusa padrões de segredo |

Outros controles já no PHP: CSRF, rate limit, prepared statements, `ATTR_EMULATE_PREPARES => false`, rejeição SSRF em URLs (`domain_allowed_http_url`), push endpoints allowlist, backup allowlist de tabelas.

---

# 21. HOSTGATOR

| Componente | HostGator | Situação |
| --- | --- | --- |
| React SPA | SIM | Build Vite `dist/` — **compatível** |
| Vite build | SIM (fora do servidor) | Gerar no PC/CI, subir ZIP |
| PWA | SIM | Apache serve `sw.js` + manifest |
| Apache | SIM | `.htaccess` SPA + API |
| PHP | SIM | 8.1+ obrigatório |
| MySQL | SIM | migrations 001–019 |
| Node server | NÃO | Não necessário se o ZIP for o da SPA+PHP |
| SSR Node | NÃO | Não usado no `main.tsx` |
| Nitro | NÃO | Ausente |
| TanStack server | NÃO | Só leftover excluído |
| PHP Cron | SIM | `tools/*.php` + `/api/cron` |
| Supabase externo | TECNICAMENTE SIM | **NÃO será backend** (alvo do passo) |
| Envio Web Push | PARCIAL | Receive/subscribe sim; send **não** |
| Scrapers municipais / blog IA | NÃO (hoje) | UI honesta de indisponível |

---

# 22. MAPA SUPABASE/NODE → PHP/MYSQL

| Funcionalidade atual | Supabase/Node (origem) | Futuro PHP (já / falta) | Futuro MySQL | Complexidade |
| --- | --- | --- | --- | --- |
| Auth | Auth JWT | **Já** sessão PHP | `users`, `profiles`, `user_roles`, reset tokens | Baixa (feito) |
| Jobs | tables + hooks | **Já** API + cron fetch | `jobs*` | Média (fontes HTTP) |
| Representatives | tables + scrape Câmara | API **já**; scrape UI **falta** | `representatives*` | Média–alta |
| Feed ao vivo | Realtime | **Já** poll 30s | live_feed_hidden + unions | Baixa (feito) |
| Tourism | tables | **Já** | `tourist_attractions` | Baixa |
| Promotions | tables | **Já** | `promotions` | Baixa |
| Coupons | tables | **Já** | `coupons` | Baixa |
| Ads | tables | **Já** | `ad_campaigns` | Baixa |
| Requests | tables | **Já** | `user_requests` | Baixa |
| WhatsApp | hooks | subscribe/opt-out **já**; envio depende de bot HTTP | `whatsapp_subscribers` | Média |
| Notifications inbox | tables | **Já** | `notifications`, `push_inbox` | Baixa |
| Push send | Node `web-push` / Edge | **Falta** | `push_*` | Alta |
| Backup | dumps | **Já** PHP allowlist | subset tabelas | Média |
| Scrapers | Firecrawl/Node | **Falta** (placeholder admin) | `public_services`, `representatives` | Alta |
| Blog CRUD | tables | **Já** | `posts` | Baixa |
| Blog IA | Lovable AI gateway | **Falta** (503) | `posts` | Média |
| Claims | tables | **Já** | `company_claims` | Baixa |
| QA | tables | **Já** | `qa_*` | Baixa |
| Transport | tables + CSV | **Já** | `transport_*` | Média |
| Sitemap | server fn + Supabase | **Já** `sitemap.php` | várias | Baixa |
| Shopee | — (extra local) | **Já** | `shopee_products` | Baixa |
| OAuth Lovable | cloud-auth | **Fora do alvo** | — | N/A |

---

# 23. RISCOS (15)

| # | Risco | Impacto | Probabilidade | Solução recomendada (não executada) |
| --- | --- | --- | --- | --- |
| 1 | Pacote antigo no ar (`/api/jobs` 404 documentado em `docs/SUBIR-HOSTGATOR.md`) | Empregos e módulos 013+ quebram | Alta se não reenviar ZIP 1.1.0 | Upload completo `dist/`+`api/`+migrate 013–019 |
| 2 | PHP &lt; 8.1 no MultiPHP | API 500 | Média | Forçar 8.1/8.2 |
| 3 | `load-env.php` no docroot ou ausente | 403/500 / vazamento | Média | Só `~/agendaqui/`; AGENDAQUI_ENV_OK |
| 4 | Envio Web Push inexistente | Notificações não saem | Certa hoje | Implementar VAPID em PHP **ou** aceitar o gap |
| 5 | Scrapers/IA stub | Paridade visual com Lovable falha | Certa hoje | PHP cURL allowlist / provedor HTTP **ou** import CSV |
| 6 | Instaladores em `public/` copiados ao `dist/` | Superfície de ataque | Média | Garantir ausência no ZIP; htaccess |
| 7 | `.env` / service role reativados no frontend | Comprometimento total | Baixa–média | Não importar `client.server.ts`; tirar npm depois |
| 8 | Seed 010 ≠ dados reais do MySQL HostGator | Duplicar/faltar empresas | Média | migrate sem reseed destrutivo; backup primeiro |
| 9 | Alterar migrations 001–012 | Quebra FKs em produção | Alta se alguém “limpar schema” | Travar 001–012; só 013+ aditivo |
| 10 | Cron sem `CRON_SHARED_SECRET` | Jobs/WhatsApp parados | Alta se esquecer env | Configurar secret + cron CLI |
| 11 | SEO SPA | Fichas mal indexadas | Média | sitemap.php + metas; SSR fora do alvo |
| 12 | SW cache agressivo (versão `v1.0.1`) | Usuário vê JS velho | Média | Bump VERSION no SW a cada release |
| 13 | WhatsApp bot sem URL/token | Opt-in grava mas não envia | Alta | Configurar bot **ou** documentar como opcional |
| 14 | `web-push` no bundle se importado | Build quebra / Node no browser | Baixa | Manter fora do grafo SPA |
| 15 | Paridade Lovable Realtime/OAuth/Firecrawl | Expectativa “idêntico ao original” | Alta | Fechar acordo: PHP+MySQL+poll, não clone Start+Supabase |

---

# 24. PLANO DE MIGRAÇÃO (10 passos — **não executados**)

1. **Congelar 001–012.** Backup MySQL + `public_html`. MultiPHP 8.1+.  
2. **Build local:** `npm run build` + `php tools/build-release.php`. Conferir SHA e ausência de secrets no ZIP.  
3. **Upload:** `dist/` → document root; `agendaqui/` (api, tools, migrations, `load-env.php`) **fora** da web.  
4. **Env PHP:** `DB_*`, `CRON_SHARED_SECRET`, `PUSH_TRACK_SECRET`, SMTP; **não** service role.  
5. **`php tools/migrate.php --status` então migrate** até 019 (idempotente). Não DROP.  
6. **Smoke:** `/api/health.php`, `/api/jobs/index.php?op=list`, `/empregos`, `/auth`, CSRF login.  
7. **Cron cPanel:** `scheduled-hooks.php --task=all` (e jobs-sync se houver fontes).  
8. **Decidir gaps:** push send, scrapers, blog IA — implementar PHP **ou** manter indisponível explícito.  
9. **Limpeza futura (passo posterior):** remover npm Supabase/Lovable/`web-push` do bundle; não reativar `legacy-server`.  
10. **Operação:** ícones PWA, bump SW, backup PHP, rollback docs. Sem Node residente.

---

# 25. RESUMO EXECUTIVO

1. **O frontend atual pode virar SPA estática?** **Sim.** Já é: `createRoot` + Vite `dist/` + `.htaccess` fallback.  
2. **O build atual funciona sem Node em produção?** **Sim**, desde que se publique o `dist/` + PHP. Node só gera o bundle.  
3. **Quais partes dependem de Node?** Build (Vite). Legado: `web-push`, TanStack Start em `legacy-server` (não empacotado). Dev: `vite dev` / proxy.  
4. **Quantas server functions existem?** **0** `createServerFn`. 5 wrappers `*.functions.ts`. 1 helper Node órfão.  
5. **Quantas APIs existem?** ~**51** arquivos PHP de endpoint; dezenas de `op`.  
6. **Quantos webhooks existem?** **0** de terceiros. **3–4** callbacks assinados (cron, WA opt-out, push track/resubscribe).  
7. **Quantos cron/jobs existem?** **6+** scripts CLI (`scheduled-hooks`, `jobs-sync`, `representatives-sync`, `bus-sync`, `shopee-import`, `transport-import`) + dispatcher HTTP.  
8. **Quanto do frontend depende diretamente do Supabase?** **Nenhuma rota ativa.** Restam lib npm + 4 arquivos de integração + 41 SQL Postgres históricos.  
9. **O que precisa ser convertido para PHP?** Quase o CRUD **já está**. Falta: **envio Web Push**, **scrapers municipais**, **IA editorial**, opcionalmente OAuth (fora do alvo).  
10. **O que precisa ser convertido/adicionado ao MySQL?** Schema **001–019 já existe**. Não copiar migrations Supabase. Dados reais = o MySQL HostGator existente + seed 010 se for instalação vazia.  
11. **O que pode ser preservado sem alteração?** SPA, PWA receive, auth PHP, catálogo, claims, listings, jobs/reps/turismo/promoções/ads/requests/transporte/Shopee APIs, sitemap.php, migrations 001–012.  
12. **Maior risco da migração?** Publicar um **ZIP incompleto / PHP antigo** em cima de um MySQL já populado **sem backup**, ou tratar o sistema como se ainda fosse Supabase+Start — quebrando `/empregos` e módulos 013+ — **mais** o gap permanente de **push send / scrapers / IA** se a expectativa for paridade 100% com o Lovable.

---

## Contagens desta auditoria

| Item | Quantidade |
| --- | --- |
| Rotas `src/routes/*.tsx` (createFileRoute) | ~100 arquivos |
| `src/**/*.ts(x)` | ~251 |
| PHP em `api/` (incl. bootstrap/importer) | ~81 |
| Endpoints HTTP PHP | ~51 |
| Migrations MySQL | 19 (001–019) |
| Migrations Supabase (históricas) | 41 |
| `createServerFn` | 0 |
| Dependência npm Supabase | 1 |
| Cron CLI principais | 6 |
| Este relatório — outros arquivos modificados | **0** (somente este `.md` criado) |

---

## Confirmação de escopo (PASSO 1)

- Nenhum código de aplicação foi editado.  
- Nenhuma migration foi criada ou alterada.  
- Nenhum banco foi tocado.  
- Nenhuma dependência foi instalada ou removida.  
- Nenhum commit ou push.  
- Nenhum script destrutivo.

**Fim do PASSO 1.** Aguardar instruções para o PASSO 2.
