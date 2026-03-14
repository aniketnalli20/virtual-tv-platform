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
        ['id' => 'live', 'title' => 'Live TV', 'icon' => '📺', 'route' => 'live'],
        ['id' => 'movies', 'title' => 'Movies', 'icon' => '🎬', 'route' => 'movies'],
        ['id' => 'apps', 'title' => 'Apps', 'icon' => '🧩', 'route' => 'apps'],
        ['id' => 'settings', 'title' => 'Settings', 'icon' => '⚙️', 'route' => 'settings'],
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
            json_response(['ok' => true, 'data' => ['authenticated' => is_authed()]]);
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

        json_response(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Unknown endpoint']], 404);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Unexpected error']], 500);
    }
}

$base = base_path();
$appName = env('TVOS_NAME', 'Virtual TV OS');
$pinEnabled = env('TVOS_PIN', '') !== '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet" />
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
            background:linear-gradient(135deg, rgba(74,214,255,.95), rgba(156,90,255,.85));
            border-color:rgba(255,255,255,.18);
        }
        button:focus{box-shadow:var(--focus); outline:none}
        .danger{color:var(--danger)}
        @media (max-width: 980px){
            body{overflow:auto}
            .main{grid-template-columns:1fr; min-height:auto}
            .rightBody{grid-template-rows: 240px auto}
        }
    </style>
