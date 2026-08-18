# Instalação HostGator — AgendaAqui

Pacote: `AgendaAqui-hostgator-v1.1.0.zip` (confira `VERSION.txt`)  
Runtime no servidor: Apache + **PHP 8.1 ou 8.2** + MySQL/MariaDB **10.2+**. **Sem Node, sem Composer, sem Git.**

PHP 8.0 **quebra** a API (`array_is_list` e spread associativo).

Este guia é a **primeira instalação**. Atualizações: `UPDATE-HOSTGATOR.md`.  
O Cursor **não** faz upload.

Há dois caminhos:

1. **CLI (preferível):** `php tools/migrate.php` em `/home/USUARIO/agendaqui_secure`
2. **HTTP:** `/atualizar-banco.php` — formulário público; **apague o arquivo no sucesso**

Não misture: o instalador HTTP não substitui `load-env.php` privado se você já o criou à mão.

---

## Requisitos

- Hospedagem compartilhada HostGator com PHP **8.1+** e MariaDB **10.2+** (coluna gerada em `procurements`)
- HTTPS no domínio
- Extensões PHP: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `session`
- **Não** é necessário SSH, mas o Terminal do cPanel (ou cron) facilita as migrations

---

## 1. Banco

1. No cPanel, abra **MySQL Databases** (ou o assistente de banco).
2. Crie um database vazio, collation `utf8mb4_unicode_ci`.
3. Crie um usuário MySQL e associe-o a esse database.
4. Privilégios nesse database: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `REFERENCES`.
5. Anote os nomes completos (a HostGator costuma prefixar com o usuário da conta).

Não execute SQL destrutivo. O runner **não** cria o database.

---

## 2. PHP

No cPanel (MultiPHP INI / seletor de versão):

- PHP **8.1 ou superior**
- `session`, `pdo_mysql` ativos
- `display_errors = Off`

O bootstrap da API procura `/home/USUARIO/agendaqui_secure/load-env.php` (e, se não houver, `/home/USUARIO/agendaqui/load-env.php` em instalações antigas).

---

## 3. Upload

Descompacte o ZIP localmente. Envie:

| Pasta do ZIP | Destino no servidor |
| --- | --- |
| `public_html/` (conteúdo) | `public_html/` do domínio (web root) |
| `agendaqui_secure/` (conteúdo) | `/home/USUARIO/agendaqui_secure/` **fora** da web |

Não envie: `.env`, senhas, `node_modules`, fixtures, dumps da Receita, `instalar.php`.

---

## 4. Configuração (`load-env.php`)

Copie `load-env.example.php` do ZIP para `/home/USUARIO/agendaqui_secure/load-env.php`.

```
DB_HOST=localhost
DB_DATABASE=SEU_BANCO
DB_USERNAME=SEU_USUARIO
DB_PASSWORD=SUA_SENHA
RATE_LIMIT_DIR=/home/USUARIO/agendaqui_secure/storage/rate-limit
CRON_SHARED_SECRET=GERE_UM_SEGREDO
PUSH_TRACK_SECRET=GERE_OUTRO_SEGREDO
```

Permissão sugerida: `0600`.  
O PHP **não** lê `DB_NAME` / `DB_USER`. Use `DB_DATABASE` e `DB_USERNAME`.

Nunca coloque `load-env.php` em `public_html`.

---

## 5. HTTPS

Ative SSL no cPanel e force HTTPS. O cookie de sessão usa `Secure` quando a requisição é HTTPS.

---

## 6. Permissões

Sugestão (ajuste ao padrão da conta):

- arquivos `644`
- pastas `755`
- `load-env.php` `0600`
- `storage/rate-limit` e `public_html/uploads` graváveis pelo PHP

Não use `777` sem necessidade.

---

## 7. Migrations

**Backup antes de aplicar.**

### A) Com Terminal / SSH / CLI PHP (preferível)

```bash
cd /home/USUARIO/agendaqui_secure
php tools/migrate.php --status
php tools/migrate.php --dry-run
php tools/migrate.php
php tools/migrate.php --status
```

Esperado nesta versão: **001–019** aplicadas, pending vazio.  
Checksum divergente de migration já aplicada: o runner **para**. Não edite 001–018.

### B) Instalador HTTP

`/atualizar-banco.php` aplica as pending via o mesmo runner. O arquivo fica público até o auto-delete (ou até você apagar). Não é “seguro”; é atalho.

### C) Sem SSH e sem instalador

Cron de **uma** execução: `php /home/USUARIO/agendaqui_secure/tools/migrate.php` — depois apague o cron.

010 e 019 usam `INSERT IGNORE`. 012 só cria `transport_*` se não existirem.

**Não** rode os importadores na instalação. Transporte, Shopee e vagas reais podem ficar vazios sem CSV/cron.

Cron recorrente (depois de `CRON_SHARED_SECRET`):

```bash
php /home/USUARIO/agendaqui_secure/tools/scheduled-hooks.php --task=all
```

---

## 8. Testes

Ver `DEPLOY-CHECKLIST.md`.

---

## 9. PWA (após HTTPS)

1. Abra `https://SEU_DOMINIO/manifest.webmanifest` — deve ser JSON, não download genérico.
2. Abra `https://SEU_DOMINIO/sw.js`.
3. No Chrome: Application → Manifest — ícones 192/512 sem 404.
4. Instale o app (banner ou menu do navegador).
5. Desligue a rede e abra uma rota nova: deve aparecer `offline.html`.

Sem HTTPS o PWA não instala. Isso é limitação do navegador, não da HostGator.
