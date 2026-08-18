# Frontend/database integration matrix

Status: implemented on `migration-hostgator` through MySQL **013–019**, PHP domain APIs and Vite adapters. This document remains the contract; it does not authorize production deploys.

## 0. Implementation status (Aug 2026)

| Batch | Contract | Local status |
| --- | --- | --- |
| A inventory | 78 upstream SQL files vs MySQL 001–012 | Done. 64 upstream tables mapped; `bus_lines` stays as normalized `transport_*` (012). |
| B 013 civic/content | blog/editorial/tourism/procurements + deltas | Done. `013_content_civic.sql` |
| C 014 ads/moderation | coupons, ads, requests, live_feed_hidden | Done. `014_engagement_ads.sql` |
| D 015 jobs | jobs + public/admin PHP + `/empregos*` | Done. `015_jobs.sql` + `api/jobs` + `src/features/jobs` |
| E 016 civic WhatsApp | representatives + subscribe/opt-out | Done. `016_representatives_whatsapp.sql` |
| F 017 transport | `bus_sync_logs` only | Done. `017_transport_compatibility.sql` |
| G auth/realtime | reset PHP, polling instead of Realtime | Done. Reset endpoints + live-feed/listings polling |
| H hooks | HostGator cron CLI, HMAC push track | Done. `api/cron`, `tools/*-sync.php`, `api/public/push/*` |
| I frontend | no `createServerFn` / live Supabase client | Done in `src/routes` and `src/lib`. Leftover files stay under `src/legacy-server` and commented `src/integrations/supabase`. |
| J release | PHP tests, Vitest, `tsc`, Vite, ZIP | HostGator ZIP is rebuilt after each batch. Live HostGator HTTP is a separate check. |
| 018 Shopee | close-by-helper offers | Done. `018_shopee.sql` |
| 019 seeds | idempotent reference rows, no company/user copies | `019_reference_seeds.sql` (tourism + example jobs) |

**Still not a HostGator Node runtime:** municipal HTML scrapers, editorial model calls and VAPID web-push send stay admin stubs until a server-only credential is configured. They must not be copied from TanStack Start routes.

**Data policy:** local MySQL + `010_seed_public.sql` is source of truth. Connected Supabase is empty and unused.

## 1. Verified baseline and corrections

- Reference inspected: all **78** files in `supabase/migrations/` and generated `src/integrations/supabase/types.ts`.
- Final generated `public` schema: **64 tables, one view (`blog_posts`), 10 generated function entries and 13 enums**. The generated functions are `admin_export_full_backup`, `admin_restore_table_tx`, `get_weekly_ranking`, `has_role`, `increment_push_counter`, `nearest_city`, `refresh_company_rating`, `show_limit`, `show_trgm`, and `track_ad_event`; the two `show_*` entries are PostgreSQL extension helpers rather than product RPCs. Repeated `CREATE TABLE`, view and function statements in the 78-file history are revisions, not extra final objects.
- Reference routes directory: **104 files**, but only **103 route source modules**: 89 `.tsx` client/layout modules, 14 `.ts` server modules (one sitemap and 13 API/hooks), plus one `README.md`. Treating all 104 as routes is incorrect.
- Current root `database/migrations/` has **12 migrations**, **54 application tables** plus the migration ledger. Of the upstream tables, 45 are name-compatible and `bus_lines` has a deliberate local replacement (`transport_lines`); 18 upstream tables and the `blog_posts` view remain absent. The local schema also has eight deliberate local-only tables.
- The old statement in `docs/SERVER-FUNCTIONS-MIGRATION.md` that “Nada foi migrado para PHP” is no longer true: the current API already covers auth, catalog, companies, claims, content, favorites, leads, listings/messages/reports, newsletter, notifications/push/QA, panel, reviews, transport, uploads and views.
- `docs/MYSQL-SCHEMA-DESIGN.md` predates the latest upstream civic/jobs/live-feed/ads/tourism/request additions. Its core type-conversion decisions remain valid, but its inventory is incomplete.
- No credentials or `.env` values were read or copied. The integration contract assumes the Supabase project is empty; no Supabase data export is required.

Migration labels below mean: `existing NNN` = preserve and reconcile columns/indexes in place through additive follow-up migrations; `NNN` = proposed new migration ownership. Never edit an already-applied migration to retrofit the upstream shape.

