<?php

declare(strict_types=1);

/**
 * Monta o pacote local de produção HostGator.
 *
 *   php tools/build-release.php
 *
 * Não acessa HostGator, não aplica migration, não contém senha.
 * Exige dist/ já gerado (`npm run build`).
 */

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Forbidden\n";
    exit(1);
}

const RELEASE_SKIP_NAMES = [
    '.',
    '..',
    '.git',
    '.github',
    'node_modules',
    '.env',
    '.env.local',
    '.env.production',
    'load-env.php',
    'instalar.php',
    'installed.lock',
];

function release_root(): string
{
    return dirname(__DIR__);
}

function release_fail(string $message): never
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function release_info(string $message): void
{
    fwrite(STDOUT, '[INFO] ' . $message . PHP_EOL);
}

function release_ok(string $message): void
{
    fwrite(STDOUT, '[OK] ' . $message . PHP_EOL);
}

function release_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        release_fail('Não foi possível limpar ' . $dir);
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            release_rmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function release_mkdir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        release_fail('Não foi possível criar ' . $dir);
    }
}

function release_copy_file(string $src, string $dst): void
{
    release_mkdir(dirname($dst));
    if (!copy($src, $dst)) {
        release_fail('Falha ao copiar ' . $src);
    }
}

/**
 * @param list<string> $skipNames
 */
function release_copy_dir(string $src, string $dst, array $skipNames = []): void
{
    if (!is_dir($src)) {
        release_fail('Pasta origem ausente: ' . $src);
    }
    release_mkdir($dst);
    $items = scandir($src);
    if ($items === false) {
        release_fail('Não foi possível ler ' . $src);
    }
    $skip = array_merge(RELEASE_SKIP_NAMES, $skipNames);
    foreach ($items as $item) {
        if (in_array($item, $skip, true)) {
            continue;
        }
        $from = $src . DIRECTORY_SEPARATOR . $item;
        $to = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($from) && !is_link($from)) {
            release_copy_dir($from, $to, $skipNames);
            continue;
        }
        if (is_file($from)) {
            $lower = strtolower($item);
            if (str_ends_with($lower, '.env') || $lower === 'load-env.php') {
                continue;
            }
            release_copy_file($from, $to);
        }
    }
}

/**
 * @return list<string>
 */
function release_php_files(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . 'deploy-') ) {
            continue;
        }
        $out[] = $path;
    }
    sort($out);

    return $out;
}

function release_php_lint(string $file): void
{
    $cmd = 'php -l ' . escapeshellarg($file);
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    if ($code !== 0) {
        release_fail('php -l falhou em ' . $file . PHP_EOL . implode(PHP_EOL, $output));
    }
}

function release_version(string $root): string
{
    $file = $root . DIRECTORY_SEPARATOR . 'VERSION.txt';
    if (!is_file($file)) {
        release_fail('VERSION.txt ausente.');
    }
    $version = trim((string) file_get_contents($file));
    if ($version === '' || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        release_fail('VERSION.txt inválido.');
    }

    return $version;
}

/**
 * @return list<string>
 */
function release_required_migrations(): array
{
    return [
        '001_create_migrations.sql',
        '002_auth.sql',
        '003_companies.sql',
        '004_engagement.sql',
        '005_claims.sql',
        '006_content.sql',
        '007_listings.sql',
        '008_ops.sql',
        '009_orphans.sql',
        '010_seed_public.sql',
        '011_company_import.sql',
        '012_transport.sql',
        '013_content_civic.sql',
        '014_engagement_ads.sql',
        '015_jobs.sql',
        '016_representatives_whatsapp.sql',
        '017_transport_compatibility.sql',
        '018_shopee.sql',
        '019_reference_seeds.sql',
    ];
}

function release_assert_migrations(string $dir): void
{
    foreach (release_required_migrations() as $name) {
        if (!is_file($dir . DIRECTORY_SEPARATOR . $name)) {
            release_fail('Migration obrigatória ausente: ' . $name);
        }
    }
    $extras = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
    if ($extras === false) {
        release_fail('Não foi possível listar migrations.');
    }
    foreach ($extras as $path) {
        $base = basename($path);
        if (!preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', $base, $m)) {
            release_fail('Nome de migration inválido no pacote: ' . $base);
        }
        if ((int) $m[1] > 19) {
            release_fail('Pacote não deve incluir migration posterior a 019: ' . $base);
        }
    }
}

/**
 * @return list<string>
 */
