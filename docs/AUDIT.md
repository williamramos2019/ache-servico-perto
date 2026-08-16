# Auditoria — Ache Serviço Perto / AgendaAqui

**Data:** 2026-08-15  
**Repositório:** https://github.com/williamramos2019/ache-servico-perto.git  
**Commit auditado:** `fd4f7c1` (`Criou sistema de reivindicar`)  
**Branch de trabalho:** `migration-hostgator`  
**Escopo desta fase:** somente leitura e documentação. Nenhuma migração foi implementada.

> Este relatório descreve o sistema **como ele existe hoje**.  
> A implementação PHP/MySQL/HostGator **não começa** até aprovação explícita do mapa de migração.

---

## 1. Stack atual

```text
Frontend:            React 19.2 + TypeScript 5.8
Framework:           TanStack Start 1.167 (SSR / full-stack) — NÃO é um SPA Vite puro
Build:               Vite 8.0 + @lovable.dev/vite-tanstack-config 2.13.1
CSS:                 Tailwind CSS 4.2 + tw-animate-css
UI:                  shadcn/ui (Radix UI) + lucide-react + CVA + tailwind-merge
Routing:             TanStack Router 1.168 (file-based em src/routes/) — NÃO é React Router
State management:    TanStack Query 5.83 + estado local React + localStorage (auth/cidade)
Forms / validação:  react-hook-form 7.71 + Zod 3.24 + @hookform/resolvers
Database:            Supabase PostgreSQL (projeto lojruwfrypgwqfgzlmop)
Authentication:      Supabase Auth (email/senha + reset) + Lovable Cloud OAuth (Google)
Storage:             Supabase Storage (buckets media e qa-attachments)
Backend:             TanStack Start server functions (createServerFn) + 1 rota API Nitro
Runtime de prod:     Nitro 3 beta, alvo padrão Cloudflare (via config Lovable)
PWA:                 Service Worker + Web Push (web-push + VAPID)
Fonte:               DM Sans + Space Grotesk (@fontsource)
```

### Versões relevantes (package.json)

| Pacote | Versão |
| --- | --- |
| react / react-dom | ^19.2.0 |
| typescript | ^5.8.3 |
| vite | ^8.0.16 |
| tailwindcss | ^4.2.1 |
| @tanstack/react-router | ^1.168.25 |
| @tanstack/react-start | ^1.167.50 |
| @tanstack/react-query | ^5.83.0 |
| @supabase/supabase-js | ^2.108.2 |
| @lovable.dev/cloud-auth-js | ^1.1.2 |
| @lovable.dev/vite-tanstack-config | 2.13.1 |
| nitro | 3.0.260603-beta |
| zod | ^3.24.2 |
| web-push | ^3.6.7 |

### Achado crítico (incompatibilidade com HostGator compartilhada)

O projeto **não é** um frontend estático React + Vite.

É uma aplicação **TanStack Start** com:

- SSR (`src/server.ts`, `src/start.ts`);
- `createServerFn` (funções de servidor);
- handlers de rota no servidor (`/sitemap.xml`, `/api/public/push/track`);
- middleware Nitro / Cloudflare;
- dependência de Node.js em runtime de produção.

**HostGator compartilhada não executa Node.js persistente.**  
Portanto, a hospedagem pedida **não consegue rodar o binário atual sem adaptação**.

Isso **não** exige reescrever páginas, layout ou UI.  
Exige decidir como o frontend será **publicado** (SPA estático vs. outro runtime). Ver seção 15.

### O que o prompt assumia vs. o que existe

| Premissa do briefing | Realidade no repositório |
| --- | --- |
| React Router | TanStack Router (file-based) |
| SPA Vite pura | TanStack Start (SSR) |
| Tabela `services` | Não existe. Serviços = categorias + empresas |
| Sem Node em produção hoje | Node/Nitro/Cloudflare já é o runtime |
| Nome “Ache Serviço Perto” | Marca na UI: **AgendaAqui** |
| Escopo nacional | App limitado a **Vespasiano** e **São José da Lapa** (`APP_CITY_SLUGS`) |

---

## 2. Estrutura do projeto

