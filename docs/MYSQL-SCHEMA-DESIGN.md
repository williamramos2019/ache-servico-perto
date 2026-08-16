# Projeto de schema MySQL/MariaDB

**Branch:** `migration-hostgator`  
**HEAD:** `fb59826`  
**Fonte:** `docs/MIGRATION-AUDIT.md` + `supabase/migrations/` + `src/integrations/supabase/types.ts` + `src/`  
**Escopo:** modelagem. Sem SQL executável, sem PHP, sem alteração de código.

Convenções propostas (únicas no projeto):

| Decisão | Escolha | Justificativa |
| --- | --- | --- |
| Engine | InnoDB | FK, transações, HostGator |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` | acentos PT-BR |
| UUID | `CHAR(36)` | debug, PDO, dumps, URLs; BINARY(16) economiza mas complica o front atual |
| IDs numéricos existentes | `BIGINT UNSIGNED` | `company_views`, `push_deliveries`, `push_inbox` já são serial |
| Tempo | `DATETIME(3)` armazenado em **UTC** | equivale a TIMESTAMPTZ sem timezone nativo do MySQL |
| Boolean | `TINYINT(1)` | |
| JSONB / arrays | `JSON` | MariaDB 10.2+ / MySQL 5.7+ na HostGator |
| ENUM PG | `VARCHAR(n)` + validação PHP | alterar ENUM MySQL exige ALTER TABLE |
| RLS | **não existe** — autorização na API PHP | risco ALTO se esquecer |

---

## 1. Inventário

| Tabela PG | Origem | Front | Legacy server | Órfã? | Classe | Prioridade | Complexidade |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `auth.users` | Supabase Auth | indireto | auth middleware | — | A | 1 | A |
| `user_roles` | migration | `has_role` | — | não | A | 1 | M |
| `profiles` | migration | panel, marketplace | — | não | A | 1 | B |
| `cities` | migration | queries, admin, geo | sitemap | não | A | 2 | B |
| `categories` | migration | queries | sitemap | não | A | 2 | B |
| `companies` | migration + ALTERs | núcleo | sitemap | não | A | 2 | A |
| `company_categories` | migration | queries | — | não | A | 2 | B |
| `company_media` | migration | join empresa | — | não | A | 2 | B |
| `reviews` | migration | site, panel | — | não | A | 3 | M |
| `leads` | migration | QuoteDialog, panel, admin | — | não | A | 3 | B |
| `favorites` | migration | favorites.ts | — | não | A | 3 | B |
| `company_claims` | migration | claims.ts, admin | — | não | A | 4 | A |
| `listings` + msgs/reports/cats | migration | marketplace, painel | — | não | A | 5 | M |
| `posts` | migration | admin.blog, scan | — | não | A | 6 | M |
| `blog_posts` | **VIEW** | `blog.ts` | — | derivada | A | 6 | B |
| `events` / `shows` / `event_categories` | migration | events, admin | — | não | A | 6 | M |
| `public_services` / `emergency_contacts` | migration | publicServices, admin | — | não | A | 6 | B |
| `company_views` | migration | insert + ranking + admin | — | não | B | 3 | B |
| `leads_planos` | migration | planos, admin | — | não | B | 3 | B |
| `newsletter_subscribers` | migration | NewsletterForm | — | não | B | 7 | B |
| `plans_config` | migration | admin | — | não | C | 7 | B |
| `system_settings` | migration | siteContent, nav, admin | — | não | C | 7 | M |
| `qa_*` | migration | qa.functions | — | não | C | 8 | M |
| `push_*` / `notification_*` | migration | push functions | push-track | não | D | 8 | A |
| `media` | migration | upload listing/empresa | — | não | D | 5 | B |
| `post_categories` | migration | sem `.from` | — | quase | B | 6 | B |
| `notifications` | migration + triggers | sem `.from` | — | trigger-only | D | 4 | B |
| `promotions` (tabela) | migration | **não** (usa JSON em companies) | — | **sim** | E | dump | B |
| `appointments` | migration | não | — | **sim** | E | dump | M |
| `banners` | migration | não | — | **sim** | E | dump | B |
| `analytics_events` | migration | não | — | **sim** | E | dump | B |
| `marketplace_items` | migration | não (usa `listings`) | — | **sim** | E | dump | B |
| `company_projects` | migration | não | — | **sim** | E | dump | B |
| `company_faqs` | migration | não | — | **sim** | E | dump | B |
| `blog_posts_legacy` | rename | não | — | **sim** | E | dump | B |

Classes: **A** essenciais · **B** secundárias · **C** administrativas · **D** infraestrutura · **E** órfãs (não remover nesta fase).

---

## 2. `users` (substitui `auth.users`)

Campos **comprovados** no código/migrations (`auth.tsx`, `handle_new_user`, `grant_default_admin`):

| Coluna MySQL | Origem | Notas |
| --- | --- | --- |
| `id` CHAR(36) PK | `auth.users.id` UUID | |
| `email` VARCHAR(255) NOT NULL UNIQUE | login / trigger admin | |
| `password_hash` VARCHAR(255) NOT NULL | **novo** | substitui hash interno do Supabase Auth; `password_hash()` |
| `email_verified_at` DATETIME(3) NULL | `email_confirmed_at` | |
| `created_at` DATETIME(3) NOT NULL | `auth.users.created_at` | |

**Não incluir** (não existem no schema public / types / uso): `phone`, `last_login`, `status`, `name` (nome está em `profiles`).

**Não armazenar senha em texto.**

Índice: UNIQUE `email`.  
ON DELETE: ver FKs — preferir `RESTRICT` em companies/claims; `CASCADE` só onde o PG já faz (favorites, push_subscriptions).

---

## 3. Roles

Não criar tabela `roles` separada: o PG usa ENUM `app_role` na própria `user_roles`.

**`user_roles`**

| Coluna | MySQL |
| --- | --- |
| `id` CHAR(36) PK | |
| `user_id` CHAR(36) NOT NULL | FK → `users.id` ON DELETE CASCADE (igual PG) |
| `role` VARCHAR(32) NOT NULL | valores: `admin`, `company_owner`, `user`, `editor`, `publisher` |
| `created_at` DATETIME(3) NOT NULL | |

UNIQUE (`user_id`, `role`) — comprovado na migration.

**Regra:** INSERT/UPDATE/DELETE desta tabela **somente** via PHP privilegiado (register default `user`; approve claim → `company_owner`; seed admin). Endpoint autenticado comum **não** escreve `user_roles`. Equivale a GRANT só SELECT + DEFINER.

---

## 4. `profiles`

**DIVERGÊNCIA com o briefing:** o PG **não** tem `profiles.user_id`. A PK **é** `id` = `auth.users.id` (1:1).

| Coluna | MySQL |
| --- | --- |
| `id` CHAR(36) PK | FK → `users.id` ON DELETE CASCADE |
| `name` VARCHAR(255) NULL | |
| `avatar_url` TEXT NULL | |
| `created_at` DATETIME(3) NOT NULL | |
| `updated_at` DATETIME(3) NOT NULL | |

Sem UNIQUE extra. Sem telefone.

---

## 5. `cities`

Campos (types + migration SEO): `id`, `name`, `slug` UNIQUE, `state` default `MG`, `lat`/`lng` DECIMAL(9,6), `hero_*`, `banner_url`, `logo_url`, `video_url`, `primary_color`, `seo_title`, `seo_description`, `og_image_url`, `featured_category_ids` JSON (era `uuid[]`), `is_active` TINYINT(1), `created_at`, `updated_at`.

Índices: UNIQUE `slug`; `is_active`; `(lat, lng)` para nearest.

**`nearest_city` (futuro, não implementar):** Haversine em SQL MySQL sobre `is_active=1` e lat/lng NOT NULL, `ORDER BY dist LIMIT 1` — ou cálculo no browser com GET `/api/cities`.

---

## 6. `categories` (empresas)

`id`, `name`, `slug` UNIQUE, `icon`, `description`, `sort` INT, `created_at`.

**Três taxonomias distintas — não unificar:**

| Tabela | Liga a |
| --- | --- |
| `categories` | `company_categories` |
| `event_categories` | `events.category_id` |
| `listing_categories` | `listings.category_slug` → `listing_categories.slug` |

`post_categories` liga `posts` ↔ `categories` (mesma tabela de empresas). Front **não** usa hoje.

---

## 7. `companies`

Todos os campos de `types.ts` Row (não inventar):

`id`, `owner_id`, `slug` UNIQUE, `name`, `tagline`, `description`, `phone`, `whatsapp`, `email`, `address`, `zip`, `city_id`, `lat`, `lng`, `website`, `instagram`, `facebook`, `tiktok`, `youtube`, `hours` JSON, `logo_url`, `banner_url`, `video_url`, `tour_360_url`, `catalog_url`, `pricebook_url`, `portfolio_pdf_url`, `plan` VARCHAR, `plan_expires_at`, `featured`, `status`, `is_verified`, `rating` DECIMAL(3,2), `review_count`, `views_count`, `founded_year`, `years_experience`, `response_time_minutes`, `response_rate` DECIMAL(5,2), `services_completed`, `clients_served`, `certifications` JSON, `coverage_cities` JSON, `quality_scores` JSON, `reputation_score`, `badges` JSON, `price_range` SMALLINT, `promotions` JSON, `financing_info` JSON, `differentials` JSON, `created_at`, `updated_at`.

**JSONB → JSON (não normalizar agora):** o painel (`painel.empresas.$id`) lê/grava `promotions`, `hours`, `differentials`, etc. como objetos. Tabelas relacionais quebrariam o front.

**Risco documentado (não corrigir):** RLS atual permite dono UPDATE em `plan`/`featured`. A API PHP **deverá** recusar essas colunas para não-admin. O schema MySQL **não** impede sozinho.

`owner_id` NULL + ON DELETE SET NULL (igual PG).  
`city_id` ON DELETE SET NULL.  
Índices: `city_id`, `featured`, `status`, `plan`, `owner_id`, UNIQUE `slug`. FULLTEXT futuro `(name, tagline)`.

---

## 8. Relacionamentos de empresa

| Tabela | PK | FKs | ON DELETE | UNIQUE |
| --- | --- | --- | --- | --- |
| `company_categories` | (`company_id`,`category_id`) | companies, categories | CASCADE (N:N) | a própria PK |
| `company_media` | `id` CHAR(36) | company_id CASCADE | | |
| `company_views` | `id` BIGINT AI | company_id CASCADE | | |
| `company_projects` | `id` | company_id | SET NULL ou CASCADE — PG CASCADE via company | órfã; manter |
| `company_faqs` | `id` | company_id | idem | órfã; manter |

`company_views`: `company_id`, `ip_hash`, `viewed_at`. Índices: `company_id`, `viewed_at` (ranking 7d).

`company_projects.images` TEXT[] → JSON.

---

## 9. `reviews`

`id`, `company_id` NOT NULL, `user_id` NULL, `rating` INT, `comment` TEXT, `author_name`, `source` default `app`, `review_date`, `created_at`.

**Sem coluna `status`** no schema real.

FK `company_id` ON DELETE CASCADE (PG).  
`user_id` ON DELETE SET NULL.

**`refresh_company_rating`:** preferir **PHP transacional** no POST/PATCH/DELETE de review (recalcula `companies.rating` / `review_count`). Trigger MySQL é opcional e mais difícil de testar na HostGator. Mesma fórmula da function PG.

---

## 10. Leads

**`leads`:** `id`, `company_id`, `user_id` NULL, `name`, `phone`, `email`, `message`, `created_at`. Sem `status` no schema.  
Acesso futuro: insert público + rate limit; SELECT dono (`companies.owner_id`) ou admin.

**`leads_planos`:** `id`, `company_name`, `contact_name`, `email`, `phone`, `city`, `plan`, `message`, `status` VARCHAR, `created_at`. Sem FK empresa.

---

## 11. `favorites`

PK composta **(`user_id`, `company_id`)** — comprovado. Não inventar `target`.  
ON DELETE CASCADE ambos (igual PG).

---

## 12. `newsletter_subscribers`

`id`, `email` UNIQUE (comprovado), `name`, `city_slug`, `created_at`.  
**Sem `status`** no schema. UNIQUE de e-mail já existe — manter.

---

## 13. `company_claims`

`id`, `company_id`, `user_id`, `full_name`, `role_in_company`, `phone`, `email`, `document`, `message`, `proof_url`, `status` default `pending`, `admin_notes`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`.

