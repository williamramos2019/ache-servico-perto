<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/live_feed.php';

app_start(['GET', 'POST', 'DELETE', 'OPTIONS']);
auth_start_session();
$pdo = db_pdo(false);
domain_require_tables($pdo, ['live_feed_hidden']);
$method = app_request_method();
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($method === 'GET' && $op === 'list') {
    $limit = domain_limit($_GET, 30, 100);
    $cursor = domain_cursor($_GET['cursor'] ?? null);
    $city = domain_string($_GET['city'] ?? null, 'city', 0, 191);
    $cursorValue = $cursor ?? '1970-01-01 00:00:00.000';
    $cityValue = $city ?? '';
    $params = [
        ':cursor_events' => $cursorValue,
        ':city_events_empty' => $cityValue,
        ':city_events' => $cityValue,
        ':cursor_jobs' => $cursorValue,
        ':city_jobs_empty' => $cityValue,
        ':city_jobs_like' => $cityValue,
        ':cursor_procurements' => $cursorValue,
        ':city_procurements_empty' => $cityValue,
        ':city_procurements' => $cityValue,
        ':cursor_promotions' => $cursorValue,
        ':city_promotions_empty' => $cityValue,
        ':city_promotions' => $cityValue,
        ':cursor_activities' => $cursorValue,
        ':city_activities_empty' => $cityValue,
        ':city_activities' => $cityValue,
    ];
    $sql = "
        SELECT CONCAT('event:', e.id) AS id, 'evento' AS kind, 'events' AS source, e.id AS source_id,
               e.title, e.location AS subtitle, e.cover_image AS image, CONCAT('/eventos/', e.slug) AS href,
               e.start_at AS timestamp, ci.slug AS city_slug
        FROM events e LEFT JOIN cities ci ON ci.id = e.city_id
        WHERE e.status = 'published' AND e.start_at > :cursor_events
          AND (:city_events_empty = '' OR ci.slug = :city_events)
        UNION ALL
        SELECT CONCAT('job:', j.id), 'vaga', 'jobs', j.id, j.title, j.company_name, j.company_logo_url,
               CONCAT('/empregos/', j.id), j.posted_at, NULL
        FROM jobs j
        WHERE j.is_active = 1 AND j.posted_at > :cursor_jobs
          AND (:city_jobs_empty = '' OR j.location_city LIKE CONCAT('%', REPLACE(:city_jobs_like, '-', ' '), '%'))
        UNION ALL
        SELECT CONCAT('procurement:', p.id), 'edital', 'procurements', p.id, p.title, p.agency, NULL,
               '/transparencia', COALESCE(p.deadline_date, p.created_at), ci.slug
        FROM procurements p JOIN cities ci ON ci.id = p.city_id
        WHERE p.status IN ('open','published','aberto','em_andamento')
          AND COALESCE(p.deadline_date, p.created_at) > :cursor_procurements
          AND (:city_procurements_empty = '' OR ci.slug = :city_procurements)
        UNION ALL
        SELECT CONCAT('promotion:', pr.id), 'promocao', 'promotions', pr.id, pr.title, pr.description,
               COALESCE(pr.image_url, pr.cover_image), '/promocoes', pr.created_at, ci.slug
        FROM promotions pr LEFT JOIN cities ci ON ci.id = pr.city_id
        WHERE pr.status = 'published' AND pr.created_at > :cursor_promotions
          AND (:city_promotions_empty = '' OR ci.slug = :city_promotions OR ci.id IS NULL)
        UNION ALL
        SELECT CONCAT('activity:', ra.id), 'atividade', 'representative_activities', ra.id, ra.title, ra.description,
               r.photo_url, CONCAT('/representantes/', COALESCE(r.slug, ra.id)), ra.occurred_at, ci.slug
        FROM representative_activities ra
        LEFT JOIN representatives r ON r.id = ra.representative_id
        JOIN cities ci ON ci.id = ra.city_id
        WHERE ra.occurred_at > :cursor_activities
          AND (:city_activities_empty = '' OR ci.slug = :city_activities)
        ORDER BY timestamp DESC LIMIT " . $limit;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable) {
        app_error('not_migrated', 'Live feed sources are not installed.', 503);
    }
    $hiddenStmt = $pdo->query('SELECT source, source_id FROM live_feed_hidden');
    $hidden = [];
    foreach ($hiddenStmt->fetchAll() as $row) {
        $hidden[(string) $row['source'] . ':' . (string) $row['source_id']] = true;
    }
    $rows = live_feed_apply_filters($rows, $hidden, live_feed_blacklist($pdo));
    $next = null;
    foreach ($rows as $row) {
        if ($next === null || (string) $row['timestamp'] > $next) {
            $next = (string) $row['timestamp'];
        }
    }
    app_success(['items' => $rows, 'cursor' => $next ?? $cursor]);
}

if ($method === 'GET' && $op === 'hidden') {
    persist_require_admin();
    app_success(['rows' => $pdo->query(
        'SELECT h.*, p.name AS hidden_by_name FROM live_feed_hidden h
         LEFT JOIN profiles p ON p.id = h.hidden_by ORDER BY h.hidden_at DESC LIMIT 500'
    )->fetchAll()]);
}

if ($method === 'GET' && $op === 'blacklist') {
    persist_require_admin();
    app_success(['terms' => live_feed_blacklist($pdo)]);
}

$body = domain_read_json();
$op = is_string($body['op'] ?? null) ? $body['op'] : $op;
$userId = domain_require_admin_write();
if ($op === 'hide') {
    $source = domain_enum($body['source'] ?? '', 'source', ['events', 'jobs', 'procurements', 'promotions', 'listings', 'representative_activities'], '');
    $sourceId = domain_uuid($body['source_id'] ?? null, 'source_id', false);
    $pdo->prepare(
        'INSERT INTO live_feed_hidden (id, source, source_id, reason, hidden_by, hidden_at)
         VALUES (:id, :source, :source_id, :reason, :user, :now)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), hidden_by = VALUES(hidden_by), hidden_at = VALUES(hidden_at)'
    )->execute([
        ':id' => auth_uuid(), ':source' => $source, ':source_id' => $sourceId,
        ':reason' => domain_string($body['reason'] ?? null, 'reason', 0, 2000),
        ':user' => $userId, ':now' => auth_now(),
    ]);
    app_success(['ok' => true]);
}
if ($op === 'unhide') {
    $source = domain_string($body['source'] ?? null, 'source', 1, 128, false);
    $sourceId = domain_uuid($body['source_id'] ?? null, 'source_id', false);
    $pdo->prepare('DELETE FROM live_feed_hidden WHERE source = :source AND source_id = :source_id')->execute([
        ':source' => $source, ':source_id' => $sourceId,
    ]);
    app_success(['ok' => true]);
}
if ($op === 'blacklist_save') {
    $terms = $body['terms'] ?? null;
    if (!is_array($terms) || !array_is_list($terms) || count($terms) > 100) {
        app_error('invalid_terms', 'terms must be an array.', 422);
    }
    $clean = [];
    foreach ($terms as $term) {
        $value = domain_string($term, 'term', 1, 80, false);
        $clean[] = strtolower($value);
    }
    persist_setting_upsert($pdo, 'live_feed_blacklist', array_values(array_unique($clean)), false);
    app_success(['ok' => true]);
}
app_error('invalid_op', 'Operação inválida.', 400);
