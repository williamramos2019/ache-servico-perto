# Transporte público

O catálogo de linhas sai do MySQL (`transport_lines`, `transport_schedules`, `transport_stops`) e da API `GET /api/transport/index.php`. A página `/transporte` não usa mais lista hardcoded.

## Regras

- Não há scraping de HTML nem CAPTCHA.
- Toda carga em lote exige `--source-name` e `--source-url`.
- Sem arquivo sourced, a UI fica vazia — isso é esperado.
- Favoritos do visitante continuam em `localStorage` (`transporte_favoritos`).

## Importar

Coloque um JSON em `storage/imports/` e rode:

```bash
php tools/migrate.php
php tools/transport-import.php --help
php tools/transport-import.php --file=linhas.json --source-name="Prefeitura de São José da Lapa" --source-url="https://www.saojosedalapa.mg.gov.br/" --dry-run
php tools/transport-import.php --file=linhas.json --source-name="Prefeitura de São José da Lapa" --source-url="https://www.saojosedalapa.mg.gov.br/" --source-type=prefeitura
```

Formato mínimo:

```json
{
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
      "stops": [{ "sequence": 1, "name": "Centro" }]
    }
  ]
}
```

`tools/fixtures/transport-sample.json` é linha fictícia de teste, não dado oficial.

## API pública

- `GET /api/transport/index.php?op=list`
- `GET /api/transport/index.php?op=show&slug=...`
- `GET /api/transport/index.php?op=schedules&line_id=...`
- `GET /api/transport/index.php?op=stops&line_id=...`

Escrita (admin + CSRF): `line_create`, `line_update`, `line_delete` via POST.

## Testes

```bash
php tools/transport-import-test.php
```