UNIQUE parcial PG: um pending por (`company_id`,`user_id`). MySQL 8 / MariaDB: índice único **funcional** se disponível; senão UNIQUE (`company_id`,`user_id`,`status`) é **inexato** (vários rejected). **Decisão proposta:** UNIQUE (`company_id`,`user_id`) **somente** se o produto aceitar um claim histórico único; senão validar pending no PHP (como o índice parcial). Preferir **validação PHP** + índice não único `(company_id, user_id, status)`.

Approve/reject: transação PHP (update company owner, roles, claims, notifications). Sem trigger.

`company_id` ON DELETE CASCADE. `user_id` ON DELETE CASCADE (PG).

---

## 14. Posts / blog

**`posts`** é a tabela. **`blog_posts` é VIEW** (`type='blog'`, `published = status='published'`). `blog.ts` lê a view.  
**Decisão:** no MySQL **não criar VIEW** no início — `blog.ts` futuro filtra `posts WHERE type='blog'`. VIEW opcional depois.

Colunas posts: `id`, `type`, `status`, `slug` UNIQUE, `title`, `excerpt`, `content`, `featured_image`, `gallery` JSON, `tags` JSON, `meta_title`, `meta_description`, `og_image`, `author_id`, `author_name`, `company_id`, `city_id`, `auto_generated`, `views_count`, `published_at`, `scheduled_at`, `created_at`, `updated_at`.

