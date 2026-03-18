<?php
declare(strict_types=1);
?>
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
            <?php require __DIR__ . '/sections/live.php'; ?>
            <?php require __DIR__ . '/sections/movies.php'; ?>
            <?php require __DIR__ . '/sections/apps.php'; ?>
            <?php require __DIR__ . '/sections/browser.php'; ?>
            <?php require __DIR__ . '/sections/settings.php'; ?>
            <?php require __DIR__ . '/sections/placeholder.php'; ?>
        </div>
    </div>
</div>

