<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function check(bool $condition, string $message): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

$bootstrap = $root . '/api/bootstrap/domains.php';
check(is_file($bootstrap), 'api/bootstrap/domains.php must exist');
if (is_file($bootstrap)) {
    require_once $bootstrap;

    check(domain_page(['page' => '2']) === 2, 'page parser accepts positive integers');
    check(domain_page(['page' => '-1']) === 1, 'page parser clamps invalid values');
    check(domain_limit(['limit' => '999'], 20, 50) === 50, 'limit parser caps public queries');
    check(domain_cursor('2026-08-16T12:30:00Z') === '2026-08-16 12:30:00.000', 'cursor parser normalizes UTC');
    check(domain_cursor('not-a-date') === null, 'cursor parser rejects invalid timestamps');
    check(domain_whatsapp_e164('(31) 99999-0000') === '+5531999990000', 'BR phone is normalized');
    check(domain_whatsapp_e164('123') === null, 'short phone is rejected');
    check(domain_allowed_http_url('https://example.com/path') === 'https://example.com/path', 'HTTPS URL accepted');
    check(domain_allowed_http_url('file:///etc/passwd') === null, 'non-HTTP URL rejected');
    check(domain_allowed_http_url('http://127.0.0.1/private') === null, 'private target rejected');
    check(domain_ad_event('impression') === 'impression', 'ad impression is allowed');
    check(domain_ad_event('click') === 'click', 'ad click is allowed');
    check(domain_ad_event('delete') === null, 'unknown ad event is rejected');
    check(domain_cron_secret_valid('expected', 'expected'), 'cron secret accepts exact match');
    check(!domain_cron_secret_valid('expected', 'wrong'), 'cron secret rejects mismatch');
    check(domain_slug('São José da Lapa') === 'sao-jose-da-lapa', 'slug helper normalizes accents');
}

$required = [
    'api/jobs/index.php',
    'api/jobs/admin.php',
    'api/jobs/admin/index.php',
    'api/representatives/index.php',
    'api/representatives/admin.php',
    'api/representatives/admin/index.php',
    'api/live-feed/index.php',
    'api/tourism/index.php',
    'api/procurements/index.php',
    'api/promotions/index.php',
    'api/ads/index.php',
    'api/requests/index.php',
    'api/whatsapp/subscribe.php',
    'api/whatsapp/opt-out.php',
    'api/whatsapp/index.php',
    'api/editorial/index.php',
    'api/auth/reset-request.php',
    'api/auth/reset-confirm.php',
    'api/cron/index.php',
    'tools/jobs-sync.php',
    'tools/representatives-sync.php',
    'tools/bus-sync.php',
    'tools/scheduled-hooks.php',
    'tools/smoke-test.php',
    'docs/PASSOS_DEPLOY_CPANEL.md',
    'sitemap.php',
    'api/shopee/index.php',
    'tools/shopee-import.php',
    'api/public/push/track.php',
    'api/public/push/resubscribe.php',
    'api/bootstrap/push.php',
    'api/bootstrap/env.php',
    'database/migrations/019_reference_seeds.sql',
    'docs/SUBIR-HOSTGATOR.md',
];
foreach ($required as $relative) {
    check(is_file($root . '/' . $relative), $relative . ' must exist');
}

$release = (string) @file_get_contents($root . '/tools/build-release.php');
foreach (['jobs-sync.php', 'representatives-sync.php', 'bus-sync.php', 'scheduled-hooks.php', 'smoke-test.php', 'sitemap.php', 'shopee-import.php', '018_shopee.sql', '019_reference_seeds.sql'] as $needle) {
    check(str_contains($release, $needle), 'release builder must package ' . $needle);
}
check(str_contains($release, 'posterior a 019'), 'release builder must accept migration 019');
check(str_contains($release, 'agendaqui_secure'), 'release builder packages the private agendaqui_secure tree');

