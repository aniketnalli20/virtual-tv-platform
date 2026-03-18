<?php
declare(strict_types=1);

$sessionPath = getenv('TVOS_SESSION_PATH');
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
    $dir = str_replace('\\', '/', dirname($scriptName));
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

function pdo_or_null(): ?PDO
{
    $host = env('DB_HOST');
    $name = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS', '');
    $port = env('DB_PORT', '3306');

    if ($host === null || $name === null || $user === null) {
        return null;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $user, $pass ?? '', $options);
    } catch (Throwable) {
        return null;
    }
}

function get_channels(): array
{
    $pdo = pdo_or_null();
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT id, name, logo_url, stream_url FROM channels ORDER BY name ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $channels = [];
        foreach ($rows as $row) {
            $channels[] = [
                'id' => (string)($row['id'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
                'logoUrl' => $row['logo_url'] !== null ? (string)$row['logo_url'] : null,
                'streamUrl' => (string)($row['stream_url'] ?? ''),
            ];
        }
        return $channels;
    }

    return [
        [
            'id' => 'demo-1',
            'name' => 'Demo Channel (HLS)',
            'logoUrl' => null,
            'streamUrl' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        ],
        [
            'id' => 'demo-2',
            'name' => 'Big Buck Bunny (MP4)',
            'logoUrl' => null,
            'streamUrl' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        ],
    ];
}

function get_apps(): array
{
    return [
        ['id' => 'live', 'title' => 'Live TV', 'icon' => '📺', 'iconName' => 'live_tv', 'route' => 'live'],
        ['id' => 'movies', 'title' => 'Movies', 'icon' => '🎬', 'iconName' => 'movie', 'route' => 'movies'],
        ['id' => 'apps', 'title' => 'Apps', 'icon' => '🧩', 'iconName' => 'apps', 'route' => 'apps'],
        ['id' => 'settings', 'title' => 'Settings', 'icon' => '⚙️', 'iconName' => 'settings', 'route' => 'settings'],
    ];
}

function get_movies(): array
{
    $pdo = pdo_or_null();
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT id, title, year, poster_url, video_url FROM movies ORDER BY title ASC');
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $movies = [];
            foreach ($rows as $row) {
                $movies[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'year' => $row['year'] !== null ? (int)$row['year'] : null,
                    'posterUrl' => $row['poster_url'] !== null ? (string)$row['poster_url'] : null,
                    'streamUrl' => (string)($row['video_url'] ?? ''),
                ];
            }
            return $movies;
        } catch (Throwable) {
        }
    }

    return [
        [
            'id' => 'movie-1',
            'title' => 'Big Buck Bunny',
            'year' => 2008,
            'posterUrl' => null,
            'streamUrl' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        ],
        [
            'id' => 'movie-2',
            'title' => 'Sintel',
            'year' => 2010,
            'posterUrl' => null,
            'streamUrl' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
        ],
        [
            'id' => 'movie-3',
            'title' => 'Tears of Steel',
            'year' => 2012,
            'posterUrl' => null,
            'streamUrl' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
        ],
    ];
}

$path = request_path();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (str_starts_with($path, '/api/')) {
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
            require_auth();
            json_response(['ok' => true, 'data' => ['movies' => get_movies()]]);
        }

        if ($method === 'GET' && $endpoint === '/server') {
            require_auth();
            $dbConfigured = env('DB_HOST') !== null && env('DB_NAME') !== null && env('DB_USER') !== null;
            $dbConnected = pdo_or_null() instanceof PDO;
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
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Unexpected error']], 500);
    }
}

