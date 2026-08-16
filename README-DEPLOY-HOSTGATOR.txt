DEPLOY HOSTGATOR — AgendaAqui (pacote FINAL)
Dominio: https://blog.autolimpezapro.com.br/
Document root: /home4/will3269/blog.autolimpezapro.com.br
Pasta privada: /home4/will3269/agendaqui

Este ZIP NAO contem senhas, .env, load-env.php real, install.php, .git, src nem node_modules.
O banco MySQL JA EXISTE. NAO recrie o banco. NAO use DROP TABLE. NAO use --force.

----------------------------------------------------------------------
0) Backup
----------------------------------------------------------------------
No File Manager, faca backup da pasta:
  /home4/will3269/blog.autolimpezapro.com.br

----------------------------------------------------------------------
1) Document root (SPA + API HTTP)
----------------------------------------------------------------------
Envie o CONTEUDO de public_html/ para:
  /home4/will3269/blog.autolimpezapro.com.br

Deve ficar nessa pasta:
  index.html
  .htaccess
  assets/
  icons/
  api/health.php
  api/...

NAO crie uma pasta dist/ dentro do document root.
NAO envie database/, tools/, storage/ nem load-env.php para o document root.
NAO envie install.php.

Permissoes tipicas: pastas 0755, ficheiros 0644.

----------------------------------------------------------------------
2) Pasta privada (migrations, runner, rate-limit, env)
----------------------------------------------------------------------
No File Manager, suba UM nivel (conta, nao o subdominio):
  /home4/will3269/

Crie a pasta:
  /home4/will3269/agendaqui

Copie o CONTEUDO de fora-public-html/agendaqui/ para essa pasta:
  api/
  database/migrations/
  tools/migrate.php
  storage/rate-limit/

O runner CLI precisa de api/bootstrap/database.php ao lado de tools/.
A API HTTP continua a ser a copia em public_html/api/.

----------------------------------------------------------------------
3) Criar load-env.php FORA da web
----------------------------------------------------------------------
Copie load-env.example.php no servidor para:
  /home4/will3269/agendaqui/load-env.php

Permissao: 0600

Preencha MANUALMENTE (nao grave isto no Git):
  DB_HOST=localhost
  DB_PORT=3306
  DB_DATABASE=will3269_GUIA2026sjl
  DB_USERNAME=will3269_GUIA2026sjl
  DB_PASSWORD=<senha real, so no servidor>
  RATE_LIMIT_DIR=/home4/will3269/agendaqui/storage/rate-limit
  MAIL_HOST=smtp.titan.email
  MAIL_PORT=465
  MAIL_ENCRYPTION=ssl
  MAIL_USERNAME=suporte@blog.autolimpezapro.com.br
  MAIL_PASSWORD=<senha Titan, so no servidor>
  MAIL_FROM=suporte@blog.autolimpezapro.com.br
  MAIL_FROM_NAME=AgendaAqui

NUNCA coloque load-env.php no document root.
NUNCA o envie para o GitHub.

Nao e obrigatorio auto_prepend_file. A API procura:
  /home4/will3269/agendaqui/load-env.php
Nao use auto_prepend com este ficheiro (ele recusa HTTP sem AGENDAQUI_ENV_OK).

----------------------------------------------------------------------
4) Apagar ferramenta temporaria
----------------------------------------------------------------------
Se ainda existir, apague:
  /home4/will3269/blog.autolimpezapro.com.br/install.php
  /home4/will3269/blog.autolimpezapro.com.br/installed.lock
  /home4/will3269/blog.autolimpezapro.com.br/load-env.php   (se estiver na web)

----------------------------------------------------------------------
5) Health (ligacao MySQL)
----------------------------------------------------------------------
Abra:
  https://blog.autolimpezapro.com.br/api/health.php

Esperado: HTTP 200 e database = ok
Se 503: pare. Confira load-env.php (caminho privado) e MySQL.
NAO altere as migrations.

----------------------------------------------------------------------
6) Migrations (schema) — Terminal cPanel
----------------------------------------------------------------------
cd /home4/will3269/agendaqui
php tools/migrate.php --status

Se 001 a 012 estiverem pending:

php tools/migrate.php --dry-run
php tools/migrate.php
php tools/migrate.php --status

Esperado: 001 a 012 applied.
001-003 = auth/empresas (imutaveis).
004-009 = restante do schema original (Supabase -> MySQL).
010 = catalogo publico copiado do Supabase ao vivo (cidades, categorias, empresas, etc.).
011 = colunas/tabelas do importador de empresas (nao reescreve cadastros existentes).
012 = tabelas de transporte publico (comeca vazio ate um JSON com fonte).
NAO copia senhas de login do Supabase. Usuarios stub nao entram com a senha antiga.
NAO use --force.
NAO importe SQL no phpMyAdmin.
NAO recrie o banco.

Importadores (CLI, pasta privada /home4/will3269/agendaqui):
  php tools/import-companies.php --help
  php tools/transport-import.php --help
Arquivos JSON/CSV vao em storage/imports/ (nao na web).
NAO rode import pela URL. NAO invente empresas nem horarios.

----------------------------------------------------------------------
7) Conferir API
----------------------------------------------------------------------
  GET https://blog.autolimpezapro.com.br/
  GET https://blog.autolimpezapro.com.br/api/health.php
  GET https://blog.autolimpezapro.com.br/api/companies/index.php
  GET https://blog.autolimpezapro.com.br/api/auth/csrf.php
  GET https://blog.autolimpezapro.com.br/api/auth/me.php

companies/index.php nao deve devolver 500 por tabela inexistente
depois das migrations aplicadas (lista vazia e HTTP 200 e valido).

me.php sem sessao: HTTP 401.

Depois: registro, login, CSRF, perfil, empresas, logout.

----------------------------------------------------------------------
8) Nao publicar
----------------------------------------------------------------------
.env, senha, node_modules, .git, src, dumps, load-env.php real, install.php.