$shopeeApi = (string) @file_get_contents($root . '/api/shopee/index.php');
check(str_contains($shopeeApi, 'persist_require_admin()'), 'Shopee admin/feeds require an admin session');
check(str_contains($shopeeApi, 'domain_require_admin_write()'), 'Shopee writes require admin session plus CSRF');
check(str_contains($shopeeApi, "op === 'toggle'"), 'Shopee toggle is admin-only');
$shopeeBoot = (string) @file_get_contents($root . '/api/bootstrap/shopee.php');
check(str_contains($shopeeBoot, "getenv('SHOPEE_FEED_'"), 'Shopee feeds come from env, not hardcoded URLs');
check(!preg_match('/shopee\\.com\\/[^\\s\'"]+/i', $shopeeBoot), 'Shopee bootstrap must not embed affiliate URLs');
foreach ([
    'atualizar-banco.php',
    'instalar-banco.php',
    'deploy-banco/instalar.php',
    'README-DEPLOY-HOSTGATOR.txt',
    'LEIA-ME-INSTALAR.txt',
] as $rel) {
    $contents = (string) @file_get_contents($root . '/' . $rel);
    check($contents !== '', $rel . ' must exist');
    check(!str_contains($contents, 'will3269_'), $rel . ' must not embed HostGator account id');
}
foreach (['public/instalar.php', 'public/instalar-banco.php', 'public/atualizar-banco.php'] as $rel) {
    check(!is_file($root . '/' . $rel), $rel . ' must not live in public/ (Vite would copy it to dist)');
}
$installer = (string) @file_get_contents($root . '/atualizar-banco.php');
check(str_contains($installer, 'CRON_SHARED_SECRET'), 'one-click installer writes cron secret outside the web root');
check(str_contains($installer, '$account . \'/agendaqui_secure\''), 'one-click installer prefers /home/USUARIO/agendaqui_secure');
$envBoot = (string) @file_get_contents($root . '/api/bootstrap/env.php');
check(str_contains($envBoot, 'agendaqui_secure'), 'env loader searches agendaqui_secure outside the web root');
check(str_contains($envBoot, 'dirname($bootstrapDir, 3)'), 'env loader checks the account root three levels above bootstrap');
$pkg = (string) @file_get_contents($root . '/package.json');
check(!str_contains($pkg, '@supabase/supabase-js'), 'package.json must not depend on supabase-js');
check(!str_contains($pkg, '@lovable.dev/cloud-auth-js'), 'package.json must not depend on Lovable cloud-auth');
check(!preg_match('/"web-push"/', $pkg), 'package.json must not depend on web-push');
check(!is_dir($root . '/src/legacy-server'), 'src/legacy-server must be removed');
check(!is_dir($root . '/src/integrations'), 'src/integrations must be removed');
check(!is_dir($root . '/supabase'), 'legacy supabase/ migrations must be removed');
$htaccess = (string) @file_get_contents($root . '/public/.htaccess');
check(str_contains($htaccess, 'RewriteCond %{HTTPS} !=on'), 'public htaccess redirects HTTP to HTTPS');
check(str_contains($htaccess, 'RewriteRule ^api(/|$)'), 'public htaccess does not send /api to the SPA');
check(str_contains((string) @file_get_contents($root . '/uploads/.htaccess'), 'php_flag engine off'), 'uploads htaccess disables PHP engine');
$seed = (string) @file_get_contents($root . '/database/migrations/010_seed_public.sql');
check(str_contains($seed, 'INSERT IGNORE'), 'public seed is idempotent');
check(!str_contains($seed, 'DROP TABLE'), 'public seed does not drop tables');

$jobsPublic = (string) @file_get_contents($root . '/api/jobs/index.php');
check(!str_contains($jobsPublic, 'LIKE :q OR'), 'jobs search uses unique LIKE placeholders');
check(str_contains($jobsPublic, 'domain_or_like'), 'jobs search uses domain_or_like');
$requestsApi = (string) @file_get_contents($root . '/api/requests/index.php');
check(!str_contains($requestsApi, 'LIKE :search OR'), 'request search uses unique LIKE placeholders');
$liveFeed = (string) @file_get_contents($root . '/api/live-feed/index.php');
check(!str_contains($liveFeed, 'REPLACE(:city_jobs,'), 'live feed jobs city filter uses a unique placeholder');
check(str_contains($liveFeed, ':city_jobs_like'), 'live feed binds city_jobs_like once');
check(str_contains($liveFeed, "'promocao'"), 'live feed includes published promotions');
check(str_contains($liveFeed, 'representative_activities'), 'live feed includes representative activities');
$pushBoot = (string) @file_get_contents($root . '/api/bootstrap/push.php');
check(str_contains($pushBoot, 'hash_hmac'), 'push tokens are HMAC-signed');
check(str_contains($pushBoot, "getenv('PUSH_TRACK_SECRET')"), 'push HMAC secret comes from env');
$pushTrack = (string) @file_get_contents($root . '/api/public/push/track.php');
check(!str_contains($pushTrack, 'persist_require_admin()'), 'push track stays public');
check(str_contains($pushTrack, 'push_verify_delivery_token'), 'push track verifies delivery HMAC');
$htaccess = (string) @file_get_contents($root . '/api/.htaccess');
check(str_contains($htaccess, 'public/push/track.php'), 'API rewrite exposes push track without .php');