```text
ache-servico-perto/
├── .env                          # CREDENCIAIS COMMITADAS (risco grave)
├── .lovable/                     # Metadados Lovable (template tanstack_start_ts)
├── components.json               # Config shadcn/ui
├── package.json                  # name: tanstack_start_ts
├── vite.config.ts                # defineConfig do pacote Lovable
├── tsconfig.json
├── bun.lock / package-lock.json
├── public/                       # PWA, robots, offline
│   ├── manifest.webmanifest
│   ├── sw.js
│   ├── offline.html
│   └── robots.txt
├── src/
│   ├── routes/                   # Páginas + 1 API route (file-based)
│   ├── components/
│   │   ├── ui/                   # shadcn/Radix — NÃO alterar
│   │   ├── site/                 # Layout, cards, busca, reviews
│   │   ├── panel/                # Formulários do dono de empresa
│   │   └── qa/                   # Relato de bugs
│   ├── hooks/
│   ├── lib/                      # Queries, painel, admin, push, blog, claims
│   ├── integrations/
│   │   ├── supabase/             # Client, types, auth middleware
│   │   └── lovable/              # OAuth Google via Lovable Cloud
│   ├── styles.css                # Tailwind 4 + tokens visuais
│   ├── router.tsx
│   ├── server.ts                 # Entry SSR
│   └── start.ts                  # Middleware Start + auth attacher
└── supabase/
    ├── config.toml               # project_id
    └── migrations/               # 41 migrations PostgreSQL
```

### Função das pastas principais

| Pasta | Função |
| --- | --- |
| `src/routes/` | Rotas públicas, painel, admin, sitemap e API de push |
| `src/components/ui/` | Biblioteca visual shadcn — deve permanecer intacta |
| `src/components/site/` | Experiência pública (header, cards, busca, reviews) |
| `src/components/panel/` | CRUD de empresa, anúncios, horários, upload |
| `src/lib/` | Camada de dados atual (Supabase direto + server functions) |
| `src/integrations/supabase/` | Cliente gerado + tipos do banco |
| `src/integrations/lovable/` | Login Google (Lovable Cloud Auth) |
| `supabase/migrations/` | Fonte da verdade do schema atual |
| `public/` | Assets estáticos de PWA/SEO |

Não existe `README.md` na raiz. Não existe pasta `backend/`, `database/` nem `docs/` (esta auditoria cria `docs/`).

---

## 3. Frontend

### Páginas e rotas (TanStack Router)

**Públicas**

| URL | Arquivo | Função |
| --- | --- | --- |
| `/` | `index.tsx` | Home do guia comercial |
| `/buscar` | `buscar.tsx` | Busca + filtros |
| `/empresa/$slug` | `empresa.$slug.tsx` | Página comercial da empresa |
| `/categoria/$slug` | `categoria.$slug.tsx` | Empresas por categoria |
| `/cidades/$slug` | `cidades.$slug.tsx` | Landing por cidade |
| `/auth` | `auth.tsx` | Login / cadastro / reset / Google |
| `/favoritos` | `favoritos.tsx` | Favoritos do usuário |
| `/planos` | `planos.tsx` | Planos free / premium / featured |
| `/sobre` | `sobre.tsx` | Institucional |
| `/contato` | `contato.tsx` | Contato |
| `/blog` | `blog.index.tsx` | Lista de posts |
| `/blog/$slug` | `blog.$slug.tsx` | Post |
| `/eventos` | `eventos.index.tsx` | Agenda de eventos |
| `/eventos/$slug` | `eventos.$slug.tsx` | Detalhe do evento |
| `/marketplace` | `marketplace.tsx` | Classificados P2P |
| `/marketplace/$slug` | `marketplace.$slug.tsx` | Anúncio + mensagens |
| `/servicos-publicos` | `servicos-publicos.tsx` | Serviços públicos |
| `/emergencia` | `emergencia.tsx` | Contatos de emergência |
| `/transporte` | `transporte.tsx` | Transporte |
| `/o-que-fazer` | `o-que-fazer.tsx` | Guia local |
| `/reputacao` | `reputacao.tsx` | Página de reputação |
| `/empregos` | `empregos.tsx` | **Coming soon** |
| `/promocoes` | `promocoes.tsx` | **Coming soon** |
| `/sitemap.xml` | `sitemap[.]xml.ts` | Sitemap gerado no servidor |

**Painel do usuário (`/painel`)** — exige login (checagem no frontend)

| URL | Função |
| --- | --- |
| `/painel` | Visão geral |
| `/painel/empresas` | Lista de empresas do dono |
| `/painel/empresas/nova` | Criar empresa |
| `/painel/empresas/$id` | Editar empresa |
| `/painel/anuncios` | Anúncios marketplace |
| `/painel/anuncios/novo` | Novo anúncio |
| `/painel/anuncios/$id/editar` | Editar anúncio |
| `/painel/mensagens` | Mensagens P2P |
| `/painel/leads` | Leads recebidos |
| `/painel/avaliacoes` | Avaliações |
| `/painel/ranking` | Ranking semanal (RPC) |
| `/painel/favoritos` | Favoritos |
| `/painel/notificacoes` | Inbox push |
| `/painel/notificacoes/preferencias` | Preferências |
| `/painel/perfil` | Perfil + logout |

**Admin (`/admin`)** — exige role `admin` via RPC `has_role` (somente no frontend)

