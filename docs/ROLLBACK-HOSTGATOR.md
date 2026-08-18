# Rollback HostGator — AgendaAqui

Não existe `migrate:rollback` nem `--undo`. Não invente reversão automática de schema.

---

## Rollback de arquivos

1. Coloque o site em manutenção se possível (página estática temporária).
2. Restaure o backup de `public_html/` (SPA + `api/`).
3. Restaure `/home/USUARIO/agendaqui/api/` e `tools/` se tiverem sido trocados.
4. **Não** restaure um `load-env.php` de outro ambiente.
5. Confirme `GET /` e `GET /api/health.php`.

---

## Rollback de banco

- Só restaure o dump MySQL feito **imediatamente antes** da migration que falhou.
- Não importe dump de desenvolvimento por cima de produção.
- Não execute `DROP DATABASE` / `DROP TABLE` / `TRUNCATE`.

Se `php tools/migrate.php --status` mostrar `success=0`:

- A migration **não** está aplicada.
- Inspecione a tabela `migrations`.
- Corrija o SQL num **arquivo novo** se necessário (não edite 001–012 já aplicadas com sucesso).
- Para retry de uma falha: apague **somente** a linha `success=0` dessa versão, depois de confirmar que o ROLLBACK deixou o schema consistente.

---

## Quando NÃO reverter migration

- 012 já aplicada com `success=1` e tabelas `transport_*` vazias: deixar as tabelas. Elas não apagam empresas.
- 010 (`INSERT IGNORE`): restaurar dump só se a inserção tiver causado problema real. Não DELETE geral.

---

## Importância do backup

Sem dump de antes da mudança, não há rollback seguro de dados. O ZIP de deploy **não** contém backup.
