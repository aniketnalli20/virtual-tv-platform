<?php
declare(strict_types=1);

/*
 * API router for /api/* endpoints.
 * Must be included after core/bootstrap.php and core/data.php.
 */

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = request_path();

$endpoint = substr($path, 5);
if ($endpoint === false) {
    $endpoint = '';
}
$endpoint = '/' . ltrim($endpoint, '/');

try {
    if ($method === 'GET' && $endpoint === '/health') {
        json_response(['ok' => true, 'data' => ['time' => gmdate('c')]]);
    }

    if ($method === 'GET' && $endpoint === '/me') {
        $osId = isset($_SESSION['mango_os_id']) ? (string)$_SESSION['mango_os_id'] : null;
        json_response(['ok' => true, 'data' => ['authenticated' => is_authed(), 'osId' => $osId]]);
    }

    if ($method === 'POST' && $endpoint === '/login') {
        $pinRequired = env('TVOS_PIN', '');
        $payload = request_json();
        $pin = isset($payload['pin']) ? (string)$payload['pin'] : '';

        if ($pinRequired !== '' && !hash_equals($pinRequired, $pin)) {
            json_response(['ok' => false, 'error' => ['code' => 'INVALID_PIN', 'message' => 'Invalid PIN']], 400);
        }

        $_SESSION['tv_os_auth'] = true;
        json_response(['ok' => true, 'data' => ['authenticated' => true]]);
    }

    if ($method === 'POST' && $endpoint === '/logout') {
        $_SESSION['tv_os_auth'] = false;
        json_response(['ok' => true, 'data' => ['authenticated' => false]]);
    }

    if ($method === 'GET' && $endpoint === '/apps') {
        require_auth();
        json_response(['ok' => true, 'data' => ['apps' => get_apps()]]);
    }

    if ($method === 'GET' && $endpoint === '/channels') {
        require_auth();
        json_response(['ok' => true, 'data' => ['channels' => get_channels()]]);
    }

    if ($method === 'GET' && $endpoint === '/movies') {
        json_response(['ok' => true, 'data' => ['movies' => get_movies()]]);
    }

    if ($method === 'GET' && $endpoint === '/server') {
        $dbConfigured = env('DB_HOST') !== null && env('DB_NAME') !== null && env('DB_USER') !== null;
        $pdo = pdo_or_null();
        $dbConnected = $pdo instanceof PDO;

        json_response([
            'ok' => true,
            'data' => [
                'pinEnabled' => env('TVOS_PIN', '') !== '',
                'dbConfigured' => $dbConfigured,
                'dbConnected' => $dbConnected,
            ],
        ]);
    }

    json_response(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Unknown endpoint']], 404);
} catch (Throwable) {
    json_response(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Unexpected error']], 500);
}