| URL | Função |
| --- | --- |
| `/admin` | Dashboard |
| `/admin/cidades` | CRUD cidades |
| `/admin/empresas` | Moderação de empresas |
| `/admin/reivindicacoes` | Aprovar/rejeitar claims |
| `/admin/eventos` | Eventos + shows |
| `/admin/blog` | Posts |
| `/admin/servicos-publicos` | Serviços públicos |
| `/admin/emergencia` | Emergência |
| `/admin/duplicados` | Detector de conteúdo duplicado |
| `/admin/push` | Campanhas Web Push |
| `/admin/push/novo`, `/admin/push/$id`, `/admin/push/historico`, `/admin/push/templates` | Push |
| `/admin/qa` | Central de qualidade / tickets |
| `/admin/menu` | Itens de menu (`system_settings`) |
| `/admin/textos` | Textos do site |
| `/admin/planos` | `plans_config` |
| `/admin/leads` | Leads + leads de planos |
| `/admin/configuracoes` | `system_settings` |

### Componentes e hooks

- **UI:** dezenas de componentes shadcn em `src/components/ui/` (button, dialog, tabs, form, etc.).
- **Site:** `SiteLayout`, `Header`, `SearchBar`, `CompanyCard`, `CompanyProfileSections`, `ReviewsSection`, `FavoriteButton`, `QuoteDialog`, `ClaimCompanyDialog`, `CitySwitch`, `CityPickerDialog`, `NewsletterForm`, `PWAInstallPrompt`, `CategoryIcon`.
- **Painel:** `ListingForm`, `CompanyImageUpload`, `HoursEditor`, `ProfileCompleteness`, `PremiumLock`.
- **Hooks:** `use-admin`, `useSelectedCity`, `useCityAutoDetect`, `use-mobile`, `useFavorites` / `useCurrentUserId` (`src/lib/favorites.ts`).

### Formulários e validações

- Auth: email/senha (mín. 6), nome no cadastro, reset por e-mail.
- Empresas: formulário amplo no painel (contato, WhatsApp, horários JSON, planos).
- Avaliações: upsert em `reviews`.
- Leads: `QuoteDialog` → `leads`.
- Newsletter: `newsletter_subscribers`.
- Marketplace: `ListingForm` + Zod implícito / tipos locais.
- QA: `BugReportButton` com upload.
- Validação principal: Zod + HTML required. Sem Yup.

### Responsividade

Layout mobile-first com Tailwind (`container`, grids `lg:grid-cols-[220px_1fr]`, `use-mobile`). Header e painel já adaptados.

### SEO atual

- Meta tags e Open Graph em `__root.tsx` e em várias rotas (`head()`).
- Canonical em `/auth` e posts de blog.
- `robots.txt` permite tudo (`Allow: /`).
- `/admin` e `/painel` com `noindex`.
- Sitemap dinâmico (cidades, categorias, empresas ativas) — **depende de SSR + Supabase**.
- Slugs em empresas, categorias, cidades, blog, eventos, marketplace.
- Título/descrição por cidade (`seo_title`, `seo_description`).
- OG image atual aponta para CDN da Lovable (R2).
- URLs de blog ainda citam `https://ache-servico-perto.lovable.app`.
- `BASE_URL` do sitemap está **vazio** (URLs relativas incompletas).
- Não há JSON-LD estruturado dedicado além do que as páginas já renderizam.

**Regra:** não mudar URLs públicas (`/empresa/:slug`, `/categoria/:slug`, `/cidades/:slug`, `/blog/:slug`, `/eventos/:slug`, `/marketplace/:slug`).

---

## 4. Todas as dependências Supabase

### Pacotes e clientes

| Item | Onde |
| --- | --- |
| `@supabase/supabase-js` | `package.json` |
| Cliente browser | `src/integrations/supabase/client.ts` |
| Cliente service role | `src/integrations/supabase/client.server.ts` |
| Auth middleware | `src/integrations/supabase/auth-middleware.ts` |
| Auth attacher Start | `src/integrations/supabase/auth-attacher.ts` |
| Tipos gerados | `src/integrations/supabase/types.ts` |
| OAuth Lovable → sessão Supabase | `src/integrations/lovable/index.ts` |

### Funcionalidades que dependem do Supabase

| Área | Uso |
| --- | --- |
| Auth | `signInWithPassword`, `signUp`, `resetPasswordForEmail`, `signOut`, `onAuthStateChange`, `getUser`, `setSession` (OAuth) |
| Database | Quase todas as telas leem/escrevem via `supabase.from(...)` |
| RPC | `has_role`, `nearest_city`, `get_weekly_ranking`, `approve_company_claim`, `reject_company_claim` |
| Storage | bucket `media` (logos, banners, anúncios); bucket `qa-attachments` |
| RLS | Toda autorização de escrita pública/autenticada |
| Triggers | perfil ao criar usuário; rating da empresa; claims; QA; premium verificado |
| View | `blog_posts` sobre `posts` |
| Server functions | Push, QA, duplicados, detecção de cidade |
| Sitemap | Client server-side no handler GET |
| Admin gate | `has_role(..., 'admin')` |

