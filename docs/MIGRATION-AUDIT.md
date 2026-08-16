# Auditoria de migração Supabase → PHP + MySQL

**Branch:** `migration-hostgator`  
**HEAD:** `fb59826` — `refactor: convert frontend to static spa`  
**Data:** 2026-08-16  
**Escopo:** somente mapeamento. Nenhuma implementação.

O frontend (React, Vite, TanStack Router, Tailwind, shadcn) permanece. A migração é só backend/comunicação.

---

## 1. Estado do Git (no momento da auditoria)

- Branch: `migration-hostgator`
- HEAD: `fb59826`
- Working tree: `docs/STATUS.md` untracked; este arquivo é novo e não commitado
- Sem push

---

## 2. Arquivos analisados

| Área | Caminhos |
| --- | --- |
| Schema | `supabase/migrations/*.sql` (~40 arquivos) |
| Tipos gerados | `src/integrations/supabase/types.ts` |
| Client | `src/integrations/supabase/client.ts`, `client.server.ts` |
| Auth UI | `src/routes/auth.tsx`, `src/integrations/lovable/index.ts` |
| Queries | `src/lib/queries.ts`, `panel.ts`, `admin.ts`, `claims.ts`, `favorites.ts`, `blog.ts`, `events.ts`, `marketplace.ts`, `publicServices.ts`, `siteContent.ts`, `navItems.ts` |
| Funções convertidas | `src/lib/*.functions.ts`, `spa-auth.ts` |
| Legacy Start | `src/legacy-server/**` |
| Env | `.env.example` (nomes apenas); `.env` está no Git |
| PWA | `public/sw.js`, `public/manifest.webmanifest` |
| Docs Fase 1 | `docs/AUDIT.md`, `STATIC-SPA.md`, `SERVER-FUNCTIONS-MIGRATION.md` |

---

## 3. Inventário de tabelas

Legenda de uso: **usado** = `supabase.from("...")` no frontend atual. **órfã** = existe no schema/types, sem query no `src/` (exceto `types.ts`).

