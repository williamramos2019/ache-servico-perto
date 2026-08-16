# API PHP de empresas / organização

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 2.8  
**Schema:** `database/migrations/003_companies.sql`

CSRF e rate limit: `docs/SECURITY-HARDENING.md`. Confirmação de e-mail **permanece** aberta (sem SMTP).

Não há reviews, leads, uploads, media, views, pagamentos, admin dashboard, OAuth, JWT nem SMTP nesta fase.

---

## 1. Arquitetura

```
GET    /api/companies/index.php
GET    /api/companies/show.php?id=
GET    /api/companies/mine.php
POST   /api/companies/create.php
PATCH  /api/companies/update.php?id=
        ↓
api/bootstrap/companies.php
        ↓
require_auth() / auth_user_id() → SESSION uid
        ↓
db_pdo(false) → cities / categories / companies / company_categories
```

Identidade só da sessão. `user_id`, `owner_id`, `company_id` e `role` no JSON **não** autorizam nada: se enviados, são campos desconhecidos (422).

Um usuário **pode** possuir várias empresas. `owner_id` não é UNIQUE. A autorização desta fase é `companies.owner_id = sessão`, não a role `company_owner`. A role **não** é atribuída ao criar empresa.

---

## 2. Tabelas (`003_companies.sql`)

Fundação documentada em `docs/MYSQL-SCHEMA-DESIGN.md` §§5–8. `cities` e `categories` entram nesta migration porque `companies.city_id` e `company_categories` têm FK explícitas.

| Tabela | Papel |
| --- | --- |
| `cities` | município do diretório; FK de `companies.city_id` |
| `categories` | taxonomia de empresas (não unificar com events/listings) |
| `companies` | organização / empresa do diretório |
| `company_categories` | N:N empresa ↔ categoria |

Não criadas aqui: `company_media`, `company_views`, reviews, leads, favorites, claims, uploads.

### 2.1 Relacionamentos

| De | Para | ON DELETE | ON UPDATE |
| --- | --- | --- | --- |
| `companies.owner_id` | `users.id` | SET NULL | RESTRICT |
| `companies.city_id` | `cities.id` | SET NULL | RESTRICT |
| `company_categories.company_id` | `companies.id` | CASCADE | RESTRICT |
| `company_categories.category_id` | `categories.id` | CASCADE | RESTRICT |

Apagar o usuário **não** apaga a empresa (o diretório permanece; `owner_id` fica NULL).  
Apagar a cidade zera `city_id`.  
Apagar empresa ou categoria remove só as linhas N:N.

### 2.2 Convenções

InnoDB, `utf8mb4_unicode_ci`, `CHAR(36)`, `DATETIME(3)` UTC, `TINYINT(1)`, JSON no lugar de JSONB/arrays. Sem ENUM, sem trigger, sem stored procedure, sem evento. Sem DEFAULT em colunas JSON (MySQL 5.7 não permite). `cities.featured_category_ids` é JSON NOT NULL — todo INSERT de cidade deve enviar `[]` ou a lista.

Índices: UNIQUE `slug` em cities/categories/companies; `owner_id`, `city_id`, `featured`, `status`, `plan`.

---

## 3. Endpoints

| Método | Caminho | Auth | Efeito |
| --- | --- | --- | --- |
| GET | `/api/companies/index.php` | não | lista `status=active` (máx. 100) |
| GET | `/api/companies/show.php?id=` | opcional | empresa ativa; inativa só para o dono |
| GET | `/api/companies/mine.php` | sim | empresas cujo `owner_id` é a sessão |
| POST | `/api/companies/create.php` | sim | cria empresa; `owner_id` = sessão |
| PATCH | `/api/companies/update.php?id=` | sim | altera só se `owner_id` = sessão |
| OPTIONS | todos | — | CORS / 204 |

Não há DELETE, admin de plano, nem CRUD de cities/categories nesta fase.

---

## 4. Autenticação

`auth_start_session()` + `require_auth()` nos endpoints de escrita e em `mine`. Cookie `agendaqui_sid` / `SESSION_NAME`, HttpOnly, SameSite=Lax.

GET público não exige sessão. GET de empresa inativa: anônimo e não-dono recebem **404** (não 403), para não revelar o recurso.

---

## 5. Autorização

Determinada no servidor:

- criar: qualquer usuário autenticado; `owner_id` gravado da sessão
- alterar: `owner_id` da linha === `uid` da sessão; o UPDATE também usa `WHERE owner_id = :owner_id`
- listar próprias: `WHERE owner_id = :owner_id` com o uid da sessão
- `plan`, `featured`, `is_verified`, `rating`, `review_count`, `views_count`, `reputation_score`, `plan_expires_at` **não** são graváveis por esta API (nem para admin — endpoint admin fica para fase posterior)