### Não encontrado

- Pasta `supabase/functions` (Edge Functions).
- `functions.invoke`.
- Realtime subscriptions no frontend (o pacote existe, mas não há uso de canal).

### Arquivos TypeScript/TSX que importam Supabase (devem mudar na integração)

```text
src/integrations/supabase/*          (substituir por apiClient)
src/integrations/lovable/index.ts
src/start.ts
src/routes/__root.tsx
src/routes/auth.tsx
src/routes/empresa.$slug.tsx
src/routes/favoritos.tsx
src/routes/marketplace.tsx
src/routes/marketplace.$slug.tsx
src/routes/painel.perfil.tsx
src/routes/painel.favoritos.tsx
src/routes/painel.mensagens.tsx
src/routes/painel.anuncios.tsx
src/routes/painel.ranking.tsx
src/routes/planos.tsx
src/routes/admin.cidades.tsx
src/routes/admin.blog.tsx
src/routes/admin.eventos.tsx
src/routes/admin.push.novo.tsx
src/routes/admin.push.templates.tsx
src/routes/sitemap[.]xml.ts
src/components/site/Header.tsx
src/components/site/ReviewsSection.tsx
src/components/site/QuoteDialog.tsx
src/components/site/NewsletterForm.tsx
src/components/panel/CompanyImageUpload.tsx
src/components/panel/ListingForm.tsx
src/components/qa/BugReportButton.tsx
src/hooks/use-admin.ts
src/lib/queries.ts
src/lib/panel.ts
src/lib/admin.ts
src/lib/favorites.ts
src/lib/blog.ts
src/lib/events.ts
src/lib/claims.ts
src/lib/marketplace.ts
src/lib/navItems.ts
src/lib/siteContent.ts
src/lib/publicServices.ts
src/lib/cityDetect.functions.ts
src/lib/push.functions.ts
src/lib/admin-push.functions.ts
src/lib/qa.functions.ts
src/lib/duplicates.functions.ts
```

---

## 5. Banco atual (PostgreSQL / Supabase)

**Extensões:** `pg_trgm` (busca por similaridade). Sem PostGIS. `nearest_city` usa distância euclidiana em lat/lng.

### Tabelas (`public`)

| Tabela | Uso no app | Observação |
| --- | --- | --- |
| `profiles` | Perfil do usuário | PK = `auth.users.id` |
| `user_roles` | Papéis | UNIQUE (user_id, role) |
| `cities` | Cidades do guia | slugs ativos: vespasiano, sao-jose-da-lapa |
| `categories` | Categorias comerciais | slug + icon + sort |
| `companies` | Núcleo do guia | slug, plan, status, geo, WhatsApp, hours JSON |
| `company_categories` | N:N empresa↔categoria | |
| `company_media` | Galeria | |
| `company_projects` | Portfólio | |
| `company_faqs` | FAQ da empresa | |
| `company_views` | Contador de visitas | insert anônimo na página da empresa |
| `company_claims` | Reivindicar empresa | RPCs approve/reject |
| `reviews` | Avaliações | trigger atualiza rating |
| `favorites` | Favoritos | |
| `leads` | Orçamentos / contato | |
| `leads_planos` | Leads de upgrade | |
| `newsletter_subscribers` | Newsletter | |
| `system_settings` | Menu, textos, config | key/value JSON |
| `plans_config` | Planos comerciais | |
| `posts` + view `blog_posts` | Blog | `blog_posts_legacy` é tabela antiga |
| `post_categories` | Categorias de post | |
| `events` | Eventos | |
| `event_categories` | Categorias de evento | |
| `shows` | Programação do evento | |
| `listings` | Marketplace P2P | |
| `listing_categories` | Categorias do marketplace | |
| `listing_messages` | Chat do anúncio | |
| `listing_reports` | Denúncias | |
| `public_services` | Serviços públicos | |
| `emergency_contacts` | Emergência | |
| `push_subscriptions` | Web Push | |
| `push_notifications` | Campanhas | |
| `push_deliveries` | Entregas | |
| `push_inbox` | Inbox do usuário | |
| `notification_preferences` | Preferências | |
| `notification_templates` | Templates admin | |
| `qa_tickets` | Qualidade | |
| `qa_ticket_comments` | Comentários QA | |
| `qa_ticket_events` | Timeline QA | |
| `analytics_events` | Analytics interno | pouco usado no UI |
| `media` | Metadados de mídia | storage usa bucket, não só esta tabela |
| `promotions` | Promoções | UI pública ainda “em breve” |
| `appointments` | Agendamentos | **schema existe, UI não usa** |
| `marketplace_items` | Marketplace legado | **schema existe; app usa `listings`** |
| `banners` | Banners | pouco/nenhum uso no UI |
| `notifications` | Notificações antigas | substituídas pelo sistema push |

