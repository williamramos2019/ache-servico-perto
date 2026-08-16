# Relatório de status — Ache Serviço Perto / AgendaAqui

**Data:** 2026-08-15  
**Branch:** `migration-hostgator`  
**Base:** `main` em `fd4f7c1` (`Criou sistema de reivindicar`)  
**Commit da conversão:** preparado (staged), ainda não gravado — falta identidade Git (`user.name` / `user.email`)

---

## 1. O que foi feito

Duas etapas concluídas, sem migrar o backend:

1. **Auditoria** do projeto Lovable/Supabase.
2. **Conversão do frontend** de TanStack Start (SSR/Nitro/Node) para **SPA estática** compatível com Apache/HostGator.

O Supabase continua sendo o backend temporário.

---

## 2. O que foi alterado

### Runtime e build

| Antes | Agora |
| --- | --- |
| TanStack Start + Nitro + Cloudflare | Vite estático (`dist/index.html` + `assets/`) |
| `@lovable.dev/vite-tanstack-config` | `vite.config.ts` próprio (React + Tailwind + TanStack Router) |
| Node necessário em produção | Node só em `npm install` / `npm run build` |
| Sem `index.html` de entrada | `index.html` + `src/main.tsx` |

Dependências removidas do `package.json`:

- `@tanstack/react-start`
- `nitro`
- `@lovable.dev/vite-tanstack-config`

Preservadas: React 19, TypeScript, Vite, Tailwind 4, shadcn/ui, TanStack Router, React Query, Supabase.

### Camada de dados (só comunicação, não o banco)

As `createServerFn` passaram a ser funções async no **navegador**, falando com o Supabase:

- cidade por GPS/IP
- inbox e preferências de push
- QA (tickets)
- admin de push (listar/ver/apagar/estatísticas)
- detector de duplicados

O disparo real de Web Push (`sendPushNow`) foi **desativado de propósito** (precisa de chave VAPID privada no servidor).

### Rotas server-only

Saíram do router da SPA e foram guardadas em `src/legacy-server/`:

- sitemap XML
- `/api/public/push/track`
- entry SSR (`server.ts`, `start.ts`)
- middlewares Start de auth

### Apache

`public/.htaccess` (copiado para `dist/` no build):

- serve arquivos reais
- reserva `/api/` para PHP futuro
- demais URLs caem em `index.html` (TanStack Router)

### Visual

Não foi refeito. Páginas, layout, Tailwind e shadcn permanecem.

---

## 3. Estado atual

```text
Browser
  → Apache / HostGator (quando publicar o dist/)
  → index.html
  → React + TanStack Router + React Query
  → Supabase (Auth + Database + Storage)
```

### O que funciona

- `npm run build` gera SPA estática
- `npx tsc --noEmit` passa
- Rotas SPA testadas localmente com `npx serve dist -s`:
  `/`, `/buscar`, `/empresa/...`, `/categoria/...`, `/cidades/...`, `/painel`, `/admin`, `/auth`, `/marketplace`
- CSS, JS, manifest, service worker e robots
- Login/cadastro/Google e CRUD via Supabase (temporário)
- PWA (manifest + `sw.js` + offline)

### O que ficou limitado nesta fase

| Item | Situação |
| --- | --- |
| Sitemap `/sitemap.xml` | Não é mais gerado no servidor |
| Tracking de push | SW chama `/api/...` e falha em silêncio |
| Envio admin de Web Push | Erro explícito na tela |
| SEO para crawler sem JS | Title inicial genérico do `index.html` |
| IP no ticket QA | Não capturado |
| Commit Git | Staged, não gravado (sem identidade) |
| `.env` no repositório | Continua versionado (não rotacionamos chaves) |

### Git

- Trabalho na branch `migration-hostgator` (não na `main`)
- Alterações **staged**, aguardando `git commit`
- Sem push

---

## 4. O que NÃO foi feito (de propósito)

- API PHP
- MySQL/MariaDB
- Migrations MySQL
- Troca de Auth / OAuth / Storage
- Remoção do Supabase
- Novo design / novo React / novo Tailwind
- Deploy na HostGator

---

## 5. Próximos passos (ordem recomendada)

### Imediato

1. Configurar identidade Git e gravar o commit  
   `refactor: convert tanstack start to static spa`
2. (Opcional) Publicar o `dist/` numa pasta da HostGator só para validar a SPA com Supabase ainda ligado.

### Fase seguinte — backend (só depois de autorizar)

```text
Supabase  →  PHP 8 REST API  →  MySQL/MariaDB  →  HostGator
```

Ordem sugerida:

1. MySQL + migrations automáticas (tabela `migrations`, sem DROP)
2. Fundação da API PHP (PDO, JSON, CORS, erros)
3. Autenticação própria (cadastro, login, sessão, papéis)
4. Empresas, categorias, cidades, busca
5. Reviews, favoritos, leads, claims
6. Marketplace, blog, eventos, admin
7. Uploads locais no lugar do Storage
8. Trocar `src/lib/*` por `src/services/*` apontando para `/api/`
9. Remover `@supabase/*` e Lovable Auth
10. Sitemap/push em PHP
11. Testes + build + guia de deploy HostGator

Documentação de apoio já existente:

- `docs/AUDIT.md` — mapa do sistema original
- `docs/ARCHITECTURE.md` — arquitetura antiga vs atual
- `docs/STATIC-SPA.md` — detalhes da conversão SPA
- `docs/SEO-MIGRATION.md` — SEO perdido e solução futura
- `docs/SERVER-FUNCTIONS-MIGRATION.md` — cada server function e destino PHP
