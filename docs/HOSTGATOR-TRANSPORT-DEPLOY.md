# Deploy HostGator — módulo de transporte

O Cursor **não** faz upload, FTP, SSH nem migration em produção. Este documento é o roteiro manual.

Produção: Apache + SPA estática (`dist/`) + PHP em `api/*.php` + MySQL. **Sem Node** no servidor.

## 1. Backup recomendado

Antes de enviar arquivos ou aplicar a 012:

- backup do `public_html` (pelo menos `api/` e `index.html` + `assets/`);
- backup do MySQL (phpMyAdmin / cPanel) se a 012 ainda não foi aplicada;
- não executar `DROP` / `TRUNCATE`.

## 2. O que construir localmente

```bash
npx tsc --noEmit
npm run build
```

Enviar o conteúdo de `dist/` (não o repositório Node).

## 3. Arquivos a enviar

### `public_html/` (web)

| Origem | Destino |
| --- | --- |
| Conteúdo de `dist/` | `public_html/` |
| `api/` completo do repo (inclui `api/transport/` e `api/importer/transport.php`) | `public_html/api/` |

### Fora de `public_html` (`/home/USUARIO/agendaqui/`)

| Origem | Destino |
| --- | --- |
| `api/` (mesma versão) | `agendaqui/api/` |
| `database/migrations/012_transport.sql` | `agendaqui/database/migrations/` |
| `tools/migrate.php` | `agendaqui/tools/` |
| `tools/transport-import.php` | `agendaqui/tools/` |
| `storage/imports/` (vazia, Deny from all) | `agendaqui/storage/imports/` |

Não enviar: `.env`, `load-env.php`, dumps da Receita, `node_modules`, fixtures para o banco real, senhas.

A 012 **não altera** empresas, usuários, favoritos de empresas nem avaliações. Só cria tabelas `transport_*` se ainda não existirem.

## 4. Migration

No checkout privado:

```bash
cd /home/USUARIO/agendaqui
php tools/migrate.php --status
php tools/migrate.php --dry-run
```

Só aplicar depois de confirmar que `012_transport.sql` está pendente e o dry-run lista só CREATE/ALTER permitidos:

```bash
php tools/migrate.php
php tools/migrate.php --status
```

Não usar `--force`.

Se a 012 já estiver applied: não reaplicar. Não editar 001–012 no servidor.

## 5. Testes HTTP (depois do upload)

Com a SPA e a API no ar:

1. `GET /transporte` — página abre; se não houver linhas, estado vazio (esperado).
2. `GET /api/transport/index.php?op=list` — JSON `success`, `lines` array, **sem** listas enormes de `schedules` em cada card.
3. `GET /api/transport/index.php?op=list&q=%27%20OR%201%3D1%20--` — 200 com lista filtrada ou vazia; **não** 500.
4. `GET /api/transport/index.php?op=show&slug=nao-existe` — `line: null`.
5. `GET /api/transport/index.php?op=schedules&line_id=x` — 422 se id inválido.
6. `POST /api/transport/index.php` sem CSRF/admin — 401/403.
7. Abrir `https://SEU-DOMINIO/tools/transport-import.php` não deve importar; o arquivo **não** deve estar em `public_html`.

## 6. Rollback seguro

- Restaurar `public_html/api/transport/` e o `dist/` anterior.
- **Não** dropar `transport_*` se já houver dados oficiais importados.
- A 012 usa `CREATE TABLE IF NOT EXISTS`; rollback de schema não é necessário se a aplicação da migration foi o único passo e as tabelas estão vazias — ainda assim, não DROP sem pedido explícito.

## 7. Dados reais

Não importar fixture. Só após arquivo oficial:

```bash
php tools/transport-import.php --file SEU.json --source-name "..." --source-url "https://..." --dry-run
```

Revisar o JSON de relatório, depois importar sem `--dry-run`.
