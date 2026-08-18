# Subir AgendaAqui 1.1.0 na HostGator

O Cursor não faz FTP. Este arquivo é o roteiro de upload.

Pacote: `AgendaAqui-hostgator-v1.1.0.zip`  
SHA-256: arquivo `AgendaAqui-hostgator-v1.1.0.zip.sha256` ao lado do ZIP.

O site atual (`/api/health.php` ok, `/api/jobs/index.php` 404) **ainda não é este pacote**. Depois do upload, `/empregos` tem de responder.

## O que este ZIP é

SPA Vite + PHP 8.1 + MySQL/MariaDB no cPanel. Sem Node no servidor.

## O que este ZIP não é

- Não é o runtime Lovable (TanStack Start / Supabase).
- Scrapers municipais, IA de blog e **envio** Web Push não rodam no compartilhado. As telas admin dizem isso.

## Antes

1. Backup de `public_html` (File Manager).
2. Dump MySQL (phpMyAdmin / backup cPanel).
3. MultiPHP do domínio: **8.1 ou 8.2**. 8.0 quebra.
4. Não apague `uploads/` nem o `load-env.php` antigo se já existir.

## Upload

Descompacte o ZIP no computador.

| Pasta do ZIP | Destino |
| --- | --- |
| conteúdo de `public_html/` | document root do domínio |
| conteúdo de `agendaqui_secure/` | `/home/USUARIO/agendaqui_secure/` **fora** da web |

Instalações antigas em `/home/USUARIO/agendaqui/` continuam sendo lidas. Não envie `.env`, senhas, `node_modules`, `src/`.

## Banco

**Atualização (já tem site):**

```bash
cd /home/USUARIO/agendaqui_secure
php tools/migrate.php --status
php tools/migrate.php --dry-run
php tools/migrate.php
```

Acrescente no `load-env.php` existente (não substitua o arquivo):

```
CRON_SHARED_SECRET=...
PUSH_TRACK_SECRET=...
```

**Primeira instalação:**

1. Envie `agendaqui_secure/` primeiro.
2. Abra `/atualizar-banco.php`, preencha banco / usuário / senha MySQL (não a senha do cPanel).
3. Confirme que o instalador se apagou. Se não, apague no File Manager.

## Testar

1. `GET /api/health.php` → `database: ok`
2. `GET /empregos` — se a API 404, o ZIP novo não está no ar
3. Login em `/auth`
4. `/representantes`, `/agora`, `/transporte`, `/ofertas-shopee`
5. `/admin/scraper-sjl` deve dizer que **não há crawler** — isso é correto

## Cron (opcional)

```
php /home/USUARIO/agendaqui_secure/tools/scheduled-hooks.php --task=all
```

Ou POST `/api/cron/index.php` com header `X-Cron-Secret`. Sem `CRON_SHARED_SECRET` o cron HTTP devolve 403.

## Se falhar

Restaure o backup de arquivos + dump. Não use `DROP TABLE`. Ver `ROLLBACK-HOSTGATOR.md`.