## 2. Public schema matrix

### Identity, directory and company ownership

| Upstream public table | Current MySQL equivalent/status | Migration |
| --- | --- | --- |
| `user_roles` | `user_roles`; PHP session/admin checks replace RLS/`has_role` | existing 002 |
| `profiles` | `profiles`; PK remains the user id; upstream location/onboarding columns are missing locally | existing 002 + **013 delta** |
| `cities` | `cities` | existing 003 |
| `categories` | `categories` | existing 003 |
| `companies` | `companies`; preserve local write whitelist and import fields; upstream `services_offered` is missing locally | existing 003 + **013 delta** |
| `company_categories` | `company_categories` | existing 003 |
| `company_media` | `company_media`; filesystem URLs replace Storage objects | existing 004 |
| `company_projects` | `company_projects` | existing 009 |
| `company_faqs` | `company_faqs` | existing 009 |
| `company_views` | `company_views` | existing 004 |
| `reviews` | `reviews`; rating refresh already belongs in PHP transaction | existing 004 |
| `leads` | `leads` | existing 004 |
| `leads_planos` | `leads_planos` | existing 004 |
| `favorites` | `favorites` | existing 004 |
| `newsletter_subscribers` | `newsletter_subscribers` | existing 004 |

### Publishing, events and public information

| Upstream public table | Current MySQL equivalent/status | Migration |
| --- | --- | --- |
| `posts` | `posts`; query `type='blog'` instead of relying on view; upstream `category_id` is missing locally | existing 006 + **013 delta** |
| `post_categories` | `post_categories` | existing 006 |
| `blog_posts_legacy` | `blog_posts_legacy`; preserve, do not promote to canonical blog source | existing 009 |
| `blog_categories` | **missing**; taxonomy for blog/editorial UI | **013** |
| `editorial_posts` | **missing**; editorial calendar | **013** |
| `events` | `events`; upstream sync provenance/dedupe columns are missing locally | existing 006 + **013 delta** |
| `event_categories` | `event_categories` | existing 006 |
| `shows` | `shows` | existing 006 |
| `event_sync_logs` | **missing**; importer audit records | **013** |
| `public_services` | `public_services`; upstream verification metadata is missing locally | existing 006 + **013 delta** |
| `emergency_contacts` | `emergency_contacts` | existing 006 |
| `tourist_attractions` | **missing** | **013** |
| `procurements` | **missing** | **013** |

### Marketplace, promotions and advertising

| Upstream public table | Current MySQL equivalent/status | Migration |
| --- | --- | --- |
| `listing_categories` | `listing_categories` | existing 007 |
| `listings` | `listings` | existing 007 |
| `listing_messages` | `listing_messages`; replace Realtime channel with polling initially | existing 007 |
| `listing_reports` | `listing_reports` | existing 007 |
| `media` | `media`; local upload filesystem is canonical | existing 007 |
| `marketplace_items` | `marketplace_items`; legacy/orphan, preserve | existing 009 |
| `promotions` | `promotions`; distinct from `companies.promotions` JSON and upstream coupons; missing `city_id`, `category`, `discount_percent`, `image_url`, `link_url` and upstream indexes | existing 009 + **014 delta** |
| `coupons` | **missing**; company-owned coupon records | **014** |
| `ad_campaigns` | **missing**; ad configuration and counters | **014** |
| `analytics_events` | `analytics_events`; extend only additively for ad events | existing 009 |
| `appointments` | `appointments`; currently orphan/legacy, preserve | existing 009 |
| `banners` | `banners`; currently orphan/legacy, preserve | existing 009 |

### Notifications, support and settings

