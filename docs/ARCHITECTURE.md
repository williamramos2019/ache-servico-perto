# Arquitetura — Ache Serviço Perto / AgendaAqui

**Branch:** `migration-hostgator`  
**Fase atual:** SPA estática (TanStack Start/SSR removido do runtime de produção)

## Arquitetura antiga

```text
Browser
  → TanStack Start (SSR)
  → TanStack Router + React Query + Supabase
  → Nitro / Node
```

## Arquitetura atual (esta fase)

```text
Browser
  → Apache / HostGator
  → index.html
  → React + TanStack Router + React Query
  → Supabase (temporário)
```

Node.js é usado **somente** em desenvolvimento e no `npm run build`.

## O que mudou

- Vite passou a gerar um `dist/` estático.
- `createServerFn` virou funções async no cliente (Supabase).
- Rotas server-only (`sitemap.xml`, `/api/public/push/track`) saíram do router.
- `.htaccess` faz fallback SPA e reserva `/api/` para PHP futuro.

## O que não mudou

- Visual, rotas, shadcn, Tailwind, TanStack Router, React Query.
- Supabase Auth, Database, Storage, RLS, OAuth Google.

## Próxima fase (não iniciada)

```text
Supabase → PHP REST API → MySQL/MariaDB
```
