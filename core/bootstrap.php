<?php
declare(strict_types=1);

/*
 * Core bootstrap:
 * - Loads env config
 * - Initializes session storage
 * - Creates a stable OS ID cookie for the UI session
 * - Exposes small HTTP helpers used by both UI and API
 */

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $scriptName = str_replace('\\', '/', $scriptName);
    if (!str_ends_with($scriptName, '.php')) {
        return '';
    }
    $dir = dirname($scriptName);
    return $dir === '/' ? '' : rtrim($dir, '/');
}

function request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) {
        return '/';
    }
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
        if ($path === false || $path === '') {
            return '/';
        }
    }
    return $path[0] === '/' ? $path : '/' . $path;
}

function is_authed(): bool
{
    $pinRequired = env('TVOS_PIN', '');
    if ($pinRequired === '') {
        return true;
    }
    return ($_SESSION['tv_os_auth'] ?? false) === true;
}

function require_auth(): void
{
    if (!is_authed()) {
        json_response(['ok' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Login required']], 401);
    }
}

$sessionPath = env('TVOS_SESSION_PATH');
if (!is_string($sessionPath) || $sessionPath === '') {
    $sessionPath = sys_get_temp_dir();
}
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
@session_save_path($sessionPath);
session_start();

$cookieName = 'mango_os_id';
$osId = '';
if (isset($_COOKIE[$cookieName]) && is_string($_COOKIE[$cookieName])) {
    $candidate = (string)$_COOKIE[$cookieName];
    if (preg_match('/^[a-zA-Z0-9_-]{10,80}$/', $candidate) === 1) {
        $osId = $candidate;
    }
}
if ($osId === '') {
    $osId = 'mgo_' . bin2hex(random_bytes(16));
}
$_SESSION['mango_os_id'] = $osId;
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie($cookieName, $osId, [
    'expires' => time() + (60 * 60 * 24 * 365),
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

return [
    'base' => base_path(),
    'platformName' => 'QwikStar',
    'osName' => env('TVOS_NAME', 'Mango OS'),
    'pinEnabled' => env('TVOS_PIN', '') !== '',
];
