<?php

declare(strict_types=1);

/**
 * @param list<string> $argv
 * @return array{
 *   source: string,
 *   city: string,
 *   file: string,
 *   dry_run: bool,
 *   limit: ?int,
 *   update: bool,
 *   enrich: bool,
 *   resume: bool|string,
 *   help: bool
 * }
 */
function importer_parse_argv(array $argv): array
{
    $opts = [
        'source' => '',
        'city' => '',
        'file' => '',
        'dry_run' => false,
        'limit' => null,
        'update' => false,
        'enrich' => false,
        'resume' => false,
        'help' => false,
    ];
    $args = array_values(array_slice($argv, 1));

    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $opts['dry_run'] = true;
            continue;
        }
        if ($arg === '--update') {
            $opts['update'] = true;
            continue;
        }
        if ($arg === '--enrich') {
            $opts['enrich'] = true;
            continue;
        }
        if ($arg === '--resume') {
            $next = $args[$i + 1] ?? '';
            if ($next !== '' && !str_starts_with($next, '--')) {
                $opts['resume'] = $next;
                $i++;
            } else {
                $opts['resume'] = true;
            }
            continue;
        }
        if (str_starts_with($arg, '--resume=')) {
            $val = substr($arg, 9);
            $opts['resume'] = $val === '' ? true : $val;
            continue;
        }
        foreach (['source', 'city', 'file', 'limit'] as $key) {
            $flag = '--' . $key;
            if ($arg === $flag) {
                $val = $args[$i + 1] ?? '';
                if ($val === '' || str_starts_with($val, '--')) {
                    throw new InvalidArgumentException("Opção $flag exige um valor.");
                }
                $opts[$key] = $val;
                $i++;
                continue 2;
            }
            if (str_starts_with($arg, $flag . '=')) {
                $opts[$key] = substr($arg, strlen($flag) + 1);
                continue 2;
            }
        }
        throw new InvalidArgumentException('Opção desconhecida: ' . $arg);
    }

    if (is_string($opts['limit']) || is_int($opts['limit'])) {
        $lim = (int) $opts['limit'];
        $opts['limit'] = $lim > 0 ? $lim : null;
    }

    return $opts;
}

function importer_touch_run_cursor(PDO $pdo, string $runId, ?string $externalId, int $batch): void
{
    $pdo->prepare(
        'UPDATE company_import_runs SET last_external_id = :eid, last_batch = :batch WHERE id = :id'
    )->execute([
        ':eid' => $externalId,
        ':batch' => $batch,
        ':id' => $runId,
    ]);
}

/**
 * @param array{
 *   source: string,
 *   city: string,
 *   file: string,
 *   dry_run: bool,
 *   limit: ?int,
 *   update: bool,
 *   enrich: bool,
 *   resume?: bool|string
 * } $opts
 * @return array<string, mixed>
 */
