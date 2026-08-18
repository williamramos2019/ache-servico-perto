<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/jobs.php';

app_start(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['jobs', 'job_sources', 'job_sync_logs']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($method === 'GET') {
    persist_require_admin();
    if ($op === 'sources') {
        $rows = $pdo->query('SELECT * FROM job_sources ORDER BY created_at DESC LIMIT 200')->fetchAll();
        foreach ($rows as &$row) {
            $row = domain_decode_row($row, ['config'], ['is_active'], ['sync_frequency_minutes']);
        }
        unset($row);
        app_success(['sources' => $rows]);
    }
    if ($op === 'logs') {
        $stmt = $pdo->query(
            'SELECT l.*, s.name AS source_name, s.slug AS source_slug
             FROM job_sync_logs l LEFT JOIN job_sources s ON s.id = l.source_id
             ORDER BY l.started_at DESC LIMIT 100'
        );
        app_success(['logs' => $stmt->fetchAll()]);
    }
    if ($op === 'list') {
        $page = domain_page($_GET);
        $limit = domain_limit($_GET, 50, 100);
        $where = ['1=1'];
        $params = [];
        $q = domain_string($_GET['q'] ?? null, 'q', 0, 120);
        if ($q !== null) {
            $like = domain_or_like(['j.title', 'j.company_name'], persist_like($q), 'q');
            $where[] = $like['sql'];
            $params = array_merge($params, $like['params']);
        }
        $source = domain_uuid($_GET['source_id'] ?? null, 'source_id');
        if ($source !== null) {
            $where[] = 'j.source_id = :source';
            $params[':source'] = $source;
        }
        $active = domain_enum($_GET['is_active'] ?? 'all', 'is_active', ['all', 'yes', 'no'], 'all');
        if ($active !== 'all') {
            $where[] = 'j.is_active = :active';
            $params[':active'] = $active === 'yes' ? 1 : 0;
        }
        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare('SELECT COUNT(*) FROM jobs j WHERE ' . $whereSql);
        $count->execute($params);
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            'SELECT j.*, s.name AS source_name FROM jobs j LEFT JOIN job_sources s ON s.id = j.source_id
             WHERE ' . $whereSql . ' ORDER BY j.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        app_success([
            'rows' => array_map(static fn (array $r): array => jobs_public_row($r, true), $stmt->fetchAll()),
            'total' => (int) $count->fetchColumn(),
            'page' => $page,
            'pageSize' => $limit,
        ]);
    }
    app_error('invalid_op', 'Operação inválida.', 400);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
domain_require_admin_write();

if ($op === 'job_save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    app_success(['id' => domain_upsert($pdo, 'jobs', jobs_validated($body), $id)], $id === null ? 201 : 200);
}
if ($op === 'job_toggle') {
    $id = domain_uuid($body['id'] ?? null, 'id', false);
    $pdo->prepare('UPDATE jobs SET is_active = :active, updated_at = :now WHERE id = :id')->execute([
        ':active' => domain_bool($body['is_active'] ?? null),
        ':now' => auth_now(),
        ':id' => $id,
    ]);
    app_success(['ok' => true]);
}
if ($op === 'job_delete') {
    domain_delete($pdo, 'jobs', $body['id'] ?? null);
    app_success(['ok' => true]);
}
if ($op === 'source_save') {
    $id = domain_uuid($body['id'] ?? null, 'id');
    $slug = domain_string($body['slug'] ?? null, 'slug', 1, 191, false);
    $kind = domain_enum($body['kind'] ?? 'manual', 'kind', ['api', 'scrape', 'manual'], 'manual');
    $endpoint = $kind === 'manual' ? null : (JOB_SOURCE_ALLOWLIST[$slug] ?? null);
    if ($kind !== 'manual' && $endpoint === null) {
        app_error('source_not_allowed', 'Automated source adapter is not allowlisted.', 422);
    }
    $fields = [
        'slug' => $slug,
        'name' => domain_string($body['name'] ?? null, 'name', 1, 255, false),
        'kind' => $kind,
        'endpoint_url' => $endpoint,
        'config' => domain_json_value($body['config'] ?? [], 'config'),
        'is_active' => domain_bool($body['is_active'] ?? null, true),
        'sync_frequency_minutes' => domain_int($body['sync_frequency_minutes'] ?? 60, 'sync_frequency_minutes', 5, 43200, 60),
    ];
    app_success(['id' => domain_upsert($pdo, 'job_sources', $fields, $id)], $id === null ? 201 : 200);
}
if ($op === 'source_delete') {
    domain_delete($pdo, 'job_sources', $body['id'] ?? null);
    app_success(['ok' => true]);
}
if ($op === 'sync') {
    $sourceId = domain_uuid($body['id'] ?? ($body['source_id'] ?? null), 'source_id', false);
    try {
        app_success(jobs_sync_source($pdo, $sourceId));
    } catch (InvalidArgumentException $e) {
        app_error('source_not_allowed', $e->getMessage(), 422);
    }
}

app_error('invalid_op', 'Operação inválida.', 400);