| Tabela | Descrição | Uso no front | Ops | Relacionamentos | Público | Auth | Admin | Migrar? | Complexidade | Risco |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `user_roles` | Papéis | indireto (`has_role`) | R via RPC | `user_id` → auth.users | | Sim | Sim | **Sim** | M | Alto (authz) |
| `profiles` | Perfil | `panel.ts`, marketplace | R/U | `id` = auth.users | leitura ampla | Sim | | **Sim** | B | Médio (PII) |
| `cities` | Cidades | queries, admin, geo | R/U | lat/lng | Sim | | Sim write | **Sim** | B | Baixo |
| `categories` | Categorias empresa | queries | R | | Sim | | Sim write | **Sim** | B | Baixo |
| `companies` | Núcleo do produto | queries, panel, admin, scan | CRUD | owner, city, JSONB | ativas | dono | Sim | **Sim** | A | Alto |
| `company_categories` | N:N | queries | R | company↔category | Sim | dono write | | **Sim** | B | Baixo |
| `company_media` | Galeria | join em queries | R | company | Sim | dono | | **Sim** | B | Baixo |
| `company_views` | Pageviews | insert público, admin count | I / count | company | insert | | SELECT | **Sim** | B | Médio (spam) |
| `reviews` | Avaliações | queries, ReviewsSection, panel | R/I/U | company, user | Sim | próprio | | **Sim** | M | Médio |
| `leads` | Orçamentos | QuoteDialog, panel, admin | I / R | company | insert | dono lê | lê todos | **Sim** | B | Médio (PII) |
| `leads_planos` | Lead de plano | `/planos`, admin | I / R | | insert | | lê | **Sim** | B | Médio |
| `favorites` | Favoritos | favorites.ts, painel | R/I/D | user, company | | próprio | | **Sim** | B | Baixo |
| `newsletter_subscribers` | Newsletter | NewsletterForm | I | | insert | | lê | **Sim** | B | Médio |
| `company_claims` | Reivindicar | claims.ts, admin | I/R + RPC | company, user | | próprio | U/D + RPC | **Sim** | A | Alto |
| `posts` | Blog/CMS | admin.blog, duplicates, view | CRUD | author, city | published | staff | | **Sim** | M | Médio |
| `post_categories` | N:N posts | schema | | | Sim | staff | | Sim se usar | B | Baixo |
| `blog_posts` | **VIEW** de `posts` type=blog | `blog.ts` | R | | published | | | Unificar em `posts` | B | Baixo |
| `blog_posts_legacy` | tabela antiga | não | | | | | | Não migrar dados mortos sem checar | B | Baixo |
| `events` | Eventos | events.ts, admin, scan | CRUD | city, company | published | dono/staff | | **Sim** | M | Médio |
| `event_categories` | Cat. evento | events.ts | R | | Sim | | write | **Sim** | B | Baixo |
| `shows` | Atrações | admin.eventos, events.ts | CRUD | event | via event published | dono | | **Sim** | B | Baixo |
| `listings` | Classificados | marketplace, painel | CRUD | user, city | ativo | dono | | **Sim** | M | Médio |
| `listing_categories` | Cat. anúncio | marketplace.ts | R | | Sim | | write | **Sim** | B | Baixo |
| `listing_messages` | Chat anúncio | marketplace, painel | I/R/U | listing, users | | participantes | | **Sim** | M | Alto (PII) |
| `listing_reports` | Denúncia | marketplace.$slug | I | listing | | reporter | lê | **Sim** | B | Médio |
| `public_services` | Serviços públicos | publicServices.ts, admin | CRUD | city | ativos | | write | **Sim** | B | Baixo |
| `emergency_contacts` | Emergência | idem | CRUD | city | ativos | | write | **Sim** | B | Baixo |
| `plans_config` | Planos | admin.ts | R/U | | Sim | | write | **Sim** | B | Médio |
| `system_settings` | CMS textos/menu | siteContent, navItems, admin | R/U | JSON value | chaves public | | write | **Sim** | M | Médio |
| `qa_tickets` | QA | qa.functions | I / admin CRUD | city, user | insert | own select | all | **Sim** | M | Alto (PII/logs) |
| `qa_ticket_comments` | Comentários QA | qa.functions | I/R | ticket | | | Sim | **Sim** | B | Médio |
| `qa_ticket_events` | Histórico QA | qa.functions + trigger | R | ticket | | | Sim | **Sim** | B | Baixo |
| `push_subscriptions` | Web Push keys | push.functions, admin stats | CRUD | user | | próprio | SELECT all | **Sim** | A | Alto |
| `push_notifications` | Campanhas | admin-push | admin CRUD | | | | Sim | **Sim** | A | Alto |
| `push_deliveries` | Entregas | admin-push | R | notif, user, sub | | own R | all | **Sim** | M | Médio |
| `push_inbox` | Inbox | push.functions | R/U/D/I | user, notif | | próprio | | **Sim** | M | Médio |
| `notification_preferences` | Prefs | push.functions | R/U | user | | próprio | | **Sim** | B | Baixo |
| `notification_templates` | Templates | admin.push.templates | R | | Sim | | write | **Sim** | B | Baixo |
| `media` | Metadados storage | CompanyImageUpload, marketplace | I | owner | | dono | | **Sim** ou fundir uploads | B | Médio |
| `notifications` | Inbox genérica | **órfã no front** (trigger QA/claims escreve) | — | user | | own | | **Sim** se quiser inbox unificada | B | Baixo |
| `promotions` | Tabela promoções | **órfã** — o app usa `companies.promotions` JSONB | — | company | | | | Avaliar: não migrar se vazia | B | Baixo |
| `appointments` | Agenda | **órfã** | — | company | | | | Só se houver dados | M | Baixo |
| `banners` | Banners | **órfã** | — | | | | | Só se houver dados | B | Baixo |
| `analytics_events` | Analytics | **órfã** | — | | insert policy | | | Opcional | B | Baixo |
| `marketplace_items` | Marketplace antigo | **órfã** — classificados usam `listings` | — | company | | | | Não migrar sem dados | B | Baixo |
| `company_projects` | Portfólio | **órfã** | — | company | | | | Checar dados | B | Baixo |
| `company_faqs` | FAQ empresa | **órfã** | — | company | | | | Checar dados | B | Baixo |

