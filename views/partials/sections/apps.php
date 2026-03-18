<?php
declare(strict_types=1);
?>
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

