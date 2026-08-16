# API PHP de usuários e perfil

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 2.7  
**Schema:** `002_auth.sql` (`users`, `profiles`, `user_roles`)

Pendências da Fase 2.6 **permanecem abertas**: rate limiting, token CSRF, confirmação de e-mail.

---

## 1. Arquitetura

```
GET  /api/auth/me.php          (já existia — leitura)
GET  /api/users/me.php         (mesmo helper, namespace users)
PATCH /api/users/me.php        (atualiza só o próprio perfil)
        ↓
require_auth() → SESSION uid → PDO
        ↓
profiles.id = users.id
```

Sem nova autenticação, sem migration, sem tabelas novas.

---

## 2. Endpoints

| Método | Caminho | Auth | Efeito |
| --- | --- | --- | --- |
| GET | `/api/auth/me.php` | sim | usuário público + perfil + roles |
| GET | `/api/users/me.php` | sim | igual ao anterior (`auth_public_user`) |
| PATCH | `/api/users/me.php` | sim | atualiza `profiles.name` e/ou `profiles.avatar_url` |

GET em `/api/users/me.php` reutiliza o helper; não há segunda fonte de verdade.

---

## 3. Autenticação

`require_auth()`. Identidade só da sessão (`uid`). `user_id` / `profile_id` no body são campos **desconhecidos** → 422; não alteram o alvo.

---

## 4. Autorização

Usuário A só lê/altera o próprio perfil. Não há GET/PATCH por id de terceiros.

---

## 5. Payloads

PATCH — somente:

```json
{ "name": "Ada", "avatar_url": "https://example.com/a.png" }
```

Pelo menos um dos dois. `avatar_url` pode ser `null` (limpa o campo).

**Campos desconhecidos são rejeitados** (422 `unexpected_fields`), inclusive `email`, `role`, `roles`, `user_id`, `password`. Assim um cliente que manda e-mail não acha que o e-mail mudou.

---

## 6. Respostas

Mesmo envelope da fundação. Sucesso: `{ success, data: { user } }` — `user` de `auth_public_user` (sem `password_hash`).

---

## 7. HTTP

| Código | Quando |
| --- | --- |
| 200 | GET/PATCH ok |
| 401 | sem sessão |
| 404 | perfil inexistente (usuário sem linha em `profiles`) |
| 405 | método fora de GET/PATCH/OPTIONS |
| 422 | JSON inválido, campo desconhecido, validação, body > 8 KB |
| 403 | não usado neste recurso (não há ação em recurso alheio) |

---

## 8. Validações

- body ≤ 8192 bytes
- `name`: string, trim, 1–255
- `avatar_url`: `null` ou URL `http`/`https` ≤ 2048
- `users.email` **não** é alterável aqui (depende de confirmação de e-mail, pendente)

---

## 9–11. Segurança / IDOR / roles

Prepared statements, `db_pdo(false)`. UPDATE `WHERE id = :id` com o uid da sessão. Role no payload → 422, banco de `user_roles` intacto.

---

## 12. CORS

Reutiliza `app.php`. Sem `*`.

---

## 13. CSRF

PATCH exige `X-CSRF-Token`. GET devolve `csrf_token`. Ver `docs/SECURITY-HARDENING.md`.

---

## 14. Rate limiting

Escrita autenticada: 60 pedidos / 15 min por `uid`. Ver `docs/SECURITY-HARDENING.md`.

---

## 15. HostGator

PHP 8 + PDO + sessão em arquivo. Sem Composer.

---

## 16. Testes

Ver relatório da Fase 2.7 (banco isolado).

---

## 17. Limitações

Sem troca de e-mail, senha, avatar upload (só URL), admin de usuários.

---

## 18. Pendências

1. Confirmação / troca de e-mail
2. Integração do frontend (`X-CSRF-Token`)
3. Rate limit cluster-wide se houver mais de um servidor
