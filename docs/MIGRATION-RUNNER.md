# Migration runner PHP + MySQL/MariaDB

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 2.3 / 2.3.2  
**Fontes:** `docs/MIGRATION-AUDIT.md`, `docs/MYSQL-SCHEMA-DESIGN.md`

Este documento descreve a infraestrutura de migrations. Ele **não** migra o schema de negócio do Supabase.

---

## 1. Objetivo

Permitir que o deploy futuro na HostGator:

1. Detecte migrations pendentes.
2. Execute-as em ordem numérica.
3. Registre o que foi aplicado.
4. Recuse arquivo já aplicado cujo conteúdo mudou (checksum SHA-256).
5. Impida execução concorrente (`GET_LOCK`).
6. Rode só com PHP 8 + PDO, sem Node em produção.

O runner **nunca** executa `DROP`/`TRUNCATE` automaticamente e **nunca** apaga dados por conta própria.

---

## 2. Arquitetura

```
deploy / operador (CLI)
   ↓
php tools/migrate.php
   ↓
api/bootstrap/database.php   (PDO, utf8mb4, UTC)
   ↓
database/migrations/*.sql
   ↓
MySQL/MariaDB
   ↓
tabela migrations
```

Arquitetura da aplicação (inalterada nesta fase):

```
Browser → React SPA → Apache → /api/*.php → PDO → MySQL
```

O runner **não** faz parte do HTTP. Não existe `/api/migrate.php`.

---

## 3. Estrutura de pastas

```
api/bootstrap/database.php
database/migrations/001_create_migrations.sql
tools/migrate.php
docs/MIGRATION-RUNNER.md
```

Ainda **não** existem: `database/schema/`, `database/seeds/`, nem `002_auth.sql` em diante.

---

## 4. Configuração

Somente variáveis de ambiente. Nenhum valor real é versionado.

| Variável | Obrigatória | Padrão | Uso |
| --- | --- | --- | --- |
| `DB_HOST` | sim | — | host MySQL |
| `DB_PORT` | não | `3306` | porta |
| `DB_DATABASE` | sim | — | banco já criado (vazio) |
| `DB_USERNAME` | sim | — | usuário |
| `DB_PASSWORD` | não | vazio | senha |
| `MIGRATIONS_DIR` | não | `database/migrations` | override para testes/isolamento |
| `MIGRATION_LOCK_TIMEOUT` | não | `30` | segundos do `GET_LOCK` |

O operador deve **criar o database vazio** no painel da HostGator (ou localmente) antes da primeira execução. O runner conecta em `DB_DATABASE`; ele não executa `CREATE DATABASE`.

Não use o `.env` do frontend (Vite/Supabase). Não copie credenciais para o repositório.

Na HostGator, defina as variáveis no ambiente do cron/SSH ou exporte-as no shell antes de `php tools/migrate.php`.

---

## 5. Como criar uma migration

1. Próximo número de 3 dígitos, sem buracos obrigatórios, sem duplicar versão.
2. Nome: `NNN_minusculo_underscore.sql`.
3. Preferir `CREATE TABLE IF NOT EXISTS` / `CREATE INDEX` idempotente quando seguro.
4. Não editar arquivo já aplicado.
5. Não colocar `DROP DATABASE`, `DROP TABLE`, `DROP COLUMN` ou `TRUNCATE`.
6. Uma migration = uma unidade de mudança. Se falhar, a seguinte não roda.

Exemplo futuro (ainda não criado):

```
002_auth.sql
003_companies.sql
```

---

## 6. Como executar

```bash
php tools/migrate.php
```

Passos internos:

1. Recusar execução se `PHP_SAPI !== cli`.
2. Validar nomes/versões dos `.sql`.
3. Conectar via PDO.
4. Adquirir `GET_LOCK('migration_runner_lock', timeout)`.
5. Ler a tabela `migrations` (se existir).
6. Validar checksums das aplicadas (`success = 1`).
7. Recusar se existir linha `success = 0`.
8. Executar pendentes em ordem, uma a uma, parando na primeira falha.
9. Registrar sucesso.
10. Liberar o lock (também em erro controlável).

Pré-requisito: database vazio já criado.

---

## 7. `--status`

```bash
php tools/migrate.php --status
```

Mostra arquivos encontrados, aplicadas, pendentes e checksums.  
Não aplica SQL. Não adquire lock.  
Exit `1` se houver checksum mismatch ou migration com `success = 0`.

---

## 8. `--dry-run`

```bash
php tools/migrate.php --dry-run
```

Igual ao status quanto à listagem, mas deixa explícito que **nenhuma alteração** seria gravada.  
Não executa SQL de migration. Não adquire lock.

---

## 9. Checksum

- Algoritmo: SHA-256 do arquivo em bytes (`hash_file`).
- Comparação: `hash_equals`.
- Arquivo pendente: executa.
- Arquivo aplicado e checksum igual: ignora.
- Arquivo aplicado e checksum diferente: **falha**.

Mensagem:

```
MIGRATION CHECKSUM MISMATCH
Migration 001_x already applied, but its checksum changed.
```