### Enums

```text
app_role:              admin | company_owner | user | editor | publisher
appointment_status:    pending | confirmed | cancelled | completed | no_show
listing_condition:     novo | seminovo | usado
listing_status:        ativo | vendido | pausado | removido
post_type:             article | news | blog | promo | event
publish_status:        draft | scheduled | published | archived
public_service_category: saude | educacao | seguranca | prefeitura | transporte | assistencia_social | emergencia | outros
qa_priority / qa_status / qa_type
```

### Funções RPC que a API PHP precisará reproduzir

| Função | Papel |
| --- | --- |
| `has_role(_user_id, _role)` | Autorização |
| `nearest_city(_lat, _lng)` | Detecção de cidade |
| `get_weekly_ranking()` | Ranking premium |
| `refresh_company_rating(_company_id)` | Recalcular nota (hoje via trigger) |
| `approve_company_claim` / `reject_company_claim` | Reivindicação |
| `handle_new_user` | Cria `profiles` no signup |
| `enforce_premium_verified` | Premium ⇒ verificado |
| `qa_on_status_change` | Timeline QA |
| `grant_default_admin` | Trigger que promove admin em condições específicas |

### Relacionamentos principais

```text
auth.users 1──1 profiles
auth.users 1──N user_roles
cities 1──N companies
categories N──N companies (company_categories)
companies 1──N reviews, leads, favorites, company_media, company_projects, company_faqs, company_views, company_claims
listings 1──N listing_messages, listing_reports
events 1──N shows
posts ── view blog_posts
```

### RLS (resumo)

RLS está **ligado em quase todas as tabelas**. Padrão:

- leitura pública de conteúdo ativo (`companies.status = 'active'`, posts publicados);
- dono (`owner_id` / `user_id`) escreve o próprio registro;
- `has_role(..., 'admin')` (e às vezes `editor`/`publisher`) para backoffice.

**Na migração PHP, RLS some.** A autorização precisa ser reimplementada nos controllers. Este é o maior risco de segurança da migração.

---

## 6. Autenticação

### Fluxo atual

1. **E-mail/senha** — `supabase.auth.signInWithPassword` / `signUp` em `/auth`.
2. **Confirmação de e-mail** — se o signup não devolver sessão, o usuário precisa confirmar o e-mail (Supabase Auth).
3. **Reset** — `resetPasswordForEmail` com redirect para `/auth`.
4. **Google** — `lovable.auth.signInWithOAuth("google")` (`@lovable.dev/cloud-auth-js`); tokens viram sessão Supabase via `setSession`.
5. **Sessão** — persistida em `localStorage` no browser; `autoRefreshToken: true`.
6. **SSR** — server functions autenticadas leem `Authorization: Bearer <jwt>` (`requireSupabaseAuth`).
7. **Perfil** — trigger `on_auth_user_created` cria linha em `profiles`.
8. **Logout** — `supabase.auth.signOut()` no Header e no perfil.

### Papéis (`app_role`)

| Papel | Uso observado |
| --- | --- |
| `admin` | Painel `/admin`, RPCs, push, QA, claims |
| `company_owner` | Atribuído ao aprovar reivindicação |
| `user` | Padrão implícito |
| `editor` / `publisher` | Políticas de posts/eventos; UI admin não diferencia menu |

### Lacunas de autorização

- `/admin` e `/painel` são protegidos **só no React**. Qualquer um que chame o client Supabase com a anon key ainda está sujeito ao RLS — isso “salva” hoje.
- Depois da migração, **toda** escrita precisa de auth no PHP. Não replicar o gate só no frontend.

---

## 7. Storage

| Bucket | Quem usa | Regras atuais |
| --- | --- | --- |
| `media` | Logo/banner da empresa; imagens de anúncios | upload autenticado; path `companies/{id}/{kind}-{ts}.ext`; MIME jpeg/png/webp; máx. 5 MB; URL assinada (10 anos empresa / 5 anos listing) |
| `qa-attachments` | Prints de bug | upload no client; URL assinada 1h para admin |

Imagens também aceitam **URL externa colada** (não passa pelo Storage).

OG/preview do site usa CDN Lovable R2, fora do Supabase.

**Na HostGator:** substituir por `uploads/` no disco + validação MIME/tamanho/nome, servido via Apache (não PHP como executável).

---

## 8. APIs e server functions atuais

Não há REST próprio. O “backend” é o PostgREST do Supabase + funções Start.

### Server functions (`createServerFn`)

