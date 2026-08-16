# Hardening de autenticação e segurança

**Branch:** `migration-hostgator`  
**HEAD de referência:** `fb59826`  
**Fase:** 2.9

Não há JWT, Redis, Composer, daemon nem integração da SPA nesta fase. Confirmação de e-mail e recuperação de senha **não** foram implementadas (sem SMTP).

---

## 1. Sessão

Cookie `SESSION_NAME` ou `agendaqui_sid`:

| Atributo | Valor |
| --- | --- |
| HttpOnly | sim |
| SameSite | Lax |
| Secure | HTTPS ou porta 443 (não usa `X-Forwarded-Proto`) |
| lifetime | 0 (some com o browser) |
| path | `/` |
| `use_strict_mode` | 1 |
| só cookies | sim (sem trans-sid) |
| `gc_maxlifetime` | 28800 (8 h) |

A sessão guarda **somente**:

- `uid`
- `csrf`
- `last_seen` (unix time)

Não guarda senha, `password_hash`, roles, perfil nem tokens extras.

Idle 8 h (`AUTH_IDLE_SECONDS`): se `now - last_seen > 28800`, a sessão é esvaziada e o id regenerado. Justificativa: painel de empresas local; cookie de sessão + 8 h de inatividade evita o default PHP de ~24 min sem forçar relogin a cada edição. O GC do PHP em hospedagem compartilhada é **probabilístico** — o idle em `last_seen` é a garantia da aplicação.

Login: `session_regenerate_id(true)`, sessão zerada, novo `uid` + CSRF.

Logout: CSRF se houver estado; depois `$_SESSION = []`, cookie expirado, `session_destroy()`. Sem cookie prévio: 200 idempotente, sem CSRF.

---

## 2. CSRF

Token sincronizador na sessão (32 bytes, hex). **Não** é validado só por cookie.

| Passo | Detalhe |
| --- | --- |
| Obter | `GET /api/auth/csrf.php` (inicia sessão anônima) |
| Também em | `GET /api/auth/me.php`, `GET /api/users/me.php`, login 200, register 201 |
| Enviar | header `X-CSRF-Token` |
| Comparar | `hash_equals` com `$_SESSION['csrf']` |
| Erro | 403 `csrf_invalid` — mensagem única: `Request could not be validated.` |

GET nunca exige CSRF. POST/PATCH/DELETE com estado de sessão exigem. Login e register exigem (o cliente chama `/csrf` antes). Empresas **não** têm exceção.

Frontend futuro (ainda não integrado):

1. `GET /api/auth/csrf.php` com `credentials: 'include'`
2. guardar `data.csrf_token`
3. em todo POST/PATCH/DELETE, header `X-CSRF-Token` + cookies
4. após login, usar o `csrf_token` da resposta (o token anterior é invalidado no `regenerate_id`)

Cookie CSRF sozinho **nunca** autentica a escrita.

---

## 3. Rate limiting

Filesystem + `flock`, **fora** de `api/` (área pública).

Diretório padrão: `{repo}/storage/rate-limit/` (0700, arquivos 0600). Override: `RATE_LIMIT_DIR` (deve ficar **acima** de `public_html` na HostGator). `.htaccess` deny-all se o path for exposto por engano.

Arquivo: `sha256(bucket + subject).json` com `{ count, reset }`. Sem senha, sem session id, sem e-mail em claro no nome.

| Bucket | Limite | Janela | Justificativa |
| --- | --- | --- | --- |
| login por IP+e-mail | 5 | 15 min | faixa comum OWASP/NIST para senha; suficiente contra brute-force sem lockout longo em compartilhado |
| login por IP | 20 | 15 min | spray de muitos e-mails no mesmo IP |
| register por IP | 5 | 60 min | criações em massa; unicidade de e-mail já existe |
| escrita autenticada por `uid` | 60 | 15 min | painel (create/update empresa, PATCH perfil); acima do uso humano, abaixo de automação |

429 `rate_limited` + `Retry-After`. Mensagem genérica. Não revela se o e-mail existe.

**Limitações HostGator:** por máquina/disco; vários workers no mesmo host compartilham `flock`; dois servidores não compartilham contadores; `open_basedir` pode exigir `RATE_LIMIT_DIR` gravável; sem Redis o limite não é cluster-wide.

---

## 4. CORS

Igual à fundação: allowlist `APP_ALLOWED_ORIGINS`, sem `*`, origem exata, credentials só se a origem estiver na lista. Allow-Headers inclui `X-CSRF-Token`. OPTIONS 204 (permitida) ou 403 (proibida / não configurada).

---

## 5. Headers (só respostas `/api`)

- `Content-Type: application/json; charset=utf-8`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: no-referrer`
- `Cache-Control: no-store`
- `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'`

A CSP vale **apenas** para JSON da API. A SPA estática é outro documento (Apache/`index.html`); uma CSP da API **não** quebra scripts da SPA. CSP da SPA fica para a integração do frontend — não foi inventada aqui.

---

## 6. Autenticação

Login: e-mail normalizado; `password_verify`; hash dummy se o e-mail não existe; 401 idêntico para e-mail/senha. Rate limit **antes** da verificação. Campos extra (`role`, `user_id`, …) → 422.

Registro: whitelist `email`, `password`, `name`. `role` / `user_id` / `owner_id` / `company_id` / `admin` → 422. Role no banco continua `user`. Rate limit por IP. 409 se e-mail existe.

---

## 7. Autorização

Identidade = `$_SESSION['uid']`. Autorização = banco (`profiles.id`, `companies.owner_id`). IDs/roles no JSON não substituem o usuário. IDOR de perfil e de empresa inalterados (404/403).

---

## 8. SQL / logs

`db_pdo(false)`, prepared statements. Production: handler só registra `API exception`; corpo sem DSN, senha, hash, token, session id, stack.

---

## 9. Como o frontend obterá o CSRF

Ver §2. Sem alteração em `src/` nesta fase.

---

## 10. Recomendações futuras

1. Confirmação de e-mail / SMTP
2. Recuperação de senha
3. CSP no HTML da SPA
4. Rate limit compartilhado se houver mais de um servidor
5. Integração da SPA (enviar `X-CSRF-Token`)
6. Captcha só se o filesystem limit for insuficiente sob ataque