Não há correção automática. Não há `--force`.

**Atenção:** mudar só o fim de linha (CRLF ↔ LF) altera o checksum. Trate migrations como imutáveis inclusive em line endings.

---

## 10. Lock

- Função: `GET_LOCK('migration_runner_lock', N)`.
- Timeout padrão: 30 segundos (`MIGRATION_LOCK_TIMEOUT`).
- Se não adquirir: aborta. Nenhuma migration roda.
- `RELEASE_LOCK` no `finally` de `main()`, após sucesso **ou** erro controlável.
- O fluxo de apply **não** chama `exit()` com lock aberto. Erros retornam código `1`; o único `exit()` posterior é `exit(main())` depois do `finally`.
- Há `exit(1)` só se o script for invocado fora de CLI (`PHP_SAPI !== cli`), antes de qualquer conexão/lock.

`--status` e `--dry-run` não usam lock.

### Divergência de nome (documentada, não silenciosa)

| Fonte | Nome do lock |
| --- | --- |
| Fase 2.2 (`docs/MYSQL-SCHEMA-DESIGN.md`) | `schema_migrate` |
| Fase 2.3 (esta implementação) | `migration_runner_lock` |

**Decisão:** usar `migration_runner_lock`, como autorizado na Fase 2.3. O mecanismo continua `GET_LOCK`.

---

## 11. Falhas

Se uma migration falhar:

1. `ROLLBACK` **se** ainda houver transação aberta.
2. Registrar `success = 0` **se** a tabela `migrations` já existir **e** o SQL tiver sido enviado ao servidor (erro de execução).
3. Mostrar o arquivo e a mensagem de erro (sem senha).
4. Parar. Não executar a próxima.
5. Liberar o lock explicitamente (`[INFO] Releasing migration lock` / `[OK] Lock released`).
6. Retornar exit code `1`.

SQL bloqueado pelo detector destrutivo **não** é registrado como migration aplicada (`success` não é gravado). A execução é interrompida **antes** de `BEGIN`/`exec`. O arquivo continua pendente até ser removido ou substituído. Isso é intencional: nada foi aplicado; gravar `success = 0` misturaria “bloqueado sem executar” com “SQL executado e falhou”.

### Estratégia de `success = 0`

Uma linha com `success = 0` **não** conta como aplicada.

Na próxima execução o runner **não** reexecuta automaticamente e **não** ignora o arquivo. Ele aborta com:

```
Migration NNN_x previously failed (success=0).
It is not applied. Inspect the database, then DELETE the failed row before retrying.
```

Motivo: DDL no MySQL/MariaDB frequentemente faz commit implícito. Reexecutar um arquivo pela metade é perigoso. A recuperação é **manual**.

Se `001_create_migrations.sql` falhar antes de criar a tabela, pode não haver linha para gravar. Nesse caso o arquivo continua pendente — o operador inspeciona o banco antes de tentar de novo.

---

## 12. Migrations imutáveis

Depois que `001_x.sql` aplica com `success = 1`, qualquer edição do arquivo gera checksum mismatch.

Não recriar. Não apagar o registro. Não reexecutar.  
Correção: nova migration (`002_...`).

---

## 13. Segurança

O runner **não**:

- imprime `DB_PASSWORD`;
- imprime DSN com senha;
- despeja `getenv()`;
- aceita SQL via HTTP ou argumento;
- oferece `--force` para bypass de checksum.

`tools/migrate.php` recusa qualquer SAPI que não seja `cli`.  
Não publique `tools/` dentro de `public_html`. O `dist/` da SPA não inclui esta pasta.

`api/bootstrap/database.php` **não** habilita multi-statements por padrão. Só o runner pede `db_pdo(true)`, porque um arquivo `.sql` pode ter mais de um statement. A API futura deve continuar com o padrão (`false`).

---

## 14. HostGator

Compatível com hospedagem compartilhada:

- PHP 8.x CLI (SSH ou cron pontual de deploy)
- PDO + pdo_mysql
- MySQL 5.7+ ou MariaDB 10.2+ (InnoDB, utf8mb4, `DATETIME(3)`, `GET_LOCK`)
- Sem Composer, Laravel, Redis, daemon ou Node

Fluxo de deploy futuro: enviar arquivos + `php tools/migrate.php`.

Coloque o repositório **acima** de `public_html` quando possível, e publique só o conteúdo de `dist/` + `api/`.

---

## 15. Limitações