`auth.users` (schema `auth` do Supabase) **não é tabela public**. No MySQL vira `users` própria.

---

## 4. Tipos PostgreSQL especiais

### Enums

| Enum | Valores | Uso MySQL |
| --- | --- | --- |
| `app_role` | admin, company_owner, user, editor, publisher | `VARCHAR(32)` + check, ou ENUM |
| `post_type` | article, news, blog, promo, event | VARCHAR |
| `publish_status` | draft, scheduled, published, archived | VARCHAR |
| `appointment_status` | pending, confirmed, cancelled, completed, no_show | só se migrar appointments |
| `listing_status` | ativo, vendido, pausado, removido | VARCHAR |
| `listing_condition` | novo, seminovo, usado | VARCHAR |
| `qa_status` / `qa_priority` / `qa_type` | ver migration QA | VARCHAR |
| `public_service_category` | saúde, educação, etc. | VARCHAR |

**Não converter ENUM PG → ENUM MySQL cegamente:** alterar ENUM no MySQL exige `ALTER TABLE`. Preferir VARCHAR + validação PHP.

### Arrays / JSON

| Onde | Tipo PG | MySQL |
| --- | --- | --- |
| `posts.tags` | `text[]` + GIN | JSON array |
| `listings.images` | array/json de URLs | JSON |
| `companies.hours` | JSONB | JSON |
| `companies.promotions`, `differentials`, `badges`, `certifications`, `quality_scores`, `financing_info` | JSON/JSONB | JSON |
| `cities.featured_category_ids` | `uuid[]` | JSON de UUIDs |
| `push_notifications.buttons`, `audience` | JSONB | JSON |
| `qa_tickets.device`, `console_logs`, `network_logs`, `extra` | JSONB | JSON |
| `system_settings.value` | JSON | JSON |
| `qa_ticket_events.payload` | JSONB | JSON |

### UUID / tempo / índices

| PG | MySQL | Nota |
| --- | --- | --- |
| `UUID` + `gen_random_uuid()` | `CHAR(36)` + UUID() no PHP (`ramsey/uuid` ou `uniqid` + validação) | Não usar INT auto se quiser URLs estáveis |
| `TIMESTAMPTZ` | `DATETIME(3)` UTC | App grava/lê ISO UTC |
| `NUMERIC(9,6)` lat/lng | `DECIMAL(9,6)` | |
| `NUMERIC` rating/score | `DECIMAL(4,2)` / `DECIMAL(10,2)` | |
| GIN `pg_trgm` em name/title | FULLTEXT ou `LIKE` + índice prefixo | ver §10 |
| GIN `tags` | índice em JSON gerado ou tabela `post_tags` | |

**Não converter direto:** `SECURITY DEFINER`, RLS, `auth.uid()`, `auth.users`, sequences de ticket (`qa_ticket_seq`), views `blog_posts`.

---

## 5. Policies RLS → regra PHP

Cada policy vira `requireUser` / `requireAdmin` / checagem de dono **no PHP**. O React **não** é barreira.

### Padrões

| Policy (resumo) | PHP |
| --- | --- |
| `USING (true)` SELECT | endpoint público GET |
| `auth.uid() = user_id` / `owner_id` | `requireUser(); $uid === $row['owner_id']` |
| `has_role(auth.uid(),'admin')` | `requireAdmin()` |
| `has_role(...,'editor'\|'publisher')` | `requireStaff(['editor','publisher','admin'])` |
| INSERT público `WITH CHECK (true)` | endpoint público + rate limit + validação |
| SELECT `status = 'active'/'published'` | `WHERE status = ...` no SQL; admin/dono vê o resto |

### Tabelas críticas (não perder)

