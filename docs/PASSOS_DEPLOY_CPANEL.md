# Passos de subida — AgendaAqui na HostGator (cPanel)

Pacote: `AgendaAqui-hostgator-v1.1.0.zip` (confira `VERSION.txt`).  
Runtime no servidor: **Apache + PHP 8.1 ou 8.2 + MySQL/MariaDB**. Sem Node, sem Composer, sem Git.

O Cursor **não** faz FTP. Este arquivo é o roteiro no cPanel.

---

## 1. Antes de enviar

1. Backup de `public_html` (File Manager → Compactar).
2. Dump MySQL (phpMyAdmin ou Backup do cPanel).
3. MultiPHP do domínio: **8.1 ou 8.2**. PHP 8.0 quebra a API.
4. Crie o banco vazio e o usuário MySQL (collation `utf8mb4_unicode_ci`). Anote `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` (a HostGator prefixa com o usuário da conta).
5. Gere dois segredos longos (cron e push). Não reutilize a senha do cPanel.

---

## 2. Extração do ZIP

No computador, extraia `AgendaAqui-hostgator-v1.1.0.zip`. Você verá:

```
AgendaAqui-hostgator-v1.1.0/
  public_html/          ← site (SPA + /api)
  agendaqui_secure/     ← privado (migrations, tools, exemplo de env)
  INSTALL-HOSTGATOR.md
  ...
```

### `public_html/` → document root

Envie **o conteúdo** de `public_html/` (não a pasta-mãe do ZIP) para o document root do domínio:

- domínio principal: `/home/USUARIO/public_html/`
- addon/subdomínio: a pasta que o cPanel marca como *document root*

Deve ficar assim no servidor:

```
/home/USUARIO/public_html/index.html
/home/USUARIO/public_html/.htaccess
/home/USUARIO/public_html/api/
/home/USUARIO/public_html/sitemap.php
/home/USUARIO/public_html/uploads/.htaccess
```

Não envie `src/`, `node_modules/`, `.env` nem `load-env.php` com senha.

### `agendaqui_secure/` → fora da web

Crie `/home/USUARIO/agendaqui_secure/` **um nível acima** de `public_html` (não dentro do site).

Envie **o conteúdo** de `agendaqui_secure/` do ZIP para essa pasta:

```
/home/USUARIO/agendaqui_secure/api/
/home/USUARIO/agendaqui_secure/database/migrations/   (001–019)
/home/USUARIO/agendaqui_secure/tools/
/home/USUARIO/agendaqui_secure/storage/
/home/USUARIO/agendaqui_secure/load-env.example.php
```

Há duas cópias de `api/` (HTTP em `public_html` e CLI em `agendaqui_secure`). Devem ser a **mesma versão**.

Instalações antigas em `/home/USUARIO/agendaqui/` continuam sendo lidas. O alvo novo é `agendaqui_secure`.

---

## 3. `load-env.php` (obrigatório, fora da web)

No File Manager, copie `load-env.example.php` para:

`/home/USUARIO/agendaqui_secure/load-env.php`

Permissão sugerida: **0600**.  
Nunca coloque este arquivo em `public_html`. Nunca commite com senha.

Substitua `USUARIO`, banco, senhas e o domínio. O PHP lê `DB_DATABASE` e `DB_USERNAME` (não `DB_NAME` / `DB_USER`).

```php
<?php

declare(strict_types=1);

if (!defined('AGENDAQUI_ENV_OK')) {
    http_response_code(403);
    exit;
}

if (!function_exists('agendaqui_putenv')) {
    function agendaqui_putenv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

agendaqui_putenv('APP_ENV', 'production');
agendaqui_putenv('APP_PUBLIC_URL', 'https://SEU-DOMINIO.com.br');
agendaqui_putenv('SESSION_NAME', 'agendaqui_sid');
agendaqui_putenv('APP_ALLOWED_ORIGINS', '');
agendaqui_putenv('AUTH_IDLE_SECONDS', '28800');

agendaqui_putenv('DB_HOST', 'localhost');
agendaqui_putenv('DB_PORT', '3306');
agendaqui_putenv('DB_DATABASE', 'USUARIO_nome_do_banco');
agendaqui_putenv('DB_USERNAME', 'USUARIO_usuario_mysql');
agendaqui_putenv('DB_PASSWORD', 'SUA_SENHA_MYSQL');

agendaqui_putenv('RATE_LIMIT_DIR', '/home/USUARIO/agendaqui_secure/storage/rate-limit');
agendaqui_putenv('UPLOAD_MAX_BYTES', '5242880');

agendaqui_putenv('MAIL_HOST', 'smtp.exemplo.com');
agendaqui_putenv('MAIL_PORT', '465');
agendaqui_putenv('MAIL_ENCRYPTION', 'ssl');
agendaqui_putenv('MAIL_USERNAME', 'SEU_EMAIL@dominio.com');
agendaqui_putenv('MAIL_PASSWORD', 'SUA_SENHA_DE_EMAIL');
agendaqui_putenv('MAIL_FROM', 'SEU_EMAIL@dominio.com');
agendaqui_putenv('MAIL_FROM_NAME', 'AgendaAqui');

agendaqui_putenv('PUSH_TRACK_SECRET', 'GERE_UM_SEGREDO_LONGO');
agendaqui_putenv('CRON_SHARED_SECRET', 'GERE_OUTRO_SEGREDO_LONGO');
```

