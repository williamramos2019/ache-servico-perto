<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
$src = $argv[1] ?? ($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'pwa' . DIRECTORY_SEPARATOR . 'icon-source-512.png');
$outDir = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'icons';

if (!is_file($src)) {
    fwrite(STDERR, "Source PNG missing: {$src}\n");
    exit(1);
}
if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "PHP GD with PNG support is required.\n");
    exit(1);
}

$source = imagecreatefrompng($src);
if ($source === false) {
    fwrite(STDERR, "Cannot read PNG.\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

/**
 * @param array{file:string,size:int} $spec
 */
function write_icon(GdImage $source, string $outDir, array $spec): void
{
    $size = $spec['size'];
    $canvas = imagecreatetruecolor($size, $size);
    if ($canvas === false) {
        throw new RuntimeException('imagecreatetruecolor failed');
    }
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
    imagealphablending($canvas, true);
    imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        0,
        0,
        $size,
        $size,
        imagesx($source),
        imagesy($source)
    );
    $path = $outDir . DIRECTORY_SEPARATOR . $spec['file'];
    if (!imagepng($canvas, $path, 6)) {
        imagedestroy($canvas);
        throw new RuntimeException('imagepng failed: ' . $path);
    }
    imagedestroy($canvas);
    fwrite(STDOUT, "Wrote {$path}\n");
}

$specs = [
    ['file' => 'icon-192.png', 'size' => 192],
    ['file' => 'icon-512.png', 'size' => 512],
    ['file' => 'icon-maskable-512.png', 'size' => 512],
    ['file' => 'apple-touch-icon.png', 'size' => 180],
    ['file' => 'badge-72.png', 'size' => 72],
];

try {
    foreach ($specs as $spec) {
        write_icon($source, $outDir, $spec);
    }
} catch (Throwable $e) {
    imagedestroy($source);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
imagedestroy($source);
exit(0);
