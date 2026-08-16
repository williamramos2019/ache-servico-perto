# Conversão para SPA estática

## Por que o preset Lovable foi substituído

`@lovable.dev/vite-tanstack-config` embute TanStack Start + Nitro.  
O build gerava um runtime Node/Cloudflare, incompatível com HostGator compartilhada.

A menor alteração que produz `dist/index.html` + `dist/assets/` foi:

```text
Vite + @vitejs/plugin-react
     + @tailwindcss/vite
     + @tanstack/router-plugin
     + vite-tsconfig-paths
```

## Arquivos alterados

| Arquivo                        | Motivo                                                                        |
| ------------------------------ | ----------------------------------------------------------------------------- |
| `vite.config.ts`               | Build estático sem Start/Nitro                                                |
| `package.json`                 | Removeu `@tanstack/react-start`, `nitro`, `@lovable.dev/vite-tanstack-config` |
| `index.html`                   | Entrada SPA                                                                   |
| `src/main.tsx`                 | `createRoot` + `RouterProvider`                                               |
| `src/routes/__root.tsx`        | Removeu `shellComponent` / `Scripts`                                          |
| `src/router.tsx`               | Registro de tipos do Router                                                   |
| `src/lib/*.functions.ts`       | `createServerFn` → funções cliente                                            |
| `src/lib/spa-auth.ts`          | `getUser()` no lugar do middleware Start                                      |
| Telas que usavam `useServerFn` | Chamada direta (sem mudar o JSX visual)                                       |
| `public/.htaccess`             | Fallback SPA + reserva `/api/`                                                |
| `tsconfig.json`                | Exclui `src/legacy-server`                                                    |

## Arquivos criados

```text
index.html
src/main.tsx
src/vite-env.d.ts
src/lib/spa-auth.ts
public/.htaccess
.env.example
src/legacy-server/**
docs/STATIC-SPA.md
docs/SEO-MIGRATION.md
docs/SERVER-FUNCTIONS-MIGRATION.md
docs/ARCHITECTURE.md
```

## Arquivos preservados (não no grafo da SPA)

```text
src/legacy-server/server.ts
src/legacy-server/start.ts
src/legacy-server/auth-attacher.ts
src/legacy-server/auth-middleware.ts
src/legacy-server/sitemap.xml.ts
src/legacy-server/push-track.ts
```

## Arquivos visuais intactos

`src/components/ui/**`, markup de site/panel, `src/styles.css`, `plans.ts`, `format.ts`, `utils.ts`, PWA em `public/`.

## Como publicar

1. `npm install`
2. `npm run build`
3. Enviar o conteúdo de `dist/` para `public_html/` (incluindo `.htaccess`)

## Como testar localmente o `dist/`

```bash
npx --yes serve dist -s
```

O `-s` simula o fallback SPA. No Apache o `.htaccess` faz o mesmo, **exceto** que `/api/` não cai no `index.html`.

## Limitações desta fase

- Sitemap dinâmico não é gerado.
- Tracking de push (`/api/public/push/track`) não tem servidor.
- Disparo admin de Web Push está desativado (VAPID privado não pode ir ao browser).
- Meta tags de `/empresa/$slug` usam o slug, não o nome real da empresa (já era assim no `head()`).
- IP do ticket QA não é capturado.
