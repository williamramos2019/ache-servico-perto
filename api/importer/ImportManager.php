<?php

declare(strict_types=1);

/**
 * @param array{
 *   source: string,
 *   city: string,
 *   file: string,
 *   dry_run: bool,
 *   limit: ?int,
 *   update: bool,
 *   enrich: bool
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
        'unmapped' => 0,
        'enriched' => 0,
        'run_id' => null,
    ];

    $runId = null;
    $now = auth_now();
    if (!$dryRun) {
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
    $pdo->beginTransaction();
    try {
        foreach ($records as $record) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }
            $processed++;
            $errors = importer_validate_record($record, $cityIbge, $source);
            if ($errors !== []) {
                $stats['rejected']++;
                if ($runId !== null) {
                    importer_log_error(
                        $pdo,
                        $runId,
                        $record['external_id'] ?? null,
                        $record['name'] ?? null,
                        'validation',
                        implode(' ', $errors),
                        $record['raw'] ?? null
                    );
                }
                continue;
            }
            $stats['valid']++;

            if (!empty($opts['enrich']) && $enrichCount < 50 && is_string($record['cnpj'] ?? null)) {
                $record = importer_brasilapi_enrich($record);
                $enrichCount++;
                $stats['enriched']++;
                usleep(400000);
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
                $dup['company']
            );
            if ($result['action'] === 'insert') {
                $stats['inserted']++;
            } elseif ($result['action'] === 'update') {
                $stats['updated']++;
            } elseif ($result['action'] === 'duplicate') {
                $stats['duplicates']++;
            }
            if ($result['company_id'] !== null && $result['action'] !== 'duplicate') {
                importer_record_source($pdo, $result['company_id'], $runId, $source, $record, $dryRun);
            }

            if (!$dryRun && ($stats['inserted'] + $stats['updated']) % IMPORTER_BATCH_SIZE === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
        }
        $pdo->commit();
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
                total_skipped = :total_skipped
             WHERE id = :id'
        )->execute([
            ':status' => 'completed',
            ':finished_at' => auth_now(),
            ':total_collected' => $stats['collected'],
            ':total_valid' => $stats['valid'],
            ':total_inserted' => $stats['inserted'],
            ':total_updated' => $stats['updated'],
            ':total_duplicates' => $stats['duplicates'],
            ':total_rejected' => $stats['rejected'],
            ':total_skipped' => $stats['skipped'],
            ':id' => $runId,
        ]);
    }

    return $stats;
}
