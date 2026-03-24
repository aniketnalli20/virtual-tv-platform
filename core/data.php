<?php
declare(strict_types=1);

/*
 * Data access helpers for the TV OS.
 * Uses MySQL when DB_* env vars exist; otherwise falls back to demo content.
 */

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
    $iconBase = 'https://unpkg.com/@tabler/icons@latest/icons/outline';
    return [
        ['id' => 'live', 'title' => 'Live TV', 'iconUrl' => $iconBase . '/device-tv.svg', 'icon' => '📺', 'iconName' => 'live_tv', 'route' => 'live'],
        ['id' => 'media', 'title' => 'Media Player', 'iconUrl' => $iconBase . '/player-play.svg', 'icon' => '▶️', 'iconName' => 'play_circle', 'route' => 'movies'],
        ['id' => 'browser', 'title' => 'Web Browser', 'iconUrl' => $iconBase . '/world.svg', 'icon' => '🌐', 'iconName' => 'language', 'route' => 'browser'],
        ['id' => 'mirroring', 'title' => 'Screen Mirroring', 'iconUrl' => $iconBase . '/cast.svg', 'icon' => '📡', 'iconName' => 'cast', 'route' => 'mirroring'],
        ['id' => 'store', 'title' => 'App Store', 'iconUrl' => $iconBase . '/shopping-bag.svg', 'icon' => '🛍️', 'iconName' => 'store', 'route' => 'apps'],
        ['id' => 'files', 'title' => 'File Manager', 'iconUrl' => $iconBase . '/folder.svg', 'icon' => '📁', 'iconName' => 'folder', 'route' => 'files'],
        ['id' => 'notifications', 'title' => 'Notifications', 'iconUrl' => $iconBase . '/bell.svg', 'icon' => '🔔', 'iconName' => 'notifications', 'route' => 'notifications'],
        ['id' => 'input', 'title' => 'Input Source', 'iconUrl' => $iconBase . '/plug.svg', 'icon' => '🧷', 'iconName' => 'input', 'route' => 'input'],
        ['id' => 'settings', 'title' => 'Settings', 'iconUrl' => $iconBase . '/settings.svg', 'icon' => '⚙️', 'iconName' => 'settings', 'route' => 'settings'],
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
