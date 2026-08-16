# SEO — o que a SPA muda e o que fica para depois

## Comportamento atual (antes)

TanStack Start renderizava HTML no servidor. O `head()` das rotas ia para o documento inicial.

`/sitemap.xml` era um handler GET que lia cidades, categorias e empresas ativas no Supabase.

`/empresa/$slug` já usava o **slug** no title/description (não o nome da empresa). Ou seja, o SSR não gerava um title comercial rico — só o slug.

## O que a SPA faz agora

- `HeadContent` atualiza title/meta **depois** do JavaScript carregar.
- Crawlers que não executam JS veem o title genérico de `index.html`.
- `/sitemap.xml` cai no fallback SPA (`index.html`) até existir um endpoint PHP.
- Canonical e OG das rotas continuam definidos no `head()` e são aplicados no cliente.

## O que se perde

| Recurso                           | Perda                        |
| --------------------------------- | ---------------------------- |
| HTML inicial com title da empresa | Sim, para crawlers sem JS    |
| Sitemap dinâmico                  | Sim, até PHP                 |
| OG image por empresa              | Já não existia de forma rica |
| JSON-LD                           | Já não existia               |

## Impacto

Googlebot moderno executa JS, então a maior parte das rotas públicas deve continuar indexável.  
Compartilhamento em redes (WhatsApp/Facebook) que leem só o HTML inicial ficará genérico até haver prerender ou PHP.

## Solução futura (não implementada)

1. **PHP `sitemap.php`** (ou `/sitemap.xml` via `.htaccess`) consultando MySQL.
2. **Prerender no CI** das rotas públicas (`/`, `/empresa/*`, `/categoria/*`, `/cidades/*`, `/blog/*`).
3. **Endpoint PHP de meta** que devolve HTML mínimo com title/OG para crawlers, se o SEO SSR for inegociável.

Nenhuma dessas opções será feita nesta fase.