`blog_posts_legacy`: órfã; dump antes de descartar.

`post_categories`: PK (`post_id`,`category_id`).

---

## 15. Events

**`event_categories`:** id, slug, name, … (ver migration).  
**`events`:** id, slug UNIQUE, title, description, cover_image, start_at, end_at, location, company_id, city_id, category_id, event_type, ticket_url, price_min/max, status, created_by, created_at, updated_at.  
**`shows`:** id, event_id, artist_name, cover_image, description, start_at, end_at, stage, sort, ticket_price, ticket_url, created_at, updated_at.

FKs: event→city SET NULL; →company SET NULL; →category SET NULL; created_by SET NULL; shows.event_id CASCADE.

---

## 16. Marketplace

**Usadas:** `listing_categories` (PK id, UNIQUE slug), `listings` (images JSON, category_slug FK para slug), `listing_messages`, `listing_reports`.  
**Órfã:** `marketplace_items`.

listings UNIQUE slug. messages: listing CASCADE; users RESTRICT/SET NULL — PG referencia auth.users CASCADE; no MySQL CASCADE em buyer/seller/sender se o user for apagado.

UNIQUE messages: nenhum além de PK.  
reports: `status` VARCHAR.

---

## 17. Serviços públicos e config

**`public_services`:** campos types (category VARCHAR, hours TEXT não JSON, lat/lng, phones, featured, is_24h, active, city_id NOT NULL).  
**`emergency_contacts`:** id, city_id, name, phone, description, icon, sort_order, active, timestamps.  
**`plans_config`:** PK `slug`, name, price_cents, duration_days, max_photos, features JSON, sort, updated_at.  
**`system_settings`:** PK `key`, `value` JSON, `is_public`, `updated_at`.

