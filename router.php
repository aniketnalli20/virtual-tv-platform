<?php
declare(strict_types=1);

/*
 * PHP built-in server router:
 * - Serves existing files directly
 * - Routes everything else (including /api/*) to index.php
 */

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uriPath) || $uriPath === '') {
    $uriPath = '/';
}

$candidate = realpath(__DIR__ . $uriPath);
if ($candidate !== false && str_starts_with($candidate, realpath(__DIR__) ?: __DIR__) && is_file($candidate)) {
    return false;
}

require __DIR__ . '/index.php';