| Módulo | Funções |
| --- | --- |
| `cityDetect.functions.ts` | `detectCityByGPS`, `detectCityByIP` |
| `push.functions.ts` | subscribe, unsubscribe, preferências, inbox, markAllRead |
| `admin-push.functions.ts` | sendPushNow, list/get/delete, dashboard stats |
| `qa.functions.ts` | create/list/get/update ticket, comment |
| `duplicates.functions.ts` | scan de duplicatas blog/empresa/evento |

### Rotas servidor

| Método | Caminho | Função |
| --- | --- | --- |
| GET | `/sitemap.xml` | XML dinâmico |
| POST/OPTIONS | `/api/public/push/track` | Tracking de push (CORS `*` hoje) |

### Chamadas client-side típicas (a reproduzir na API PHP)

Leitura pública: cities, categories, companies (filtros, slug, featured), reviews, events, posts, listings, public_services, emergency_contacts, system_settings.

Escrita autenticada: companies CRUD, reviews upsert, favorites, leads, listings, listing_messages, listing_reports, newsletter, claims, profiles, company_views.

Admin: cities, companies, plans_config, system_settings, leads, events/shows, posts, claims RPCs, push, QA.

---

## 9. Rotas — mapa comercial a preservar

Funcionalidades do guia comercial que **não podem ser inventadas nem removidas**:

- empresas, categorias, cidades, busca, filtros, WhatsApp, página comercial;
- favoritos, avaliações, leads/orçamento, newsletter;
- planos free/premium/featured e limites (`src/lib/plans.ts`);
- reivindicar empresa;
- painel do dono + ranking semanal;
- marketplace P2P + mensagens;
- blog, eventos, serviços públicos, emergência, transporte, o-que-fazer, reputação;
- admin completo (cidades, empresas, claims, blog, eventos, push, QA, menu, textos, planos, leads, duplicados);
- PWA + notificações;
- páginas “em breve”: empregos e promoções.

Não existe entidade `services` separada. Não criar `/api/services` sem necessidade.

---

## 10. Variáveis de ambiente

Presentes em `.env` (arquivo **rastreado pelo Git** — ver riscos):

```text
SUPABASE_PROJECT_ID
SUPABASE_PUBLISHABLE_KEY
SUPABASE_URL
VITE_SUPABASE_PROJECT_ID
VITE_SUPABASE_PUBLISHABLE_KEY
VITE_SUPABASE_URL
VITE_LOVABLE_CONNECTOR_GOOGLE_MAPS_BROWSER_KEY
VITE_LOVABLE_CONNECTOR_GOOGLE_MAPS_TRACKING_ID
```

Usadas no código, mas **não** no `.env` versionado:

```text
SUPABASE_SERVICE_ROLE_KEY     # client.server.ts (push track)
VAPID_PUBLIC_KEY              # também hardcoded em src/lib/push-config.ts
VAPID_PRIVATE_KEY
VAPID_SUBJECT
```

`.gitignore` **não** ignora `.env`.  
**Não copiar valores reais para documentação nem para novos commits.**

Variáveis futuras (proposta, não implementada):

```text
APP_ENV, APP_URL
DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
UPLOAD_PATH
SESSION_SECRET
CORS_ORIGIN
VAPID_*
GOOGLE_MAPS_BROWSER_KEY
```

---

## 11. Funcionalidades administrativas

Proteção atual: `useAdmin()` → `supabase.rpc("has_role")`. Sem middleware de servidor nas páginas.

Módulos:

1. Dashboard (contagens de empresas, planos, views).
2. Cidades (CRUD + SEO/hero).
3. Empresas (editar plan/status/featured, excluir).
4. Reivindicações (RPC approve/reject).
5. Eventos e shows.
6. Blog.
7. Serviços públicos e emergência.
8. Detector de duplicados (server function).
9. Web Push (audiências, templates, histórico, envio via `web-push`).
10. QA / tickets.
11. Menu e textos (`system_settings`).
12. Planos (`plans_config`).
13. Leads e leads de planos.
14. Configurações gerais.

Trigger `grant_default_admin` existe nas migrations — precisa ser revisado na migração para **não** promover usuários automaticamente em produção.

---

## 12. Pontos de risco

