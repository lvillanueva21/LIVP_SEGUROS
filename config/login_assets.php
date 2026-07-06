<?php

declare(strict_types=1);

function loginDefaultAssetsRoot(): string
{
    return dirname(__DIR__) . '/assets/login/default';
}

function loginAssetExtensions(string $type): array
{
    return match ($type) {
        'favicon' => ['ico', 'png', 'svg'],
        'logo' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
        'carrusel' => ['png', 'jpg', 'jpeg', 'webp'],
        default => [],
    };
}

function loginAssetsFor(string $type): array
{
    $directory = loginDefaultAssetsRoot() . '/' . $type;
    $extensions = loginAssetExtensions($type);

    if (!is_dir($directory) || $extensions === []) {
        return [];
    }

    $entries = scandir($directory);
    if (!is_array($entries)) {
        return [];
    }

    $files = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
            continue;
        }

        $path = $directory . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }

        $extension = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions, true)) {
            continue;
        }

        $files[] = [
            'name' => $entry,
            'url' => appRelativeUrl('assets/login/default/' . $type . '/' . rawurlencode($entry)),
        ];
    }

    usort($files, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

    return $files;
}

function loginAssets(): array
{
    $favicons = loginAssetsFor('favicon');
    $logos = loginAssetsFor('logo');

    return [
        'favicon' => $favicons[0]['url'] ?? '',
        'logo' => $logos[0]['url'] ?? '',
        'carrusel' => loginAssetsFor('carrusel'),
    ];
}
