# AgendaAqui — Entrega 1 (Núcleo Público)

Marketplace regional de serviços/empresas, inspirado no visual do template U-Listing, reconstruído como app moderno em React + Lovable Cloud. Esta entrega cobre o que o usuário final vê e usa para descobrir empresas. Áreas da empresa, planos, créditos, pagamentos, blog, chat IA Lara, agendamento e admin ficam para próximas entregas.

## Stack
- Frontend: TanStack Start (React + TS), Tailwind, shadcn/ui
- Backend: Lovable Cloud (Postgres + Auth + Storage)
- Mapa: Google Maps (via conector Lovable, configurável depois — fallback estático no v1)
- Domínio agendaaqui.online: apontado depois via Publish

> Observação: Laravel/PHP/MySQL/FilamentPHP/HostGator não são suportados no Lovable. O equivalente é Lovable Cloud (Postgres) e o painel admin será web em React (entrega futura).

## Identidade visual
- Marca: AgendaAqui — "Seu serviço certo, na hora certa."
- Tokens semânticos (index.css):
  - `--primary` #0057FF / `--primary-dark` #0037A6
  - `--accent` #FF6B00
  - `--surface` #F5F7FA / `--background` #FFFFFF
- Tipografia: Inter (corpo) + Plus Jakarta Sans (display) via @fontsource
- Componentes inspirados no U-Listing: hero com busca dupla, cards de categoria com ícone, cards de listing com foto/rating/badge, mapa lateral, breadcrumb.

## Páginas (rotas TanStack)
1. `/` Home
   - Hero com busca: "O que procura?" + "Cidade" (4 cidades) + botão Buscar
   - Grade de 12 categorias (ícone + nome)
   - Carrossel "Empresas em destaque"
   - Bloco "Como funciona" e cidades atendidas
2. `/buscar` Resultados
   - Filtros: categoria, cidade, distância, avaliação, abertos agora
   - Lista de cards + mapa lateral (desktop) / toggle (mobile)
   - Paginação
3. `/categoria/$slug` Listagem por categoria
4. `/empresa/$slug` Perfil
   - Banner + logo, descrição, galeria, horários, mapa, avaliações
   - Botões WhatsApp, Ligar, Compartilhar (Solicitar Orçamento abre modal simples gravando lead)
5. `/cidades/$slug` Página da cidade (SEO)
6. `/sobre`, `/contato` (estáticas leves)
7. Auth: `/auth` (login/cadastro email+senha + Google) — necessário só para deixar avaliação

## Banco de dados (Lovable Cloud)
Tabelas v1 com RLS:
- `cities` (id, name, slug, state)
- `categories` (id, name, slug, icon, description)
- `companies` (id, slug, name, description, phone, whatsapp, address, city_id, zip, lat, lng, website, instagram, facebook, hours jsonb, logo_url, banner_url, plan text default 'free', featured bool, status, created_at)
- `company_categories` (company_id, category_id) — N:N
- `company_media` (id, company_id, type [photo|video], url, sort)
- `reviews` (id, company_id, user_id, rating 1-5, comment, created_at)
- `leads` (id, company_id, name, phone, message, created_at) — solicitar orçamento
- `profiles` (id=auth.uid, name, avatar_url)
- `user_roles` + enum `app_role` + função `has_role` (preparar admin)

Políticas:
- SELECT público (anon) em cities, categories, companies, company_media, reviews
- INSERT reviews/leads apenas authenticated; user só edita o próprio review
- Roles para futuro admin

## Seed
~25 empresas fictícias distribuídas entre Vespasiano, São José da Lapa, Lagoa Santa e BH, cobrindo as 12 categorias, com lat/lng aproximadas, horários e 2–4 reviews cada.

## SEO
- `head()` por rota com title/description/OG/twitter
- JSON-LD `LocalBusiness` nas páginas de perfil
- Sitemap dinâmico + robots.txt
- URLs amigáveis por slug

## Fora do escopo desta entrega
Área da empresa (dashboard), planos pagos, sistema de créditos, gateways (PIX/MP/Asaas/PagSeguro), blog, chat IA Lara, agendamento com calendário, PWA/push, painel admin completo, notificações por email/WhatsApp. Esquema deixa ganchos (campo `plan`, `featured`, tabela `leads`) para encaixar depois.

## Próximos passos sugeridos (entregas futuras)
2. Área da empresa + cadastro/edição de perfil
3. Chat IA Lara (Lovable AI) + agendamento
4. Planos + créditos + pagamentos
5. Blog + painel admin web
6. PWA + notificações