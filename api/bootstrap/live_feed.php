<?php

declare(strict_types=1);

require_once __DIR__ . '/domains.php';

function live_feed_normalize_text(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

/**
 * @param list<array<string, mixed>> $items
 * @param array<string, bool> $hidden
 * @param list<string> $blacklist
 * @return list<array<string, mixed>>
 */
function live_feed_apply_filters(array $items, array $hidden, array $blacklist): array
{
    $terms = [];
    foreach ($blacklist as $term) {
        $term = live_feed_normalize_text(trim($term));
        if ($term !== '') {
            $terms[] = $term;
        }
    }

    return array_values(array_filter($items, static function (array $item) use ($hidden, $terms): bool {
        $key = (string) ($item['source'] ?? '') . ':' . (string) ($item['source_id'] ?? '');
        if (isset($hidden[$key])) {
            return false;
        }
        $haystack = live_feed_normalize_text(
            trim((string) ($item['title'] ?? '') . ' ' . (string) ($item['subtitle'] ?? ''))
        );
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }));
}

/**
 * @return list<string>
 */
function live_feed_blacklist(PDO $pdo): array
{
    $value = persist_setting_get($pdo, 'live_feed_blacklist');
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_filter($value, static fn (mixed $term): bool => is_string($term) && trim($term) !== ''));
}