---

## 18. QA

**`qa_tickets`:** todos os campos types + `ticket_number` UNIQUE. Sequence `qa_ticket_seq` → `AUTO_INCREMENT` auxiliar ou tabela `qa_ticket_counters` / `MAX+1` no PHP com lock.

JSON: device, console_logs, network_logs, extra.  
API futura: whitelist de colunas no INSERT (não aceitar status/priority/assigned_to do cliente).

comments / events: id, ticket_id CASCADE, author/actor, body/kind/payload, created_at.

---

## 19. `media`

`id`, `url`, `kind`, `meta` JSON, `owner_id`, `company_id`, `created_at`.  
Usado no upload (signed URL hoje). Paths futuros em `/uploads/...`. Sem filesystem nesta fase.

---

## 20. Push

Não guardar VAPID privada no banco.

| Tabela | PK | Notas |
| --- | --- | --- |
| `push_subscriptions` | CHAR(36) | UNIQUE `endpoint`; p256dh, auth; user_id CASCADE |
| `notification_templates` | CHAR(36) | UNIQUE slug |
| `push_notifications` | CHAR(36) | audience/buttons JSON; template_id SET NULL |
| `push_deliveries` | BIGINT AI | UNIQUE (notification_id, user_id, subscription_id) |
| `push_inbox` | BIGINT AI | UNIQUE (user_id, notification_id) |
| `notification_preferences` | PK `user_id` | booleanos + quiet_* |