| Upstream public table | Current MySQL equivalent/status | Migration |
| --- | --- | --- |
| `system_settings` | `system_settings` | existing 008 |
| `plans_config` | `plans_config` | existing 008 |
| `notifications` | `notifications`; generated type confirms `payload`, not old `data` | existing 008 |
| `notification_preferences` | `notification_preferences` | existing 008 |
| `notification_templates` | `notification_templates` | existing 008 |
| `push_subscriptions` | `push_subscriptions` | existing 008 |
| `push_notifications` | `push_notifications` | existing 008 |
| `push_deliveries` | `push_deliveries`; upstream retry scheduling columns/index are missing locally | existing 008 + **014 delta** |
| `push_inbox` | `push_inbox` | existing 008 |
| `qa_tickets` | `qa_tickets` | existing 008 |
| `qa_ticket_comments` | `qa_ticket_comments` | existing 008 |
| `qa_ticket_events` | `qa_ticket_events` | existing 008 |
| `user_requests` | **missing**; public request intake/admin workflow | **014** |
| `live_feed_hidden` | **missing**; moderation list; blacklist remains in settings JSON | **014** |

### Jobs, civic representatives and messaging

| Upstream public table | Current MySQL equivalent/status | Migration |
| --- | --- | --- |
| `job_sources` | **missing** | **015** |
| `jobs` | **missing** | **015** |
| `job_sync_logs` | **missing** | **015** |
| `representatives` | **missing** | **016** |
| `representative_activities` | **missing** | **016** |
| `representative_attendance` | **missing** | **016** |
| `representative_sync_logs` | **missing** | **016** |
| `whatsapp_subscribers` | **missing** | **016** |

### Transport

| Upstream public table | Current MySQL equivalent/status | Migration |
| --- | --- | --- |
| `bus_lines` | `transport_lines`; map upstream `departures` JSON into normalized `transport_schedules`, retain route metadata | existing 012 + **017 compatibility** |
| `bus_sync_logs` | **missing**; do not overload company import runs | **017** |

### Upstream view

| Upstream object | Current status | Contract |
| --- | --- | --- |
| `blog_posts` view | absent by design | Keep `posts` canonical and implement the view shape in `GET /api/content?op=posts&type=blog&status=published`; an optional MySQL view may be added in 013 only if existing PHP consumers require its exact columns. |

### Local-only tables that are not upstream public tables

Preserve these through every batch: `company_claims` (005), `company_import_runs`, `company_sources`, `company_import_errors` (011), and `transport_sources`, `transport_schedules`, `transport_stops` (012). `users` replaces Supabase `auth.users` and is also mandatory local infrastructure. None may be dropped, renamed or repurposed to make an upstream import easier.

### Explicit additive deltas for existing tables

These changes belong to new migrations even though their tables already exist. Implement them with preflight checks and `ALTER TABLE`; never edit migrations 002–009.

| New migration | Existing table | Verified upstream delta |
| --- | --- | --- |
| **013** | `profiles` | Add nullable `city_id` FK → `cities`, `state`, `onboarding_completed_at`, and `onboarding_version`; add indexes for `city_id` and `state`. |
| **013** | `companies` | Add non-null `services_offered` JSON (converted from `text[]`, default empty array); update PHP read/write shapes and owner-safe whitelist. |
| **013** | `posts` | Add nullable `category_id` FK → new `blog_categories`, plus category and `(type,status,published_at)` indexes. This is separate from legacy `post_categories`. |
| **013** | `events` | Add nullable `source`, `source_url`, `external_id`, and `dedupe_hash`; enforce uniqueness for non-null dedupe hashes in PHP plus the best supported MySQL unique-index strategy. |
| **013** | `public_services` | Add `verification_status`, `verified_at`, `verified_source`, and nullable `verified_by`; index status and validate allowed values in PHP. |
| **014** | `promotions` | Add nullable `city_id` FK → `cities`, `category`, `discount_percent` (0–100), `image_url`, and `link_url`; add city and `(status,valid_to)` indexes. Preserve existing `cover_image`/price fields and define API precedence rather than deleting them. |
| **014** | `push_deliveries` | Add non-null `retry_count` default 0 and nullable `next_retry_at`; add `(status,next_retry_at)` index because MySQL cannot reproduce the PostgreSQL partial index portably. |
| **015** | `jobs` | The new table must include the later premium/company/application fields (`is_premium`, `company_id`, logo/size/culture, requirements/nice-to-have/benefits/responsibilities JSON, workload, apply email/WhatsApp, deadline, featured-until), not merely the first migration’s shape. |
| **014** | `ad_campaigns` | Create from its final shape, including later `route_patterns` JSON and nullable `company_id` FK plus active/date indexes. |