1. **DDL e transações:** o runner faz `BEGIN` → SQL → `COMMIT`. Em erro, `ROLLBACK` **somente se** a transação ainda estiver aberta. `CREATE TABLE` / `ALTER` no MySQL/MariaDB costumam commitar implicitamente. **ROLLBACK não é garantia de reversão física de DDL.** Não há rollback mágico.
2. **Detector destrutivo (camada extra, não parser):** regex após remover comentários `--`, `#` e `/* */`. Bloqueia no mínimo `DROP DATABASE`, `DROP SCHEMA`, `DROP TABLE`, `DROP COLUMN`, `TRUNCATE` e `ALTER TABLE ... DROP`. **Não é um parser SQL completo e não oferece garantia absoluta.** Falsos positivos ocorrem (ex.: `SELECT 'DROP TABLE x'`). SQL ofuscado ou construções não previstas podem passar. O desenvolvedor continua responsável por revisar cada migration. Não bloqueia `DROP INDEX` / `DROP TRIGGER` (fora da lista da Fase 2.3).
3. **SQL destrutivo bloqueado:** interrompe antes da aplicação. **Não** grava linha em `migrations`. **Não** é considerado aplicado.
4. **Checksum é do arquivo bruto**, não de um SQL normalizado.
5. **Database precisa existir** antes da primeira conexão.
6. **`DATETIME(3)`** exige MySQL ≥ 5.6.4 / MariaDB equivalente. Alvo: 5.7+ / MariaDB 10.2+.
7. **`GET_LOCK`** é por conexão. Dois processos no mesmo servidor são bloqueados; dois servidores diferentes com o mesmo banco também, se usarem o mesmo nome de lock.
8. O runner **não altera** o SQL das migrations.

---

## 16. Rollback manual

Não há rollback automático nem arquivos `down`.

Procedimento:

1. Ler o erro e o nome da migration.
2. Inspecionar o banco (`SHOW TABLES`, `SHOW CREATE TABLE`, dados).
3. Reverter à mão apenas o que for seguro (sem apagar dados de produção sem backup).
4. Se existir linha `success = 0`, `DELETE FROM migrations WHERE version = '00N' AND success = 0`.
5. Se o detector bloqueou SQL destrutivo, **não** haverá linha: basta remover ou reescrever o arquivo.
6. Corrigir com uma **nova** migration, se o arquivo original já tiver sido aplicado com sucesso em outro ambiente.
7. Rodar `php tools/migrate.php --status` e depois o apply.

Nunca: `DROP DATABASE` pelo runner; apagar a tabela `migrations` para “recomeçar” em produção.

---

## 17. Como adicionar uma nova migration

1. `php tools/migrate.php --status`
2. Criar `database/migrations/00N_descricao.sql`
3. `php tools/migrate.php --dry-run`
4. `php tools/migrate.php`
5. Conferir `--status` de novo

Nesta fase só existe `001_create_migrations.sql`.

---

## 18. O que NÃO fazer

- Não editar migration já aplicada.
- Não criar `/api/migrate.php`.
- Não commitar senhas.
- Não rodar o runner via HTTP.
- Não usar `--force` (não existe).
- Não criar schema de users/companies/reviews nesta fase.
- Não apontar o runner para o PostgreSQL/Supabase.
- Não executar `DROP`/`TRUNCATE` “só para limpar”.
- Não assumir que `ROLLBACK` desfaz `CREATE TABLE`.

---

## 19. Tabela `migrations`

| Coluna | Tipo | Notas |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED AI` | PK |
| `version` | `VARCHAR(32)` | UNIQUE (`001`, `002`, …) |
| `name` | `VARCHAR(255)` | nome do arquivo |
| `checksum` | `CHAR(64)` | SHA-256 hex |
| `executed_at` | `DATETIME(3)` | UTC |
| `execution_time_ms` | `INT UNSIGNED` | duração |
| `success` | `TINYINT(1)` | `1` aplicada; `0` falhou, não aplicada |

`created_at` **não** foi adicionado: `executed_at` já registra o momento.

### Divergências de modelo (refinamentos, não conflito de negócio)

| Fonte | Modelo | Decisão da 2.3 |
| --- | --- | --- |
| Fase 2.1 | `id, filename, applied_at` e `cron/migrate.php` | Superado: `tools/migrate.php` + colunas da 2.2/2.3 |
| Fase 2.2 | sem `execution_time_ms` / `success` | Incluídos, como a 2.3 autorizou |
| Fase 2.2 | arquivo `001_migrations.sql` | Nome real: `001_create_migrations.sql` |

Nenhuma tabela de negócio foi inventada ou criada.

---

## 20. PHP / MySQL mínimos

| Peça | Mínimo razoável |
| --- | --- |
| PHP | 8.0+ (desenvolvido/verificado a sintaxe em 8.3) |
| Extensões | `pdo`, `pdo_mysql` |
| MySQL | 5.7+ |
| MariaDB | 10.2+ |
| Engine | InnoDB |
| Charset | utf8mb4 / utf8mb4_unicode_ci |

Não usa PostGIS, `JSON_TABLE`, CTE, window functions, Composer ou framework.

---

## 21. Correções da Fase 2.3.2

| Ressalva 2.3.1 | Decisão |
| --- | --- |
| `exit()` no `catch` podia ocultar o `RELEASE_LOCK` do `finally` | `fail()` removido. Erros controláveis usam `return 1`; `finally` libera o lock; `exit(main())` só no fim |
| Detector regex / falso positivo | Mantido conservador; limitações explícitas na seção 15 |
| Bloqueio destrutivo sem `success = 0` | **Mantido de propósito:** nada foi executado; registrar falha confundiria com SQL aplicado pela metade |