---

## 21. `notifications`

Tabela **real**. Escrita por triggers/RPCs (`qa_resolved`, `claim_approved`, `claim_rejected`). Front **não** faz `.from("notifications")`. Inbox do usuário usa `push_inbox`.

Colunas types: `id`, `user_id`, `type`, `title`, `body`, `payload` JSON, `read_at`, `created_at`.

**DIVERGÊNCIA:** migration inicial QA inseria coluna `data`; migration seguinte e claims usam `payload`. types.ts = `payload`. **Decisão:** modelar só `payload`.

---

## 22. Enums → VARCHAR

| Enum PG | Valores | Uso front | MySQL |
| --- | --- | --- | --- |
| `app_role` | admin, company_owner, user, editor, publisher | has_role admin | VARCHAR(32) |
| `post_type` | article, news, blog, promo, event | admin.blog | VARCHAR(16) |
| `publish_status` | draft, scheduled, published, archived | posts/events | VARCHAR(16) |
| `listing_status` | ativo, vendido, pausado, removido | marketplace | VARCHAR(16) |
| `listing_condition` | novo, seminovo, usado | marketplace | VARCHAR(16) |
| `qa_*` | ver migration | QA | VARCHAR(32) |
| `public_service_category` | health/education/… | publicServices | VARCHAR(32) |
| `appointment_status` | … | só órfã appointments | se migrar órfã |

Justificativa VARCHAR: HostGator + migrations incrementais sem rebuild de ENUM.

---

## 23. UUID vs BIGINT

Estratégia **mista, fiel ao PG:**

- Quase tudo: `CHAR(36)` gerado no PHP (`Ramsey\Uuid` ou UUID v4).
- Já serial: `company_views.id`, `push_deliveries.id`, `push_inbox.id` → `BIGINT UNSIGNED AUTO_INCREMENT`.

Não converter esses três para UUID (o front usa `id` numérico em inboxAction).

---

## 24. Timestamps / timezone

- Banco: UTC, `DATETIME(3)`.
- PHP: `new DateTimeImmutable('now', new DateTimeZone('UTC'))`.
- API: ISO-8601 com `Z` (como o Supabase hoje).
- Browser: `new Date(iso)` — sem mudança no front.

`start_at` de eventos é instante absoluto → UTC.

---

## 25. JSON / arrays

| Campo | PG | MySQL | Front |
| --- | --- | --- | --- |
| companies.hours, promotions, certifications, quality_scores, financing_info | JSONB | JSON | painel empresa |
| companies.badges, differentials, coverage_cities | text[] / uuid[] | JSON | painel |
| cities.featured_category_ids | uuid[] | JSON | admin cidades |
| posts.tags | text[] | JSON | admin blog |
| posts.gallery, listings.images, media.meta | JSON | JSON | |
| push audience/buttons, qa logs, settings.value, plans.features, notifications.payload | JSONB | JSON | |

Não explodir em tabelas agora (quebraria o shape do React).

---

## 26. Índices PG → MySQL

| PG | Finalidade | MySQL |
| --- | --- | --- |
| UNIQUE slugs | rotas | UNIQUE |
| `gin_trgm` name/title | similaridade (não usada no client) | FULLTEXT opcional `(name, tagline)` / `(title)` |
| GIN `posts.tags` | tags | índice gerado ou ignorar até query |
| `company_views(company_id)`, `(viewed_at)` | ranking | iguais |
| `listings (city_id, status, created_at)` | listagem | iguais |
| `push_inbox(user_id, received_at)` | inbox | iguais |
| `qa_tickets(status)`, fingerprint | admin | iguais |
| `company_claims(status, created_at)` | admin | iguais |

Sem GIST/PostGIS.