function release_scan_secrets(string $dir): array
{
    $hits = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js', 'html', 'json', 'txt', 'md', 'htaccess', 'webmanifest'], true) && $file->getFilename()[0] !== '.') {
            continue;
        }
        $name = $file->getFilename();
        if ($name === 'load-env.example.php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match('/sk_live_|BEGIN PRIVATE KEY|SUPABASE_SERVICE_ROLE|mysql:\/\/[^:]+:[^@]+@/i', $contents) === 1) {
            $hits[] = $file->getPathname();
        }
        if (preg_match('/will3269_/', $contents) === 1) {
            $hits[] = $file->getPathname() . ' (identificador de conta)';
        }
    }

    return $hits;
}

function release_add_zip(ZipArchive $zip, string $abs, string $local): void
{
    if (is_dir($abs)) {
        $localDir = str_replace('\\', '/', $local);
        if ($localDir !== '') {
            $zip->addEmptyDir($localDir);
        }
        $items = scandir($abs);
        if ($items === false) {
            release_fail('ZIP: não foi possível ler ' . $abs);
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $childLocal = $local === '' ? $item : $local . '/' . $item;
            release_add_zip($zip, $abs . DIRECTORY_SEPARATOR . $item, $childLocal);
        }
        return;
    }
    if (!$zip->addFile($abs, str_replace('\\', '/', $local))) {
        release_fail('ZIP: falha ao adicionar ' . $local);
    }
}

$root = release_root();
$version = release_version($root);
$dist = $root . DIRECTORY_SEPARATOR . 'dist';
$migrations = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$pkgName = 'AgendaAqui-hostgator-v' . $version;
$releaseDir = $root . DIRECTORY_SEPARATOR . 'release';
$pkgDir = $releaseDir . DIRECTORY_SEPARATOR . $pkgName;
$zipPath = $root . DIRECTORY_SEPARATOR . $pkgName . '.zip';
$shaPath = $zipPath . '.sha256';

release_info('Versão ' . $version);
release_assert_migrations($migrations);
release_ok('Migrations 001–019 presentes');

if (!is_file($dist . DIRECTORY_SEPARATOR . 'index.html')) {
    release_fail('dist/index.html ausente. Execute npm run build antes.');
}
if (is_file($dist . DIRECTORY_SEPARATOR . 'instalar.php')) {
    unlink($dist . DIRECTORY_SEPARATOR . 'instalar.php');
    release_info('Removido dist/instalar.php');
}
if (is_file($dist . DIRECTORY_SEPARATOR . '.env')) {
    release_fail('dist/.env não pode existir.');
}
$pwaRequired = [
    'index.html',
    'manifest.webmanifest',
    'sw.js',
    'offline.html',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'icons/icon-maskable-512.png',
    'icons/apple-touch-icon.png',
    'icons/badge-72.png',
];
foreach ($pwaRequired as $rel) {
    $pwaPath = $dist . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($pwaPath)) {
        release_fail('Arquivo PWA ausente em dist/: ' . $rel);
    }
}
release_ok('PWA em dist/ (manifest, sw, ícones)');

release_ok('dist/ válido');

$phpTargets = array_merge(
    release_php_files($root . DIRECTORY_SEPARATOR . 'api'),
    [
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'migrate.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'transport-import.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'import-companies.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'jobs-sync.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'shopee-import.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'representatives-sync.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'bus-sync.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'scheduled-hooks.php',
        $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smoke-test.php',
        $root . DIRECTORY_SEPARATOR . 'sitemap.php',
        $root . DIRECTORY_SEPARATOR . 'atualizar-banco.php',
        $root . DIRECTORY_SEPARATOR . 'load-env.example.php',
        __FILE__,
    ]
);
foreach ($phpTargets as $phpFile) {
    if (!is_file($phpFile)) {
        release_fail('PHP ausente: ' . $phpFile);
    }
    release_php_lint($phpFile);
}
release_ok('php -l nos PHP do pacote');

release_rmdir($pkgDir);
release_mkdir($pkgDir);
$web = $pkgDir . DIRECTORY_SEPARATOR . 'public_html';
$priv = $pkgDir . DIRECTORY_SEPARATOR . 'agendaqui_secure';
release_mkdir($web);
release_mkdir($priv);

