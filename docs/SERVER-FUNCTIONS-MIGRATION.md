# Server functions — inventário e destino

Nesta fase as funções deixaram de ser `createServerFn` (TanStack Start).  
Nada foi migrado para PHP.

## Inventário

| Arquivo                        | Função                                  | Finalidade            | Destino nesta fase              | Destino futuro           |
| ------------------------------ | --------------------------------------- | --------------------- | ------------------------------- | ------------------------ |
| `cityDetect.functions.ts`      | `detectCityByGPS`                       | RPC `nearest_city`    | Cliente → Supabase RPC          | `/api/geo/nearest`       |
| `cityDetect.functions.ts`      | `detectCityByIP`                        | Geo por IP            | Cliente → `ipapi.co/json` + RPC | `/api/geo/ip`            |
| `push.functions.ts`            | subscribe / unsubscribe / prefs / inbox | Push do usuário       | Cliente → Supabase (RLS)        | `/api/push/*`            |
| `admin-push.functions.ts`      | list / get / delete / stats             | Admin lê campanhas    | Cliente → Supabase              | `/api/admin/push`        |
| `admin-push.functions.ts`      | `sendPushNow`                           | Disparo VAPID         | **Desativado** (erro explícito) | PHP + `web-push`         |
| `qa.functions.ts`              | create / list / get / update / comment  | QA                    | Cliente → Supabase              | `/api/qa`                |
| `duplicates.functions.ts`      | `scanDuplicates`                        | Detector de cópia     | Cliente → Supabase + cálculo    | `/api/admin/duplicates`  |
| `legacy-server/sitemap.xml.ts` | sitemap                                 | XML dinâmico          | Fora do router                  | `sitemap.php`            |
| `legacy-server/push-track.ts`  | track                                   | Contadores de entrega | SW falha em silêncio            | `/api/public/push/track` |
| `legacy-server/server.ts`      | SSR entry                               | Nitro/Start           | Preservado, não usado           | —                        |
| `legacy-server/start.ts`       | Start instance                          | Middleware            | Preservado, não usado           | —                        |
| `lib/push-send.server.ts`      | `sendWebPush`                           | VAPID Node            | Não importado pela SPA          | PHP                      |

## Dependências SSR removidas do runtime

| Dependência                         | Finalidade             | Necessária para SPA? | Necessária para build? | Produção HostGator? | Ação                                                 |
| ----------------------------------- | ---------------------- | -------------------- | ---------------------- | ------------------- | ---------------------------------------------------- |
| `@tanstack/react-start`             | SSR + server functions | Não                  | Não                    | Não                 | Removida                                             |
| `nitro`                             | Runtime do Start       | Não                  | Não                    | Não                 | Removida                                             |
| `@lovable.dev/vite-tanstack-config` | Preset Start+Nitro     | Não                  | Impedia build estático | Não                 | Removida                                             |
| `@tanstack/router-plugin`           | Rotas file-based       | Sim                  | Sim                    | Não (só build)      | Mantida                                              |
| `@tanstack/react-router`            | Router                 | Sim                  | Sim                    | Bundle              | Mantida                                              |
| `@vitejs/plugin-react`              | JSX                    | Sim                  | Sim                    | Não                 | Mantida                                              |
| `@tailwindcss/vite`                 | CSS                    | Sim                  | Sim                    | Não                 | Mantida                                              |
| `web-push`                          | Envio VAPID            | Não nesta fase       | Não                    | Futuro PHP          | Mantida no package (código em `push-send.server.ts`) |
| `@lovable.dev/cloud-auth-js`        | Google OAuth           | Sim (temporário)     | Sim                    | Bundle              | Mantida                                              |
| `@supabase/supabase-js`             | Backend temporário     | Sim                  | Sim                    | Bundle              | Mantida                                              |

## Fluxo futuro

```text
server function  →  PHP REST API  →  MySQL
```
