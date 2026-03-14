Build a PHP 8+ web app with folders: api/, core/, assets/, views/.

UI must mimic LG webOS launcher with bottom horizontal app cards and keyboard navigation.

Frontend stack: HTML5, CSS, Vanilla JS only. Allow hls.js for HLS playback.

Config must come only from env vars: TVOS_NAME, TVOS_PIN, TVOS_SESSION_PATH, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS. Never hardcode secrets.

If TVOS_PIN exists, require login for /api/apps and /api/channels using PHP sessions.

API responses must follow: success {"ok":true,"data":{}} or error {"ok":false,"error":{"code":"","message":""}}.

Required endpoints: /api/health, /api/me, /api/login, /api/logout, /api/apps, /api/channels.

If DB vars exist, load channels from MySQL table channels(id,name,logo_url,stream_url); otherwise use demo streams.


Playback: MP4 native, .m3u8 via hls.js fallback.



Security: validate inputs, escape output, prepared SQL queries.