| Tabela | Quem lê | Quem escreve |
| --- | --- | --- |
| `companies` | público: `status=active`; dono/admin: todas | insert/update: dono ou admin; delete: **só admin** no RLS (o front `deleteMyCompany` pode falhar para dono) |
| `company_claims` | próprio ou admin | insert próprio; update/delete admin; approve/reject só RPC admin |
| `push_notifications` | só admin | só admin |
| `push_subscriptions` | próprio ou admin (admin vê chaves) | insert/update próprio; delete próprio ou admin |
| `qa_tickets` | próprio se `user_id`; admin all | insert anon (WITH CHECK frouxo); update só admin |
| `qa_ticket_comments/events` | só admin | só admin |
| `listing_messages` | buyer ou seller | insert participante |
| `leads` | dono da empresa ou admin | insert público |
| `reviews` | público | insert/update próprio |
| `user_roles` | usuário lê só a própria linha | sem INSERT autenticado (só DEFINER) |

### Policies a reimplementar com cuidado

1. **QA INSERT** não trava `status`/`priority`/`assigned_to` — no PHP validar whitelist de colunas.
2. **companies UPDATE** do dono não trava `plan`/`featured` — no PHP dono não pode alterar plano.
3. **events/posts staff** — editor/publisher existem no enum; o front admin hoje só checa `isAdmin`.
4. **push_inbox embed** de campanha: usuário comum não tem SELECT em `push_notifications` (já documentado na Fase 1).

---

## 6. RPCs e triggers

| Nome | Tipo | Objetivo | Chamadores | Vira |
| --- | --- | --- | --- | --- |
| `has_role` | RPC DEFINER | existe role? | spa-auth, admin.ts | PHP: query `user_roles` |
| `nearest_city` | RPC INVOKER | Haversine 1 cidade | cityDetect | PHP GET `/api/geo/nearest` **ou** cálculo no browser + lista de cidades |
| `approve_company_claim` | RPC DEFINER | owner_id, role, rejeita outros, notifica | claims.ts | PHP POST admin transacional |
| `reject_company_claim` | RPC DEFINER | rejeita + notifica | claims.ts | PHP POST admin |
| `get_weekly_ranking` | RPC DEFINER | ranking premium 7d | painel.ranking | PHP GET autenticado + regra premium |
| `refresh_company_rating` | FUNCTION | média/count reviews | trigger reviews | PHP após review **ou** trigger MySQL |
| `trg_reviews_refresh_company` | trigger fn | chama refresh | AFTER reviews | trigger MySQL / service |
| `handle_new_user` | trigger auth.users | cria `profiles` | signup | PHP no register |
| `grant_default_admin` | trigger auth.users | admin se e-mail fixo | signup/confirm | **seed/config**, nunca hardcode em trigger público |
| `set_updated_at` | trigger | `updated_at = now()` | várias tabelas | `ON UPDATE CURRENT_TIMESTAMP` ou PHP |
| `qa_on_status_change` | trigger DEFINER | evento + notificação | UPDATE qa | PHP no update de ticket |
| `enforce_premium_verified` | trigger companies | regras premium | UPDATE companies | PHP na atualização de plano |
| `show_trgm` / `show_limit` | extensão pg_trgm | não usadas no app | — | **não migrar** |

---

## 7. Autenticação (hoje → PHP)

### Hoje (Supabase Auth)

| Fluxo | Onde | Mecanismo |
| --- | --- | --- |
| Cadastro e-mail/senha | `auth.tsx` `signUp` | Supabase; confirmação de e-mail possível |
| Login | `signInWithPassword` | JWT no `localStorage` (supabase-js) |
| Logout | Header, painel.perfil `signOut` | |
| Sessão | `getUser`, `onAuthStateChange` | persistSession + autoRefresh |
| Reset senha | `resetPasswordForEmail` | e-mail Supabase |
| Google | `lovable.auth.signInWithOAuth` → `setSession` | **Lovable Cloud**, não Google direto no app |
| Roles | `user_roles` + `has_role` | admin, company_owner, user, editor, publisher |
| Admin UI | `use-admin.ts` | RPC `has_role` admin |
| Painel | `painel.tsx` | só `userId` (qualquer logado) |
| Claims approve | RPC checa admin | |

Não há tela de “trocar senha logado” além do reset por e-mail.

### Arquitetura PHP proposta (não implementar)

