<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/jobs.php';

app_start(['GET', 'OPTIONS']);
$pdo = db_pdo(false);
domain_require_tables($pdo, ['jobs']);
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : 'list';

if ($op === 'show') {
    $id = domain_uuid($_GET['id'] ?? null, 'id', false);
    $stmt = $pdo->prepare(
        'SELECT j.*, s.name AS source_name, s.slug AS source_slug
         FROM jobs j LEFT JOIN job_sources s ON s.id = j.source_id
         WHERE j.id = :id AND j.is_active = 1
           AND (j.expires_at IS NULL OR j.expires_at >= UTC_TIMESTAMP(3)) LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    app_success(['job' => $row === false ? null : jobs_public_row($row, true)]);
}

if ($op === 'facets') {
    $facets = [];
    foreach (['employment_type' => 'employment', 'experience_level' => 'experience', 'category' => 'category'] as $column => $key) {
        $stmt = $pdo->query(
            "SELECT DISTINCT `$column` AS value FROM jobs
             WHERE is_active = 1 AND `$column` IS NOT NULL AND `$column` <> ''
               AND (expires_at IS NULL OR expires_at >= UTC_TIMESTAMP(3))
             ORDER BY `$column` LIMIT 200"
        );
        $facets[$key] = array_values(array_map(static fn (array $r): string => (string) $r['value'], $stmt->fetchAll()));
    }
    app_success($facets);
}

if (!in_array($op, ['list', 'premium'], true)) {
    app_error('invalid_op', 'Operação inválida.', 400);
}

$page = domain_page($_GET);
$limit = domain_limit($_GET, $op === 'premium' ? 6 : 20, $op === 'premium' ? 30 : 50);
$where = ['j.is_active = 1', '(j.expires_at IS NULL OR j.expires_at >= UTC_TIMESTAMP(3))'];
$params = [];
if ($op === 'premium') {
    $where[] = 'j.is_premium = 1';
    $where[] = '(j.featured_until IS NULL OR j.featured_until >= UTC_TIMESTAMP(3))';
}
$q = domain_string($_GET['q'] ?? null, 'q', 0, 120);
if ($q !== null) {
    $like = domain_or_like(
        ['j.title', 'j.company_name', 'j.description', 'j.category'],
        persist_like($q),
        'q'
    );
    $where[] = $like['sql'];
    $params = array_merge($params, $like['params']);
}
foreach (['city' => 'location_city', 'state' => 'location_state', 'category' => 'category', 'employment' => 'employment_type', 'experience' => 'experience_level'] as $query => $column) {
    $value = domain_string($_GET[$query] ?? null, $query, 0, 128);
    if ($value !== null) {
        $where[] = "j.`$column` = :$query";
        $params[':' . $query] = $value;
    }
}
$remote = domain_enum($_GET['remote'] ?? 'all', 'remote', ['all', 'yes', 'no'], 'all');
if ($remote !== 'all') {
    $where[] = 'j.is_remote = :remote';
    $params[':remote'] = $remote === 'yes' ? 1 : 0;
}
$salary = domain_decimal($_GET['salaryMin'] ?? null, 'salaryMin', 0, 1000000);
if ($salary !== null && (float) $salary > 0) {
    $where[] = '(j.salary_max >= :salary_max OR j.salary_min >= :salary_min)';
    $params[':salary_max'] = $salary;
    $params[':salary_min'] = $salary;
}
$sort = domain_enum($_GET['sort'] ?? 'recent', 'sort', ['recent', 'salary_desc', 'salary_asc'], 'recent');
$order = match ($sort) {
    'salary_desc' => 'j.salary_max DESC, j.posted_at DESC',
    'salary_asc' => 'j.salary_min ASC, j.posted_at DESC',
    default => 'j.is_premium DESC, j.posted_at DESC',
};
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare('SELECT COUNT(*) FROM jobs j WHERE ' . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$offset = ($page - 1) * $limit;
$stmt = $pdo->prepare(
    'SELECT j.* FROM jobs j WHERE ' . $whereSql . ' ORDER BY ' . $order .
    ' LIMIT ' . $limit . ' OFFSET ' . $offset
);
$stmt->execute($params);
$rows = array_map(static fn (array $row): array => jobs_public_row($row), $stmt->fetchAll());
app_success(['rows' => $rows, 'total' => $total, 'page' => $page, 'pageSize' => $limit]);
