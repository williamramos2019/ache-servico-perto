<?php

declare(strict_types=1);

require __DIR__ . '/api/bootstrap/database.php';

function sitemap_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$base = rtrim(trim((string) getenv('APP_PUBLIC_URL')), '/');
if ($base === '') {
    $host = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $base = $host !== '' ? 'https://' . $host : 'https://agendaqui.local';
}
$urls = [
    ['path' => '/', 'updated' => gmdate('Y-m-d')],
    ['path' => '/empregos', 'updated' => gmdate('Y-m-d')],
    ['path' => '/representantes', 'updated' => gmdate('Y-m-d')],
    ['path' => '/roteiro-turistico', 'updated' => gmdate('Y-m-d')],
    ['path' => '/transparencia', 'updated' => gmdate('Y-m-d')],
    ['path' => '/promocoes', 'updated' => gmdate('Y-m-d')],
    ['path' => '/ao-vivo', 'updated' => gmdate('Y-m-d')],
    ['path' => '/agora', 'updated' => gmdate('Y-m-d')],
    ['path' => '/ofertas-shopee', 'updated' => gmdate('Y-m-d')],
    ['path' => '/cadastre-sua-empresa', 'updated' => gmdate('Y-m-d')],
];

try {
    $pdo = db_pdo(false);
    $queries = [
        ["SELECT CONCAT('/empresa/', slug) AS path, DATE(updated_at) AS updated FROM companies WHERE status = 'active'", []],
        ["SELECT CONCAT('/eventos/', slug) AS path, DATE(updated_at) AS updated FROM events WHERE status = 'published'", []],
        ["SELECT CONCAT('/blog/', slug) AS path, DATE(updated_at) AS updated FROM posts WHERE type = 'blog' AND status = 'published'", []],
        ["SELECT CONCAT('/empregos/', id) AS path, DATE(updated_at) AS updated FROM jobs WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= UTC_TIMESTAMP(3))", []],
        ["SELECT CONCAT('/representantes/', slug) AS path, DATE(updated_at) AS updated FROM representatives WHERE is_active = 1", []],
    ];
    foreach ($queries as [$sql, $params]) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $urls[] = ['path' => (string) $row['path'], 'updated' => (string) ($row['updated'] ?? gmdate('Y-m-d'))];
        }
    }
} catch (Throwable) {
    http_response_code(503);
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach (array_slice($urls, 0, 50000) as $url) {
    echo "  <url><loc>" . sitemap_escape($base . $url['path']) . "</loc><lastmod>"
        . sitemap_escape($url['updated']) . "</lastmod></url>\n";
}
echo "</urlset>\n";
