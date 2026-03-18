<?php
declare(strict_types=1);
?>
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

