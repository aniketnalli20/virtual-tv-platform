<?php
declare(strict_types=1);

/*
 * Main TV OS UI shell (HTML).
 * Expects $tvos array from core/bootstrap.php.
 */

$base = (string)($tvos['base'] ?? '');
$platformName = (string)($tvos['platformName'] ?? 'QwikStar');
$osName = (string)($tvos['osName'] ?? 'Mango OS');
$pinEnabled = (bool)($tvos['pinEnabled'] ?? false);
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
    <link rel="stylesheet" href="<?= htmlspecialchars($base . '/assets/os.css', ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body>
    <div class="webos" data-base="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>" data-pin-enabled="<?= $pinEnabled ? '1' : '0' ?>" data-focus="launcher">
        <div class="webosBg" aria-hidden="true"></div>

        <?php require __DIR__ . '/partials/statusbar.php'; ?>
        <?php require __DIR__ . '/partials/app_card.php'; ?>
        <?php require __DIR__ . '/partials/overview.php'; ?>
        <?php require __DIR__ . '/partials/quick_menu.php'; ?>
        <?php require __DIR__ . '/partials/launcher.php'; ?>
    </div>

    <?php require __DIR__ . '/partials/toast.php'; ?>
    <?php require __DIR__ . '/partials/login_modal.php'; ?>

    <script src="<?= htmlspecialchars($base . '/assets/os.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>

