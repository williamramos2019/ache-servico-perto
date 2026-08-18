# Transporte público

O catálogo sai do MySQL (`transport_lines`, `transport_schedules`, `transport_stops`, `transport_sources`) e da API `GET /api/transport/index.php`. A página `/transporte` não usa lista hardcoded e **não inventa linhas**. Sem import oficial, a UI permanece vazia.

Favoritos do visitante: `localStorage` chave `transporte_favoritos` (slug; ainda reconhece `code` antigo). Não usa a tabela `favorites` das empresas.

## Tabelas (migration 012)

Não criar 013: bairro de ponto, se vier no JSON/CSV, é concatenado em `transport_stops.address` (`Bairro: …`). Status extras (`suspended`, `temporary`) e dias (`vacation`, `atypical`) cabem no VARCHAR já existente.

## API pública

- `GET /api/transport/index.php?op=list&city=&q=&type=&status=&page=1&limit=24`
  - **Não** devolve horários/pontos completos. Devolve `schedule_count`, `stop_count`, `source`, `facets`.
  - `limit` máximo 50.
  - Busca no servidor: código, nome, operador, cidade, nome/endereço de ponto (EXISTS).
- `GET ...?op=show&slug=` ou `&id=` — ficha da linha, sem horários/pontos.
- `GET ...?op=schedules&line_id=`
- `GET ...?op=stops&line_id=`

Escrita (admin + CSRF): `line_create`, `line_update`, `line_delete`, `source_upsert`, `schedule_save`, `schedule_delete`, `stop_save`, `stop_delete`.

## Fontes (arquitetura; não declaradas oficiais até confirmação)

Prioridade quando o arquivo for fornecido:

1. Prefeitura de Vespasiano  
2. Prefeitura de São José da Lapa  
3. DER-MG (`http://www.consultas.der.mg.gov.br/grgx/sgtm/consulta_linha.xhtml`)  
4. Órgão metropolitano / consórcio  
5. Operador oficial  
6. Dados abertos oficiais  

Campos: `transport_sources.name`, `url`, `type` (`prefeitura|der|consorcio|operador|dados-abertos|other`), `collected_at` (data da **coleta/import**, não necessariamente a data do documento). Se a fonte não informar a data do quadro, a UI diz: “Data de atualização não informada pela fonte.”

Não há scraping de Google, Instagram, Facebook ou áreas com login.

## Importar (CLI)

Arquivo em `storage/imports/`. `--source-name` e `--source-url` http(s) obrigatórios.

```bash
php tools/transport-import.php --help
php tools/transport-import.php --file linhas.json --source-name "Prefeitura" --source-url "https://exemplo.gov.br" --dry-run
php tools/transport-import.php --file linhas.csv --source-name "Prefeitura" --source-url "https://exemplo.gov.br" --resume
php tools/transport-import.php --file linhas.json --source-name "DER-MG" --source-url "https://exemplo.gov.br" --source=der --dry-run
```

`--dry-run` não insere `transport_sources` nem linhas/horários/pontos.

Sem `--update`, linha já existente (mesmo `code` + `type` + `city_id`) é **duplicata** e não é alterada — preserva horários/pontos editados no admin.

Com `--update`, a linha é atualizada e horários/pontos **dessa linha** são regravados a partir do arquivo.

`--resume` ignora já cadastradas.

Via HTTP o CLI responde **403**.

### JSON

```json
{
  "source_type": "prefeitura",
  "lines": [
    {
      "code": "TZ-01",
      "name": "Centro / Bairro",
      "city_slug": "sao-jose-da-lapa",
      "type": "tarifa-zero",
      "operator_name": "Prefeitura",
      "fare": "Gratuito",
      "status": "active",
      "schedules": [{ "direction": "ida", "day_type": "weekday", "departure_time": "06:00" }],
      "stops": [{ "sequence": 1, "name": "Centro", "neighborhood": "Centro", "lat": null, "lng": null }]
    }
  ]
}
```

`tools/fixtures/transport-sample.json` é **fictício** e não deve ir para o banco real.

### CSV (linhas)

Cabeçalho: `code,name,city_slug,type,status,fare,operator_name,notes`

Horários e pontos em CSV de linhas-only não vêm; use JSON para itinerário completo.

## Admin

`/admin/transporte`: criar/editar linha, status, tipo, tarifa, operador, fonte, horários e pontos. Sem botão de importação web.

## Testes

```bash
php tools/transport-import-test.php
```

## Deploy

`docs/HOSTGATOR-TRANSPORT-DEPLOY.md`
