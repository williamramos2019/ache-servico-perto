# Atualização HostGator — AgendaAqui

Use este guia em **toda** versão nova. Nunca substitui o banco nem `load-env.php`.

---

## Fluxo

```
BACKUP (arquivos + MySQL)
  → upload dos arquivos novos
  → manter load-env.php e uploads
  → php tools/migrate.php --status
  → php tools/migrate.php --dry-run
  → php tools/migrate.php   (só se houver pending)
  → testes HTTP
```

---

## 1. Backup

Antes de migration nova ou troca grande de `api/` / `dist/`:

- Arquivos: baixar `public_html/` (pelo menos `index.html`, `assets/`, `api/`, `uploads/`)
- Banco: export no phpMyAdmin / backup do cPanel
- Guardar o dump **fora** do Git e **fora** do ZIP

---

## 2. O que substituir

Pode substituir:

- conteúdo de `public_html/` vindo de `public_html/` do ZIP (SPA + `api/`)
- `/home/USUARIO/agendaqui_secure/api/`
- `/home/USUARIO/agendaqui_secure/database/migrations/` (arquivos **novos** apenas; não reescrever 001–012 já aplicadas se o checksum mudar)
- `/home/USUARIO/agendaqui_secure/tools/migrate.php` e importadores CLI

---

## 3. O que nunca substituir

- `load-env.php`
- `public_html/uploads/` (fotos já enviadas)
- `storage/imports/` com arquivos privados
- dumps e senhas

Se o ZIP trouxer pasta `uploads` vazia, **não** apague a pasta de produção.

---

## 4. Migrations na atualização

```bash
cd /home/USUARIO/agendaqui_secure
php tools/migrate.php --status
php tools/migrate.php --dry-run
```

- Sem pending: não rode o apply.
- Com pending (ex.: 013–019 nesta versão): backup e `php tools/migrate.php`.
- Checksum mismatch: **pare**. Não edite a migration antiga. Abra um ciclo de desenvolvimento com arquivo novo (013+), só se houver necessidade estrutural aprovada.

Sem SSH: Terminal do cPanel ou cron de uma execução, depois remover o cron.

Não rode importador de empresas nem de transporte automaticamente.

---

## 5. Validar

1. `GET /api/health.php`
2. `GET /`
3. Login + CSRF
4. `/buscar` (catálogo)
5. `/transporte` (pode estar vazio)
6. `/empregos`, `/representantes`, `/agora` (exigem 013–019)
7. `/admin` (com conta admin)
8. Scrapers/IA: a tela deve dizer que **não rodam** no cPanel — isso não é bug de upload

Detalhes: `DEPLOY-CHECKLIST.md`.

---

## 6. Se algo falhar

Ver `ROLLBACK-HOSTGATOR.md`. Restaure arquivos do backup. **Não** faça DROP de tabelas para “voltar” schema.
