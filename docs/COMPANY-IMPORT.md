# Importação controlada de empresas

Pipeline CLI para incluir empresas de São José da Lapa (IBGE `3162955`) e Vespasiano (IBGE `3171204`) a partir de arquivos locais. Não há scraping, não há importação pela web e o importador não inventa cadastros.

## O que entra no banco

- `status = active`, `origin = imported`, `plan = free`, `is_verified = 0`, `owner_id = NULL`
- CNPJ válido (obrigatório na fonte Receita), município da cidade alvo, UF MG
- Categoria só se o CNAE/nome casar com uma das 27 categorias já existentes (farmácia → `saude`). Sem match, a empresa entra sem categoria.

## O que nunca é sobrescrito

Dono, plano, destaque, verificação, avaliações, WhatsApp, fotos e telefone já preenchidos. `--update` só preenche campos vazios.

## Preparar o arquivo

1. Coloque o JSON ou CSV em `storage/imports/` (pasta bloqueada no Apache).
2. Fonte Receita / dados abertos CNPJ: filtre antes por município IBGE. Campos aceitos incluem `cnpj`, `razao_social`, `nome_fantasia`, `cnae_fiscal`, `codigo_municipio`, `uf`, `logradouro`, `numero`, `bairro`, `cep`, `situacao_cadastral`.
3. Fonte municipal: JSON/CSV com nome e endereço; CNPJ opcional.

Caminhos com `..` ou fora de `storage/imports` são recusados.

## Comandos

```bash
php tools/migrate.php
php tools/import-companies.php --help
php tools/import-companies.php --source=receita --city=sjl --file=sjl.json --dry-run
php tools/import-companies.php --source=receita --city=sjl --file=sjl.json --limit=100
php tools/import-companies.php --source=municipal --city=vespasiano --file=vesp.json --update
```

`--enrich` consulta BrasilAPI só para completar campos vazios (máx. 50 por execução, com pausa). Não usa BrasilAPI como coleta.

`--dry-run` não grava nada.

## Testes

```bash
php tools/company-import-test.php
```

O arquivo `tools/fixtures/company-import-sample.json` é fictício e não deve ir para produção.

## Admin

`/admin/imports` mostra o histórico (somente leitura). Não dispara importação.