$base = base_path();
$platformName = 'QwikStar';
$osName = env('TVOS_NAME', 'Mango OS');
$pinEnabled = env('TVOS_PIN', '') !== '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($platformName . ' · ' . $osName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,600,0,0&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js" integrity="sha256-8L0IDK0orztpITt4dsYMgSuxkZ32l1oU8c4zYBPKv9w=" crossorigin="anonymous"></script>
    <style>
        :root{
            --bg0:#070A12;
            --bg1:#0B1730;
            --card:#0E213F;
            --card2:#0B1A34;
            --text:#E9F0FF;
            --muted:#9FB3D9;
            --accent:#4AD6FF;
            --accent2:#9C5AFF;
            --danger:#FF6B6B;
            --focus:0 0 0 4px rgba(74,214,255,.35);
            --radius:18px;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
            color:var(--text);
            background:radial-gradient(1200px 700px at 10% 10%, rgba(74,214,255,.20), transparent 55%),
                       radial-gradient(1200px 700px at 85% 15%, rgba(156,90,255,.18), transparent 55%),
                       linear-gradient(180deg, var(--bg1), var(--bg0));
            overflow:hidden;
        }
        .shell{
            height:100%;
            display:grid;
            grid-template-rows:auto 1fr;
        }
        .topbar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:18px 26px;
        }
        .brand{
            display:flex;
            gap:12px;
            align-items:center;
            min-width:0;
        }
        .logo{
            width:42px;
            height:42px;
            border-radius:12px;
            background:linear-gradient(135deg, rgba(74,214,255,.95), rgba(156,90,255,.85));
            box-shadow:0 10px 30px rgba(0,0,0,.35);
        }
        .titlewrap{
            min-width:0;
        }
        .title{
            font-weight:800;
            letter-spacing:.2px;
            margin:0;
            line-height:1.1;
            font-size:18px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .subtitle{
            margin:0;
            color:var(--muted);
            font-size:12px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .status{
            display:flex;
            align-items:center;
            gap:14px;
            color:var(--muted);
            font-size:13px;
        }
        .pill{
            padding:8px 10px;
            border-radius:999px;
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.10);
            backdrop-filter:blur(10px);
        }
        .main{
            padding:0 26px 26px;
            display:grid;
            grid-template-columns: 1.05fr .95fr;
            gap:18px;
            min-height:0;
        }
        .panel{
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.12);
            border-radius:var(--radius);
            overflow:hidden;
            min-height:0;
            backdrop-filter: blur(16px);
            box-shadow:0 18px 60px rgba(0,0,0,.35);
        }
        .panelHeader{
            padding:16px 18px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            border-bottom:1px solid rgba(255,255,255,.10);
        }
        .panelHeader h2{
            margin:0;
            font-size:14px;
            letter-spacing:.5px;
            text-transform:uppercase;
            color:var(--muted);
        }
        .grid{
            padding:16px;
            display:grid;
            gap:14px;
            grid-template-columns:repeat(2, minmax(0, 1fr));
        }
        .tile{
            display:flex;
            flex-direction:column;
            gap:10px;
            padding:16px;
            border-radius:18px;
            background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
            border:1px solid rgba(255,255,255,.12);
            min-height:120px;
            cursor:pointer;
            user-select:none;
            outline:none;
            transition:transform .12s ease, border-color .12s ease, background .12s ease;
        }
        .tile:focus{
            box-shadow:var(--focus);
            border-color:rgba(74,214,255,.55);
            transform:translateY(-2px);
        }
        .tile .icon{
            font-size:26px;
        }
        .tile .label{
            font-weight:800;
            font-size:18px;
            line-height:1.15;
        }
        .tile .desc{
            color:var(--muted);
            font-size:13px;
            line-height:1.35;
        }
        .rightBody{
            min-height:0;
            display:grid;
            grid-template-rows: 320px 1fr;
        }
        .player{
            background:linear-gradient(180deg, rgba(0,0,0,.35), rgba(0,0,0,.55));
            border-bottom:1px solid rgba(255,255,255,.10);
            position:relative;
        }
        video{
            width:100%;
            height:100%;
            object-fit:cover;
            background:#000;
        }
        .playerOverlay{
            position:absolute;
            inset:auto 14px 14px 14px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            pointer-events:none;
        }
        .nowPlaying{
            pointer-events:none;
            padding:10px 12px;
            border-radius:14px;
            background:rgba(0,0,0,.45);
            border:1px solid rgba(255,255,255,.12);
            backdrop-filter:blur(10px);
            min-width:0;
        }
        .nowPlayingTitle{
            margin:0;
            font-weight:800;
            font-size:14px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .nowPlayingSub{
            margin:0;
            font-size:12px;
            color:rgba(255,255,255,.72);
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .hint{
            padding:10px 12px;
            border-radius:14px;
            background:rgba(0,0,0,.35);
            border:1px solid rgba(255,255,255,.10);
            color:rgba(255,255,255,.75);
            font-size:12px;
            backdrop-filter:blur(10px);
        }
        .list{
            min-height:0;
            overflow:auto;
        }
        .listItem{
            display:flex;
            align-items:center;
            gap:12px;
            padding:14px 16px;
            border-bottom:1px solid rgba(255,255,255,.08);
            cursor:pointer;
            outline:none;
        }
        .listItem:focus{
            box-shadow:var(--focus);
            background:rgba(74,214,255,.10);
        }
        .avatar{
            width:38px;
            height:38px;
            border-radius:12px;
            background:rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.10);
            display:grid;
            place-items:center;
            overflow:hidden;
            flex:0 0 auto;
        }
        .avatar img{
            width:100%;
            height:100%;
            object-fit:cover;
        }
        .liText{
            min-width:0;
            flex:1 1 auto;
        }
        .liName{
            margin:0;
            font-weight:800;
            font-size:14px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .liMeta{
            margin:0;
            color:var(--muted);
            font-size:12px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .toast{
            position:fixed;
            left:26px;
            bottom:22px;
            background:rgba(0,0,0,.55);
            border:1px solid rgba(255,255,255,.14);
            border-radius:14px;
            padding:12px 14px;
            color:rgba(255,255,255,.88);
            font-size:13px;
            backdrop-filter:blur(12px);
            max-width:520px;
            display:none;
        }
        .modal{
            position:fixed;
            inset:0;
            background:rgba(0,0,0,.72);
            display:none;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .card{
            width:min(520px, 100%);
            border-radius:22px;
            background:linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.06));
            border:1px solid rgba(255,255,255,.14);
            box-shadow:0 30px 120px rgba(0,0,0,.55);
            backdrop-filter:blur(18px);
            padding:18px;
        }
        .card h3{
            margin:0 0 6px;
            font-size:18px;
        }
        .card p{
            margin:0 0 14px;
            color:var(--muted);
            font-size:13px;
            line-height:1.45;
        }
        .row{
            display:flex;
            gap:10px;
        }
        input[type="password"], input[type="text"]{
            width:100%;
            padding:12px 12px;
            border-radius:14px;
            border:1px solid rgba(255,255,255,.18);
            background:rgba(0,0,0,.25);
            color:var(--text);
            outline:none;
            font-size:14px;
        }
        input[type="password"]:focus, input[type="text"]:focus{
            box-shadow:var(--focus);
            border-color:rgba(74,214,255,.60);
        }
        button{
            padding:12px 14px;
            border-radius:14px;
            border:1px solid rgba(255,255,255,.18);
            background:rgba(255,255,255,.10);
            color:var(--text);
            font-weight:800;
            cursor:pointer;
        }
        button.primary{
            background:linear-gradient(135deg, var(--accent), var(--accent2));
            border-color:rgba(255,255,255,.18);
        }
        button:focus{box-shadow:var(--focus); outline:none}
        .danger{color:var(--danger)}
        @media (max-width: 980px){
            body{overflow:auto}
            .main{grid-template-columns:1fr; min-height:auto}
            .rightBody{grid-template-rows: 240px auto}
        }
        .webos{
            height:100%;
            position:relative;
            overflow:hidden;
            --ui-scale:1;
        }
        .webosBg{
            position:absolute;
            inset:-40px;
            background:
                radial-gradient(420px 260px at 18% 30%, rgba(74,214,255,.18), transparent 60%),
                radial-gradient(520px 320px at 84% 24%, rgba(156,90,255,.16), transparent 62%),
                radial-gradient(620px 360px at 55% 78%, rgba(255,255,255,.06), transparent 70%);
            filter: blur(2px);
            opacity:.9;
            pointer-events:none;
        }
        .webos[data-wallpaper="mango"] .webosBg{
            background:
                radial-gradient(520px 320px at 18% 34%, rgba(255,196,76,.18), transparent 60%),
                radial-gradient(520px 320px at 86% 20%, rgba(74,214,255,.14), transparent 62%),
                radial-gradient(720px 420px at 55% 82%, rgba(255,255,255,.06), transparent 70%);
        }
        .webos[data-wallpaper="midnight"] .webosBg{
            background:
                radial-gradient(520px 320px at 20% 30%, rgba(74,214,255,.10), transparent 64%),
                radial-gradient(520px 320px at 80% 22%, rgba(156,90,255,.14), transparent 62%),
                radial-gradient(820px 520px at 55% 86%, rgba(0,0,0,.18), transparent 70%);
            opacity:.75;
        }
        .webos[data-wallpaper="sunset"] .webosBg{
            background:
                radial-gradient(560px 340px at 16% 34%, rgba(255,94,164,.16), transparent 62%),
                radial-gradient(560px 340px at 86% 22%, rgba(255,196,76,.16), transparent 62%),
                radial-gradient(820px 520px at 55% 86%, rgba(255,255,255,.06), transparent 70%);
        }
        .statusbar{
            position:relative;
            z-index:6;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:18px 26px 10px;
            gap:18px;
        }
        .statusLeft{
            display:flex;
            align-items:center;
            gap:12px;
            min-width:0;
        }
        .brandMark{
            width:44px;
            height:44px;
            border-radius:16px;
            background:linear-gradient(135deg, var(--accent), var(--accent2));
            box-shadow:0 14px 40px rgba(0,0,0,.45);
            flex:0 0 auto;
        }
        .statusText{min-width:0}
        .statusTitle{
            margin:0;
            font-weight:800;
            font-size:16px;
            letter-spacing:.2px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .statusSub{
            margin:0;
            color:var(--muted);
            font-size:12px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .statusRight{
            display:flex;
            align-items:center;
            gap:10px;
            color:var(--muted);
            font-size:13px;
            flex:0 0 auto;
        }
        .chip{
            padding:8px 10px;
            border-radius:999px;
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.10);
            backdrop-filter:blur(12px);
            color:rgba(255,255,255,.86);
        }
        .stageWebos{
            position:absolute;
            inset:74px 0 0 0;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:18px 24px 140px;
            z-index:4;
        }
        .appCard{
            width:min(1240px, 94vw);
            height:min(690px, 68vh);
            background:linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.06));
            border:1px solid rgba(255,255,255,.14);
            border-radius:26px;
            backdrop-filter:blur(18px);
            box-shadow:0 18px 70px rgba(0,0,0,.55);
            overflow:hidden;
            display:flex;
            flex-direction:column;
            transform: scale(var(--ui-scale));
            transform-origin: 50% 100%;
            transition: transform .18s ease, filter .18s ease, opacity .18s ease;
        }
        .webos[data-focus="launcher"] .appCard{
            transform: translateY(26px) scale(calc(var(--ui-scale) * .965));
            filter:saturate(.95);
        }
        .appCardHeader{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:16px;
            padding:18px 18px 12px;
            border-bottom:1px solid rgba(255,255,255,.10);
        }
        .appCardTitleWrap{min-width:0}
        .appCardTitle{
            margin:0;
            font-weight:800;
            font-size:18px;
            letter-spacing:.2px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .appCardSub{
            margin:6px 0 0;
            color:var(--muted);
            font-size:13px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .appCardBody{
            flex:1 1 auto;
            min-height:0;
        }
        .liveView{
            height:100%;
            display:grid;
            grid-template-columns: 1.55fr .95fr;
            min-height:0;
        }
        .playerWrap{
            position:relative;
            background:rgba(0,0,0,.55);
            border-right:1px solid rgba(255,255,255,.10);
            min-height:0;
        }
        video{
            width:100%;
            height:100%;
            object-fit:cover;
            background:#000;
        }
        .playerOverlay{
            position:absolute;
            left:16px;
            right:16px;
            bottom:16px;
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:12px;
            pointer-events:none;
        }
        .nowPlaying{
            padding:12px 14px;
            border-radius:18px;
            background:rgba(0,0,0,.46);
            border:1px solid rgba(255,255,255,.12);
            backdrop-filter:blur(14px);
            min-width:0;
        }
        .nowPlayingTitle{
            margin:0;
            font-weight:800;
            font-size:14px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .nowPlayingSub{
            margin:4px 0 0;
            font-size:12px;
            color:rgba(255,255,255,.74);
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .hint{
            padding:10px 12px;
            border-radius:18px;
            background:rgba(0,0,0,.36);
            border:1px solid rgba(255,255,255,.10);
            color:rgba(255,255,255,.78);
            font-size:12px;
            backdrop-filter:blur(12px);
        }
        .channelPane{
            display:flex;
            flex-direction:column;
            min-height:0;
            background:rgba(0,0,0,.18);
        }
        .channelHeader{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding:14px 14px 10px;
            border-bottom:1px solid rgba(255,255,255,.10);
        }
        .channelList{
            min-height:0;
            overflow:auto;
        }
        .chanItem{
            display:flex;
            align-items:center;
            gap:12px;
            padding:14px 14px;
            border-bottom:1px solid rgba(255,255,255,.08);
            cursor:pointer;
            outline:none;
        }
        .chanItem:focus{
            box-shadow:var(--focus);
            background:rgba(74,214,255,.10);
        }
        .chanAvatar{
            width:42px;
            height:42px;
            border-radius:16px;
            background:rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.12);
            display:grid;
            place-items:center;
            overflow:hidden;
            flex:0 0 auto;
        }
        .chanAvatar img{
            width:100%;
            height:100%;
            object-fit:cover;
        }
        .chanText{
            min-width:0;
            flex:1 1 auto;
        }
        .chanName{
            margin:0;
            font-weight:800;
            font-size:14px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .chanMeta{
            margin:4px 0 0;
            color:var(--muted);
            font-size:12px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .placeholderView{
            height:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .placeholderCard{
            width:min(720px, 100%);
            border-radius:28px;
            background:rgba(0,0,0,.22);
            border:1px solid rgba(255,255,255,.12);
            backdrop-filter:blur(14px);
            padding:18px;
            display:flex;
            align-items:center;
            gap:16px;
        }
        .placeholderIcon{
            width:62px;
            height:62px;
            border-radius:22px;
            background:linear-gradient(135deg, rgba(74,214,255,.95), rgba(156,90,255,.85));
            display:grid;
            place-items:center;
            font-size:28px;
            flex:0 0 auto;
        }
        .placeholderText{min-width:0}
        .placeholderTitle{
            margin:0;
            font-weight:800;
            font-size:18px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .placeholderSub{
            margin:6px 0 0;
            color:var(--muted);
            font-size:13px;
            line-height:1.4;
        }
        .material-symbols-rounded{
            font-family:'Material Symbols Rounded';
            font-weight:600;
            font-style:normal;
            font-size:22px;
            line-height:1;
            letter-spacing:normal;
            text-transform:none;
            display:inline-block;
            white-space:nowrap;
            word-wrap:normal;
            direction:ltr;
            -webkit-font-feature-settings:'liga';
            -webkit-font-smoothing:antialiased;
        }
        .viewScroll{
            height:100%;
            overflow:auto;
            padding:18px;
        }
        .browserFrameWrap{
            height: min(520px, calc(68vh - 140px));
            border-radius:22px;
            overflow:hidden;
            border:1px solid rgba(255,255,255,.14);
            background:rgba(0,0,0,.22);
            box-shadow:0 18px 70px rgba(0,0,0,.50);
        }
        #browserFrame{
            width:100%;
            height:100%;
            border:0;
            background:#000;
        }
        .sectionTitle{
            font-weight:800;
            font-size:18px;
            letter-spacing:.2px;
        }
        .appsGrid{
            display:grid;
            grid-template-columns:repeat(3, minmax(0, 1fr));
            gap:12px;
        }
        .appTile{
            display:flex;
            align-items:center;
            gap:12px;
            padding:14px 14px;
            border-radius:20px;
            border:1px solid rgba(255,255,255,.14);
            background:linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.05));
            outline:none;
            cursor:pointer;
            user-select:none;
        }
        .appTile:focus{
            box-shadow:var(--focus);
            border-color:rgba(74,214,255,.60);
        }
        .appTileIcon{
            width:44px;
            height:44px;
            border-radius:16px;
            background:rgba(0,0,0,.22);
            border:1px solid rgba(255,255,255,.12);
            display:grid;
            place-items:center;
            flex:0 0 auto;
        }
        .appTileText{min-width:0}
        .appTileTitle{
            margin:0;
            font-weight:800;
            font-size:14px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .appTileSub{
            margin:4px 0 0;
            color:var(--muted);
            font-size:12px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .settingsList{
            display:flex;
            flex-direction:column;
            gap:10px;
        }
        .setItem{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            padding:14px 14px;
            border-radius:20px;
            border:1px solid rgba(255,255,255,.14);
            background:rgba(0,0,0,.16);
            cursor:pointer;
            outline:none;
        }
        .setItem:focus{
            box-shadow:var(--focus);
            border-color:rgba(74,214,255,.60);
        }
        .setLeft{
            display:flex;
            align-items:center;
            gap:12px;
            min-width:0;
        }
        .setIcon{
            width:44px;
            height:44px;
            border-radius:16px;
            background:rgba(255,255,255,.08);
            border:1px solid rgba(255,255,255,.12);
            display:grid;
            place-items:center;
            flex:0 0 auto;
        }
        .setText{min-width:0}
        .setName{
            margin:0;
            font-weight:800;
            font-size:14px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .setMeta{
            margin:4px 0 0;
            color:var(--muted);
            font-size:12px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .setValue{
            padding:8px 10px;
            border-radius:999px;
            border:1px solid rgba(255,255,255,.12);
            background:rgba(255,255,255,.06);
            color:rgba(255,255,255,.86);
            font-size:12px;
            flex:0 0 auto;
        }
        @media (max-width: 980px){
            .appsGrid{grid-template-columns:repeat(2, minmax(0, 1fr))}
        }
        .launcher{
            position:absolute;
            left:50%;
            bottom:22px;
            transform:translateX(-50%);
            width:min(1060px, 94vw);
            height:106px;
            border-radius:32px;
            background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(0,0,0,.34));
            border:1px solid rgba(255,255,255,.14);
            backdrop-filter:blur(18px);
            box-shadow:0 18px 80px rgba(0,0,0,.65), inset 0 1px 0 rgba(255,255,255,.14);
            display:flex;
            align-items:center;
            gap:14px;
            padding:14px 18px;
            overflow-x:auto;
            overflow-y:hidden;
            z-index:8;
            scroll-behavior:smooth;
            scroll-snap-type:x mandatory;
            position:fixed;
        }
        .launcher::before{
            content:"";
            position:absolute;
            inset:1px;
            border-radius:31px;
            background:
                radial-gradient(420px 120px at 10% 25%, rgba(74,214,255,.12), transparent 60%),
                radial-gradient(420px 120px at 90% 35%, rgba(156,90,255,.10), transparent 62%),
                linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,0));
            pointer-events:none;
        }
        .launcher::after{
            content:"";
            position:absolute;
            inset:0;
            border-radius:32px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
            pointer-events:none;
        }
        .launcher::-webkit-scrollbar{height:8px}
        .launcher::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12); border-radius:999px}
        .appIcon{
            width:82px;
            height:82px;
            border-radius:26px;
            border:1px solid rgba(255,255,255,.14);
            background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:6px;
            cursor:pointer;
            outline:none;
            user-select:none;
            flex:0 0 auto;
            scroll-snap-align:center;
            position:relative;
            box-shadow:0 10px 24px rgba(0,0,0,.30);
            transition: transform .14s ease, border-color .14s ease, background .14s ease, box-shadow .14s ease, filter .14s ease;
        }
        .appIcon:hover{
            border-color:rgba(255,255,255,.20);
            transform: translateY(-2px) scale(1.03);
        }
        .appIcon::after{
            content:"";
            position:absolute;
            left:14px;
            right:14px;
            bottom:8px;
            height:4px;
            border-radius:999px;
            background:linear-gradient(90deg, var(--accent), var(--accent2));
            opacity:0;
            transform:scaleX(.5);
            transform-origin:50% 50%;
            transition: opacity .14s ease, transform .14s ease;
        }
        .appIcon:focus{
            box-shadow:var(--focus);
            border-color:rgba(74,214,255,.60);
            transform: translateY(-3px) scale(1.06);
            filter:saturate(1.05);
        }
        .appIcon:focus::after,
        .appIcon[aria-selected="true"]::after{
            opacity:1;
            transform:scaleX(1);
        }
        .appGlyph{
            width:46px;
            height:46px;
            border-radius:18px;
            display:grid;
            place-items:center;
            font-size:22px;
            color:#061019;
            background:linear-gradient(135deg, var(--accent), var(--accent2));
            box-shadow:0 10px 24px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.25);
            border:1px solid rgba(255,255,255,.14);
        }
        .appLabel{
            font-size:12px;
            font-weight:600;
            color:rgba(255,255,255,.86);
            max-width:76px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .toast{
            bottom:142px;
            z-index:10;
        }
        .modal{z-index:20}
        html[data-reduce-motion="1"] *{
            transition-duration:0ms !important;
            animation-duration:0ms !important;
            scroll-behavior:auto !important;
        }
        @media (max-width: 980px){
            .stageWebos{position:relative; inset:auto; padding:14px 14px 140px}
            .appCard{height:auto; min-height:520px}
            .liveView{grid-template-columns:1fr; grid-template-rows: 280px 1fr}
            .playerWrap{border-right:none; border-bottom:1px solid rgba(255,255,255,.10)}
        }
    </style>
</head>
<body>
    <div class="webos" data-base="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>" data-pin-enabled="<?= $pinEnabled ? '1' : '0' ?>" data-focus="launcher">
        <div class="webosBg" aria-hidden="true"></div>

        <div class="statusbar">
            <div class="statusLeft">
                <div class="brandMark" aria-hidden="true"></div>
                <div class="statusText">
                    <p class="statusTitle"><?= htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="statusSub">OS: <?= htmlspecialchars($osName, ENT_QUOTES, 'UTF-8') ?> · Left/Right: launcher · Up: open · Enter: select</p>
                </div>
            </div>
            <div class="statusRight">
                <div class="chip" id="authPill">Guest</div>
                <div class="chip" id="netStatus">Offline</div>
                <div class="chip" id="clock">--:--</div>
            </div>
        </div>

        <div class="stageWebos">
            <div class="appCard" id="appCard" role="region" aria-label="App card">
                <div class="appCardHeader">
                    <div class="appCardTitleWrap">
                        <p class="appCardTitle" id="cardTitle">Home</p>
                        <p class="appCardSub" id="cardSub">Launcher</p>
                    </div>
                    <div class="chip" id="cardHint">Live TV</div>
                </div>

                <div class="appCardBody" id="cardBody">
                    <div class="liveView" id="liveView">
                        <div class="playerWrap">
                            <video id="video" playsinline controls></video>
                            <div class="playerOverlay">
                                <div class="nowPlaying">
                                    <p class="nowPlayingTitle" id="nowTitle">Nothing playing</p>
                                    <p class="nowPlayingSub" id="nowSub">Select a channel</p>
                                </div>
                                <div class="hint">Enter: play · Backspace: stop · Down: launcher</div>
                            </div>
                        </div>
                        <div class="channelPane">
                            <div class="channelHeader">
                                <div class="chip">Channels</div>
                                <div class="chip" id="channelCount">0</div>
                            </div>
                            <div class="channelList" id="list" role="list"></div>
                        </div>
                    </div>

                    <div class="liveView" id="moviesView" style="display:none;">
                        <div class="playerWrap">
                            <video id="movieVideo" playsinline controls></video>
                            <div class="playerOverlay">
                                <div class="nowPlaying">
                                    <p class="nowPlayingTitle" id="movieNowTitle">Nothing playing</p>
                                    <p class="nowPlayingSub" id="movieNowSub">Select a movie</p>
                                </div>
                                <div class="hint">Enter: play · Backspace: stop · Left: launcher</div>
                            </div>
                        </div>
                        <div class="channelPane">
                            <div class="channelHeader">
                                <div class="chip">Movies</div>
                                <div class="chip" id="movieCount">0</div>
                            </div>
                            <div class="channelList" id="moviesList" role="list"></div>
                        </div>
                    </div>

                    <div class="viewScroll" id="appsView" style="display:none;">
                        <div class="sectionTitle">Apps</div>
                        <div class="row" style="margin-top:12px;">
                            <input type="text" id="appUrlInput" placeholder="Open URL (https://...)" inputmode="url" autocomplete="url" />
                            <button class="primary" id="appUrlBtn">Open</button>
                        </div>
                        <div class="row" style="margin-top:12px;">
                            <input type="text" id="googleQueryInput" placeholder="Google Search" autocomplete="off" />
                            <button class="primary" id="googleSearchBtn">Search</button>
                        </div>
                        <div class="appsGrid" id="appsGrid" role="list" style="margin-top:14px;"></div>
                    </div>

                    <div class="viewScroll" id="browserView" style="display:none;">
                        <div class="sectionTitle">Browser</div>
                        <div class="row" style="margin-top:12px;">
                            <input type="text" id="browserUrlInput" placeholder="Enter URL" inputmode="url" autocomplete="url" />
                            <button id="browserGoBtn" class="primary">Go</button>
                        </div>
                        <div class="row" style="margin-top:10px;">
                            <button id="browserBackBtn">Back</button>
                            <button id="browserForwardBtn">Forward</button>
                            <button id="browserReloadBtn">Reload</button>
                            <button id="browserOpenNewTabBtn">Open in new tab</button>
                            <button id="browserCloseBtn">Close</button>
                        </div>
                        <div class="browserFrameWrap" style="margin-top:12px;">
                            <iframe id="browserFrame" title="Browser" sandbox="allow-forms allow-scripts allow-same-origin allow-popups allow-downloads allow-popups-to-escape-sandbox"></iframe>
                        </div>
                        <div class="setMeta" id="browserHint" style="margin-top:10px;">If a site doesn’t load, it may block embedding. Use “Open in new tab”.</div>
                    </div>

                    <div class="viewScroll" id="settingsView" style="display:none;">
                        <div class="sectionTitle">Settings</div>
                        <div class="settingsList" id="settingsList" role="list" style="margin-top:12px;"></div>
                    </div>

                    <div class="placeholderView" id="placeholderView" style="display:none;">
                        <div class="placeholderCard">
                            <div class="placeholderIcon" id="phIcon" aria-hidden="true">🧩</div>
                            <div class="placeholderText">
                                <p class="placeholderTitle" id="phTitle">Apps</p>
                                <p class="placeholderSub" id="phSub">This is a simple webOS-style UI shell. Live TV plays HLS/MP4 streams.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="launcher" id="launcher" role="list" aria-label="Launcher"></div>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    <div class="modal" id="loginModal" aria-modal="true" role="dialog">
        <div class="card">
            <h3>Login</h3>
            <p>Enter your TV OS PIN to unlock apps and channels.</p>
            <div class="row">
                <input type="password" id="pinInput" inputmode="numeric" autocomplete="one-time-code" placeholder="PIN" />
                <button class="primary" id="pinBtn">Unlock</button>
            </div>
            <p class="danger" id="pinErr" style="display:none;margin-top:12px;">Invalid PIN</p>
        </div>
    </div>

    <script>
        const shell = document.querySelector('.webos');
        const base = shell?.dataset?.base ?? '';
        const pinEnabled = (shell?.dataset?.pinEnabled ?? '0') === '1';

        const launcherEl = document.getElementById('launcher');
        const authPillEl = document.getElementById('authPill');
        const netStatusEl = document.getElementById('netStatus');
        const clockEl = document.getElementById('clock');
        const toastEl = document.getElementById('toast');

        const cardTitleEl = document.getElementById('cardTitle');
        const cardSubEl = document.getElementById('cardSub');
        const cardHintEl = document.getElementById('cardHint');

        const liveViewEl = document.getElementById('liveView');
        const listEl = document.getElementById('list');
        const channelCountEl = document.getElementById('channelCount');
        const videoEl = document.getElementById('video');
        const nowTitleEl = document.getElementById('nowTitle');
        const nowSubEl = document.getElementById('nowSub');

        const moviesViewEl = document.getElementById('moviesView');
        const moviesListEl = document.getElementById('moviesList');
        const movieCountEl = document.getElementById('movieCount');
        const movieVideoEl = document.getElementById('movieVideo');
        const movieNowTitleEl = document.getElementById('movieNowTitle');
        const movieNowSubEl = document.getElementById('movieNowSub');

        const appsViewEl = document.getElementById('appsView');
        const appsGridEl = document.getElementById('appsGrid');
        const appUrlInputEl = document.getElementById('appUrlInput');
        const appUrlBtnEl = document.getElementById('appUrlBtn');
        const googleQueryInputEl = document.getElementById('googleQueryInput');
        const googleSearchBtnEl = document.getElementById('googleSearchBtn');

        const browserViewEl = document.getElementById('browserView');
        const browserFrameEl = document.getElementById('browserFrame');
        const browserUrlInputEl = document.getElementById('browserUrlInput');
        const browserGoBtnEl = document.getElementById('browserGoBtn');
        const browserBackBtnEl = document.getElementById('browserBackBtn');
        const browserForwardBtnEl = document.getElementById('browserForwardBtn');
        const browserReloadBtnEl = document.getElementById('browserReloadBtn');
        const browserOpenNewTabBtnEl = document.getElementById('browserOpenNewTabBtn');
        const browserCloseBtnEl = document.getElementById('browserCloseBtn');

        const settingsViewEl = document.getElementById('settingsView');
        const settingsListEl = document.getElementById('settingsList');

        const placeholderViewEl = document.getElementById('placeholderView');
        const phIconEl = document.getElementById('phIcon');
        const phTitleEl = document.getElementById('phTitle');
        const phSubEl = document.getElementById('phSub');

        const loginModalEl = document.getElementById('loginModal');
        const pinInputEl = document.getElementById('pinInput');
        const pinBtnEl = document.getElementById('pinBtn');
        const pinErrEl = document.getElementById('pinErr');

        const state = {
            apps: [],
            channels: [],
            movies: [],
            server: null,
            focusMode: 'launcher',
            appIndex: 0,
            channelIndex: 0,
            movieIndex: 0,
            appsIndex: 0,
            settingsIndex: 0,
            activeRoute: 'live',
            playing: null,
            hls: null,
            authed: false,
            osId: null,
            settings: null,
            settingsItems: [],
        };

        const SETTINGS_KEY = 'tvos_settings_v1';
        const LAST_PLAY_KEY = 'tvos_last_play_v1';
        const FEATURED_APPS = [
            { id: 'open-url', title: 'Open URL', sub: 'Launch any website', iconName: 'public', action: 'openUrl' },
            { id: 'google-search', title: 'Google Search', sub: 'Search the web', iconName: 'search', action: 'googleSearch' },
            { id: 'google-products', title: 'Google Products', sub: 'Browse Google apps', iconName: 'apps', url: 'https://about.google/products/' },
            { id: 'youtube', title: 'YouTube', sub: 'Video platform', iconName: 'smart_display', url: 'https://www.youtube.com/' },
            { id: 'gmail', title: 'Gmail', sub: 'Email', iconName: 'mail', url: 'https://mail.google.com/' },
            { id: 'drive', title: 'Google Drive', sub: 'Cloud storage', iconName: 'cloud', url: 'https://drive.google.com/' },
            { id: 'maps', title: 'Google Maps', sub: 'Maps', iconName: 'map', url: 'https://maps.google.com/' },
            { id: 'calendar', title: 'Google Calendar', sub: 'Calendar', iconName: 'calendar_month', url: 'https://calendar.google.com/' },
            { id: 'photos', title: 'Google Photos', sub: 'Photos', iconName: 'photo_library', url: 'https://photos.google.com/' },
            { id: 'docs', title: 'Google Docs', sub: 'Documents', iconName: 'description', url: 'https://docs.google.com/' },
            { id: 'wikipedia', title: 'Wikipedia', sub: 'Free encyclopedia', iconName: 'language', url: 'https://www.wikipedia.org/' },
            { id: 'openstreetmap', title: 'OpenStreetMap', sub: 'Free world map', iconName: 'map', url: 'https://www.openstreetmap.org/' },
            { id: 'archive', title: 'Internet Archive', sub: 'Free library of media', iconName: 'inventory_2', url: 'https://archive.org/' },
        ];

        function apiUrl(path) {
            return `${base}${path}`;
        }

        function showToast(message, ms = 2400) {
            toastEl.textContent = message;
            toastEl.style.display = 'block';
            window.clearTimeout(showToast._t);
            showToast._t = window.setTimeout(() => {
                toastEl.style.display = 'none';
            }, ms);
        }

        async function apiFetch(path, options = {}) {
            const res = await fetch(apiUrl(path), {
                method: options.method ?? 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    ...(options.headers ?? {}),
                },
                body: options.body ? JSON.stringify(options.body) : undefined,
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || data.ok !== true) {
                const err = data?.error?.message ?? `Request failed (${res.status})`;
                throw new Error(err);
            }
            return data.data;
        }

        function escapeHtml(s) {
            return String(s)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function escapeAttr(s) {
            return escapeHtml(s).replaceAll('`', '&#096;');
        }

        function materialIcon(name) {
            return `<span class="material-symbols-rounded" aria-hidden="true">${escapeHtml(name)}</span>`;
        }

        function loadSettings() {
            const fallback = {
                autoplayOnHighlight: false,
                rememberLast: true,
                showVideoControls: true,
                hlsLowLatency: true,
                backgroundPlayback: false,
                openLinksInOs: false,
                reduceMotion: false,
                clock24h: true,
                themePreset: 'aqua',
                wallpaperPreset: 'aurora',
                uiScale: 1,
                defaultVolume: 0.9,
            };
            try {
                const raw = localStorage.getItem(SETTINGS_KEY);
                if (!raw) return fallback;
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return fallback;
                return { ...fallback, ...parsed };
            } catch {
                return fallback;
            }
        }

        function saveSettings(next) {
            state.settings = next;
            localStorage.setItem(SETTINGS_KEY, JSON.stringify(next));
            applySettings();
        }

        function applySettings() {
            const s = state.settings ?? loadSettings();
            state.settings = s;
            document.documentElement.dataset.reduceMotion = s.reduceMotion ? '1' : '0';
            shell.dataset.wallpaper = String(s.wallpaperPreset ?? 'aurora');
            shell.style.setProperty('--ui-scale', String(s.uiScale ?? 1));

            const themes = {
                aqua: { accent: '#4AD6FF', accent2: '#9C5AFF', focus: '0 0 0 4px rgba(74,214,255,.35)' },
                purple: { accent: '#9C5AFF', accent2: '#4AD6FF', focus: '0 0 0 4px rgba(156,90,255,.35)' },
                mango: { accent: '#FFC44C', accent2: '#FF5EA4', focus: '0 0 0 4px rgba(255,196,76,.34)' },
                mono: { accent: 'rgba(255,255,255,.90)', accent2: '#4AD6FF', focus: '0 0 0 4px rgba(255,255,255,.18)' },
            };
            const themeKey = String(s.themePreset ?? 'aqua');
            const theme = themes[themeKey] ?? themes.aqua;
            document.documentElement.style.setProperty('--accent', theme.accent);
            document.documentElement.style.setProperty('--accent2', theme.accent2);
            document.documentElement.style.setProperty('--focus', theme.focus);

            if (s.showVideoControls) {
                videoEl.setAttribute('controls', '');
                movieVideoEl.setAttribute('controls', '');
            } else {
                videoEl.removeAttribute('controls');
                movieVideoEl.removeAttribute('controls');
            }
        }

        function stopAllPlayback() {
            if (state.hls) {
                try { state.hls.destroy(); } catch {}
                state.hls = null;
            }
            [videoEl, movieVideoEl].forEach((v) => {
                try { v.pause(); } catch {}
                v.removeAttribute('src');
                try { v.load(); } catch {}
            });
            state.playing = null;
            nowTitleEl.textContent = 'Nothing playing';
            nowSubEl.textContent = 'Select a channel';
            movieNowTitleEl.textContent = 'Nothing playing';
            movieNowSubEl.textContent = 'Select a movie';
        }

        function setFocusMode(mode) {
            state.focusMode = mode;
            shell.dataset.focus = mode;
        }

        function focusLauncher(idx) {
            const el = launcherEl.querySelector(`.appIcon[data-index="${idx}"]`);
            if (el) el.focus();
        }

        function focusChannel(idx) {
            const el = listEl.querySelector(`.chanItem[data-index="${idx}"]`);
            if (el) el.focus();
        }

        function setLauncherSelected(selectedIndex) {
            launcherEl.querySelectorAll('.appIcon[data-index]').forEach((el) => {
                el.setAttribute('aria-selected', el.dataset.index === String(selectedIndex) ? 'true' : 'false');
            });
        }

        function focusMovie(idx) {
            const el = moviesListEl.querySelector(`.chanItem[data-index="${idx}"]`);
            if (el) el.focus();
        }

        function focusAppTile(idx) {
            const el = appsGridEl.querySelector(`.appTile[data-index="${idx}"]`);
            if (el) el.focus();
        }

        function focusSetting(idx) {
            const el = settingsListEl.querySelector(`.setItem[data-index="${idx}"]`);
            if (el) el.focus();
        }

        function appColor(app) {
            const route = app?.route ?? '';
            if (route === 'live') return 'linear-gradient(135deg, rgba(74,214,255,.95), rgba(22,240,180,.70))';
            if (route === 'movies') return 'linear-gradient(135deg, rgba(255,196,76,.95), rgba(255,94,164,.80))';
            if (route === 'apps') return 'linear-gradient(135deg, rgba(156,90,255,.90), rgba(74,214,255,.75))';
            if (route === 'settings') return 'linear-gradient(135deg, rgba(255,255,255,.80), rgba(74,214,255,.65))';
            return 'linear-gradient(135deg, rgba(74,214,255,.95), rgba(156,90,255,.85))';
        }

        function renderLauncher() {
            launcherEl.innerHTML = '';
            state.apps.forEach((app, idx) => {
                const item = document.createElement('div');
                item.className = 'appIcon';
                item.tabIndex = 0;
                item.setAttribute('role', 'listitem');
                item.dataset.index = String(idx);
                item.setAttribute('aria-selected', idx === state.appIndex ? 'true' : 'false');

                const iconHtml = app?.iconName
                    ? `<span class="material-symbols-rounded" aria-hidden="true">${escapeHtml(app.iconName)}</span>`
                    : escapeHtml(app.icon ?? '⬚');

                item.innerHTML = `
                    <div class="appGlyph" style="background:${escapeAttr(appColor(app))}" aria-hidden="true">${iconHtml}</div>
                    <div class="appLabel">${escapeHtml(app.title ?? 'App')}</div>
                `;
                item.addEventListener('click', () => {
                    state.appIndex = idx;
                    openAppFromLauncher(true);
                });
                item.addEventListener('focus', () => {
                    setFocusMode('launcher');
                    state.appIndex = idx;
                    setLauncherSelected(idx);
                    syncActiveApp();
                });
                launcherEl.appendChild(item);
            });
            focusLauncher(state.appIndex);
        }

        function renderChannels() {
            listEl.innerHTML = '';
            channelCountEl.textContent = String(state.channels.length);
            const s = state.settings ?? loadSettings();
            state.settings = s;

            if (state.channels.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'chanItem';
                empty.tabIndex = 0;
                empty.innerHTML = `
                    <div class="chanAvatar" aria-hidden="true">⛔</div>
                    <div class="chanText">
                        <p class="chanName">No channels</p>
                        <p class="chanMeta">Configure MySQL or use demo streams</p>
                    </div>
                `;
                listEl.appendChild(empty);
                return;
            }

            state.channels.forEach((ch, idx) => {
                const item = document.createElement('div');
                item.className = 'chanItem';
                item.tabIndex = 0;
                item.setAttribute('role', 'listitem');
                item.dataset.index = String(idx);
                const avatar = ch.logoUrl ? `<img alt="" src="${escapeAttr(ch.logoUrl)}" />` : materialIcon('tv');
                item.innerHTML = `
                    <div class="chanAvatar" aria-hidden="true">${avatar}</div>
                    <div class="chanText">
                        <p class="chanName">${escapeHtml(ch.name)}</p>
                        <p class="chanMeta">${escapeHtml(ch.streamUrl)}</p>
                    </div>
                `;
                item.addEventListener('click', () => playChannel(idx));
                item.addEventListener('focus', () => {
                    setFocusMode('channels');
                    state.channelIndex = idx;
                    if (s.autoplayOnHighlight) playChannel(idx);
                });
                listEl.appendChild(item);
            });
        }

        function renderMovies() {
            moviesListEl.innerHTML = '';
            movieCountEl.textContent = String(state.movies.length);
            const s = state.settings ?? loadSettings();
            state.settings = s;

            if (state.movies.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'chanItem';
                empty.tabIndex = 0;
                empty.innerHTML = `
                    <div class="chanAvatar" aria-hidden="true">${materialIcon('movie')}</div>
                    <div class="chanText">
                        <p class="chanName">No movies</p>
                        <p class="chanMeta">Configure a movies table or keep demo content</p>
                    </div>
                `;
                moviesListEl.appendChild(empty);
                return;
            }

            state.movies.forEach((mv, idx) => {
                const item = document.createElement('div');
                item.className = 'chanItem';
                item.tabIndex = 0;
                item.setAttribute('role', 'listitem');
                item.dataset.index = String(idx);
                const meta = mv.year ? String(mv.year) : String(mv.streamUrl ?? '');
                item.innerHTML = `
                    <div class="chanAvatar" aria-hidden="true">${materialIcon('theaters')}</div>
                    <div class="chanText">
                        <p class="chanName">${escapeHtml(mv.title ?? '')}</p>
                        <p class="chanMeta">${escapeHtml(meta)}</p>
                    </div>
                `;
                item.addEventListener('click', () => playMovie(idx));
                item.addEventListener('focus', () => {
                    setFocusMode('movies');
                    state.movieIndex = idx;
                    if (s.autoplayOnHighlight) playMovie(idx);
                });
                moviesListEl.appendChild(item);
            });
        }

        function renderApps() {
            appsGridEl.innerHTML = '';
            FEATURED_APPS.forEach((app, idx) => {
                const tile = document.createElement('div');
                tile.className = 'appTile';
                tile.tabIndex = 0;
                tile.setAttribute('role', 'listitem');
                tile.dataset.index = String(idx);
                tile.innerHTML = `
                    <div class="appTileIcon" aria-hidden="true">${materialIcon(app.iconName)}</div>
                    <div class="appTileText">
                        <p class="appTileTitle">${escapeHtml(app.title)}</p>
                        <p class="appTileSub">${escapeHtml(app.sub)}</p>
                    </div>
                `;
                tile.addEventListener('focus', () => {
                    setFocusMode('apps');
                    state.appsIndex = idx;
                });
                tile.addEventListener('click', () => activateAppTile(idx));
                appsGridEl.appendChild(tile);
            });
        }

        function normalizeUrl(url) {
            const u = String(url ?? '').trim();
            if (u === '') return '';
            if (/^https?:\/\//i.test(u)) return u;
            return `https://${u}`;
        }

        function openInOsBrowser(url) {
            const finalUrl = normalizeUrl(url);
            if (finalUrl === '') {
                showToast('Enter a URL', 2200);
                return;
            }
            appsViewEl.style.display = 'none';
            browserViewEl.style.display = 'block';
            browserUrlInputEl.value = finalUrl;
            browserFrameEl.src = finalUrl;
            setFocusMode('browser');
            browserUrlInputEl.focus();
            browserUrlInputEl.select();
        }

        function openExternal(url) {
            const finalUrl = normalizeUrl(url);
            if (finalUrl === '') {
                showToast('Enter a URL', 2200);
                return;
            }
            const s = state.settings ?? loadSettings();
            state.settings = s;
            if (s.openLinksInOs) {
                openInOsBrowser(finalUrl);
                return;
            }
            window.open(finalUrl, '_blank', 'noopener,noreferrer');
        }

        function openGoogleSearch(query) {
            const q = String(query ?? '').trim();
            if (q === '') {
                openExternal('https://www.google.com/');
                return;
            }
            const url = `https://www.google.com/search?q=${encodeURIComponent(q)}`;
            openExternal(url);
        }

        function activateAppTile(idx) {
            const app = FEATURED_APPS[idx];
            if (!app) return;
            if (app.action === 'openUrl') {
                appUrlInputEl.focus();
                appUrlInputEl.select();
                return;
            }
            if (app.action === 'googleSearch') {
                googleQueryInputEl.focus();
                googleQueryInputEl.select();
                return;
            }
            if (app.url) openExternal(app.url);
        }

        function buildSettingsItems() {
            const s = state.settings ?? loadSettings();
            state.settings = s;
            let lastPlaybackValue = 'None';
            try {
                const raw = localStorage.getItem(LAST_PLAY_KEY);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    const title = typeof parsed?.title === 'string' ? parsed.title : '';
                    const type = typeof parsed?.type === 'string' ? parsed.type : '';
                    if (title !== '' && type !== '') {
                        lastPlaybackValue = `${type}: ${title}`;
                    } else if (type !== '') {
                        lastPlaybackValue = type;
                    }
                }
            } catch {
            }
            const items = [
                { type: 'toggle', title: 'Autoplay on highlight', meta: 'Play when selecting items', icon: 'play_circle', key: 'autoplayOnHighlight' },
                { type: 'toggle', title: 'Remember last playback', meta: 'Auto-resume on next load', icon: 'history', key: 'rememberLast' },
                { type: 'toggle', title: 'Video controls', meta: 'Show/hide native controls', icon: 'tune', key: 'showVideoControls' },
                { type: 'toggle', title: 'HLS low latency', meta: 'Hls.js low latency mode', icon: 'speed', key: 'hlsLowLatency' },
                { type: 'toggle', title: 'Background playback', meta: 'Keep playing when switching apps', icon: 'headphones', key: 'backgroundPlayback' },
                { type: 'toggle', title: 'Open links inside OS', meta: 'Use built-in browser (some sites may block embedding)', icon: 'web', key: 'openLinksInOs' },
                { type: 'toggle', title: 'Reduce motion', meta: 'Disable most animations', icon: 'motion_photos_off', key: 'reduceMotion' },
                { type: 'toggle', title: '24-hour clock', meta: 'Clock format', icon: 'schedule', key: 'clock24h' },
                {
                    type: 'cycle',
                    title: 'Theme',
                    meta: 'Accent colors',
                    icon: 'palette',
                    key: 'themePreset',
                    options: [
                        { label: 'Aqua', value: 'aqua' },
                        { label: 'Purple', value: 'purple' },
                        { label: 'Mango', value: 'mango' },
                        { label: 'Mono', value: 'mono' },
                    ],
                },
                {
                    type: 'cycle',
                    title: 'Wallpaper',
                    meta: 'Background style',
                    icon: 'wallpaper',
                    key: 'wallpaperPreset',
                    options: [
                        { label: 'Aurora', value: 'aurora' },
                        { label: 'Mango', value: 'mango' },
                        { label: 'Midnight', value: 'midnight' },
                        { label: 'Sunset', value: 'sunset' },
                    ],
                },
                {
                    type: 'cycle',
                    title: 'UI scale',
                    meta: 'Card size',
                    icon: 'aspect_ratio',
                    key: 'uiScale',
                    options: [
                        { label: '80%', value: 0.8 },
                        { label: '90%', value: 0.9 },
                        { label: '100%', value: 1 },
                        { label: '110%', value: 1.1 },
                        { label: '120%', value: 1.2 },
                    ],
                },
                {
                    type: 'cycle',
                    title: 'Default volume',
                    meta: 'Applies when starting playback',
                    icon: 'volume_up',
                    key: 'defaultVolume',
                    options: [
                        { label: '100%', value: 1 },
                        { label: '75%', value: 0.75 },
                        { label: '50%', value: 0.5 },
                        { label: '25%', value: 0.25 },
                        { label: 'Mute', value: 0 },
                    ],
                },
            ];

            if (state.osId) {
                items.push({ type: 'info', title: 'OS ID', meta: '', icon: 'badge', value: String(state.osId) });
                items.push({ type: 'action', title: 'Copy OS ID', meta: 'Copy to clipboard', icon: 'content_copy', action: 'copy_os_id' });
            }

            if (state.server) {
                items.push(
                    { type: 'info', title: 'PIN lock', meta: '', icon: 'lock', value: state.server.pinEnabled ? 'Enabled' : 'Disabled' },
                    { type: 'info', title: 'Database', meta: '', icon: 'database', value: state.server.dbConnected ? 'Connected' : (state.server.dbConfigured ? 'Not connected' : 'Not configured') },
                );
            }

            items.push(
                { type: 'info', title: 'Last playback', meta: '', icon: 'history_toggle_off', value: lastPlaybackValue },
                { type: 'info', title: 'Local storage', meta: '', icon: 'storage', value: `${localStorage.length} items` },
                { type: 'action', title: 'Check server health', meta: 'Call /api/health', icon: 'health_and_safety', action: 'health' },
                { type: 'action', title: 'Stop playback', meta: 'Stop all video', icon: 'stop_circle', action: 'stop' },
                { type: 'action', title: 'Export settings', meta: 'Copy settings JSON', icon: 'download', action: 'export_settings' },
                { type: 'action', title: 'Import settings', meta: 'Paste settings JSON', icon: 'upload', action: 'import_settings' },
                { type: 'action', title: 'Reset local settings', meta: 'Clear saved settings & last playback', icon: 'restart_alt', action: 'reset' },
            );

            if (pinEnabled) {
                items.push({ type: 'action', title: 'Log out', meta: 'Lock this TV OS', icon: 'logout', action: 'logout' });
            }

            return items;
        }

        function renderSettings() {
            state.settingsItems = buildSettingsItems();
            settingsListEl.innerHTML = '';
            state.settingsItems.forEach((it, idx) => {
                const el = document.createElement('div');
                el.className = 'setItem';
                el.tabIndex = 0;
                el.setAttribute('role', 'listitem');
                el.dataset.index = String(idx);

                let valueText = '';
                if (it.type === 'toggle') {
                    valueText = (state.settings?.[it.key] ?? false) ? 'On' : 'Off';
                } else if (it.type === 'cycle') {
                    const current = state.settings?.[it.key];
                    const match = (it.options ?? []).find((o) => o.value === current);
                    valueText = match ? match.label : 'Change';
                } else if (it.type === 'info') {
                    valueText = it.value ?? '';
                } else {
                    valueText = 'Run';
                }

                el.innerHTML = `
                    <div class="setLeft">
                        <div class="setIcon" aria-hidden="true">${materialIcon(it.icon)}</div>
                        <div class="setText">
                            <p class="setName">${escapeHtml(it.title)}</p>
                            <p class="setMeta">${escapeHtml(it.meta ?? '')}</p>
                        </div>
                    </div>
                    <div class="setValue">${escapeHtml(valueText)}</div>
                `;

                el.addEventListener('focus', () => {
                    setFocusMode('settings');
                    state.settingsIndex = idx;
                });
                el.addEventListener('click', () => activateSetting(idx));
                settingsListEl.appendChild(el);
            });
        }

        async function activateSetting(idx) {
            const it = state.settingsItems[idx];
            if (!it) return;
            if (it.type === 'toggle') {
                const s = state.settings ?? loadSettings();
                const next = { ...s, [it.key]: !s[it.key] };
                saveSettings(next);
                renderSettings();
                showToast(`${it.title}: ${next[it.key] ? 'On' : 'Off'}`);
                return;
            }
            if (it.type === 'cycle') {
                const s = state.settings ?? loadSettings();
                const options = it.options ?? [];
                if (options.length === 0) return;
                const current = s[it.key];
                const currentIdx = options.findIndex((o) => o.value === current);
                const nextOpt = options[(currentIdx + 1 + options.length) % options.length];
                const next = { ...s, [it.key]: nextOpt.value };
                saveSettings(next);
                renderSettings();
                showToast(`${it.title}: ${nextOpt.label}`);
                return;
            }
            if (it.type === 'action') {
                if (it.action === 'stop') {
                    stopAllPlayback();
                    showToast('Stopped');
                    return;
                }
                if (it.action === 'copy_os_id') {
                    const text = state.osId ? String(state.osId) : '';
                    if (text === '') {
                        showToast('OS ID not available', 2200);
                        return;
                    }
                    if (navigator.clipboard?.writeText) {
                        navigator.clipboard.writeText(text)
                            .then(() => showToast('Copied', 1600))
                            .catch(() => showToast('Copy failed', 2200));
                        return;
                    }
                    window.prompt('Copy OS ID', text);
                    return;
                }
                if (it.action === 'export_settings') {
                    const json = JSON.stringify(state.settings ?? loadSettings(), null, 2);
                    if (navigator.clipboard?.writeText) {
                        navigator.clipboard.writeText(json)
                            .then(() => showToast('Settings copied', 1800))
                            .catch(() => showToast('Copy failed', 2200));
                        return;
                    }
                    window.prompt('Copy settings JSON', json);
                    return;
                }
                if (it.action === 'import_settings') {
                    const raw = window.prompt('Paste settings JSON');
                    if (!raw) return;
                    try {
                        const parsed = JSON.parse(raw);
                        const base = loadSettings();
                        const next = { ...base };
                        if (typeof parsed?.autoplayOnHighlight === 'boolean') next.autoplayOnHighlight = parsed.autoplayOnHighlight;
                        if (typeof parsed?.rememberLast === 'boolean') next.rememberLast = parsed.rememberLast;
                        if (typeof parsed?.showVideoControls === 'boolean') next.showVideoControls = parsed.showVideoControls;
                        if (typeof parsed?.hlsLowLatency === 'boolean') next.hlsLowLatency = parsed.hlsLowLatency;
                        if (typeof parsed?.backgroundPlayback === 'boolean') next.backgroundPlayback = parsed.backgroundPlayback;
                        if (typeof parsed?.reduceMotion === 'boolean') next.reduceMotion = parsed.reduceMotion;
                        if (typeof parsed?.clock24h === 'boolean') next.clock24h = parsed.clock24h;
                        if (typeof parsed?.themePreset === 'string') next.themePreset = parsed.themePreset;
                        if (typeof parsed?.wallpaperPreset === 'string') next.wallpaperPreset = parsed.wallpaperPreset;
                        if (typeof parsed?.uiScale === 'number') next.uiScale = parsed.uiScale;
                        if (typeof parsed?.defaultVolume === 'number') next.defaultVolume = parsed.defaultVolume;
                        saveSettings(next);
                        renderSettings();
                        showToast('Imported', 1800);
                    } catch (e) {
                        showToast('Invalid JSON', 2200);
                    }
                    return;
                }
                if (it.action === 'reset') {
                    localStorage.removeItem(SETTINGS_KEY);
                    localStorage.removeItem(LAST_PLAY_KEY);
                    state.settings = loadSettings();
                    applySettings();
                    renderSettings();
                    showToast('Reset complete');
                    return;
                }
                if (it.action === 'logout') {
                    await logout();
                    return;
                }
                if (it.action === 'health') {
                    try {
                        const d = await apiFetch('/api/health');
                        showToast(`OK · ${d.time}`, 2800);
                    } catch (e) {
                        showToast(String(e?.message ?? e), 3200);
                    }
                }
            }
        }

        function syncActiveApp() {
            const app = state.apps[state.appIndex];
            const title = app?.title ?? 'Home';
            const route = app?.route ?? 'apps';
            const prevRoute = state.activeRoute;
            const s = state.settings ?? loadSettings();
            state.settings = s;
            if (prevRoute !== route && !s.backgroundPlayback) {
                stopAllPlayback();
            }
            state.activeRoute = route;

            cardTitleEl.textContent = title;
            cardHintEl.textContent = route === 'live' ? 'Live TV' : route === 'movies' ? 'Movies' : route === 'apps' ? 'Apps' : route === 'settings' ? 'Settings' : 'Home';
            cardSubEl.textContent = route === 'live'
                ? 'Up/Enter: channels · Left: launcher'
                : route === 'movies'
                    ? 'Up/Enter: movies · Left: launcher'
                    : route === 'apps'
                        ? 'Open URLs and web apps'
                        : route === 'settings'
                            ? 'Toggles and diagnostics'
                            : 'Launcher';

            liveViewEl.style.display = route === 'live' ? 'grid' : 'none';
            moviesViewEl.style.display = route === 'movies' ? 'grid' : 'none';
            appsViewEl.style.display = route === 'apps' ? 'block' : 'none';
            settingsViewEl.style.display = route === 'settings' ? 'block' : 'none';
            placeholderViewEl.style.display = (route !== 'live' && route !== 'movies' && route !== 'apps' && route !== 'settings') ? 'flex' : 'none';

            if (route === 'apps') {
                renderApps();
            }
            if (route === 'settings') {
                renderSettings();
            }

            phIconEl.textContent = app?.icon ?? '🧩';
            phTitleEl.textContent = title;
            phSubEl.textContent = route === 'settings'
                ? (pinEnabled ? 'Esc logs out. Backspace stops playback.' : 'PIN lock is disabled. Set TVOS_PIN to enable.')
                : 'This is a webOS-style UI shell.';
        }

        function openAppFromLauncher(moveFocus) {
            syncActiveApp();
            if (!moveFocus) return;
            if (state.activeRoute === 'live') {
                setFocusMode('channels');
                focusChannel(state.channelIndex);
                return;
            }
            if (state.activeRoute === 'movies') {
                setFocusMode('movies');
                focusMovie(state.movieIndex);
                return;
            }
            if (state.activeRoute === 'apps') {
                setFocusMode('apps');
                focusAppTile(state.appsIndex);
                return;
            }
            if (state.activeRoute === 'settings') {
                setFocusMode('settings');
                focusSetting(state.settingsIndex);
                return;
            }
            setFocusMode('launcher');
            focusLauncher(state.appIndex);
        }

        function stopPlayback() {
            stopAllPlayback();
        }

        function isHlsUrl(url) {
            return /\.m3u8(\?.*)?$/i.test(url);
        }

        function playUrl(url, targetVideo = videoEl) {
            stopAllPlayback();
            if (!url) return;
            const s = state.settings ?? loadSettings();
            state.settings = s;
            const vol = Number(s.defaultVolume);
            if (Number.isFinite(vol)) {
                targetVideo.volume = Math.min(1, Math.max(0, vol));
            }
            if (isHlsUrl(url)) {
                if (targetVideo.canPlayType('application/vnd.apple.mpegurl')) {
                    targetVideo.src = url;
                    targetVideo.play().catch(() => {});
                    return;
                }
                if (window.Hls && window.Hls.isSupported()) {
                    state.hls = new window.Hls({ enableWorker: true, lowLatencyMode: !!s.hlsLowLatency });
                    state.hls.loadSource(url);
                    state.hls.attachMedia(targetVideo);
                    state.hls.on(window.Hls.Events.MANIFEST_PARSED, () => {
                        targetVideo.play().catch(() => {});
                    });
                    state.hls.on(window.Hls.Events.ERROR, (_evt, data) => {
                        if (data?.fatal) showToast('Playback error: stream not supported', 3200);
                    });
                    return;
                }
                showToast('HLS not supported in this browser (need Hls.js)', 3200);
                return;
            }
            targetVideo.src = url;
            targetVideo.play().catch(() => {});
        }

        function rememberLastPlayback(payload) {
            try {
                const s = state.settings ?? loadSettings();
                state.settings = s;
                if (!s.rememberLast) return;
                localStorage.setItem(LAST_PLAY_KEY, JSON.stringify({ ...payload, time: Date.now() }));
            } catch {
            }
        }

        function restoreLastPlayback() {
            const s = state.settings ?? loadSettings();
            state.settings = s;
            if (!s.rememberLast) return;
            try {
                const raw = localStorage.getItem(LAST_PLAY_KEY);
                if (!raw) return;
                const last = JSON.parse(raw);
                if (!last || typeof last !== 'object') return;
                if (last.type === 'channel') {
                    const idx = state.channels.findIndex((c) => (c?.id ?? '') === (last.id ?? ''));
                    if (idx >= 0) {
                        const appIdx = state.apps.findIndex((a) => a?.route === 'live');
                        if (appIdx >= 0) state.appIndex = appIdx;
                        syncActiveApp();
                        setFocusMode('channels');
                        focusChannel(idx);
                        playChannel(idx);
                    }
                    return;
                }
                if (last.type === 'movie') {
                    const idx = state.movies.findIndex((m) => (m?.id ?? '') === (last.id ?? ''));
                    if (idx >= 0) {
                        const appIdx = state.apps.findIndex((a) => a?.route === 'movies');
                        if (appIdx >= 0) state.appIndex = appIdx;
                        syncActiveApp();
                        setFocusMode('movies');
                        focusMovie(idx);
                        playMovie(idx);
                    }
                }
            } catch {
            }
        }

        function playChannel(idx) {
            const ch = state.channels[idx];
            if (!ch) return;
            state.channelIndex = idx;
            state.playing = { type: 'channel', id: ch.id, title: ch.name, url: ch.streamUrl };
            nowTitleEl.textContent = ch.name;
            nowSubEl.textContent = ch.streamUrl;
            rememberLastPlayback({ type: 'channel', id: ch.id, title: ch.name });
            playUrl(ch.streamUrl, videoEl);
        }

        function playMovie(idx) {
            const mv = state.movies[idx];
            if (!mv) return;
            state.movieIndex = idx;
            state.playing = { type: 'movie', id: mv.id, title: mv.title, url: mv.streamUrl };
            movieNowTitleEl.textContent = mv.title ?? 'Movie';
            movieNowSubEl.textContent = mv.year ? String(mv.year) : String(mv.streamUrl ?? '');
            rememberLastPlayback({ type: 'movie', id: mv.id, title: mv.title ?? 'Movie' });
            playUrl(mv.streamUrl, movieVideoEl);
        }

        function updateClock() {
            const now = new Date();
            const s = state.settings ?? loadSettings();
            state.settings = s;
            let hours = now.getHours();
            const mm = String(now.getMinutes()).padStart(2, '0');
            if (!s.clock24h) {
                const suffix = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                if (hours === 0) hours = 12;
                clockEl.textContent = `${String(hours).padStart(2, '0')}:${mm} ${suffix}`;
                return;
            }
            clockEl.textContent = `${String(hours).padStart(2, '0')}:${mm}`;
        }

        function updateNetworkStatus() {
            netStatusEl.textContent = navigator.onLine ? 'Online' : 'Offline';
        }

        function setAuthed(authed) {
            state.authed = authed;
            authPillEl.textContent = authed ? 'Unlocked' : (pinEnabled ? 'Locked' : 'Guest');
        }

        async function ensureAuth() {
            const me = await apiFetch('/api/me');
            state.osId = me.osId ?? null;
            setAuthed(!!me.authenticated);
            if (!state.authed && pinEnabled) {
                loginModalEl.style.display = 'flex';
                pinInputEl.focus();
            } else {
                loginModalEl.style.display = 'none';
            }
        }

        async function loginWithPin(pin) {
            pinErrEl.style.display = 'none';
            await apiFetch('/api/login', { method: 'POST', body: { pin } });
            setAuthed(true);
            loginModalEl.style.display = 'none';
            pinInputEl.value = '';
            showToast('Unlocked');
            await loadData();
        }

        async function logout() {
            try {
                await apiFetch('/api/logout', { method: 'POST' });
            } catch {}
            stopPlayback();
            setAuthed(false);
            if (pinEnabled) {
                loginModalEl.style.display = 'flex';
                pinInputEl.focus();
            }
        }

        async function loadData() {
            state.apps = (await apiFetch('/api/apps')).apps ?? [];
            state.channels = (await apiFetch('/api/channels')).channels ?? [];
            state.movies = (await apiFetch('/api/movies')).movies ?? [];
            try {
                state.server = await apiFetch('/api/server');
            } catch {
                state.server = null;
            }
            const liveIdx = state.apps.findIndex((a) => a?.route === 'live');
            state.appIndex = liveIdx >= 0 ? liveIdx : 0;
            state.settings = state.settings ?? loadSettings();
            applySettings();
            renderLauncher();
            renderChannels();
            renderMovies();
            syncActiveApp();
            restoreLastPlayback();
        }

        document.addEventListener('keydown', (e) => {
            if (loginModalEl.style.display === 'flex') {
                if (e.key === 'Enter') pinBtnEl.click();
                if (e.key === 'Escape') pinInputEl.value = '';
                return;
            }

            const activeEl = document.activeElement;
            const activeTag = activeEl ? String(activeEl.tagName ?? '').toUpperCase() : '';
            const isTyping = activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeEl?.isContentEditable === true;
            if (isTyping) {
                if (activeEl === appUrlInputEl && e.key === 'Enter') {
                    openExternal(appUrlInputEl.value);
                    return;
                }
                if (activeEl === googleQueryInputEl && e.key === 'Enter') {
                    openGoogleSearch(googleQueryInputEl.value);
                    return;
                }
                if (activeEl === appUrlInputEl && e.key === 'Escape') {
                    appUrlInputEl.blur();
                    setFocusMode('apps');
                    focusAppTile(state.appsIndex);
                    return;
                }
                if (activeEl === googleQueryInputEl && e.key === 'Escape') {
                    googleQueryInputEl.blur();
                    setFocusMode('apps');
                    focusAppTile(state.appsIndex);
                    return;
                }
                return;
            }

            if (e.key === 'Backspace') {
                e.preventDefault();
                stopPlayback();
                showToast('Stopped');
                return;
            }

            if (e.key === 'Escape') {
                if (pinEnabled) {
                    logout();
                    return;
                }
                if (state.focusMode !== 'launcher') {
                    setFocusMode('launcher');
                    focusLauncher(state.appIndex);
                }
                return;
            }

            if (e.key === 'Enter') {
                if (state.focusMode === 'launcher') {
                    openAppFromLauncher(true);
                    return;
                }
                if (state.focusMode === 'channels') {
                    playChannel(state.channelIndex);
                    return;
                }
                if (state.focusMode === 'movies') {
                    playMovie(state.movieIndex);
                    return;
                }
                if (state.focusMode === 'apps') {
                    activateAppTile(state.appsIndex);
                    return;
                }
                if (state.focusMode === 'settings') {
                    activateSetting(state.settingsIndex);
                }
                return;
            }

            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            e.preventDefault();

            if (state.focusMode === 'launcher') {
                const max = Math.max(0, state.apps.length - 1);
                let next = state.appIndex;
                if (e.key === 'ArrowRight') next = Math.min(max, next + 1);
                if (e.key === 'ArrowLeft') next = Math.max(0, next - 1);
                if (e.key === 'ArrowUp') {
                    openAppFromLauncher(true);
                    return;
                }
                state.appIndex = next;
                syncActiveApp();
                focusLauncher(next);
                launcherEl.querySelector(`.appIcon[data-index="${next}"]`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                return;
            }

            if (state.focusMode === 'channels') {
                if (state.activeRoute !== 'live') return;
                const max = Math.max(0, state.channels.length - 1);
                let next = state.channelIndex;
                if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
                if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
                if (e.key === 'ArrowLeft') {
                    setFocusMode('launcher');
                    focusLauncher(state.appIndex);
                    return;
                }
                if (e.key === 'ArrowRight') return;
                state.channelIndex = next;
                focusChannel(next);
                return;
            }

            if (state.focusMode === 'movies') {
                if (state.activeRoute !== 'movies') return;
                const max = Math.max(0, state.movies.length - 1);
                let next = state.movieIndex;
                if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
                if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
                if (e.key === 'ArrowLeft') {
                    setFocusMode('launcher');
                    focusLauncher(state.appIndex);
                    return;
                }
                if (e.key === 'ArrowRight') return;
                state.movieIndex = next;
                focusMovie(next);
                return;
            }

            if (state.focusMode === 'apps') {
                if (state.activeRoute !== 'apps') return;
                const cols = window.innerWidth <= 980 ? 2 : 3;
                const max = Math.max(0, FEATURED_APPS.length - 1);
                let next = state.appsIndex;
                if (e.key === 'ArrowRight') next = Math.min(max, next + 1);
                if (e.key === 'ArrowLeft') {
                    const isFirstCol = (next % cols) === 0;
                    if (isFirstCol) {
                        setFocusMode('launcher');
                        focusLauncher(state.appIndex);
                        return;
                    }
                    next = Math.max(0, next - 1);
                }
                if (e.key === 'ArrowDown') next = Math.min(max, next + cols);
                if (e.key === 'ArrowUp') next = Math.max(0, next - cols);
                state.appsIndex = next;
                focusAppTile(next);
                return;
            }

            if (state.focusMode === 'settings') {
                if (state.activeRoute !== 'settings') return;
                const max = Math.max(0, state.settingsItems.length - 1);
                let next = state.settingsIndex;
                if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
                if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
                if (e.key === 'ArrowLeft') {
                    setFocusMode('launcher');
                    focusLauncher(state.appIndex);
                    return;
                }
                if (e.key === 'ArrowRight') return;
                state.settingsIndex = next;
                focusSetting(next);
            }
        });

        appUrlBtnEl.addEventListener('click', () => {
            openExternal(appUrlInputEl.value);
        });

        googleSearchBtnEl.addEventListener('click', () => {
            openGoogleSearch(googleQueryInputEl.value);
        });

        function closeOsBrowser() {
            browserFrameEl.removeAttribute('src');
            browserViewEl.style.display = 'none';
            if (state.activeRoute === 'apps') {
                appsViewEl.style.display = 'block';
                setFocusMode('apps');
                focusAppTile(state.appsIndex);
                return;
            }
            setFocusMode('launcher');
            focusLauncher(state.appIndex);
        }

        function navigateBrowserTo(input) {
            const url = normalizeUrl(input);
            if (url === '') {
                showToast('Enter a URL', 2200);
                return;
            }
            browserUrlInputEl.value = url;
            browserFrameEl.src = url;
        }

        browserGoBtnEl.addEventListener('click', () => {
            navigateBrowserTo(browserUrlInputEl.value);
        });
        browserUrlInputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') navigateBrowserTo(browserUrlInputEl.value);
            if (e.key === 'Escape') closeOsBrowser();
        });
        browserBackBtnEl.addEventListener('click', () => {
            try { browserFrameEl.contentWindow.history.back(); } catch {}
        });
        browserForwardBtnEl.addEventListener('click', () => {
            try { browserFrameEl.contentWindow.history.forward(); } catch {}
        });
        browserReloadBtnEl.addEventListener('click', () => {
            try { browserFrameEl.contentWindow.location.reload(); } catch {}
        });
        browserOpenNewTabBtnEl.addEventListener('click', () => {
            openExternal(browserUrlInputEl.value);
        });
        browserCloseBtnEl.addEventListener('click', closeOsBrowser);

        pinBtnEl.addEventListener('click', async () => {
            const pin = (pinInputEl.value ?? '').trim();
            try {
                await loginWithPin(pin);
            } catch (e) {
                pinErrEl.style.display = 'block';
            }
        });

        pinInputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') pinBtnEl.click();
        });

        async function boot() {
            state.settings = loadSettings();
            applySettings();
            updateClock();
            updateNetworkStatus();
            window.setInterval(updateClock, 1000);
            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);

            try {
                await ensureAuth();
                if (state.authed || !pinEnabled) {
                    await loadData();
                }
            } catch (e) {
                showToast(String(e?.message ?? e), 3600);
            }
        }

        boot();
    </script>
</body>
</html>
