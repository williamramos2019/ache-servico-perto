# Importação controlada de empresas

Pipeline CLI para incluir empresas de São José da Lapa (IBGE `3162955`) e Vespasiano (IBGE `3171204`) a partir de **arquivos locais**. Não há scraping, não há download automático da base da Receita, não há importação pela web e o importador não inventa cadastros.

Esta fase **não executa carga real**. Só depois de você fornecer o arquivo oficial: dry-run → revisar → importar 100 SJL → validar → importar 100 Vespasiano.

## Migration 011

`database/migrations/011_company_import.sql` (já versionada; não criar 013 para estes campos):

- `companies`: `cnpj` CHAR(14) NULL UNIQUE, `legal_name`, `cnae_primary`, `neighborhood`, `origin` (default `manual` para linhas já existentes)
- `company_import_runs` (inclui `dry_run`, totais, `last_external_id`, `last_batch`)
- `company_sources`
- `company_import_errors`

InnoDB, utf8mb4, UUID `CHAR(36)`. Vários `cnpj` NULL são permitidos (cadastros manuais sem CNPJ).

## Origem (`origin`)

| Situação | Regra |
| --- | --- |
| Empresa **nova** criada pela importação | `origin = imported` |
| Empresa **já existente** | `origin` **não muda** (campo protegido) |

`origin` não representa dono da ficha. Não reclassificar um cadastro manual/reivindicado só porque o mesmo CNPJ apareceu num arquivo.

## Status de empresa nova importada

`status = active`, `plan = free`, `is_verified = 0`, `featured = 0`, `owner_id = NULL`.

## Campos importados vs protegidos

Mapeamento (quando a fonte traz o dado):

| Fonte | Coluna |
| --- | --- |
| CNPJ | `companies.cnpj` |
| Razão social | `legal_name` |
| Nome fantasia | `name` (se vazio, usa razão social) |
| CNAE principal | `cnae_primary` |
| Bairro | `neighborhood` |
| Município IBGE | `city_id` (só 3162955 ou 3171204) |
| Telefone público | `phone` (só se vazio no cadastro) |
| Endereço | `address` (só se vazio) |
| CEP | `zip` (só se vazio) |

**Nunca** preenchidos ou sobrescritos pela importação:

`owner_id`, `plan`, `featured`, `is_verified`, `rating`, `review_count`, `whatsapp`, `logo_url`, `banner_url`, `description`, `origin`, avaliações, fotos.

`--update` só preenche campos públicos **vazios** e somente em match **exato** de CNPJ ou `external_id`. Match por nome+cidade **não** atualiza.

## Deduplicação

1. CNPJ exato → mesma empresa (não cria duplicata).
2. Identificador externo estável da fonte (`company_sources.source_type` + `external_id`).
3. Sem CNPJ: nome fantasia normalizado + mesma cidade → **candidato**, log `duplicate_candidate`, **não altera** o existente e **não funde**.

Mesmo nome em cidades diferentes ≠ a mesma empresa. Razão social ≠ nome fantasia como chave de merge.

## Resume

`--resume` ignora CNPJ / `external_id` já gravados em `companies` / `company_sources`. Não depende só de offset.

`--resume=UUID` continua o `run_id` (não usar um run com `dry_run = 1`). A cada lote a execução grava `last_external_id` e `last_batch`.

Se o processo cair no registro 437, a próxima execução com `--resume` não duplica os registros já commitados.

## Dry-run

Somente leitura: não insere empresas, não atualiza `companies`, não altera categorias. Relatório em JSON no stdout. **Não** grava a execução como importação concluída (não cria linha em `company_import_runs`). Se no futuro um dry-run for persistido para auditoria, a coluna `dry_run` deve ser `1` e o status não pode significar carga real.

```bash
php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --dry-run
php tools/import-companies.php --source receita --city vespasiano --file receita/arquivo.json --dry-run
php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --dry-run --limit=100
```

