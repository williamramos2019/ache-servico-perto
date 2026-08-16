# Setup do MySQL na HostGator

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 3.1

Este documento descreve como **criar** o banco na HostGator e como **aplicar** as migrations.  
Ele **não** executa nada em produção.

---

## Estado atual (obrigatório)

| Item | Estado |
| --- | --- |
| Banco de produção HostGator | **NÃO INSTALADO** (não confirmado neste repositório) |
| Migrations em produção | **NÃO EXECUTADAS** |
| Tabelas em produção | **NÃO CONFIRMADAS** |

Não assumir que `001` / `002` / `003` existem no MySQL da HostGator.

Não usar o MariaDB isolado das fases 2.x/3.0 como se fosse produção.

---

## Nomes das variáveis (código real)

O PHP **não** lê `DB_NAME` nem `DB_USER`. Os nomes corretos são:

| Variável | Obrigatória | Padrão |
| --- | --- | --- |
| `DB_HOST` | sim | — (na HostGator compartilhada costuma ser `localhost`) |
| `DB_PORT` | não | `3306` |
| `DB_DATABASE` | sim | — |
| `DB_USERNAME` | sim | — |
| `DB_PASSWORD` | não | vazio |

Fonte: `api/bootstrap/database.php` (`getenv` apenas). O `.env` do Vite **não** alimenta o PHP.

---

## 1. Criar o banco no cPanel

1. Entrar no cPanel da HostGator.
2. Abrir **MySQL Databases** (ou **MySQL Database Wizard**).
3. Criar um database **vazio**.
4. Anotar o nome completo (a HostGator prefixa o usuário da conta, ex. `conta_agendaqui`).
5. Collation desejada: `utf8mb4_unicode_ci`.

O runner **não** executa `CREATE DATABASE`.

---

## 2. Criar o usuário

1. No mesmo ecrã, criar um utilizador MySQL **novo**.
2. Gerar uma senha forte **fora do Git**.
3. Anotar o nome completo (também prefixado).

Não reutilizar a senha do `.env` do frontend / Supabase.

---

## 3. Associar o usuário ao banco

Em **Add User To Database**, associar o utilizador criado ao database criado.

---

## 4. Privilégios

Conceder ao utilizador da aplicação, **neste database apenas**:

- `SELECT`, `INSERT`, `UPDATE`, `DELETE`
- `CREATE`, `ALTER`, `INDEX`, `REFERENCES`
- `CREATE TEMPORARY TABLES` (não obrigatório hoje)
- `LOCK TABLES` (não obrigatório hoje)
- `EXECUTE` não é necessário (não há procedures)

Não conceder `DROP` global, `SUPER`, nem acesso a outros databases.

O runner bloqueia SQL destrutivo (`DROP TABLE`, `TRUNCATE`, etc.) no ficheiro de migration. Mesmo assim, não dar privilégios extra “por comodidade”.

---

## 5. Configurar credenciais no ambiente de produção

A API só lê `getenv()`. O Apache da HostGator **não** herda variáveis de um `.env` na pasta do projeto.

Procedimento seguro (ficheiros **fora** de `public_html`):

1. Criar `/home/USUARIO/agendaqui/load-env.php` com permissão `0600`.
2. Esse ficheiro chama `putenv(...)` / `$_ENV[...]` **sem** credenciais no Git.
3. No cPanel → **MultiPHP INI Editor**, para o domínio:

   `auto_prepend_file = /home/USUARIO/agendaqui/load-env.php`

4. O mesmo ficheiro (ou `export` no SSH) deve estar visível para:

   `php tools/migrate.php`

Exemplo **sem valores reais** (criar só no servidor):

```php
<?php
putenv('APP_ENV=production');
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=SUBSTITUA');
putenv('DB_USERNAME=SUBSTITUA');
putenv('DB_PASSWORD=SUBSTITUA');
putenv('RATE_LIMIT_DIR=/home/USUARIO/agendaqui/storage/rate-limit');
// APP_ALLOWED_ORIGINS vazio = same-origin (recomendado)
// SESSION_NAME opcional; padrão agendaqui_sid
```

Não colocar este ficheiro em `public_html`.  
Não usar `SetEnv` no `.htaccess` público com senha.  
Não colocar `DB_PASSWORD` no bundle da SPA.

Detalhes de pastas: `docs/HOSTGATOR-DEPLOY.md`.

---

## 6. Testar a conexão (depois do upload da API)

Ainda **nesta fase 3.1** isto não deve ser executado em produção.

Quando o operador autorizar o primeiro deploy:

```http
GET /api/health.php
```

Esperado com banco ok:

- HTTP `200`
- `Content-Type: application/json`
- `{ "success": true, "data": { "status": "ok", "database": "ok" } }`

Se as variáveis faltarem ou a senha estiver errada:

- HTTP `503`
- `error.code = database_unavailable`
- **sem** DSN, senha ou stack no corpo

Não interpretar `503` como “migrations já corridas”. Health só faz `SELECT 1`.

---

## 7. Executar o migration runner

Somente CLI. **Não existe** `/api/migrate.php`.

A partir do checkout **acima** de `public_html` (ver deploy):

```bash
cd /home/USUARIO/agendaqui
php tools/migrate.php --status
php tools/migrate.php --dry-run
php tools/migrate.php
php tools/migrate.php --status
```

Ordem fixa:

1. `001_create_migrations.sql`
2. `002_auth.sql`
3. `003_companies.sql`

Checksums SHA-256 (bytes do ficheiro; LF vs CRLF altera o hash):

| Ficheiro | SHA-256 |
| --- | --- |
| `001_create_migrations.sql` | `fefe0a3ac99dac2a3b563ec5bf4094dfa99f079b9ce120ada83bdde033aef0e0` |
| `002_auth.sql` | `1d44ee69e3016666a75e11bb286f297759415b0acf3115629a01a5790143c7d5` |
| `003_companies.sql` | `222948248e09fd1492d689d5e709b1fa1213f7a22bea7d9adc7f5d53a10e995d` |

Segunda execução com os mesmos ficheiros: **nenhuma pendente**.

Não há `--force`. Não editar migrations já aplicadas.

Procedimento de erro / ausência de rollback: `docs/HOSTGATOR-ROLLBACK.md` e `docs/MIGRATION-RUNNER.md`.

---

## 8. Conferir status

`php tools/migrate.php --status` deve listar as três como **Applied** e **Pending: (none)**.

Exit `1` se:

- checksum de uma aplicada mudou; ou
- existe linha `success = 0`.

---

## 9. Conferir tabelas

No phpMyAdmin (só leitura, depois do apply):

Esperado no mínimo:

- `migrations`
- `users`, `profiles`, `user_roles`
- `cities`, `categories`, `companies` (e demais da `003`)

Não importar dumps de teste.  
Não executar `DROP`.  
Não copiar o datadir do MariaDB local para a HostGator.
