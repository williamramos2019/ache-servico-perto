# AgendaAqui — Planos, Ranking e Painel Admin

Você já tem a base (1.743 empresas, categorias, cidades, busca, página de empresa rica, favoritos, blog, newsletter, planos como página comercial). Esta entrega adiciona a **lógica de planos como motor do sistema** + **painel admin completo**.

---

## 1. Banco de dados (migration)

Reaproveita a tabela `companies` existente (já tem `plan`, `featured`, `status`). Acrescenta:

- `companies.is_verified boolean default false`
- `companies.views_count int default 0`
- `companies.plan_expires_at timestamptz null`
- `companies.banner_url` (já existe) — usado só por premium
- `companies.video_url text null` (premium)
- Constraint: `plan in ('free','premium','featured')`

Novas tabelas:

- `system_settings` (key/value JSON) — preço premium, duração, limite fotos free, raio busca, etc. RLS: leitura pública das chaves marcadas como públicas; escrita só admin.
- `company_views` (company_id, viewed_at, ip_hash) — para métricas do dashboard.
- `plans_config` (slug, name, price_cents, duration_days, max_photos, features jsonb) — fonte de verdade dos planos.

GRANTs + RLS conforme padrão (admin via `has_role`).

## 2. Lógica de ranking (queries.ts)

Toda listagem (home, `/buscar`, `/categoria/$slug`, `/cidades/$slug`, "perto de você") ordena por:

```
ORDER BY
  CASE plan WHEN 'premium' THEN 0 WHEN 'featured' THEN 1 ELSE 2 END,
  rating DESC NULLS LAST,
  review_count DESC
```

Filtros novos na busca: `?plan=free|premium`, abas **Grátis** / **Destaques**.

## 3. UI — diferenciação visual Grátis vs Premium

- **CompanyCard**: variante premium maior, borda gradiente, badge ⭐ Destaque, selo "Patrocinado"; free com card padrão e badge cinza "Grátis" (opcional).
- **Página da empresa**:
  - Free: galeria limitada a 3 fotos, sem banner, sem vídeo, CTAs padrão.
  - Premium: banner topo, galeria ilimitada, vídeo embed, botão WhatsApp grande, CTA fixo no mobile, botão "Solicitar Orçamento" destacado.
- Helper `getPlanLimits(plan)` centraliza limites (fotos, banner, vídeo, badge).

## 4. Mapa (Google Maps)

Novo componente `CompaniesMap` na `/buscar` e home:
- Ícones diferentes: premium = pin laranja maior, free = pin cinza pequeno.
- Filtro proximidade (geolocation do navegador).

## 5. Painel Admin (`/admin/*`, dentro de `_authenticated/`)

Gate: `_authenticated` + verifica `has_role(uid, 'admin')`; se não for admin → redirect.

Rotas:
- `/admin` — Dashboard: total empresas, free, premium, novos (7d), visualizações totais, gráfico simples (recharts já instalado).
- `/admin/empresas` — tabela com busca/filtro, ações: editar, excluir, aprovar, mudar plano, toggle featured/verified.
- `/admin/empresas/nova` e `/admin/empresas/$id` — formulário CRUD completo (com upload de fotos/banner via Storage bucket `company-media`).
- `/admin/planos` — editar `plans_config` (preço, duração, max fotos, features).
- `/admin/configuracoes` — `system_settings` (raio busca, categorias ativas, cidades ativas, limites upload, toggle mapa).
- `/admin/leads` — visualizar leads e leads_planos.

Server functions com `requireSupabaseAuth` + checagem `has_role('admin')` no handler para todas as mutações.

## 6. Storage

Bucket `company-media` (público) para fotos/banners/vídeos. Policies: admin escreve qualquer; owner escreve só sua pasta.

## 7. Mobile-first

Revisar header/cards/CTAs com foco em toque grande, CTA fixo bottom no mobile na página da empresa premium.

## 8. Seed

- Promover ~30 empresas reais existentes para `plan='premium'` e ~15 para `featured` (top rated) só pra demonstrar ranking funcionando.
- Criar 1 conta admin (instruções no fim: usuário se cadastra em `/auth`, rodamos SQL pra atribuir role admin ao email informado).

---

## Entregáveis nesta rodada

1. Migration: colunas novas, `system_settings`, `company_views`, `plans_config`, storage bucket.
2. `src/lib/plans.ts` (limites por plano) + ranking nas queries.
3. Visual: CompanyCard premium + página empresa diferenciada por plano + filtros/abas em `/buscar`.
4. `CompaniesMap` com ícones diferenciados.
5. Painel admin completo (dashboard, CRUD empresas, planos, configurações, leads).
6. Seed promovendo empresas existentes a premium/featured.

## Fora do escopo (ficam para depois)

- Cobrança real (Stripe/Paddle) — hoje plano muda manualmente pelo admin.
- Sistema de impulsionamento pago por busca.
- App mobile nativo.
- Auto-expiração de planos (a coluna `plan_expires_at` fica pronta, mas o cron job fica para próxima).

Confirma que posso seguir? Se quiser, me diz **qual email** vai virar admin (te crio com role direto) — senão você cria conta em `/auth` e me passa o email depois.