if (function_exists('domain_or_like')) {
    $like = domain_or_like(['j.title', 'j.company_name'], '%x%', 'q');
    check($like['sql'] === '(j.title LIKE :q0 OR j.company_name LIKE :q1)', 'domain_or_like emits unique placeholders');
    check(($like['params'][':q0'] ?? null) === '%x%' && ($like['params'][':q1'] ?? null) === '%x%', 'domain_or_like binds each placeholder');
}

$pushFile = $root . '/api/bootstrap/push.php';
if (is_file($pushFile)) {
    require_once $pushFile;
    putenv('PUSH_TRACK_SECRET=unit-test-secret');
    $_ENV['PUSH_TRACK_SECRET'] = 'unit-test-secret';
    $signed = push_sign_delivery_token(42);
    check($signed !== '' && push_verify_delivery_token(42, $signed), 'push HMAC accepts the signed token');
    check(!push_verify_delivery_token(42, 'tampered-token-value!!'), 'push HMAC rejects a forged token');
    check(!push_verify_delivery_token(43, $signed), 'push HMAC is bound to delivery id');
}

$hooks = (string) @file_get_contents($root . '/tools/scheduled-hooks.php');
check(str_contains($hooks, 'cron_jobs_due'), 'scheduled hooks run due job syncs');
check(str_contains($hooks, "PHP_SAPI !== 'cli'"), 'scheduled hooks refuse HTTP');
$smoke = (string) @file_get_contents($root . '/tools/smoke-test.php');
check(str_contains($smoke, "PHP_SAPI !== 'cli'"), 'smoke test refuses HTTP');
check(str_contains($smoke, '/api/health.php'), 'smoke test hits health');
check(str_contains($smoke, '/api/auth/csrf.php'), 'smoke test hits CSRF');
check(str_contains($smoke, '/sitemap.php'), 'smoke test hits sitemap');
check(str_contains($smoke, 'op=cities'), 'smoke test hits catalog cities');
check(str_contains($smoke, '/uploads/.htaccess'), 'smoke test forbids uploads htaccess');
check(str_contains($smoke, '/load-env.php'), 'smoke test forbids load-env.php');
check(str_contains($smoke, '/buscar'), 'smoke test checks SPA /buscar');
check(str_contains($smoke, '/vespasiano'), 'smoke test checks SPA /vespasiano');
$passos = (string) @file_get_contents($root . '/docs/PASSOS_DEPLOY_CPANEL.md');
check(str_contains($passos, 'agendaqui_secure'), 'cPanel guide uses agendaqui_secure');
check(str_contains($passos, 'php tools/migrate.php'), 'cPanel guide documents migrate.php');
check(str_contains($passos, 'scheduled-hooks.php --task=all'), 'cPanel guide documents cron CLI');
$seed019 = (string) @file_get_contents($root . '/database/migrations/019_reference_seeds.sql');
check(str_contains($seed019, 'INSERT IGNORE'), '019 seeds are idempotent');
check(!preg_match('/\b(?:DROP|TRUNCATE)\b/i', $seed019), '019 does not drop or truncate');
check(!str_contains($seed019, 'INSERT INTO `companies`'), '019 does not copy companies');
check(is_file($root . '/deploy-banco/database/migrations/019_reference_seeds.sql'), 'deploy-banco includes migration 019');

$adminGuards = [
    'api/jobs/admin.php' => 'persist_require_admin()',
    'api/representatives/admin.php' => 'persist_require_admin()',
    'api/admin/backup.php' => "require_role('admin')",
    'api/cron/index.php' => 'domain_cron_secret_valid',
    'api/requests/index.php' => 'persist_require_admin()',
];
foreach ($adminGuards as $file => $needle) {
    check(str_contains((string) @file_get_contents($root . '/' . $file), $needle), $file . ' is authorization-gated');
}
check(!str_contains((string) @file_get_contents($root . '/api/jobs/index.php'), 'persist_require_admin()'), 'public jobs list is not admin-gated');
check(!str_contains((string) @file_get_contents($root . '/api/representatives/index.php'), 'persist_require_admin()'), 'public representatives list is not admin-gated');
check(!str_contains((string) @file_get_contents($root . '/api/whatsapp/subscribe.php'), 'persist_require_admin()'), 'WhatsApp opt-in stays public');

if ($failures !== []) {
    fwrite(STDERR, "FAIL ($checks checks)\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK ($checks checks)\n");
