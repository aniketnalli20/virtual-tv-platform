# Virtual TV Platform (WebOS-style UI)

Simple PHP 8 webapp that mimics an LG webOS-style launcher and “app card” experience, with a Live TV player (HLS/MP4) and JSON REST endpoints.

## Requirements

- PHP 8+
- A web server (XAMPP/Apache) or PHP built-in dev server
- Optional: MySQL (only if you want channels from a database)

## Run

### Option A: XAMPP / Apache

Place this folder under your web root and open:

- `http://localhost/virtual-tv-platform/`

### Option B: PHP built-in server

From the project directory:

```bash
php -S 127.0.0.1:8000 -t .
```

Open:

- `http://127.0.0.1:8000/`

## Controls (Keyboard Remote)

- Left/Right: move across the bottom launcher
- Up: open the selected app (focus channels in Live TV)
- Enter: open / play the selected channel
- Backspace: stop playback
- Esc: log out (only when PIN lock is enabled)

## Environment Variables

All configuration is via environment variables (no hard-coded secrets).

- `TVOS_NAME` (optional): display name (default: `Virtual TV OS`)
- `TVOS_PIN` (optional): if set (non-empty), enables PIN lock and requires login for `/api/apps` and `/api/channels`
- `TVOS_SESSION_PATH` (optional): session storage directory (useful if PHP cannot write to the default temp path)

### MySQL (optional)

If all of these are set, channels are loaded from MySQL; otherwise demo streams are used.

- `DB_HOST`
- `DB_PORT` (optional, default: `3306`)
- `DB_NAME`
- `DB_USER`
- `DB_PASS` (optional)

## Database Schema (Optional)

Create a `channels` table:

```sql
CREATE TABLE channels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  logo_url TEXT NULL,
  stream_url TEXT NOT NULL
);
```

Example rows:

```sql
INSERT INTO channels (name, logo_url, stream_url) VALUES
('Demo Channel (HLS)', NULL, 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8'),
('Big Buck Bunny (MP4)', NULL, 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4');
```

## API (JSON)

All endpoints return JSON with the shape:

- Success: `{"ok": true, "data": {...}}`
- Error: `{"ok": false, "error": {"code": "...", "message": "..."}}`

Endpoints:

- `GET /api/health`
- `GET /api/me`
- `POST /api/login` body: `{"pin":"1234"}` (only required if `TVOS_PIN` is set)
- `POST /api/logout`
- `GET /api/apps`
- `GET /api/channels`

## Playback Notes

- HLS playback uses open-source `hls.js` in browsers that do not natively support `.m3u8`.
- MP4 playback uses the browser’s native video support.

