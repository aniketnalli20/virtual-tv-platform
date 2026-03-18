<?php
declare(strict_types=1);
?>
<div class="statusbar">
    <div class="statusLeft">
        <div class="brandMark" aria-hidden="true"></div>
        <div class="statusText">
            <p class="statusTitle"><?= htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="statusSub">OS: <?= htmlspecialchars($osName, ENT_QUOTES, 'UTF-8') ?> · Left/Right: launcher · Up: open · Tab: switcher · M/Menu: quick menu · Enter: select</p>
        </div>
    </div>
    <div class="statusRight">
        <div class="chip chipBtn" id="authPill" tabindex="0" role="button" aria-label="Authentication">Guest</div>
        <div class="chip chipBtn" id="netStatus" tabindex="0" role="button" aria-label="Network status">Offline</div>
        <div class="chip" id="clock">--:--</div>
        <div class="chip chipBtn" id="menuBtn" tabindex="0" role="button" aria-label="Menu">
            <span class="material-symbols-rounded" aria-hidden="true">menu</span>
            Menu
        </div>
    </div>
</div>