## BrasilAPI (`--enrich`)

Só enriquecimento de **CNPJ já conhecido**. Nunca coleta/descoberta. Timeout, retry limitado, pausa entre pedidos, máximo 50 por execução. Se a API falhar, a importação principal continua.

## Arquivo da Receita (local)

Coloque o JSON ou CSV em `storage/imports/receita/`. O CLI **não baixa** a base.

Formato aceito: array JSON, `{ "empresas": [ ... ] }`, `{ "data": [ ... ] }` ou CSV com cabeçalho. Campos reconhecidos (nomes da Receita / dados abertos CNPJ):

- `cnpj` **ou** `cnpj_basico` + `cnpj_ordem` + `cnpj_dv`
- `razao_social`, `nome_fantasia`
- `cnae_fiscal`, `cnae_fiscal_descricao`
- `codigo_municipio` (IBGE 7 dígitos)
- `uf`, `logradouro`, `numero`, `complemento`, `bairro`, `cep`
- `situacao_cadastral` (`ATIVA` / `02`)
- `ddd_telefone_1` / `telefone`, `email` (opcionais)

Qualquer município que não seja `3162955` ou `3171204` é rejeitado. O nome da cidade no texto do endereço **não** vale como filtro.

Detalhes: `storage/imports/receita/README.txt`.

## Cadastro municipal (CSV / JSON)

Adapter genérico em `storage/imports/municipal/`. Origem identificada (`source_type = municipal`). CNPJ opcional; `external_id` estável é obrigatório na prática (se ausente, gera hash de nome+endereço — prefira enviar `external_id` da prefeitura).

Interface: `storage/imports/municipal/README.txt`. Não assumimos colunas de um arquivo que ainda não foi fornecido.

## Comandos

```bash
php tools/migrate.php
php tools/import-companies.php --help
php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --dry-run
php tools/import-companies.php --source receita --city sjl --file receita/arquivo.json --limit=100 --resume
php tools/import-companies.php --source municipal --city vespasiano --file municipal/vesp.json --dry-run
php tools/company-import-test.php
```

Via navegador o CLI responde **403**. Não usa `shell_exec` / `exec` / `system` / `passthru`.

## Testes e fixture

`tools/fixtures/company-import-sample.json` é fictício (nomes TESTE, CNPJ de exemplo). Serve só para o código. **Não inserir no banco real.**

## Admin

`/admin/imports` mostra histórico (data, fonte, cidade, dry-run, encontrados, inseridos, atualizados, ignorados, erros, status). Somente leitura. Sem botão “Executar importação”.

## Pacote para instalação posterior (não é deploy)

Não alterar a HostGator nesta fase. Quando for instalar, o pacote deve conter:

- `database/migrations/011_company_import.sql`
- `tools/import-companies.php`
- `tools/company-import-test.php`
- `tools/fixtures/company-import-sample.json` (só teste)
- `api/importer/` (adapters, normalizers, validators, CLI bootstrap)
- `docs/COMPANY-IMPORT.md`
- `storage/imports/receita/README.txt`
- `storage/imports/municipal/README.txt`

Sem `.env`, senhas, dumps da Receita ou logs.

Pasta privada sugerida: `/home/USUARIO/agendaqui/` (fora de `public_html`). Aplicar `php tools/migrate.php` até a 011 (e 012 se transporte). `api/importer/` também precisa estar na cópia HTTP em `public_html/api/` se o admin ler as runs.

## Como fornecer o arquivo real da Receita

1. Filtrar (fora deste repositório) estabelecimentos ativos com `codigo_municipio` 3162955 e/ou 3171204.
2. Exportar JSON ou CSV com os campos acima.
3. Enviar o arquivo por canal privado (não commitar a base completa).
4. Operador copia para `storage/imports/receita/` no servidor.
5. Dry-run → revisar JSON de relatório → `--limit=100` SJL → validar fichas → repetir Vespasiano.