| # | Risco | Gravidade | Por quê |
| --- | --- | --- | --- |
| 1 | TanStack Start exige runtime JS | **Crítica** | HostGator compartilhada não roda Nitro/Node |
| 2 | RLS some ao sair do Supabase | **Crítica** | Autorização precisa nascer no PHP |
| 3 | `.env` commitado | **Crítica** | Chaves anon + Maps no GitHub; rotacionar |
| 4 | Auth + e-mail + OAuth Google | Alta | PHP precisa de sessão, hash, reset e (se mantido) OAuth |
| 5 | Web Push / VAPID / cron | Alta | Envio hoje é Node (`web-push`); compartilhada não tem worker persistente |
| 6 | Tipos PG → MySQL | Alta | UUID, JSONB, arrays (`text[]`), enums, `pg_trgm`, TIMESTAMPTZ |
| 7 | RPCs e triggers | Alta | Rating, claims, nearest_city, ranking, handle_new_user |
| 8 | URLs assinadas do Storage | Média | Logos/banners atuais apontam para Supabase; links quebram se não migrar arquivos |
| 9 | SEO / sitemap SSR | Média | Sitemap e meta de `/empresa/$slug` hoje no servidor |
| 10 | Lovable Cloud Auth | Média | Google login some sem substituto |
| 11 | Admin só no frontend | Média | Hoje RLS cobre; amanhã não |
| 12 | Push track com CORS `*` | Média | Endpoint público + service role |
| 13 | Dependência `@lovable.dev/vite-tanstack-config` | Média | Build atual acoplado à Lovable |
| 14 | Tabelas órfãs vs. UI | Baixa | `appointments`, `marketplace_items`, `banners` |
| 15 | Marca AgendaAqui vs. nome do repo | Baixa | Não mudar identidade visual |

---

## 13. Arquivos que precisarão mudar

Somente a **camada de comunicação** e o que o runtime HostGator exigir.

### Substituição de dados (obrigatório)

Todos os arquivos da lista da seção 4 (imports Supabase).

### Novos (fase de implementação, não agora)

```text
backend/          PHP REST
database/         migrations MySQL + runner
src/services/     apiClient e *Api.ts
.env.example
public/.htaccess  (ou equivalente no pacote de deploy)
docs restantes
```

### Possível ajuste de build (decisão pendente)

```text
vite.config.ts    # hoje acoplado ao preset Lovable/TanStack Start
src/server.ts     # só existe por causa do SSR
src/start.ts
src/routes/sitemap[.]xml.ts
src/routes/api/public/push/track.ts
package.json      # remover @supabase e, se aprovado, desligar Start SSR
.gitignore        # incluir .env
```

---

## 14. Arquivos que devem permanecer intactos

Não reescrever, não trocar biblioteca, não mudar layout:

```text
src/components/ui/**          # shadcn
src/components/site/**        # visual público (exceto imports de dados)
src/components/panel/**       # visual do painel (exceto upload/API)
src/styles.css                # tokens e tema
src/routes/*.tsx              # JSX/markup das páginas
src/lib/plans.ts              # regras comerciais
src/lib/format.ts
src/lib/utils.ts
src/lib/navItems.ts           # estrutura de menu (trocar só o fetch)
public/manifest.webmanifest
public/sw.js
public/offline.html
components.json
```

Não fazer upgrade de React, Vite, Tailwind, Router ou UI “por preferência”.

---

## 15. Estratégia de migração (ainda não executada)

Ordem pedida, ajustada à realidade do repo:

1. **Auditoria** ← você está aqui.
2. Aprovar arquitetura proposta e o modo de publicação do frontend.
3. Mapear cada tabela/RPC/policy → MySQL + PHP.
4. Criar `database/migrations` + runner seguro (tabela `migrations`, sem DROP).
5. Fundação da API PHP (PDO, JSON, CORS, erros).
6. Auth (register/login/logout/me/reset) + papéis.
7. Empresas + categorias + cidades + busca.
8. Reviews, favoritos, leads, claims.
9. Marketplace, mensagens, blog, eventos, serviços públicos.
10. Admin (espelhar módulos existentes).
11. Uploads locais.
12. Trocar `src/lib/*` e rotas para `src/services/*`.
13. Remover `@supabase/*`, Lovable auth, `src/integrations/supabase`.
14. Testes (build, auth, CRUD, upload, migrations).
15. Build estático + `.htaccess` + guia HostGator.

### Decisão bloqueante antes da Fase 4

Como publicar o frontend **sem Node** na HostGator, preservando o código visual:

**Opção A (recomendada):** modo SPA/cliente do TanStack Router. `npm run build` gera `dist/` estático. Apache faz fallback para `index.html`, exceto `/api/`. Sitemap e detecção de cidade viram PHP. SEO de páginas públicas fica no client + tags estáticas / endpoint PHP de meta se necessário.

**Opção B:** manter TanStack Start SSR. **Incompatível** com HostGator compartilhada. Só faria sentido em VPS/Node.

**Opção C:** prerender das rotas públicas no CI e API PHP para o resto. Mais complexo; só se o SEO SSR for inegociável.

Nenhuma opção recria o frontend.

---

## Arquitetura atual

```text
Browser
  │
  ├─ TanStack Start (SSR + hidratação)
  ├─ TanStack Router + React Query
  │
  ├─ supabase-js (PostgREST + Auth + Storage)
  ├─ createServerFn (Node/Nitro)
  └─ Lovable Cloud Auth (Google)
           │
           ▼
     Supabase (PostgreSQL + Auth + Storage + RLS)
```

## Arquitetura proposta (após aprovação)

