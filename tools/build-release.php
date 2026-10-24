<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$directory = $root.'/dist';
if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
    throw new RuntimeException('Cannot create release directory.');
}
$path = $directory.'/laravel-ai-ocr-release.zip';
$zip = new ZipArchive;
if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create release archive.');
}
$files = ['composer.json', 'README.md', 'LICENSE', 'CHANGELOG.md', 'SECURITY.md'];
foreach (['src', 'config', 'database', 'resources', 'routes'] as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && ! $file->isLink()) {
            $files[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}
sort($files);
foreach ($files as $file) {
    $zip->addFile($root.'/'.$file, 'laravel-ai-ocr/'.$file);
    $zip->setMtimeName('laravel-ai-ocr/'.$file, 315532800);
}
$zip->close();
file_put_contents($directory.'/SHA256SUMS', hash_file('sha256', $path).'  '.basename($path).PHP_EOL);
printf("Built %s (%d files)\n", $path, count($files));
