<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/bootstrap/whatsapp.php';
require_once $root . '/api/bootstrap/cron.php';
require_once $root . '/api/bootstrap/auth.php';
require_once $root . '/api/bootstrap/promotions.php';
require_once $root . '/api/bootstrap/live_feed.php';

$failures = [];
$checks = 0;

function important_check(bool $condition, string $message): void
{
    global $failures, $checks;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

$functions = [
    'whatsapp_optout_sign',
    'whatsapp_optout_token_valid',
    'cron_digest_is_due',
    'cron_digest_run_key',
    'cron_claim_is_stale',
    'cron_normalize_bus_items',
    'auth_reset_plan',
    'auth_reset_token_valid',
    'auth_session_version_valid',
    'promotions_sponsored_value',
    'representatives_feed_columns',
    'representatives_ranking_sql',
    'live_feed_apply_filters',
    'jobs_sync_backoff_seconds',
    'jobs_source_is_due',
    'sync_idempotency_key',
];
foreach ($functions as $function) {
    important_check(function_exists($function), $function . ' helper must exist');
}

if (function_exists('whatsapp_optout_sign') && function_exists('whatsapp_optout_token_valid')) {
    $token = whatsapp_optout_sign('test-secret', '11111111-1111-4111-8111-111111111111', '+5531999990000', 1_700_000_000);
    $hash = hash('sha256', $token);
    important_check(
        whatsapp_optout_token_valid('test-secret', $token, '11111111-1111-4111-8111-111111111111', '+5531999990000', $hash),
        'valid opt-out token is accepted'
    );
    important_check(
        !whatsapp_optout_token_valid('test-secret', $token . 'x', '11111111-1111-4111-8111-111111111111', '+5531999990000', $hash),
        'tampered opt-out token is rejected'
    );
    important_check(
        !whatsapp_optout_token_valid('test-secret', $token, '11111111-1111-4111-8111-111111111111', '+5531888880000', $hash),
        'opt-out token is bound to phone'
    );
}

if (function_exists('cron_digest_is_due') && function_exists('cron_digest_run_key') && function_exists('cron_claim_is_stale')) {
    $friday = new DateTimeImmutable('2026-08-21 10:00:00', new DateTimeZone('UTC'));
    $thursday = new DateTimeImmutable('2026-08-20 10:00:00', new DateTimeZone('UTC'));
    important_check(cron_digest_is_due($friday, null, 5, 8, 18), 'new subscriber is due in Friday window');
    important_check(!cron_digest_is_due($thursday, null, 5, 8, 18), 'digest is blocked outside weekday window');
    important_check(
        !cron_digest_is_due($friday, new DateTimeImmutable('2026-08-18 10:00:00', new DateTimeZone('UTC')), 5, 8, 18),
        'digest is blocked when sent within seven days'
    );
    important_check(
        cron_digest_is_due($friday, new DateTimeImmutable('2026-08-14 12:00:00', new DateTimeZone('UTC')), 5, 8, 18),
        'previous-week delivery is due even when its clock time was later'
    );
    important_check(cron_digest_run_key($friday) === 'whatsapp-digest:2026-W34', 'digest run key is weekly');
    important_check(
        cron_claim_is_stale(
            new DateTimeImmutable('2026-08-21 09:00:00', new DateTimeZone('UTC')),
            $friday,
            1800
        ),
        'old delivery claim is reclaimable'
    );
}

if (function_exists('auth_reset_plan') && function_exists('auth_session_version_valid')) {
    $plan = auth_reset_plan('11111111-1111-4111-8111-111111111111', 4);
    important_check($plan['invalidate_all_tokens'] === true, 'reset plan invalidates every user token');
    important_check($plan['next_session_version'] === 5, 'reset plan increments durable session version');
    important_check(auth_session_version_valid(5, 5), 'matching session version remains valid');
    important_check(!auth_session_version_valid(4, 5), 'old session version is revoked');
}
if (function_exists('auth_reset_token_valid')) {
    $resetToken = str_repeat('a', 64);
    $resetHash = hash('sha256', $resetToken);
    important_check(auth_reset_token_valid($resetToken, $resetHash, 1_700_000_100, 1_700_000_000), 'valid reset token is accepted');
    important_check(!auth_reset_token_valid($resetToken . 'b', $resetHash, 1_700_000_100, 1_700_000_000), 'malformed reset token is rejected');
    important_check(!auth_reset_token_valid($resetToken, $resetHash, 1_699_999_999, 1_700_000_000), 'expired reset token is rejected');
}

if (function_exists('promotions_sponsored_value')) {
    important_check(promotions_sponsored_value(false, true, false) === false, 'owner cannot create sponsored coupon');
    important_check(promotions_sponsored_value(false, true, true) === true, 'owner cannot alter existing sponsored state');
    important_check(promotions_sponsored_value(true, true, false) === true, 'admin can sponsor coupon');
}

if (function_exists('representatives_feed_columns') && function_exists('representatives_ranking_sql')) {
    $columns = representatives_feed_columns();
    important_check(!in_array('raw_payload', $columns, true), 'representative feed excludes raw_payload');
    important_check(in_array('dedupe_hash', $columns, true) === false, 'representative feed excludes dedupe hash');
    $sql = representatives_ranking_sql();
    important_check(substr_count($sql, 'GROUP BY representative_id') >= 2, 'ranking independently pre-aggregates both child tables');
    important_check(!str_contains($sql, 'LEFT JOIN representative_activities a ON'), 'ranking avoids direct activity/attendance Cartesian join');
}

if (function_exists('live_feed_apply_filters')) {
    $items = [
        ['source' => 'events', 'source_id' => 'a', 'title' => 'Festa da cidade', 'subtitle' => null],
        ['source' => 'jobs', 'source_id' => 'b', 'title' => 'Spam imperdível', 'subtitle' => 'teste'],
        ['source' => 'jobs', 'source_id' => 'c', 'title' => 'Vaga segura', 'subtitle' => 'Empresa'],
    ];
    $filtered = live_feed_apply_filters($items, ['events:a' => true], ['spam', 'teste']);
    important_check(count($filtered) === 1 && $filtered[0]['source_id'] === 'c', 'hidden and blacklisted feed items are removed');
}

if (function_exists('jobs_sync_backoff_seconds') && function_exists('jobs_source_is_due')) {
    important_check(jobs_sync_backoff_seconds(1) === 300, 'first job sync failure backs off five minutes');
    important_check(jobs_sync_backoff_seconds(5) <= 21600, 'job sync backoff is capped');
    $now = new DateTimeImmutable('2026-08-21 10:00:00', new DateTimeZone('UTC'));
    important_check(
        !jobs_source_is_due($now, new DateTimeImmutable('2026-08-21 10:05:00', new DateTimeZone('UTC')), null),
        'source is not due before next_sync_at'
    );
    important_check(
        !jobs_source_is_due($now, null, new DateTimeImmutable('2026-08-21 10:05:00', new DateTimeZone('UTC'))),
        'source is not due while locked'
    );
    important_check(jobs_source_is_due($now, null, null), 'unlocked source without delay is due');
}

if (function_exists('sync_idempotency_key')) {
    important_check(
        sync_idempotency_key('representatives', 'source-a', '2026-08-21') === sync_idempotency_key('representatives', 'source-a', '2026-08-21'),
        'sync idempotency keys are deterministic'
    );
}
if (function_exists('cron_normalize_bus_items')) {
    $normalizedBus = cron_normalize_bus_items('vespasiano', [
        ['code' => '1', 'city_slug' => 'sao-jose-da-lapa'],
        'invalid',
    ]);
    important_check(
        count($normalizedBus) === 1 && $normalizedBus[0]['city_slug'] === 'vespasiano',
        'HTTP bus service pins every item to allowlisted target city'
    );
}

$migration13 = (string) file_get_contents($root . '/database/migrations/013_content_civic.sql');
important_check(str_contains($migration13, 'password_reset_tokens'), 'migration creates durable password reset tokens');
important_check(str_contains($migration13, 'session_version'), 'migration adds durable session version');
$migration15 = (string) file_get_contents($root . '/database/migrations/015_jobs.sql');
important_check(str_contains($migration15, 'sync_locked_until'), 'jobs migration adds source locking');
important_check(str_contains($migration15, 'next_sync_at'), 'jobs migration adds failure backoff schedule');
$migration16 = (string) file_get_contents($root . '/database/migrations/016_representatives_whatsapp.sql');
important_check(str_contains($migration16, 'opt_out_token_hash'), 'WhatsApp migration stores opt-out token hash');
important_check(str_contains($migration16, 'scheduled_hook_runs'), 'WhatsApp migration creates hook run log');
important_check(str_contains($migration16, 'digest_claim_token'), 'WhatsApp migration adds delivery claims');

$optOutEndpoint = (string) file_get_contents($root . '/api/whatsapp/opt-out.php');
important_check(str_contains($optOutEndpoint, 'whatsapp_optout_token_valid'), 'opt-out endpoint validates signed token');
important_check(str_contains($optOutEndpoint, 'user_id'), 'opt-out endpoint checks authenticated ownership');
$resetRequest = (string) file_get_contents($root . '/api/auth/reset-request.php');
important_check(str_contains($resetRequest, "rate_limit_hit('password_reset_email', \$email"), 'reset rate bucket is independent of IP');
important_check(str_contains($resetRequest, 'DELETE FROM password_reset_tokens WHERE user_id'), 'reset request invalidates prior tokens');
$resetConfirm = (string) file_get_contents($root . '/api/auth/reset-confirm.php');
important_check(str_contains($resetConfirm, 'session_version = session_version + 1'), 'reset confirm revokes existing sessions');
important_check(str_contains($resetConfirm, 'DELETE FROM password_reset_tokens WHERE user_id'), 'reset confirm invalidates every token');
$promotions = (string) file_get_contents($root . '/api/promotions/index.php');
important_check(str_contains($promotions, 'promotions_sponsored_value'), 'promotion writes enforce sponsored policy helper');
$representatives = (string) file_get_contents($root . '/api/representatives/index.php');
important_check(str_contains($representatives, 'representatives_feed_columns'), 'representative feed uses safe projection helper');
important_check(str_contains($representatives, 'representatives_ranking_sql'), 'representative ranking uses tested SQL helper');
$liveFeed = (string) file_get_contents($root . '/api/live-feed/index.php');
important_check(str_contains($liveFeed, 'live_feed_apply_filters'), 'live feed applies tested moderation filters');
important_check(str_contains($liveFeed, 'live_feed_blacklist'), 'live feed loads blacklist setting');
$jobs = (string) file_get_contents($root . '/api/bootstrap/jobs.php');
important_check(str_contains($jobs, 'sync_lock_token'), 'job sync claims per-source lock');
important_check(str_contains($jobs, 'jobs_sync_backoff_seconds'), 'job sync applies tested failure backoff');
$cronEndpoint = (string) file_get_contents($root . '/api/cron/index.php');
important_check(!str_contains($cronEndpoint, "app_json(503"), 'representative and bus HTTP hooks are functional');
important_check(str_contains($cronEndpoint, 'cron_representatives_sync'), 'HTTP representative hook uses shared service');
important_check(str_contains($cronEndpoint, 'cron_bus_sync'), 'HTTP bus hook uses shared service');

if ($failures !== []) {
    fwrite(STDERR, "FAIL ($checks checks)\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK ($checks checks)\n");
