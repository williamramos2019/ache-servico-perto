# Checklist de deploy HostGator — AgendaAqui

Marque só com evidência. Produção não testada pelo Cursor = deixar em branco.

## Pacote local

- [ ] `npx tsc --noEmit` exit 0
- [ ] `npm run build` exit 0
- [ ] `php tools/build-release.php` exit 0
- [ ] ZIP e arquivo `.sha256` conferidos
- [ ] `dist/instalar.php` ausente
- [ ] Nenhum `.env` no ZIP

## Servidor (manual)

- [ ] Backup
- [ ] Upload de `public_html/` e `agendaqui_secure/`
- [ ] Configuração (`load-env.php` fora da web)
- [ ] PHP **8.1+** + `display_errors=Off`
- [ ] HTTPS
- [ ] Migration (`--status` / apply se pending **013–019**)
- [ ] `CRON_SHARED_SECRET` e `PUSH_TRACK_SECRET` no `load-env.php`
- [ ] API (`/api/index.php`, `/api/health.php`)
- [ ] Frontend (`GET /`)
- [ ] Refresh SPA (`/transporte`, `/buscar`, `/empregos`, `/admin`)
- [ ] Login
- [ ] Admin
- [ ] Transporte (`/api/transport/index.php?op=list` — lista vazia é válida)
- [ ] Catálogo (`/api/catalog/index.php`)
- [ ] CSRF (POST sem token → 403)
- [ ] `/api/bootstrap/` bloqueado
- [ ] Logs (sem senha/DSN no HTML)
- [ ] PWA (`/manifest.webmanifest`, `/sw.js`, ícones, instalar no HTTPS)
- [ ] Smoke test (`php tools/smoke-test.php https://SEU-DOMINIO.com.br`)
- [ ] Produção OK

## Não fazer

- [ ] Não enviar `load-env.php` com senha no Git/ZIP
- [ ] Não rodar importador de empresas
- [ ] Não importar fixture de transporte
- [ ] Não abrir migration pelo navegador