Rows not listed here were checked against the current migrations at the meaningful contract level and either already contain their upstream columns (for example review attribution, city presentation fields and event category/ticket fields) or are wholly owned by a new-table migration above. Exact engine-specific defaults, lengths and indexes still require Batch A preflight.

## 3. PostgreSQL/Supabase replacement contract

| Upstream construct | PHP 8/MySQL replacement |
| --- | --- |
| `auth.users`, `auth.uid()`, `authenticated`/`anon` roles | Local `users`, PHP session cookies, `auth_require_user()`/admin guard; password hashes are local and never copied from Supabase. |
| RLS and policies | Explicit authorization and field whitelists in every PHP handler. Public reads, owner reads/writes and admin writes must be tested separately. MySQL grants are defense in depth, not policy replacement. |
| `has_role`/`SECURITY DEFINER` | Server-side role lookup and privileged PHP transaction; never trust role/user ids supplied by the client. |
| `UUID`, `gen_random_uuid()` | `CHAR(36)` and cryptographically generated UUID v4 in PHP. Preserve existing UUIDs. |
| `TIMESTAMPTZ`, `now()` | `DATETIME(3)` in UTC and ISO-8601 `Z` at the API boundary. |
| `JSONB`, `jsonb_*`, arrays | MySQL/MariaDB `JSON`; decode/validate/encode in PHP. |
| PostgreSQL enums (13 final generated enums) | `VARCHAR` plus PHP validation constants. Includes role, post/publish, listing, QA, public-service, appointment and representative enums. |
| `ILIKE`, `pg_trgm`, GIN/GIST | Escaped `LIKE` first; indexed exact filters; optional FULLTEXT only after measuring supported HostGator version. |
| Partial indexes | Transactional existence check plus supporting composite index (notably one pending claim). |
| Triggers `set_updated_at`, review rating, QA notifications, promotion limits, user bootstrap | Set timestamps and enforce invariants in the same PHP transaction. Keep DB constraints for FK/unique/check behavior supported by the deployed engine. |
| RPC `nearest_city` | `GET /api/catalog?op=nearest&lat=&lng=` (already present). |
| RPC `get_weekly_ranking` | `GET /api/admin?op=ranking` (already present; expose an owner-safe alias if panel access differs). |
| RPC `refresh_company_rating` | Existing PHP review upsert/recalculation transaction. |
| RPC `track_ad_event` | `POST /api/ads?op=track`; atomically update campaign/analytics counters. |
| RPC `increment_push_counter` | `POST /api/ops?op=push_track` or dedicated public push endpoint with strict counter allowlist. |
| RPC `admin_restore_table_tx`, `admin_export_full_backup` | Admin-only PHP backup service with table allowlist, streamed JSON/SQL and transaction. Never accept arbitrary table SQL. |
| Generated helpers `show_limit`, `show_trgm` | Do not port; these expose PostgreSQL extension behavior and have no product API contract. |
| Supabase Storage buckets/policies/signed URLs | `/uploads` outside executable paths, generated filenames, MIME/size checks, ownership in PHP; backup files outside public web root. |
| Supabase Realtime channels | Polling with cursor/`updated_at` for listing messages and live feed first; SSE/WebSocket only if hosting support is proven. |
| `pg_cron` + `net.http_post` | HostGator cron invoking authenticated CLI PHP scripts. Use lock files/DB advisory rows and idempotent run logs. |
| Supabase server client/service role | PDO credentials server-side only; no database credential or privileged token in Vite bundles. |
| TanStack `createServerFn` | Typed client wrapper around `/api/*.php`; Zod/browser checks are convenience only, PHP validates again. |
| Server route sitemap | `sitemap.php` or a generated static sitemap, querying local MySQL only. |
| Browser push tracking | `POST /api/public/push/track.php` validates a short-lived HMAC delivery token bound to `delivery_id` (and preferably event/expiry), compares it in constant time, allows only known event transitions, and increments counters atomically/idempotently. It does **not** accept the cron shared secret. |
| Browser push endpoint rotation | `POST /api/public/push/resubscribe.php` verifies the old endpoint exists, validates HTTPS and an allowlisted push-service host for old/new endpoints, validates key sizes/shapes, retains the existing local user ownership, rotates in one transaction, rate-limits requests and never accepts a caller-supplied user id. It does **not** accept the cron shared secret. |

