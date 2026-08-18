DEPLOY HOSTGATOR — AgendaAqui 1.1.0

Nao coloque senha, .env nem load-env.php neste arquivo nem no Git.

Document root do dominio: public_html do cPanel
Pasta privada: /home/USUARIO/agendaqui_secure

Este ZIP NAO contem senhas. O banco MySQL JA EXISTE em atualizacao. NAO recrie o banco. NAO use DROP TABLE.

----------------------------------------------------------------------
0) Backup
----------------------------------------------------------------------
File Manager: baixe public_html
phpMyAdmin / backup cPanel: dump MySQL
Guarde fora do Git.

----------------------------------------------------------------------
1) public_html
----------------------------------------------------------------------
Envie o CONTEUDO de public_html/ do ZIP para o document root.
NAO crie pasta dist/ dentro do document root.
NAO deixe atualizar-banco.php no ar depois do sucesso.

----------------------------------------------------------------------
2) Pasta privada
----------------------------------------------------------------------
Copie o CONTEUDO de agendaqui_secure/ para /home/USUARIO/agendaqui_secure
(api, database/migrations 001-019, tools, storage)

----------------------------------------------------------------------
3) load-env.php FORA da web
----------------------------------------------------------------------
Copie load-env.example.php para /home/USUARIO/agendaqui_secure/load-env.php
Preencha no servidor:
  DB_DATABASE / DB_USERNAME / DB_PASSWORD
  CRON_SHARED_SECRET
  PUSH_TRACK_SECRET

PHP no MultiPHP: 8.1 ou 8.2. Nao use 8.0.

----------------------------------------------------------------------
4) Migrations
----------------------------------------------------------------------
cd /home/USUARIO/agendaqui_secure
php tools/migrate.php --status
php tools/migrate.php --dry-run
php tools/migrate.php

Esperado: 001-019 aplicadas.

----------------------------------------------------------------------
5) Testes
----------------------------------------------------------------------
GET /api/health.php
GET /empregos  (404 de API = ZIP antigo ainda no ar)
Login + CSRF
Apague atualizar-banco.php se ainda existir

O que NAO vai funcionar neste cPanel:
  scrapers municipais Node
  gerador de blog por IA
  disparo Web Push com VAPID privado
