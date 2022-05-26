<?php

// _bootstrap.php: Base code for all other PHP scripts
error_reporting(-1);

function sgg_get_data_path(?string $relativePath = null): string
{
    $basePath = dirname(__DIR__) . '/data';
    if (!$relativePath) {
        return $basePath;
    }

    return $basePath . '/' . ltrim($relativePath, '/');
}

function sgg_data_load(string $filename): array
{
    return sgg_json_decode_file(sgg_get_data_path($filename));
}

function sgg_data_save(string $filename, array $data, bool $minify = true): void
{
    sgg_json_encode($data, $minify, sgg_get_data_path($filename));
}

function sgg_json_decode(string $json): array
{
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function sgg_json_decode_file(string $fileName): array
{
    if (!file_exists($fileName)) {
        throw new RuntimeException("File '$fileName' does not exist");
    }

    return sgg_json_decode(file_get_contents($fileName));
}

function sgg_json_prettify_file(string $fileName): void
{
    sgg_json_encode(sgg_json_decode_file($fileName), false, $fileName);
}

function sgg_json_minify_file(string $fileName): void
{
    sgg_json_encode(sgg_json_decode_file($fileName), true, $fileName);
}

function sgg_json_encode(array $data, bool $minify = true, ?string $outputFile = null): string
{
    $jsonFlags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if (!$minify) {
        $jsonFlags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    }
    $json = json_encode($data, $jsonFlags);
    if ($outputFile !== null) {
        sgg_create_file_dir_tree($outputFile);
        file_put_contents($outputFile, $json);
    }

    return $json;
}

function sgg_create_file_dir_tree(string $fileName): void
{
    $dir = dirname($fileName);
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
    }
}

function sgg_json_files_in_dir_tree(?string $relativeDataPath = null, bool $ignoreMinified = true): array
{
    $dir = sgg_get_data_path($relativeDataPath);
    if (!is_dir($dir)) {
        throw new RuntimeException("Directory '$dir' does not exist");
    }

    /** @var SplFileInfo[] $iterator */
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY
    );
    $found = [];

    foreach ($iterator as $path) {
        if ($path->isDir()) {
            continue;
        }
        $file = (string) $path;
        if (!str_ends_with($file, '.json')) {
            continue;
        }
        if ($ignoreMinified && str_ends_with($file, '.min.json')) {
            continue;
        }
        $found[] = $file;
    }

    return $found;
}