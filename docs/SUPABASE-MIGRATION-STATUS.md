# Estado da migração Supabase → PHP/MySQL

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 3.1  
**Fonte:** Fase 3.0 (integração SPA/API)

Nada foi removido. O diretório `supabase/` permanece.  
O primeiro deploy HostGator **convive** com o Supabase nas áreas ainda não migradas.

---

## Já no PHP (não remover o resto)

| Área | Endpoints | Notas |
| --- | --- | --- |
| Login e-mail/senha | `POST /api/auth/login.php` | Cookie de sessão; confirmar com `/me` |
| Registro | `POST /api/auth/register.php` | Sem sessão automática |
| Logout | `POST /api/auth/logout.php` | |
| Sessão /me | `GET /api/auth/me.php` | 401 = anónimo |
| CSRF | `GET /api/auth/csrf.php` | memória no cliente |
| Perfil | `GET`/`PATCH /api/users/me.php` | |
| Empresas do dono | `mine` / `create` / `show?id=` / `update` | sem `owner_id` no body |

Ficheiros: `src/lib/php-api.ts`, `src/lib/php-auth.ts`, `src/routes/auth.tsx` (senha), `src/hooks/use-admin.ts`, `src/lib/panel.ts` (CRUD empresas + perfil), Header/logout.

---

## Ainda dependente do Supabase

Para cada item: **PODE MIGRAR AGORA = não** — não há endpoint PHP equivalente (Fase 3.1 não cria endpoints).

### Autenticação residual

ARQUIVO: `src/routes/auth.tsx`  
FUNÇÃO: botão Google  
DEPENDÊNCIA: `lovable.auth.signInWithOAuth("google")`  
STATUS: ativo na UI  
PODE MIGRAR AGORA: não  
MOTIVO: API PHP não tem OAuth.

ARQUIVO: `src/routes/auth.tsx`  
FUNÇÃO: recuperar senha  
DEPENDÊNCIA: toast “ainda não está disponível” (antes Supabase reset)  
STATUS: desativado de propósito  
PODE MIGRAR AGORA: não  
MOTIVO: sem endpoint PHP de reset; não reativar Supabase reset nesta fase.

ARQUIVO: `src/lib/spa-auth.ts`  
FUNÇÃO: `requireUser` / `requireAdmin`  
DEPENDÊNCIA: `supabase.auth.getUser` + RPC `has_role`  
STATUS: usado por admin, push, QA, duplicados  
PODE MIGRAR AGORA: não  
MOTIVO: sessão PHP não alimenta este helper.

ARQUIVO: `src/integrations/supabase/client.ts`  
FUNÇÃO: cliente browser  
DEPENDÊNCIA: `VITE_SUPABASE_URL` + anon key  
STATUS: em uso  
PODE MIGRAR AGORA: não  
MOTIVO: restante do produto ainda lê PostgREST.

---

### Diretório público

ARQUIVO: `src/lib/queries.ts`  
FUNÇÃO: home, featured, categorias, busca  
DEPENDÊNCIA: tabelas `companies`, `categories`, cidades  
STATUS: único alimentador das listagens públicas  
PODE MIGRAR AGORA: não  
MOTIVO: `GET /api/companies/index.php` não cobre slug/filtros/cidade; não está ligado à UI.

ARQUIVO: `src/routes/empresa.$slug.tsx`  
FUNÇÃO: ficha pública  
DEPENDÊNCIA: `companies` por **slug**  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: `show.php` é por `id`, não por slug.

ARQUIVO: `src/lib/publicServices.ts`, `src/lib/navItems.ts`, `src/lib/siteContent.ts`  
FUNÇÃO: serviços públicos, menu, textos  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

ARQUIVO: `src/lib/cityDetect.functions.ts`  
FUNÇÃO: cidade por GPS/IP  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

---

### Painel (parcial)