```
POST /api/auth/register     password_hash(), cria users + profiles, e-mail confirmação
POST /api/auth/login        password_verify(), session_regenerate_id, cookie HttpOnly Secure SameSite=Lax
POST /api/auth/logout       session_destroy + cookie expire
GET  /api/auth/me           sessão → user + roles
POST /api/auth/forgot       token aleatório na tabela password_resets, e-mail
POST /api/auth/reset        valida token, password_hash
POST /api/auth/google       (fase posterior) OAuth próprio; NÃO depender de Lovable
```

- Cookie de sessão PHP, **não** JWT no localStorage (XSS atual some).
- CORS: mesma origem (`dominio.com` serve SPA + `/api`).
- CSRF: token em cookie + header em POSTs, ou SameSite cookie same-origin.
- Roles em `user_roles`; `company_owner` criado no approve claim (como a RPC).

---

## 8. Storage / uploads

| Bucket | Quem envia | Quem lê | Front | Limite no client |
| --- | --- | --- | --- | --- |
| `media` | autenticado, `owner = auth.uid()` | dono (signed URL 5 anos nos listings) | `marketplace.ts`, `CompanyImageUpload` | JPG/PNG/WebP, 5 MB |
| `qa-attachments` | anon + auth | só admin | `BugReportButton` | print/vídeo |

Proposta HostGator (filesystem):

```
/uploads/companies/{companyId}/
/uploads/listings/{userId}/
/uploads/qa/{ticketId}/   (fora do docroot ou com auth admin)
```

PHP: validar MIME real (`finfo`), extensão, tamanho, nome `bin2hex(random_bytes(8))`, gravar path relativo no MySQL. URL pública `/uploads/...` via Apache. QA: não servir sem sessão admin.

---

## 9. Busca

| Hoje | Onde |
| --- | --- |
| `name.ilike` / `tagline.ilike` | `queries.ts` buscar/suggest |
| `events.title` ilike | `events.ts` |
| `qa_tickets.description` ilike | `qa.functions` admin |
| Índice `gin_trgm` companies/posts/listings | migrations; **o client não chama `similarity()`** |

MySQL: `LIKE '%q%'` (igual ao ilike atual) + índice FULLTEXT em `companies (name, tagline)` para depois. Sem PostGIS. Sem `unaccent` nativo — normalizar no PHP (`iconv`).

---

## 10. Geo

| Peça | Hoje | PHP? |
| --- | --- | --- |
| GPS | browser → `nearest_city` | opcional; pode calcular no client com lista de cidades |
| IP | browser → `ipapi.co` → RPC | **sim** se quiser IP confiável |
| `cities.lat/lng` | NUMERIC | DECIMAL |
| Headers CF | perdidos na SPA | PHP `REMOTE_ADDR` |

---

## 11. Ranking (`get_weekly_ranking`)

- Exige `auth.uid()` e empresa **premium ativa** (`plan=premium`, `status=active`, `plan_expires_at` nulo ou futuro).
- Agrega 7 dias: `company_views` (×1), `leads` (×5), `reviews` count (×8) + `avg(rating)` (×4).
- Só empresas premium ativas; `RANK()`; `is_self`; limite 100.
- PHP: mesma fórmula em SQL MySQL ou query + sort. Sem DEFINER: filtrar no PHP se o user não for premium → 403.

---

## 12. Admin (rotas → backend)

O layout admin **não** será recriado. Só API.

| Rota | Tabelas | Ops | Role futura |
| --- | --- | --- | --- |
| `/admin` | companies, company_views | counts | admin |
| `/admin/empresas` | companies | CRUD | admin |
| `/admin/reivindicacoes` | company_claims + RPCs | R + approve/reject | admin |
| `/admin/cidades` | cities | R/U | admin |
| `/admin/servicos-publicos` | public_services | CRUD | admin |
| `/admin/emergencia` | emergency_contacts | CRUD | admin |
| `/admin/eventos` | events, shows | CRUD | admin |
| `/admin/blog` | posts | CRUD | admin (schema também editor/publisher) |
| `/admin/duplicados` | posts, companies, events | R + scan | admin |
| `/admin/push/*` | push_* | R/D; send desligado | admin |
| `/admin/qa` | qa_* | R/U + comment | admin |
| `/admin/menu` `/admin/textos` `/admin/configuracoes` | system_settings | R/U | admin |
| `/admin/planos` | plans_config | R/U | admin |
| `/admin/leads` | leads, leads_planos | R | admin |