function importer_run(PDO $pdo, array $opts): array
{
    $citySlug = importer_resolve_city_slug($opts['city']);
    $cityIbge = importer_city_ibge($citySlug);
    $source = strtolower(trim($opts['source']));
    if (!in_array($source, IMPORTER_ALLOWED_SOURCES, true)) {
        throw new InvalidArgumentException('Fonte inválida. Use receita, municipal ou brasilapi.');
    }
    if ($source === 'brasilapi') {
        throw new InvalidArgumentException('BrasilAPI é só enriquecimento. Use --source=receita|municipal e --enrich.');
    }
    $file = importer_safe_file($opts['file']);
    $dryRun = $opts['dry_run'];
    $allowUpdate = $opts['update'];
    $limit = $opts['limit'];
    $resumeOpt = $opts['resume'] ?? false;
    $resumeEnabled = $resumeOpt === true || (is_string($resumeOpt) && $resumeOpt !== '');
    $resumeRunId = is_string($resumeOpt) && preg_match('/^[0-9a-fA-F-]{36}$/', $resumeOpt) === 1
        ? $resumeOpt
        : null;

    $cityStmt = $pdo->prepare('SELECT id FROM cities WHERE slug = :slug LIMIT 1');
    $cityStmt->execute([':slug' => $citySlug]);
    $cityId = $cityStmt->fetchColumn();
    if (!is_string($cityId) || $cityId === '') {
        throw new RuntimeException('Cidade não cadastrada no banco: ' . $citySlug);
    }

    $catRows = $pdo->query('SELECT id, slug FROM categories')->fetchAll();
    $categoriesBySlug = [];
    foreach ($catRows as $row) {
        $categoriesBySlug[(string) $row['slug']] = (string) $row['id'];
    }

    $records = $source === 'municipal'
        ? importer_collect_municipal_file($file, $cityIbge)
        : importer_collect_cnpj_file($file, $cityIbge);

    $stats = [
        'city' => $citySlug,
        'source' => $source,
        'file' => basename($file),
        'dry_run' => $dryRun,
        'collected' => count($records),
        'valid' => 0,
        'inserted' => 0,
        'updated' => 0,
        'duplicates' => 0,
        'rejected' => 0,
        'skipped' => 0,
        'candidates' => 0,
        'unmapped' => 0,
        'enriched' => 0,
        'processed' => 0,
        'last_external_id' => null,
        'last_batch' => 0,
        'run_id' => null,
        'resumed' => $resumeEnabled,
    ];

    $runId = null;
    $now = auth_now();
    $prevInserted = 0;
    $prevUpdated = 0;
    $prevDuplicates = 0;
    $prevRejected = 0;
    $prevSkipped = 0;
    $prevValid = 0;
    $lastBatch = 0;

    if ($resumeRunId !== null && !$dryRun) {
        $prev = $pdo->prepare('SELECT * FROM company_import_runs WHERE id = :id LIMIT 1');
        $prev->execute([':id' => $resumeRunId]);
        $prevRow = $prev->fetch();
        if ($prevRow === false) {
            throw new InvalidArgumentException('run_id de --resume não encontrado.');
        }
        if ((int) $prevRow['dry_run'] === 1) {
            throw new InvalidArgumentException('Não é possível retomar uma execução dry-run como importação real.');
        }
        if ((string) $prevRow['city_slug'] !== $citySlug || (string) $prevRow['source'] !== $source) {
            throw new InvalidArgumentException('O run_id não corresponde à cidade/fonte desta execução.');
        }
        $runId = $resumeRunId;
        $prevInserted = (int) $prevRow['total_inserted'];
        $prevUpdated = (int) $prevRow['total_updated'];
        $prevDuplicates = (int) $prevRow['total_duplicates'];
        $prevRejected = (int) $prevRow['total_rejected'];
        $prevSkipped = (int) $prevRow['total_skipped'];
        $prevValid = (int) $prevRow['total_valid'];
        $lastBatch = (int) $prevRow['last_batch'];
        $stats['last_external_id'] = $prevRow['last_external_id'];
        $pdo->prepare(
            'UPDATE company_import_runs SET status = :status, error_message = NULL WHERE id = :id'
        )->execute([':status' => 'running', ':id' => $runId]);
        $stats['run_id'] = $runId;
    } elseif (!$dryRun) {
        $runId = auth_uuid();
        $pdo->prepare(
            'INSERT INTO company_import_runs
                (id, city_slug, source, dry_run, status, started_at, total_collected, importer_version, created_at)
             VALUES
                (:id, :city_slug, :source, 0, :status, :started_at, :total_collected, :importer_version, :created_at)'
        )->execute([
            ':id' => $runId,
            ':city_slug' => $citySlug,
            ':source' => $source,
            ':status' => 'running',
            ':started_at' => $now,
            ':total_collected' => count($records),
            ':importer_version' => IMPORTER_VERSION,
            ':created_at' => $now,
        ]);
        $stats['run_id'] = $runId;
    }

    $processed = 0;
    $enrichCount = 0;
    $committedWrites = 0;
    $inTx = false;
    if (!$dryRun) {
        $pdo->beginTransaction();
        $inTx = true;
    }
    try {
        foreach ($records as $record) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }
            $processed++;
            $stats['processed'] = $processed;
            $externalId = is_string($record['external_id'] ?? null)
                ? (string) $record['external_id']
                : (is_string($record['cnpj'] ?? null) ? (string) $record['cnpj'] : null);

            $errors = importer_validate_record($record, $cityIbge, $source);
            if ($errors !== []) {
                $stats['rejected']++;
                if ($runId !== null) {
                    importer_log_error(
                        $pdo,
                        $runId,
                        $externalId,
                        $record['name'] ?? null,
                        'validation',
                        implode(' ', $errors),
                        $record['raw'] ?? null
                    );
                }
                importer_log_line('Registro ' . $processed . ': rejeitado — ' . implode(' ', $errors));
                continue;
            }
            $stats['valid']++;

            if ($resumeEnabled && importer_already_imported($pdo, $source, $record)) {
                $stats['skipped']++;
                $stats['last_external_id'] = $externalId;
                importer_log_line('Registro ' . $processed . ': ignorado (já importado, resume).');
                continue;
            }

            if (!empty($opts['enrich']) && $enrichCount < IMPORTER_BRASILAPI_MAX && is_string($record['cnpj'] ?? null)) {
                try {
                    $record = importer_brasilapi_enrich($record);
                    $enrichCount++;
                    $stats['enriched']++;
                    usleep(IMPORTER_BRASILAPI_SLEEP_US);
                } catch (Throwable $e) {
                    importer_log_line('BrasilAPI indisponível no registro ' . $processed . '; importação continua.');
                }
            }

            $class = importer_classify(
                (string) $record['name'],
                $record['cnae_primary'] ?? null,
                $record['cnae_description'] ?? null
            );
            $categoryId = null;
            if ($class['slug'] !== null && isset($categoriesBySlug[$class['slug']])) {
                $categoryId = $categoriesBySlug[$class['slug']];
            } else {
                $stats['unmapped']++;
            }

            $dup = importer_find_duplicate($pdo, $record, $cityId);
            $result = importer_persist_company(
                $pdo,
                $record,
                $cityId,
                $citySlug,
                $categoryId,
                $allowUpdate,
                $dryRun,
                $dup['company'],
                $dup['match']
            );
            if ($result['action'] === 'insert') {
                $stats['inserted']++;
                importer_log_line('Registro ' . $processed . ': empresa criada.');
            } elseif ($result['action'] === 'update') {
                $stats['updated']++;
                importer_log_line('Registro ' . $processed . ': campos vazios preenchidos.');
            } elseif ($result['action'] === 'duplicate') {
                $stats['duplicates']++;
                importer_log_line('Registro ' . $processed . ': duplicidade (CNPJ/fonte) — ignorado.');
            } elseif ($result['action'] === 'candidate') {
                $stats['candidates']++;
                $stats['skipped']++;
                if ($runId !== null) {
                    importer_log_error(
                        $pdo,
                        $runId,
                        $externalId,
                        $record['name'] ?? null,
                        'duplicate_candidate',
                        'Possível duplicidade por nome+cidade. Empresa existente não foi alterada.',
                        $record['raw'] ?? null
                    );
                }
                importer_log_line('Registro ' . $processed . ': possível duplicidade — não alterada.');
            }

            if ($result['company_id'] !== null && in_array($result['action'], ['insert', 'update'], true)) {
                importer_record_source($pdo, $result['company_id'], $runId, $source, $record, $dryRun);
                $stats['last_external_id'] = $externalId;
                if ($runId !== null && !$dryRun) {
                    $committedWrites++;
                    $batchNo = $lastBatch + (int) ceil($committedWrites / IMPORTER_BATCH_SIZE);
                    $stats['last_batch'] = $batchNo;
                    importer_touch_run_cursor($pdo, $runId, $externalId, $batchNo);
                }
            }

            if (!$dryRun && $inTx && $committedWrites > 0 && $committedWrites % IMPORTER_BATCH_SIZE === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
        }
        if ($inTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($runId !== null) {
            $pdo->prepare(
                'UPDATE company_import_runs SET status = :status, finished_at = :finished_at, error_message = :error_message WHERE id = :id'
            )->execute([
                ':status' => 'failed',
                ':finished_at' => auth_now(),
                ':error_message' => $e->getMessage(),
                ':id' => $runId,
            ]);
        }
        throw $e;
    }

    if ($runId !== null) {
        $pdo->prepare(
            'UPDATE company_import_runs SET
                status = :status,
                finished_at = :finished_at,
                total_collected = :total_collected,
                total_valid = :total_valid,
                total_inserted = :total_inserted,
                total_updated = :total_updated,
                total_duplicates = :total_duplicates,
                total_rejected = :total_rejected,
                total_skipped = :total_skipped,
                last_external_id = :last_external_id,
                last_batch = :last_batch
             WHERE id = :id'
        )->execute([
            ':status' => 'completed',
            ':finished_at' => auth_now(),
            ':total_collected' => $stats['collected'],
            ':total_valid' => $prevValid + $stats['valid'],
            ':total_inserted' => $prevInserted + $stats['inserted'],
            ':total_updated' => $prevUpdated + $stats['updated'],
            ':total_duplicates' => $prevDuplicates + $stats['duplicates'],
            ':total_rejected' => $prevRejected + $stats['rejected'],
            ':total_skipped' => $prevSkipped + $stats['skipped'],
            ':last_external_id' => $stats['last_external_id'],
            ':last_batch' => $stats['last_batch'],
            ':id' => $runId,
        ]);
    }

    return $stats;
}
