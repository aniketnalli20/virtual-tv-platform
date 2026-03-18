<?php
declare(strict_types=1);
?>
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

