<?php
declare(strict_types=1);
?>
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