release_copy_dir($dist, $web);
if (is_file($web . DIRECTORY_SEPARATOR . 'instalar.php')) {
    unlink($web . DIRECTORY_SEPARATOR . 'instalar.php');
}
$installer = $root . DIRECTORY_SEPARATOR . 'atualizar-banco.php';
if (is_file($installer)) {
    release_copy_file($installer, $web . DIRECTORY_SEPARATOR . 'atualizar-banco.php');
    release_copy_file($installer, $web . DIRECTORY_SEPARATOR . 'instalar-banco.php');
}
release_copy_dir($root . DIRECTORY_SEPARATOR . 'api', $web . DIRECTORY_SEPARATOR . 'api');
release_copy_dir($root . DIRECTORY_SEPARATOR . 'api', $priv . DIRECTORY_SEPARATOR . 'api');
release_copy_dir($migrations, $priv . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
release_mkdir($priv . DIRECTORY_SEPARATOR . 'tools');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'migrate.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'migrate.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'transport-import.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'transport-import.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'import-companies.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'import-companies.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'jobs-sync.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'jobs-sync.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'shopee-import.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'shopee-import.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'representatives-sync.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'representatives-sync.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'bus-sync.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'bus-sync.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'scheduled-hooks.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'scheduled-hooks.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smoke-test.php', $priv . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smoke-test.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'sitemap.php', $web . DIRECTORY_SEPARATOR . 'sitemap.php');
release_copy_file($root . DIRECTORY_SEPARATOR . 'load-env.example.php', $priv . DIRECTORY_SEPARATOR . 'load-env.example.php');

release_mkdir($priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports');
release_mkdir($priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit');
release_mkdir($priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups');
release_copy_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.htaccess', $priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.htaccess');
release_copy_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . '.htaccess', $priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . '.htaccess');
if (is_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'README.txt')) {
    release_copy_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'README.txt', $priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'README.txt');
}
if (is_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit' . DIRECTORY_SEPARATOR . '.htaccess')) {
    release_copy_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit' . DIRECTORY_SEPARATOR . '.htaccess', $priv . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate-limit' . DIRECTORY_SEPARATOR . '.htaccess');
}

release_mkdir($web . DIRECTORY_SEPARATOR . 'uploads');
if (is_file($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess')) {
    release_copy_file($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess', $web . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess');
}
if (is_file($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.gitkeep')) {
    release_copy_file($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.gitkeep', $web . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.gitkeep');
}

$docs = [
    'docs/SUBIR-HOSTGATOR.md' => 'SUBIR-HOSTGATOR.md',
    'docs/HOSTGATOR-DEPLOY.md' => 'README-DEPLOY-HOSTGATOR.md',
    'docs/INSTALL-HOSTGATOR.md' => 'INSTALL-HOSTGATOR.md',
    'docs/UPDATE-HOSTGATOR.md' => 'UPDATE-HOSTGATOR.md',
    'docs/ROLLBACK-HOSTGATOR.md' => 'ROLLBACK-HOSTGATOR.md',
    'docs/DEPLOY-CHECKLIST.md' => 'DEPLOY-CHECKLIST.md',
    'docs/PASSOS_DEPLOY_CPANEL.md' => 'PASSOS_DEPLOY_CPANEL.md',
    'load-env.example.php' => 'load-env.example.php',
    'VERSION.txt' => 'VERSION.txt',
];
foreach ($docs as $from => $to) {
    $src = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $from);
    if (!is_file($src)) {
        release_fail('Documento ausente: ' . $from);
    }
    release_copy_file($src, $pkgDir . DIRECTORY_SEPARATOR . $to);
    if (str_starts_with($from, 'docs/')) {
        release_copy_file($src, $priv . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . basename($from));
    }
}

$secretHits = release_scan_secrets($pkgDir);
if ($secretHits !== []) {
    release_fail("Possível segredo no release:\n - " . implode("\n - ", $secretHits));
}
release_ok('Varredura de segredos do release');

if (is_file($zipPath)) {
    unlink($zipPath);
}
if (is_file($shaPath)) {
    unlink($shaPath);
}

if (!class_exists('ZipArchive')) {
    release_fail('Extensão ZipArchive do PHP é necessária para gerar o ZIP.');
}
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    release_fail('Não foi possível criar ' . $zipPath);
}
release_add_zip($zip, $pkgDir, $pkgName);
$zip->close();
if (!is_file($zipPath)) {
    release_fail('ZIP não foi gravado.');
}

$hash = hash_file('sha256', $zipPath);
if ($hash === false) {
    release_fail('Não foi possível calcular SHA256.');
}
file_put_contents($shaPath, $hash . '  ' . basename($zipPath) . PHP_EOL);

$bytes = filesize($zipPath);
release_ok('ZIP ' . basename($zipPath) . ' (' . $bytes . ' bytes)');
release_ok('SHA256 ' . $hash);
fwrite(STDOUT, json_encode([
    'version' => $version,
    'zip' => $zipPath,
    'sha256' => $hash,
    'bytes' => $bytes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
exit(0);