Proteção atual: UI `isAdmin` + RLS. Futuro: **só** `requireAdmin()` no PHP.

---

## 13. Web Push

| Peça | Destino |
| --- | --- |
| PushManager + VAPID **pública** | continua no JS (`push-client.ts`, `sw.js`) |
| Guardar subscription | `POST /api/push/subscribe` |
| Envio | PHP + cron HostGator + biblioteca Web Push PHP (ex. `minishlink/web-push`) + VAPID **privada** só no servidor |
| Track | `POST /api/public/push/track` (substitui legacy) |
| Inbox/prefs | endpoints autenticados |

Sem worker Node. Cron: `* * * * * php cron/push-send.php` (ou a cada 5 min).

---

## 14. SEO / sitemap

- `public/robots.txt` → `Allow: /`
- Sitemap Start removido; cópia em `src/legacy-server/sitemap.xml.ts`
- Meta via `head()` no client (atraso SEO)

PHP: `GET /sitemap.xml` (rota Apache real, **não** rewrite para SPA — já reservável; hoje cai no `index.html`). Incluir `/`, `/buscar`, `/cidades/{slug}`, `/categoria/{slug}`, `/empresa/{slug}` ativas, blog/eventos published.

Canonical já existe em várias rotas; não alterar agora.

---

## 15. Variáveis de ambiente (somente nomes)

**Usadas no build SPA hoje**

- `VITE_SUPABASE_URL`
- `VITE_SUPABASE_PUBLISHABLE_KEY`
- `VITE_SUPABASE_PROJECT_ID`
- `VITE_LOVABLE_CONNECTOR_GOOGLE_MAPS_BROWSER_KEY`
- `VITE_LOVABLE_CONNECTOR_GOOGLE_MAPS_TRACKING_ID`

**Server-only (não VITE_; não devem ir ao browser)**

- `SUPABASE_URL`, `SUPABASE_PUBLISHABLE_KEY`, `SUPABASE_SERVICE_ROLE_KEY`
- `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`

**Futuro PHP (proposta)**

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `SESSION_NAME`, `APP_URL`
- `SMTP_*` (reset/confirmação)
- `VAPID_*`
- `GOOGLE_OAUTH_*` (quando sair do Lovable)
- `MAPS_BROWSER_KEY` (pode continuar VITE_ até o fim)

**Problema:** `.env` está no Git e **não** está no `.gitignore`. Não rotacionar agora. Recomendação: parar de versionar `.env` numa fase de higiene, sem apagar o arquivo local.

---

## 16. Mapa frontend → apiClient (não criar arquivos)

| Arquivo atual | API futura |
| --- | --- |
| `integrations/supabase/client.ts` | `services/apiClient.ts` (fetch + credentials) |
| `routes/auth.tsx`, lovable | `authApi.ts` |
| `lib/queries.ts` | `companiesApi.ts`, `citiesApi.ts`, `categoriesApi.ts` |
| `lib/panel.ts` | `companiesApi.ts` (owner) |
| `lib/admin.ts` + rotas admin | `adminApi.ts` |
| `lib/claims.ts` | `claimsApi.ts` |
| `lib/favorites.ts` | `favoritesApi.ts` |
| `ReviewsSection`, QuoteDialog | `reviewsApi.ts`, `leadsApi.ts` |
| `lib/blog.ts`, admin.blog | `postsApi.ts` |
| `lib/events.ts` | `eventsApi.ts` |
| `lib/marketplace.ts`, listings | `listingsApi.ts` |
| `lib/publicServices.ts` | `publicServicesApi.ts` |
| `lib/siteContent.ts`, `navItems.ts` | `settingsApi.ts` |
| `lib/push*.ts` | `pushApi.ts` |
| `lib/qa.functions.ts` | `qaApi.ts` |
| `lib/duplicates.functions.ts` | `adminApi.scanDuplicates` |
| `lib/cityDetect.functions.ts` | `geoApi.ts` |
| `CompanyImageUpload`, listing upload | `uploadsApi.ts` |
| `spa-auth.ts` | some; auth no cookie |

