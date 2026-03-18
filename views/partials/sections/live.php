<?php
declare(strict_types=1);
?>
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