## 4. Frontend routes and feature/API contract

Priority: **P0** keeps current production behavior working; **P1** ports new high-value upstream UI; **P2** admin/automation; **P3** optional/legacy polish. Every listed upstream client route is covered below.

| Upstream routes/features | Current dependency | Proposed PHP endpoint | Priority |
| --- | --- | --- | --- |
| `/`, `/buscar`, `/cidades/$slug`, `/categoria/$slug`, `/empresa/$slug`, `/vespasiano` | Supabase directory queries and view tracking | Existing `/api/catalog` ops + `/api/views/hit.php` | P0 |
| `/auth`, `/reset-password`, root auth listener | Supabase Auth | Existing `/api/auth/{login,register,logout,me,csrf}.php`; add reset-request/reset-confirm endpoints and remove auth listeners | P0 |
| `/painel`, `/painel/`, `/painel/perfil`, `/painel/empresas`, `/painel/empresas/nova`, `/painel/empresas/$id`, `/painel/leads`, `/painel/avaliacoes`, `/painel/favoritos`, `/painel/ranking` | Auth, companies, leads, reviews, favorites, `get_weekly_ranking` | Existing `/api/panel/activity.php`, `/api/users/me.php`, `/api/companies/*`, `/api/leads/*`, `/api/reviews/upsert.php`, `/api/favorites`, `/api/admin?op=ranking` | P0 |
| `/marketplace`, `/marketplace/$slug`, `/painel/anuncios`, `/painel/anuncios/novo`, `/painel/anuncios/$id/editar`, `/painel/mensagens` | Supabase tables, Storage and Realtime | Existing `/api/listings` ops + `/api/upload/image.php`; poll message thread | P0 |
| `/blog/`, `/blog/$slug`, `/eventos/`, `/eventos/$slug`, `/servicos-publicos`, `/emergencia`, `/o-que-fazer`, `/reputacao`, `/sobre`, `/contato` | Supabase content tables/settings | Existing `/api/content` ops; static routes need no DB endpoint | P0 |
| `/planos`, `/promocoes`, `/painel/promocoes` | `leads_planos`, companies JSON promotions, direct Supabase notification | Existing `/api/leads/planos.php`; add `/api/promotions` notification/coupon operations | P0/P1 |
| `/transporte`, `/transporte/linhas` | Upstream `bus_lines` | Existing `/api/transport` with adapter shape for `transport_lines/schedules/stops` | P1 |
| `/empregos`, `/empregos/premium`, `/empregos/$id` and `features/jobs/*` | `createServerFn` (`listJobs`, `getJob`, premium/facets), jobs tables | `GET /api/jobs` (`list`, `show`, `premium`, `facets`) | P1 |
| `/representantes`, `/representantes/`, `/representantes/$id`, `/representantes/feed`, `/representantes/ranking`, `features/representatives/*` | Supabase representative tables/selectors | `GET /api/representatives` (`list`, `show`, `feed`, `ranking`) | P1 |
| `WhatsAppSubscribeDialog` / `subscribeWhatsapp` on `/representantes/` | `createServerFn`, privileged upsert to `whatsapp_subscribers`, optional bot welcome send | `POST /api/public/whatsapp/subscribe.php`: validate trimmed name (2–80), BR phone (10–13 digits) normalized to E.164, allowlisted city slug and literal consent; rate-limit by IP+phone; upsert phone as active with `opted_in_at` and cleared `opted_out_at`; send the welcome message only after successful opt-in, best-effort with server-only bot credentials and no rollback on messaging failure | P1 |
| `/ao-vivo`, `/agora`, `features/live-feed/*` | external/feed queries, Supabase hidden ids/settings | `GET /api/live-feed`; admin moderation in `POST /api/live-feed` | P1 |
| `/roteiro-turistico` | direct `tourist_attractions` query | `GET /api/tourism?op=list` | P1 |
| `/transparencia` | `createServerFn(listProcurements)` | `GET /api/procurements` | P1 |
| `/favoritos`, `/painel/notificacoes`, `/painel/notificacoes/preferencias` | Supabase favorites and eight push `createServerFn`s | Existing `/api/favorites` and `/api/ops` prefs/inbox actions | P0 |
| `/admin`, `/admin/`, `/admin/empresas`, `/admin/cidades`, `/admin/leads`, `/admin/planos`, `/admin/configuracoes`, `/admin/textos`, `/admin/menu`, `/admin/duplicados` | direct Supabase + duplicate server function | Existing `/api/admin` ops; retain PHP duplicate scan | P0 |
| `/admin/eventos`, `/admin/blog`, `/admin/servicos-publicos`, `/admin/emergencia` | direct Supabase CRUD | Existing `/api/content` admin ops | P0 |
| `/admin/qa` | five QA `createServerFn`s and Storage attachments | Existing `/api/ops` QA ops + local upload attachment endpoint | P0 |
| `/admin/push`, `/admin/push/`, `/admin/push/novo`, `/admin/push/historico`, `/admin/push/$id`, `/admin/push/templates` | five admin push functions/direct templates | Existing `/api/ops` push/template ops; add PHP VAPID sender/track endpoint | P1 |
| `/admin/empregos` | nine admin jobs server functions | `/api/jobs/admin` CRUD/sync/log operations | P1 |
| `/admin/turismo` | direct Supabase CRUD | `/api/tourism` admin CRUD | P1 |
| `/admin/promocoes`, `/admin/anuncios`, `/admin/analytics-anuncios` | companies/cities/coupons/ad campaigns/analytics, `track_ad_event` | `/api/promotions`, `/api/ads` CRUD/stats/track | P1 |
| `/admin/calendario-editorial`, `/admin/blog-ai` | editorial tables, AI `createServerFn`, external model | `/api/editorial`; `/api/admin/blog-ai` with server-held provider key | P2 |
| `/admin/solicitacoes` and request form components | four user-request server functions | `/api/requests` public create + admin list/update/delete | P1 |
| `/admin/ao-vivo` | direct hidden/settings CRUD | `/api/live-feed` admin operations | P1 |
| `/admin/scraper-vespasiano`, `/admin/scraper-sjl`, `/admin/scraper-camara-sjl` | six scraper/import server functions | Admin-triggered `/api/importer/*`; enforce allowlisted sources, CSRF and timeouts | P2 |
| `/admin/backup` | five backup server functions, Storage and restore RPC | `/api/admin/backup` outside web root; allowlisted tables only | P2 |
| `sitemap.xml` | server route + Supabase queries | `/sitemap.php` or build/cron-generated XML from MySQL | P1 |