---

## 27. Busca (só especificação)

| Entidade | Hoje | Índice futuro |
| --- | --- | --- |
| companies | `ILIKE` name/tagline | LIKE + FULLTEXT depois |
| events | ilike title | LIKE |
| listings | filtros, trgm no banco | LIKE title |
| QA | ilike description | LIKE |
| cities/categories | slug/name exact | UNIQUE slug |

---

## 28. Foreign keys (resumo)

| De | Para | ON DELETE proposto | Motivo |
| --- | --- | --- | --- |
| user_roles.user_id | users | CASCADE | igual PG |
| profiles.id | users | CASCADE | 1:1 |
| companies.owner_id | users | SET NULL | igual PG |
| companies.city_id | cities | SET NULL | |
| company_categories.* | parents | CASCADE | N:N |
| reviews.company_id | companies | CASCADE | |
| reviews.user_id | users | SET NULL | |
| leads.company_id | companies | CASCADE | |
| favorites.* | users/companies | CASCADE | PK composta |
| claims.company_id / user_id | companies/users | CASCADE | |
| listings.user_id | users | CASCADE | |
| listings.city_id | cities | SET NULL | |
| listings.category_slug | listing_categories.slug | RESTRICT | |
| listing_messages.listing_id | listings | CASCADE | |
| posts.city/company | | SET NULL | |
| events.* | | SET NULL | |
| shows.event_id | events | CASCADE | |
| push_*.user_id | users | CASCADE | |
| push_deliveries.notification_id | push_notifications | CASCADE | |
| qa_*.ticket_id | qa_tickets | CASCADE | |
| public_services.city_id | cities | RESTRICT | city_id NOT NULL |

**Não** CASCADE automático em `companies` a partir de `users` (perderia o diretório). SET NULL no owner.

ON UPDATE: `RESTRICT` em todos (IDs imutáveis).

---

## 29. Auditoria

Só o que já existe: `created_at` / `updated_at` nas tabelas que já têm.  
`created_by` só em `events.created_by` e `push_notifications.created_by`.  
`reviewed_by` em claims.  
**Não** adicionar `updated_by` genérico.

---

## 30. Tabela `migrations` (runner futuro)

| Coluna | Uso |
| --- | --- |
| `id` BIGINT AI | |
| `version` VARCHAR(32) | `001`, `002`… ordenação |
| `name` VARCHAR(255) | filename |
| `checksum` CHAR(64) | SHA-256 do arquivo; detecta edição perigosa |
| `executed_at` DATETIME(3) | |

Checksum: **sim** — evita aplicar arquivo alterado sem querer.  
Lock: `GET_LOCK('schema_migrate', 30)`.  
Transação por arquivo. Falha = STOP, não segue.  
Rollback: **manual** (arquivo `down` opcional, nunca automático).  
**Proibido:** DROP TABLE / DELETE em massa no runner.

---

## 31. Estrutura futura de arquivos (não criar)

```
database/
  migrations/
    001_migrations.sql
    002_auth.sql          -- users, profiles, user_roles
    003_taxonomy.sql      -- cities, categories
    004_companies.sql     -- companies + N:N + media + views
    005_engagement.sql    -- reviews, leads, favorites, newsletter
    006_claims.sql
    007_content.sql       -- posts, events, shows
    008_listings.sql
    009_civic.sql         -- public_services, emergency
    010_settings.sql
    011_qa.sql
    012_push.sql
    013_orphans.sql       -- promotions, appointments, … (dump)
    014_indexes.sql
  schema/
  seeds/
```

---

## 32. HostGator

InnoDB, utf8mb4, PDO, DATETIME UTC. Evitar: PostGIS, GIN, jobs nativos, `JSON_TABLE` avançado se MariaDB for antiga (JSON básico basta). Índices FULLTEXT só depois de medir. Tabelas de views/deliveries podem crescer — cron de retenção é fase posterior.

---

## 33. Matriz de conversão

