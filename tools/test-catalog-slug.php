<?php

declare(strict_types=1);

require dirname(__DIR__) . '/api/bootstrap/catalog.php';

$failed = 0;
function expect_true(bool $ok, string $msg): void
{
    global $failed;
    if (!$ok) {
        fwrite(STDERR, "FAIL: $msg\n");
        $failed++;
    }
}

$truncated = 'auto-limpeza-pro-higienizacao-de-sofa-sao-jose-da-lapa-e-vespasiano-sao-jose-da-';
$full = 'auto-limpeza-pro-higienizacao-de-sofa-sao-jose-da-lapa-e-vespasiano-sao-jose-da-lapa';

expect_true(catalog_slug_is_safe($truncated), 'truncated slug ending with hyphen must be accepted');
expect_true(catalog_slug_is_safe('transportes-mg-cargas'), 'normal slug must be accepted');
expect_true(!catalog_slug_is_safe(''), 'empty slug must be rejected');
expect_true(!catalog_slug_is_safe('foo%bar'), 'wildcard slug must be rejected');

$values = catalog_slug_lookup_values($truncated);
expect_true(in_array($truncated, $values, true), 'lookup keeps exact truncated slug');
expect_true(in_array(rtrim($truncated, '-'), $values, true), 'lookup also tries rtrim');

expect_true(catalog_slug_is_safe($full), 'longer intended slug must be accepted for prefix fallback');

if ($failed > 0) {
    fwrite(STDERR, "$failed assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "OK catalog slug helpers\n");