The 13 upstream server routes are not copied into Vite: `/api/public/push/{track,resubscribe}` and hooks for `whatsapp-weekly-digest`, `whatsapp-opt-out`, `sync-representatives`, `sync-original`, `sync-bus`, `scrape-services`, `scrape-procurements`, `scrape-events`, `push-scheduler`, `jobs-sync`, and `daily-blog-post`. The 11 hook/cron routes use a shared secret stored only in server configuration, replay protection/idempotency, and run logging. The two browser push routes are public browser/service-worker flows and use the HMAC delivery-token and endpoint-rotation validation contracts above, never the hook/cron secret.

## 5. Merge boundaries

### Always manual-merge (never overwrite from reference)

- `package.json`, lockfile, Vite/TanStack config, `src/main.tsx`, `src/router.tsx`, generated `src/routeTree.gen.ts`, `src/routes/__root.tsx`, global CSS and PWA/service-worker files: the current project is a static Vite SPA, while upstream assumes TanStack Start/Nitro.
- Every overlapping `src/routes/*`, `src/components/*`, hook and library file: preserve current PHP calls and import only upstream presentation/feature deltas.
- `src/integrations/supabase/*`, `src/lib/php-api.ts`, `src/lib/php-auth.ts`, `src/lib/spa-auth.ts`: Supabase clients are migration references, not the destination.
- `src/lib/queries.ts`, `admin.ts`, `panel.ts`, `blog.ts`, `events.ts`, `marketplace.ts`, `favorites.ts`, `publicServices.ts`, `siteContent.ts`, push/QA/duplicate helpers: keep PHP transports and reconcile upstream response shapes.
- `api/**`, `database/migrations/**`, `tools/**`, `docs/**`, `.htaccess` and deployment scripts: local HostGator behavior wins.

### Local-only files/directories to preserve