```text
Browser (mesmo React + TS + Vite + Tailwind + shadcn + TanStack Router)
  │
  │ HTTPS / REST
  ▼
src/services/apiClient.ts
  │
  ▼
PHP 8.x REST API  (Apache + .htaccess)
  │
  ▼
MySQL/MariaDB + uploads/ no disco
  │
  ▼
HostGator compartilhada
```

Node.js permanece **só** em desenvolvimento e no `npm run build`.

---

## Mapa de migração

| Funcionalidade | Implementação atual | Nova implementação | Arquivos envolvidos | Risco |
| --- | --- | --- | --- | --- |
| Build / runtime | TanStack Start + Nitro + Cloudflare | SPA estático + Apache (Opção A) | `vite.config.ts`, `src/server.ts`, `src/start.ts` | Alto |
| Auth e-mail | Supabase Auth | PHP `password_hash` + sessão/cookie | `auth.tsx`, `favorites.ts`, `Header.tsx` | Alto |
| Auth Google | Lovable Cloud Auth | Adiar ou OAuth PHP (Google) | `integrations/lovable` | Médio |
| Sessão | JWT Supabase + localStorage | Cookie HttpOnly + `/api/auth/me` | `__root.tsx`, `use-admin.ts` | Alto |
| Empresas | `supabase.from("companies")` | `/api/companies` | `queries.ts`, `panel.ts`, `admin.ts`, rotas empresa | Médio |
| Categorias / cidades | `from("categories"/"cities")` | `/api/categories`, `/api/cities` | `queries.ts`, `admin.cidades.tsx` | Baixo |
| Busca / filtros | PostgREST + `pg_trgm` | SQL MySQL com FULLTEXT/LIKE + índices | `queries.ts`, `buscar.tsx` | Médio |
| Reviews | `reviews` + trigger rating | `/api/reviews` + recálculo no PHP | `ReviewsSection.tsx` | Médio |
| Favoritos | tabela `favorites` | `/api/favorites` | `favorites.ts` | Baixo |
| Leads | `leads` / `leads_planos` | `/api/leads` | `QuoteDialog.tsx`, `admin.ts` | Baixo |
| Claims | tabela + RPC | `/api/claims` + approve/reject | `claims.ts` | Médio |
| Planos | `plans_config` + `lib/plans.ts` | `/api/plans` + manter `plans.ts` | `plans.ts`, `admin.planos.tsx` | Baixo |
| Marketplace | `listings` + storage | `/api/listings` + uploads | `marketplace.ts`, `ListingForm.tsx` | Médio |
| Blog / eventos | `posts` / `events` | `/api/posts`, `/api/events` | `blog.ts`, `events.ts` | Baixo |
| Serviços públicos | tabelas dedicadas | `/api/public-services`, `/api/emergency` | `publicServices.ts` | Baixo |
| Menu / textos | `system_settings` | `/api/settings` | `navItems.ts`, `siteContent.ts` | Baixo |
| Admin | RLS + `has_role` | middleware PHP `requireAdmin` | `hooks/use-admin.ts`, rotas admin | Alto |
| Upload | Supabase Storage | `uploads/` + validação PHP | `CompanyImageUpload.tsx`, `marketplace.ts`, QA | Médio |
| Cidade por GPS/IP | RPC + server fn | `/api/geo/nearest`, `/api/geo/ip` | `cityDetect.functions.ts` | Médio |
| Ranking | `get_weekly_ranking` | `/api/ranking/weekly` | `painel.ranking.tsx` | Médio |
| Sitemap | handler Start | `sitemap.php` ou rota PHP | `sitemap[.]xml.ts` | Médio |
| Web Push | server fn + `web-push` | PHP Web Push **ou** fase 2 | `push*.ts`, `admin-push` | Alto |
| QA / duplicados | server fn | `/api/qa`, `/api/admin/duplicates` | `qa.functions.ts`, `duplicates.functions.ts` | Médio |
| PWA | `sw.js` + manifest | manter arquivos | `public/*`, `pwa.ts` | Baixo |
| SEO / visual | rotas + Tailwind | **não alterar markup** | `src/routes/*`, `components/site/*` | — |

---

## Segurança imediata (sem implementar a migração)

1. O arquivo `.env` está no Git com chaves do projeto Supabase e do conector Google Maps.
2. Recomendação: rotacionar as chaves no painel Supabase/Google, adicionar `.env` ao `.gitignore` e parar de versionar segredos.
3. **Não** executar isso sem autorização — altera o repositório e pode quebrar o app Lovable ainda publicado.

---

## Critério desta fase

- [x] Repositório clonado e limpo (`main` = `fd4f7c1`)
- [x] Branch `migration-hostgator` criada
- [x] Stack, rotas, Supabase, banco, auth, storage e riscos documentados
- [ ] Implementação **não iniciada** (aguardando aprovação)
)