Troca incremental: um módulo por vez, `VITE_API_BASE=/api`.

---

## 17. Endpoints PHP propostos (conceitual)

Autenticação: **none** | **user** | **admin**. Erros: 400 validação, 401, 403, 404, 409 unique, 429 rate limit.

| Método | Endpoint | Auth | Tabelas | Segurança |
| --- | --- | --- | --- | --- |
| POST | `/api/auth/register` | none | users, profiles | rate limit; e-mail único |
| POST | `/api/auth/login` | none | users, user_roles | lockout |
| POST | `/api/auth/logout` | user | session | |
| GET | `/api/auth/me` | user | users, roles, profiles | |
| POST | `/api/auth/forgot` `/reset` | none | password_resets | token hash |
| GET | `/api/cities` | none | cities | só ativas |
| GET | `/api/categories` | none | categories | |
| GET | `/api/companies` | none | companies | só active + filtros |
| GET | `/api/companies/{slug}` | none | companies+joins | 404 se inativa (exceto dono/admin) |
| POST | `/api/companies` | user | companies | owner_id = session |
| PATCH | `/api/companies/{id}` | user | companies | dono; sem plan/featured |
| DELETE | `/api/companies/{id}` | admin | companies | alinhar RLS |
| POST | `/api/leads` | none | leads | honeypot + rate |
| GET | `/api/panel/leads` | user | leads | só empresas do user |
| POST | `/api/reviews` | user | reviews | 1 por user/empresa |
| GET/POST/DELETE | `/api/favorites` | user | favorites | próprio |
| POST | `/api/claims` | user | company_claims | user_id = session |
| GET | `/api/claims/mine` | user | | |
| POST | `/api/admin/claims/{id}/approve` | admin | claims, companies, roles | transação |
| POST | `/api/admin/claims/{id}/reject` | admin | | |
| GET | `/api/posts` `/posts/{slug}` | none | posts | published |
| CRUD | `/api/admin/posts` | admin | posts | |
| GET | `/api/events` | none | events, shows | published |
| CRUD | `/api/listings` | user | listings | dono |
| POST | `/api/listings/{id}/messages` | user | listing_messages | não o próprio anúncio |
| POST | `/api/uploads` | user | files | MIME/size |
| GET | `/api/ranking` | user | views, leads, reviews | premium |
| GET | `/api/geo/nearest` | none | cities | |
| CRUD | `/api/push/*` | user/admin | push_* | ver RLS |
| POST | `/api/qa` | none | qa_tickets | colunas whitelist |
| * | `/api/admin/*` | admin | várias | |
| GET | `/sitemap.xml` | none | cities, cats, companies, posts | arquivo PHP fora do rewrite SPA |

---

## 18. Estrutura futura do backend (não criar agora)

```
database/
  migrations/
    001_initial.sql          -- tabela migrations
    002_auth.sql             -- users, profiles, user_roles, password_resets
    003_taxonomy.sql         -- cities, categories
    004_companies.sql
    005_engagement.sql       -- reviews, leads, favorites, views
    006_claims.sql
    007_content.sql          -- posts, events, shows
    008_listings.sql
    009_public_services.sql
    010_settings.sql
    011_qa.sql
    012_push.sql
    013_indexes.sql
  seeds/
  schema/
public/   (ou api/ na raiz do public_html)
  api/
    index.php                -- front controller
    ...
  uploads/
  sitemap.php
cron/
  push-send.php
  migrate.php
```

**Migration runner (futuro):** tabela `migrations (id, filename, applied_at)`; executa `001_`, `002_` em ordem; nunca `DROP` automático; idempotente com `CREATE TABLE IF NOT EXISTS`; deploy = upload arquivos + `php cron/migrate.php`.

---

## 19. Arquitetura final

```
Browser (React SPA estática)
  → apiClient.ts (cookie sessão)
  → Apache
       ├─ /assets /index.html     (SPA)
       ├─ /api/*                  (PHP 8 + PDO)
       ├─ /uploads/*              (arquivos)
       └─ /sitemap.xml            (PHP)
            → MySQL/MariaDB

cron HostGator → PHP → Web Push (VAPID privado)
```