- All PHP API/bootstrap/importer code and root/deployment copies currently under `api/`, `database/`, `tools/`, `deploy-*`, and release packaging scripts.
- `database/migrations/001-012`, especially claims (005), importer (011) and normalized transport (012).
- `src/legacy-server/*` until all sitemap/push behavior is proven replaced; do not reactivate Nitro.
- HostGator documentation, installer/updater/rollback files, upload protections, local auth/session/CSRF/rate-limit code.
- Existing uncommitted files and generated deployment archives are outside this task and must not be modified.

New upstream-only route/component/feature files may be copied only after removing `createServerFn`, server-only imports and direct Supabase access. Do not copy `supabase/`, `.env*`, upstream generated types as runtime truth, Nitro config, or service-role usage.

## 6. Seed and data policy

1. Local MySQL is the sole runtime and seed source of truth.
2. The Supabase project is assumed empty. Do not add an export/import dependency and do not seed from Supabase network calls.
3. Preserve `010_seed_public.sql` and add only idempotent inserts/upserts in later numbered migrations or explicit seed scripts.
4. Preserve IDs/slugs already present in MySQL. Never truncate, drop, mass-delete or recreate a table to match upstream.
5. Migration 013+ must be additive, checksum-tracked, forward-only and safe to rerun through the existing runner conventions.
6. Before adding a unique/FK constraint, ship a read-only preflight query and resolve conflicts explicitly; never silently delete duplicates.
7. Legacy/orphan tables and local-only tables remain until a separately approved retention/data migration proves they can be retired.
8. Uploads and backups are data: retain local paths, keep executable code out of upload directories, and never put secrets/private keys in rows or frontend assets.

## 7. Executable implementation batches

1. **Batch A — contract tests and schema preflight.** Snapshot current table/column/index/API response shapes; add no production behavior. Confirm HostGator MySQL/MariaDB version and JSON/check/index support.
2. **Batch B — migration 013, content/civic read models and existing-table deltas.** Add `blog_categories`, `editorial_posts`, `event_sync_logs`, `tourist_attractions`, `procurements`; alter `profiles`, `companies`, `posts`, `events`, and `public_services` exactly as assigned above; optionally add the compatible blog view. Build read APIs and port `/roteiro-turistico` and `/transparencia`.
3. **Batch C — migration 014, engagement/moderation/ads and push retry deltas.** Add `coupons`, final-shape `ad_campaigns`, `user_requests`, `live_feed_hidden`; alter `promotions` and `push_deliveries` exactly as assigned above; build owner/admin policies in PHP and port requests, live feed, ads and coupon UI.
4. **Batch D — migration 015, jobs.** Add three jobs tables, public/admin APIs, idempotent sync runner and `/empregos*` routes/features.
5. **Batch E — migration 016, representatives/WhatsApp.** Add five tables, public representative APIs, admin importer, validated/rate-limited public WhatsApp opt-in endpoint, best-effort welcome send, opt-out/digest handlers and representative routes. Keep message-provider credentials server-side.
6. **Batch F — migration 017, transport compatibility.** Add `bus_sync_logs`; adapt upstream bus response to existing normalized transport schema without replacing migrations 012 tables.
7. **Batch G — auth/storage/realtime closure.** Add password reset, remove remaining Supabase auth/storage calls, implement attachment/promotion upload ownership and polling cursors.
8. **Batch H — server-function and hook closure.** Port the remaining scraper, AI, push sender, HMAC-validated browser tracker, validated browser resubscription, sitemap, scheduled hooks and backup functions. Apply shared secrets only to hook/cron entry points. Configure HostGator cron only after local CLI tests.
9. **Batch I — frontend merge.** Merge route groups in priority order, regenerate route tree, then verify no `createServerFn`, `@tanstack/react-start`, Nitro server import, direct `.from(...)`, `.rpc(...)`, Supabase Storage/Auth or Realtime runtime dependency remains.
10. **Batch J — release verification.** Run migration preflight on a copy of MySQL, PHP syntax/tests, frontend lint/build, endpoint authorization matrix, SPA deep-link test, upload denial tests and rollback rehearsal. Production deployment remains a separate explicit approval.

Each batch should be independently reviewable and must preserve a working static SPA. No batch may commit, push, mutate production, or edit credentials without separate authorization.
