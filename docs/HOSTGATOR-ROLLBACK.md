# Rollback HostGator

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 3.1

O runner **não tem** comando de rollback (`DOWN`, `migrate:rollback`, `--undo`).  
Não inventar reversão automática de schema.

---

## 1. O que o runner faz em falha

Cada migration corre numa transação PDO:

- sucesso → `COMMIT` + linha em `migrations` com `success = 1`
- erro → `ROLLBACK` do SQL dessa migration + tentativa de gravar `success = 0`
- as seguintes **não** correm

Não há `DROP` automático. Não há `--force`.

---

## 2. Como interromper uma migration

Se o processo CLI ainda está a correr: interromper o processo (`Ctrl+C` / matar o PID).

Depois:

```bash
cd /home/USUARIO/agendaqui
php tools/migrate.php --status
```

O lock MySQL é `GET_LOCK('migration_runner_lock')` (timeout default 30 s). Se o processo morreu de forma abrupta, o lock some com a sessão MySQL.

Não lançar um segundo `php tools/migrate.php` em paralelo.

---

## 3. Como identificar migration parcialmente aplicada

Sinais:

- `--status` exit 1 com “previously failed (success=0)”
- tabela `migrations` com `success = 0` para essa versão
- health pode continuar `database = ok` (o PDO liga; o schema é que ficou a meio)

O SQL da migration falhada **não** deve ter ficado persistido se o `ROLLBACK` correu. Exceção rara: statement que faça commit implícito (não é o caso das `001`–`003` atuais, que são `CREATE TABLE`).

Inspecionar no phpMyAdmin **sem** apagar dados de negócio:

```sql
SELECT version, name, success, checksum, executed_at
FROM migrations
ORDER BY version;
```

Não executar `DROP DATABASE`. Não restaurar dump de teste por cima de produção.

---

## 4. Como recuperar de erro (schema)

1. Ler a mensagem do runner (sem copiar senha/DSN para tickets públicos).
2. Se `success = 0`: corrigir a **causa** (permissões, disco, SQL incompatível). **Não** editar o `.sql` já usado se a linha de sucesso `1` já existir.
3. Para `success = 0` o próprio runner pede: inspecionar o banco e **DELETE da linha falhada** antes de repetir (ver `tools/migrate.php` / `classify_migrations`).
4. Só então: `--status` → `--dry-run` → apply.

Checksum mismatch (`success = 1` mas hash diferente): **não** “corrigir” o ficheiro antigo. Adicionar uma migration nova `004_...sql` numa fase posterior. Restaurar o ficheiro aplicado ao hash registado se alguém o alterou por acidente.

---

## 5. Como remover / restaurar a SPA

A SPA é ficheiros estáticos em `public_html/`.

Remover a versão atual (exemplo):

1. Manter uma cópia `public_html.bak-AAAAMMDD/` **fora** ou ao lado, feita **antes** de substituir.
2. Apagar apenas artefactos do build anterior: `index.html`, `assets/`, `sw.js`, etc.
3. **Não** apagar `public_html/api/` a menos que se esteja a repor a API de propósito.
4. Enviar de novo o conteúdo de `dist/` da versão anterior (build local guardado).

Restaurar:

- Copiar de volta `public_html.bak-...` sobre `public_html`, ou
- Fazer `npm run build` no commit anterior e reenviar `dist/`.

Refresh Apache: o `.htaccess` da SPA tem de voltar com o `dist`.

---

## 6. Como preservar o banco

| Ação | Permitido? |
| --- | --- |
| Restaurar só ficheiros SPA | sim |
| Restaurar só `public_html/api/` | sim |
| `DROP DATABASE` / `DROP TABLE` | **não** (automático nem “para limpar”) |
| Reimportar dump de teste | **não** |
| Apagar linhas de `users` / `companies` em massa | **não** nesta fase |
| Backup mysqldump **antes** do primeiro apply | sim (guardar **fora** da web, 0600) |

O banco é a fonte de verdade das contas PHP. Rollback de frontend **não** desfaz registos.

---

## 7. O que NÃO apagar

- Database MySQL do cPanel
- Utilizador MySQL da aplicação
- `load-env.php` (fora da web)
- `storage/rate-limit/` (só JSON de contagem; pode-se limpar se o limite estiver preso, sem ser “rollback de schema”)
- Tabelas `migrations`, `users`, `profiles`, `user_roles`, `companies`, …
- Checkout `agendaqui/database/migrations/` (imutáveis)

---

## 8. API / sessão

Repor `public_html/api/` para a versão anterior se um PHP novo quebrar o site.

Sessões PHP estão no `session.save_path` do plano (muitas vezes `~/tmp`). Apagar sessões **desloga toda a gente**; não reverte schema.

CSRF vive na sessão: depois de restaurar PHP, um reload emite token novo.

---

## 9. Ordem sugerida de incidente

1. Confirmar se o problema é SPA (HTML/JS) ou API (`/api/health.php`).
2. Se API 503: configuração/`getenv`/MySQL — não apagar tabelas.
3. Se API 500: repor `api/` anterior; ver error_log **sem** publicar stack.
4. Se migration `success=0`: secção 4.
5. Se SPA 404 em rotas: repor `.htaccess` do `dist`.
6. Só em último caso: repor `public_html` completo a partir do backup de ficheiros, **preservando** o MySQL.