| PostgreSQL | MySQL | Estratégia | Risco |
| --- | --- | --- | --- |
| UUID | CHAR(36) | preservação | baixo |
| BIGSERIAL | BIGINT AI | preservação | baixo |
| TIMESTAMPTZ | DATETIME(3) UTC | convenção app | médio |
| JSONB | JSON | preservação | médio |
| TEXT[] / UUID[] | JSON | preservação | médio |
| ENUM | VARCHAR | PHP valida | baixo |
| GIN/trgm | LIKE / FULLTEXT | queries atuais não usam similarity | médio |
| VIEW blog_posts | filtro SQL | sem VIEW no dia 1 | baixo |
| RLS | PHP | **sem equivalente** | **ALTO** |
| SECURITY DEFINER | serviço PHP + transação | claims, ranking, rating | **ALTO** |
| auth.users | users + password_hash | re-login geral | **ALTO** |
| Storage | /uploads | URLs mudam | médio |
| Índice parcial claims | PHP | pending único | médio |

---

## 34. Riscos

| Nível | Problema | Local | Impacto | Estratégia |
| --- | --- | --- | --- | --- |
| CRÍTICO | RLS some | todas as writes | buraco se API falhar | checklist por endpoint = policy |
| CRÍTICO | senhas Supabase não migram | auth.users | reset em massa | comunicar usuários |
| ALTO | dono altera plan | companies | fraude de plano | PHP whitelist |
| ALTO | claims transação | RPCs | owner inconsistente | BEGIN/COMMIT único |
| ALTO | push keys | subscriptions | spam/privacidade | só admin/PHP envio |
| MÉDIO | JSON vs tabela promotions | companies vs promotions | duas fontes | JSON é a canônica do front |
| MÉDIO | UNIQUE pending claims | índice parcial | duplicata | validar no PHP |
| MÉDIO | notifications `data` vs `payload` | migrations | dump | usar payload |
| MÉDIO | signed URLs Storage | media/listings | imagens quebram | reupload ou copiar arquivos |
| BAIXO | órfãs | E | dados esquecidos | dump antes de dropar |
| BAIXO | editor/publisher | enum | front só checa admin | preservar no schema |

---

## 35. Divergências (não corrigidas)

| # | DIVERGÊNCIA | LOCAL | IMPACTO | DECISÃO PROPOSTA |
| --- | --- | --- | --- | --- |
| 1 | Briefing pediu `profiles.user_id`; PG usa `profiles.id` = user id | types + handle_new_user | modelagem | **manter PK compartilhada** |
| 2 | Tabela `promotions` vs `companies.promotions` JSON | migrations vs painel | duas verdades | JSON é a usada; tabela órfã no dump |
| 3 | `blog_posts` VIEW vs tabela | migration + blog.ts | leitura pública | MySQL: filtrar `posts` |
| 4 | QA trigger `data` vs `payload` | 2 migrations QA + types | dump notifications | coluna `payload` |
| 5 | types.companies Relationships omite `owner_id` FK | types.ts | gerador incompleto | FK owner existe na migration |
| 6 | `deleteMyCompany` vs RLS só admin | panel.ts vs SQL | delete dono falha | schema não resolve; regra na API |
| 7 | Front admin ignora editor/publisher | use-admin vs enum | papéis mortos | **manter** no MySQL |
| 8 | Briefing sugeriu phone/last_login em users | — | inventar colunas | **não criar** |
| 9 | `listings.images` é JSON, não text[] | types | — | JSON |
| 10 | Inbox push vs SELECT campanhas | RLS Fase 1 | UI vazia | JOIN no PHP, não no schema |

---

## 36. Dados que exigem atenção no dump

- UUIDs de `auth.users` devem ser os mesmos em `users` / FKs.
- Senhas: **não copiáveis** → fluxo de redefinição.
- `coverage_cities` / `featured_category_ids`: arrays de UUID → JSON strings.
- `ticket_number` QA e sequences.
- Arquivos dos buckets `media` e `qa-attachments`.
- Órfãs: exportar contagem antes de qualquer DROP futuro.

---

## 37. O que esta fase NÃO fez

Não criou `.sql`, PHP, API, conexão MySQL, tabelas reais, alterações em `src/` ou `supabase/`, commit ou push.

---

## 38. Próximo passo

Quando autorizado: **Fase 2.3** — especificação do migration runner (ainda sem executar SQL), ou **2.4** fundação PHP, conforme a ordem da auditoria 2.1.