Node/npm **só** no `npm run build`.

---

## 20. Problemas encontrados (não corrigir agora)

| # | Problema | Impacto | Local | Recomendação |
| --- | --- | --- | --- | --- |
| 1 | `.env` no Git | vazamento de chaves | repo | parar de versionar; não rotacionar nesta fase |
| 2 | OAuth Google via Lovable | some sem Lovable | `lovable/index.ts` | OAuth PHP próprio depois do e-mail/senha |
| 3 | Dono pode `UPDATE plan` via PostgREST | upgrade grátis | RLS companies | PHP bloqueia colunas |
| 4 | QA INSERT frouxo | tickets forjados | RLS qa_tickets | whitelist PHP |
| 5 | `deleteMyCompany` vs RLS só admin | delete do dono falha | panel.ts | decidir regra e alinhar |
| 6 | `grant_default_admin` e-mail fixo | acoplado a um e-mail | migration | seed/config |
| 7 | Tabelas órfãs | lixo ou dados ocultos | promotions, appointments, etc. | dump antes de descartar |
| 8 | Inbox push sem SELECT de campanha | UI vazia | RLS | JOIN no PHP |
| 9 | pg_trgm no banco, ilike no app | índices subutilizados | migrations vs queries | FULLTEXT no MySQL |
| 10 | Confirmação/reset de e-mail | precisa SMTP | Auth | configurar na HostGator |

---

## 21. Dependências que somem / ficam

| Fica no front | Some / substitui |
| --- | --- |
| React 19, Vite, TanStack Router/Query, Tailwind, shadcn | `@supabase/supabase-js` |
| `web-push` no **dev** (hoje morto no SPA) | vira PHP |
| Google Maps browser key | pode ficar VITE_ |
| `@lovable.dev/cloud-auth-js` | OAuth PHP |

---

## 22. O que pode quebrar na troca

- Qualquer tela que chama `supabase.*` até o módulo ser trocado
- Login Google até OAuth próprio
- Imagens signed URL do bucket `media` (expiram / domínio muda)
- Sessão: usuários terão que logar de novo
- Realtime: não há subscription realtime crítica; reviews/leads são request-response
- `auth.uid()` em defaults some
- Ticket `QA-000123` precisa de sequence MySQL

---

## 23. Ordem recomendada (ajustada)

Mais segura: **fundação + auth + leitura pública** antes de writes e admin.

| Fase | Conteúdo |
| --- | --- |
| 2.1 | Esta auditoria |
| 2.2 | Schema MySQL (tabelas usadas) + dump seletivo do PG |
| 2.3 | Migration runner |
| 2.4 | Front controller PHP, PDO, erros, CORS same-origin |
| 2.5 | Auth e-mail/senha + sessão + roles (`/auth` aponta para API) |
| 2.6 | Cities, categories, companies GET público |
| 2.7 | Reviews, favorites, leads, views |
| 2.8 | Claims + approve/reject |
| 2.9 | Listings + uploads |
| 2.10 | Posts, events, shows, public_services |
| 2.11 | Admin (mesmo UI) |
| 2.12 | Push + cron |
| 2.13 | Sitemap + QA |
| 2.14 | Ranking, geo, settings/CMS |
| 2.15 | Remover `@supabase/supabase-js` e Lovable auth |
| 2.16 | Testes manuais + deploy HostGator |

Não remover Supabase até 2.15. Convívio temporário: módulos já migrados usam `/api`, o resto ainda Supabase.

---

## 24. Critérios de sucesso desta fase

- [x] Tabelas usadas vs órfãs
- [x] Policies mapeadas para PHP
- [x] RPCs e triggers
- [x] Enums e tipos especiais
- [x] Uploads/buckets
- [x] Auth e papéis
- [x] Endpoints futuros
- [x] Queries importantes (busca, ranking, geo)
- [x] Módulos admin
- [x] Estratégias MySQL, PHP, Auth, Storage, Push, Sitemap, runner, deploy

---

## 25. Próximo passo

**Não implementar.** Revisar este documento. Quando autorizado: Fase 2.2 — rascunho do schema MySQL **em arquivo novo**, sem aplicar no banco e sem trocar o frontend.