</head>
<body>
    <div class="shell" data-base="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>" data-pin-enabled="<?= $pinEnabled ? '1' : '0' ?>">
        <div class="topbar">
            <div class="brand">
                <div class="logo" aria-hidden="true"></div>
                <div class="titlewrap">
                    <p class="title"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="subtitle">Arrow keys to navigate · Enter to open · Backspace to stop</p>
                </div>
            </div>
            <div class="status">
                <div class="pill" id="netStatus">Offline</div>
                <div class="pill" id="clock">--:--</div>
            </div>
        </div>

        <div class="main">
            <div class="panel" role="region" aria-label="Apps">
                <div class="panelHeader">
                    <h2>Home</h2>
                    <div class="pill" id="authPill">Guest</div>
                </div>
                <div class="grid" id="tiles" role="list"></div>
            </div>

            <div class="panel" role="region" aria-label="Player and Channels">
                <div class="panelHeader">
                    <h2 id="rightTitle">Live TV</h2>
                    <div class="pill" id="rightPill">Channels</div>
                </div>
                <div class="rightBody">
                    <div class="player">
                        <video id="video" playsinline controls></video>
                        <div class="playerOverlay">
                            <div class="nowPlaying">
                                <p class="nowPlayingTitle" id="nowTitle">Nothing playing</p>
                                <p class="nowPlayingSub" id="nowSub">Select a channel</p>
                            </div>
                            <div class="hint">Enter: play · Backspace: stop</div>
                        </div>
                    </div>
                    <div class="list" id="list" role="list"></div>
                </div>
            </div>
        </div>
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
        const shell = document.querySelector('.shell');
        const base = shell?.dataset?.base ?? '';
        const pinEnabled = (shell?.dataset?.pinEnabled ?? '0') === '1';

        const tilesEl = document.getElementById('tiles');
        const listEl = document.getElementById('list');
        const rightTitleEl = document.getElementById('rightTitle');
        const rightPillEl = document.getElementById('rightPill');
        const authPillEl = document.getElementById('authPill');
        const netStatusEl = document.getElementById('netStatus');
        const clockEl = document.getElementById('clock');
        const toastEl = document.getElementById('toast');
        const videoEl = document.getElementById('video');
        const nowTitleEl = document.getElementById('nowTitle');
        const nowSubEl = document.getElementById('nowSub');

        const loginModalEl = document.getElementById('loginModal');
        const pinInputEl = document.getElementById('pinInput');
        const pinBtnEl = document.getElementById('pinBtn');
        const pinErrEl = document.getElementById('pinErr');

        const state = {
            apps: [],
            channels: [],
            focusMode: 'tiles',
            tileIndex: 0,
            listIndex: 0,
            playing: null,
            hls: null,
            authed: false,
        };

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

        function renderTiles() {
            tilesEl.innerHTML = '';
            state.apps.forEach((app, idx) => {
                const tile = document.createElement('div');
                tile.className = 'tile';
                tile.tabIndex = 0;
                tile.setAttribute('role', 'listitem');
                tile.dataset.index = String(idx);
                tile.innerHTML = `
                    <div class="icon" aria-hidden="true">${app.icon}</div>
                    <div class="label">${escapeHtml(app.title)}</div>
                    <div class="desc">${escapeHtml(app.route === 'live' ? 'Pick a channel and play HLS/MP4' : app.route === 'settings' ? 'Session and playback options' : 'Demo screen')}</div>
                `;
                tile.addEventListener('click', () => activateTile(idx));
                tile.addEventListener('focus', () => {
                    state.focusMode = 'tiles';
                    state.tileIndex = idx;
                });
                tilesEl.appendChild(tile);
            });
            focusTile(state.tileIndex);
        }

        function renderList() {
            listEl.innerHTML = '';
            if (state.channels.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'listItem';
                empty.tabIndex = 0;
                empty.innerHTML = `
                    <div class="avatar" aria-hidden="true">⛔</div>
                    <div class="liText">
                        <p class="liName">No channels</p>
                        <p class="liMeta">Configure DB or add demo streams</p>
                    </div>
                `;
                listEl.appendChild(empty);
                return;
            }
            state.channels.forEach((ch, idx) => {
                const item = document.createElement('div');
                item.className = 'listItem';
                item.tabIndex = 0;
                item.setAttribute('role', 'listitem');
                item.dataset.index = String(idx);
                const avatar = ch.logoUrl
                    ? `<img alt="" src="${escapeAttr(ch.logoUrl)}" />`
                    : `📡`;
                item.innerHTML = `
                    <div class="avatar" aria-hidden="true">${avatar}</div>
                    <div class="liText">
                        <p class="liName">${escapeHtml(ch.name)}</p>
                        <p class="liMeta">${escapeHtml(ch.streamUrl)}</p>
                    </div>
                `;
                item.addEventListener('click', () => playChannel(idx));
                item.addEventListener('focus', () => {
                    state.focusMode = 'list';
                    state.listIndex = idx;
                });
                listEl.appendChild(item);
            });
            focusList(state.listIndex);
        }

        function focusTile(idx) {
            const tile = tilesEl.querySelector(`.tile[data-index="${idx}"]`);
            if (tile) tile.focus();
        }

        function focusList(idx) {
            const item = listEl.querySelector(`.listItem[data-index="${idx}"]`);
            if (item) item.focus();
        }

        function activateTile(idx) {
            const app = state.apps[idx];
            if (!app) return;
            state.tileIndex = idx;
            if (app.route === 'live') {
                rightTitleEl.textContent = 'Live TV';
                rightPillEl.textContent = 'Channels';
                state.focusMode = 'list';
                focusList(state.listIndex);
                showToast('Live TV: select a channel to play');
                return;
            }
            if (app.route === 'settings') {
                rightTitleEl.textContent = 'Settings';
                rightPillEl.textContent = 'Session';
                state.focusMode = 'tiles';
                focusTile(state.tileIndex);
                showToast('Settings: Esc to log out (PIN mode)');
                return;
            }
            rightTitleEl.textContent = app.title;
            rightPillEl.textContent = 'Demo';
            state.focusMode = 'tiles';
            focusTile(state.tileIndex);
            showToast(`${app.title}: demo screen`);
        }

        function stopPlayback() {
            if (state.hls) {
                try { state.hls.destroy(); } catch {}
                state.hls = null;
            }
            videoEl.pause();
            videoEl.removeAttribute('src');
            videoEl.load();
            state.playing = null;
            nowTitleEl.textContent = 'Nothing playing';
            nowSubEl.textContent = 'Select a channel';
        }

        function isHlsUrl(url) {
            return /\.m3u8(\?.*)?$/i.test(url);
        }

        function playUrl(url) {
            stopPlayback();
            if (!url) return;
            if (isHlsUrl(url)) {
                if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
                    videoEl.src = url;
                    videoEl.play().catch(() => {});
                    return;
                }
                if (window.Hls && window.Hls.isSupported()) {
                    state.hls = new window.Hls({ enableWorker: true, lowLatencyMode: true });
                    state.hls.loadSource(url);
                    state.hls.attachMedia(videoEl);
                    state.hls.on(window.Hls.Events.MANIFEST_PARSED, () => {
                        videoEl.play().catch(() => {});
                    });
                    state.hls.on(window.Hls.Events.ERROR, (_evt, data) => {
                        if (data?.fatal) {
                            showToast('Playback error: stream not supported', 3200);
                        }
                    });
                    return;
                }
                showToast('HLS not supported in this browser (need Hls.js)', 3200);
                return;
            }
            videoEl.src = url;
            videoEl.play().catch(() => {});
        }

        function playChannel(idx) {
            const ch = state.channels[idx];
            if (!ch) return;
            state.listIndex = idx;
            state.playing = ch;
            nowTitleEl.textContent = ch.name;
            nowSubEl.textContent = ch.streamUrl;
            playUrl(ch.streamUrl);
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

        function updateClock() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            clockEl.textContent = `${hh}:${mm}`;
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
            renderTiles();
            renderList();
        }

        document.addEventListener('keydown', (e) => {
            if (loginModalEl.style.display === 'flex') {
                if (e.key === 'Enter') {
                    pinBtnEl.click();
                }
                if (e.key === 'Escape') {
                    pinInputEl.value = '';
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
                if (pinEnabled) logout();
                return;
            }
            if (e.key === 'Enter') {
                if (state.focusMode === 'tiles') {
                    activateTile(state.tileIndex);
                } else {
                    playChannel(state.listIndex);
                }
                return;
            }
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            e.preventDefault();

            if (state.focusMode === 'tiles') {
                const cols = 2;
                const max = state.apps.length - 1;
                let next = state.tileIndex;
                if (e.key === 'ArrowRight') next = Math.min(max, next + 1);
                if (e.key === 'ArrowLeft') next = Math.max(0, next - 1);
                if (e.key === 'ArrowDown') next = Math.min(max, next + cols);
                if (e.key === 'ArrowUp') next = Math.max(0, next - cols);
                state.tileIndex = next;
                focusTile(next);
                return;
            }

            if (state.focusMode === 'list') {
                const max = Math.max(0, state.channels.length - 1);
                let next = state.listIndex;
                if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
                if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
                if (e.key === 'ArrowLeft') {
                    state.focusMode = 'tiles';
                    focusTile(state.tileIndex);
                    return;
                }
                if (e.key === 'ArrowRight') {
                    return;
                }
                state.listIndex = next;
                focusList(next);
            }
        });

        pinBtnEl.addEventListener('click', async () => {
            const pin = (pinInputEl.value ?? '').trim();
            try {
                await loginWithPin(pin);
            } catch (e) {
                pinErrEl.style.display = 'block';
            }
        });

        pinInputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                pinBtnEl.click();
            }
        });

        async function boot() {
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