Crie as pastas `storage/rate-limit`, `storage/imports` e `storage/backups` se o File Manager não as tiver enviado (graváveis pelo PHP, sem `777` se 755/775 bastar).  
`public_html/uploads` também precisa ser gravável.

---

## 4. Migrations no Terminal do cPanel

cPanel → **Terminal** (ou SSH). Confirme o PHP:

```bash
php -v
```

Tem de ser **8.1+**. Se `php` for 8.0, use o binário do MultiPHP, por exemplo:

```bash
/opt/cpanel/ea-php81/root/usr/bin/php -v
/opt/cpanel/ea-php82/root/usr/bin/php -v
```

Aplique as migrations **001–019** (não edite 001–012):

```bash
cd /home/USUARIO/agendaqui_secure
php tools/migrate.php --status
php tools/migrate.php --dry-run
php tools/migrate.php
php tools/migrate.php --status
```

Esperado: 001–019 **applied**, pending vazio.

Sem Terminal: cPanel → Cron Jobs, **uma** execução do comando abaixo, depois **apague** esse cron.

```bash
/usr/local/bin/php /home/USUARIO/agendaqui_secure/tools/migrate.php
```

Atalho HTTP (só primeira instalação): abra `/atualizar-banco.php`, preencha o MySQL, confirme o sucesso e **apague** `atualizar-banco.php` e `instalar-banco.php` do `public_html`.

---

## 5. Cron Jobs no cPanel

cPanel → **Cron Jobs**. Use o PHP 8.1/8.2 da conta (troque o caminho se `which php` no Terminal mostrar outro).

Substitua `USUARIO` pelo usuário da hospedagem.

### Recorrente (obrigatório para vagas / WhatsApp / limpeza)

A cada 15 minutos:

```
*/15 * * * * /usr/local/bin/php /home/USUARIO/agendaqui_secure/tools/scheduled-hooks.php --task=all >/dev/null 2>&1
```

Equivalente com EasyApache PHP 8.2:

```
*/15 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/USUARIO/agendaqui_secure/tools/scheduled-hooks.php --task=all >/dev/null 2>&1
```

### Alternativa HTTP (se o CLI do cron não achar o `load-env.php`)

O endpoint só aceita **POST** com o header `X-Cron-Secret` igual a `CRON_SHARED_SECRET`. Sem o segredo responde 403.

```
*/15 * * * * curl -sS -X POST -H "Content-Type: application/json" -H "X-Cron-Secret: COLE_AQUI_O_CRON_SHARED_SECRET" -d "{\"op\":\"scheduled-hooks\"}" https://SEU-DOMINIO.com.br/api/cron/index.php >/dev/null 2>&1
```

Prefira o CLI. Não coloque o segredo no `public_html`.

### Opcional — import Shopee (se houver `SHOPEE_FEED_*` no `load-env.php`)

Uma vez por dia, 06:10:

```
10 6 * * * /usr/local/bin/php /home/USUARIO/agendaqui_secure/tools/shopee-import.php >/dev/null 2>&1
```

Não cadastre importadores de empresas nem fixtures de transporte no cron.

---

## 6. Smoke test

No Terminal do cPanel, **depois** das migrations e do HTTPS:

```bash
cd /home/USUARIO/agendaqui_secure
php tools/smoke-test.php https://SEU-DOMINIO.com.br
```

Na máquina local, apontando para o site já no ar:

```bash
php tools/smoke-test.php https://SEU-DOMINIO.com.br
```

Esperado: `PASS` com health 200 + banco `ok`, CSRF, sitemap XML, cidades, `/uploads/` sem PHP, `/load-env.php` 403/404, `/buscar` e `/vespasiano` servindo o `index.html` da SPA.

---

## 7. Depois que passar

1. Apague `atualizar-banco.php` e `instalar-banco.php` se ainda existirem no document root.
2. Confirme SSL (cadeado) e PWA: `/manifest.webmanifest`, `/sw.js`.
3. Login em `/auth`.
4. Checklist: `DEPLOY-CHECKLIST.md`.

Se falhar: restaure o backup de arquivos + dump. Não use `DROP TABLE`. Ver `ROLLBACK-HOSTGATOR.md`.