Role no payload → 422. Role no banco **não** é consultada para autorizar empresa nesta fase.

---

## 6. Validações

Body ≤ 65536 bytes. JSON objeto (não array). Campos fora da whitelist → 422 `unexpected_fields`.

**Obrigatórios no POST:** `name` (1–255), `slug` (`[a-z0-9]+(-[a-z0-9]+)*`, único).

**Opcionais (dono):** `tagline`, `description`, `phone`, `whatsapp`, `email`, `address`, `zip`, `city_id`, `lat`, `lng`, `website` e demais URLs `http`/`https`, `hours` e outros JSON documentados, `status` (`active` \| `inactive`), `founded_year`, `years_experience`, `response_time_minutes`, `response_rate`, `services_completed`, `clients_served`, `price_range`, `category_ids` (array de UUIDs existentes).

`city_id` inexistente → 422. `category_ids` inexistente → 422. Slug duplicado → 409 `slug_taken`.

PATCH exige `?id=` UUID e ao menos um campo permitido.

---

## 7. Códigos HTTP

| Código | Quando |
| --- | --- |
| 200 | GET/PATCH ok |
| 201 | POST ok |
| 204 | OPTIONS ok (origem permitida ou same-origin) |
| 401 | sem sessão em mine/create/update |
| 403 | PATCH em empresa de outro dono; OPTIONS de origem não permitida |
| 404 | empresa inexistente, ou inativa sem ser dono |
| 405 | método não permitido |
| 409 | slug em uso |
| 422 | JSON inválido, campo desconhecido, validação, id inválido |
| 500 | erro interno (mensagem genérica em production) |

---

## 8. Exemplos

### POST `/api/companies/create.php`

```json
{
  "name": "Oficina Central",
  "slug": "oficina-central",
  "city_id": "11111111-1111-4111-8111-111111111111",
  "category_ids": ["22222222-2222-4222-8222-222222222222"]
}
```

201:

```json
{
  "success": true,
  "data": {
    "company": {
      "id": "…",
      "owner_id": "…",
      "slug": "oficina-central",
      "name": "Oficina Central",
      "plan": "free",
      "featured": false,
      "status": "active",
      "category_ids": ["22222222-2222-4222-8222-222222222222"]
    }
  }
}
```

Campos omitidos no exemplo existem no objeto completo; senha, DSN e `password_hash` nunca aparecem.

### Erro — campo privilegiado

```json
{ "success": false, "error": { "code": "unexpected_fields", "message": "One or more fields are not allowed." } }
```

---

## 9. Segurança

- `db_pdo(false)` — sem multi-statements
- prepared statements
- identidade só da sessão
- whitelist de campos (bloqueia `plan` / `featured` / `owner_id` / `user_id` / `role`)
- CORS existente (`APP_ALLOWED_ORIGINS`; sem `*`)
- exceções internas não vazam DSN/senha
- empresa inativa não é listada no índice público

CSRF (`X-CSRF-Token`) e rate limit de escrita autenticada: ver `docs/SECURITY-HARDENING.md`. SameSite=Lax permanece.

---

## 10. Testes

Banco MariaDB/MySQL **isolado**. Runner: `001`/`002`/`003` aplicadas; segunda execução vazia; checksum mismatch em `003` restaurada.

Cobertura: anônimo, autenticado, IDOR A→B, `company_id`/`user_id` no body, SQL injection, payload inválido, campos desconhecidos, método errado, CORS, FK inválida, SET NULL / CASCADE.

---

## 11. Compatibilidade HostGator

PHP 8+, PDO, `pdo_mysql`, MySQL 5.7+ / MariaDB. Sem Composer, sem Node no servidor, sem framework, sem recursos exclusivos do MySQL 8 (sem `DEFAULT` em JSON, sem `CHECK`, sem `WINDOW`).

---

## 12. Limitações

- sem DELETE
- sem admin (`plan` / `featured` / verificação)
- sem CRUD de cities/categories
- sem busca, paginação real, geo ou FULLTEXT
- sem `company_owner` automático
- sem integração da SPA
- JSON de horários/promoções aceito como objeto/array; não há schema interno desses blobs

---

## 13. Pendências

1. Rate limiting (2.6)
2. Token CSRF se cookie cross-origin (2.6 / integração SPA)
3. Confirmação de e-mail (2.6)
4. Endpoints admin de plano/destaque
5. Media, reviews, leads (fases seguintes)
6. Integração do frontend