ARQUIVO: `src/lib/panel.ts`  
FUNÇÃO: `listCities`, `listMyLeads`, `listMyReviews`, stats de leads/favoritos  
DEPENDÊNCIA: `cities`, `leads`, `reviews`, `favorites`  
STATUS: ativo (CRUD de empresa já é PHP)  
PODE MIGRAR AGORA: não  
MOTIVO: sem endpoints; `cities` no MySQL pode estar vazio.

ARQUIVO: `src/components/panel/CompanyImageUpload.tsx`  
FUNÇÃO: upload logo/banner  
DEPENDÊNCIA: Supabase Storage  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: HostGator ainda sem upload PHP.

ARQUIVO: `src/components/panel/ListingForm.tsx`  
FUNÇÃO: anúncios  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

---

### Favoritos, reviews, orçamentos

ARQUIVO: `src/lib/favorites.ts`, `src/routes/favoritos.tsx`, `src/routes/painel.favoritos.tsx`  
FUNÇÃO: favoritos  
DEPENDÊNCIA: tabela `favorites` + auth Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: login PHP não preenche `supabase.auth`.

ARQUIVO: `src/components/site/ReviewsSection.tsx`  
FUNÇÃO: avaliações na ficha  
DEPENDÊNCIA: `reviews`  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP de reviews.

ARQUIVO: `src/components/site/QuoteDialog.tsx`, `src/routes/painel.mensagens.tsx`  
FUNÇÃO: leads / mensagens  
DEPENDÊNCIA: `leads`  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

---

### Claims, marketplace, blog, eventos, planos

ARQUIVO: `src/lib/claims.ts`  
FUNÇÃO: reivindicar empresa  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

ARQUIVO: `src/lib/marketplace.ts`, `src/routes/marketplace.tsx`, `src/routes/marketplace.$slug.tsx`  
FUNÇÃO: marketplace  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

ARQUIVO: `src/lib/blog.ts`, `src/routes/admin.blog.tsx`  
FUNÇÃO: blog  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

ARQUIVO: `src/lib/events.ts`, `src/routes/admin.eventos.tsx`  
FUNÇÃO: eventos  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

ARQUIVO: `src/routes/planos.tsx`  
FUNÇÃO: planos  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: `plan`/`featured` são recusados na API de empresas.

---

### Push, QA, admin

ARQUIVO: `src/lib/push.functions.ts`, `src/lib/admin-push.functions.ts`, rotas `admin.push.*`  
FUNÇÃO: Web Push  
DEPENDÊNCIA: `spa-auth` + tabelas + VAPID (privado não vai ao browser)  
STATUS: disparo real já desativado na conversão SPA  
PODE MIGRAR AGORA: não  
MOTIVO: precisa PHP/cron + chave no servidor; fora do escopo.

ARQUIVO: `src/lib/qa.functions.ts`, `src/components/qa/BugReportButton.tsx`  
FUNÇÃO: tickets QA  
DEPENDÊNCIA: Supabase + admin  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

ARQUIVO: `src/lib/admin.ts`, `src/lib/duplicates.functions.ts`, `src/routes/admin.cidades.tsx` e demais `admin.*`  
FUNÇÃO: backoffice  
DEPENDÊNCIA: `requireAdmin` + PostgREST  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: role `admin` no PHP existe no `/me`, mas o admin UI não usa essa sessão.

ARQUIVO: `src/components/site/NewsletterForm.tsx`  
FUNÇÃO: newsletter  
DEPENDÊNCIA: Supabase  
STATUS: ativo  
PODE MIGRAR AGORA: não  
MOTIVO: sem API PHP.

---

## Implicação para o primeiro deploy

A HostGator pode servir a SPA + API PHP de **conta/perfil/empresas do dono**.  
Listagens públicas e a maior parte do produto **continuam a falar com o Supabase** (chaves Vite no bundle).

Isso é esperado até fases posteriores. Não desligar o projeto Supabase.
