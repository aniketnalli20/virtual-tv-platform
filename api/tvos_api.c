#define _CRT_SECURE_NO_WARNINGS
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <ctype.h>
#include <errno.h>
#include <sys/stat.h>
#include <windows.h>
#include <bcrypt.h>

static const char *env_str(const char *key) {
    const char *v = getenv(key);
    if (!v || !*v) return NULL;
    return v;
}

static int str_eq(const char *a, const char *b) {
    if (!a || !b) return 0;
    return strcmp(a, b) == 0;
}

static int starts_with(const char *s, const char *prefix) {
    size_t sl = s ? strlen(s) : 0;
    size_t pl = prefix ? strlen(prefix) : 0;
    if (!s || !prefix) return 0;
    if (pl > sl) return 0;
    return strncmp(s, prefix, pl) == 0;
}

static void write_status(int code, const char *reason) {
    if (!reason) reason = "";
    printf("Status: %d %s\r\n", code, reason);
}

static void write_header(const char *k, const char *v) {
    if (!k) return;
    if (!v) v = "";
    printf("%s: %s\r\n", k, v);
}

static void write_json_escape(const char *s) {
    const unsigned char *p = (const unsigned char *)(s ? s : "");
    putchar('"');
    while (*p) {
        unsigned char c = *p++;
        if (c == '\\' || c == '"') {
            putchar('\\');
            putchar((int)c);
        } else if (c == '\b') {
            fputs("\\b", stdout);
        } else if (c == '\f') {
            fputs("\\f", stdout);
        } else if (c == '\n') {
            fputs("\\n", stdout);
        } else if (c == '\r') {
            fputs("\\r", stdout);
        } else if (c == '\t') {
            fputs("\\t", stdout);
        } else if (c < 0x20) {
            printf("\\u%04x", (unsigned int)c);
        } else {
            putchar((int)c);
        }
    }
    putchar('"');
}

static void json_ok_start(void) {
    fputs("{\"ok\":true,\"data\":", stdout);
}

static void json_err(const char *code, const char *message) {
    fputs("{\"ok\":false,\"error\":{\"code\":", stdout);
    write_json_escape(code ? code : "");
    fputs(",\"message\":", stdout);
    write_json_escape(message ? message : "");
    fputs("}}", stdout);
}

static int is_valid_id(const char *s, size_t minLen, size_t maxLen) {
    size_t n;
    if (!s) return 0;
    n = strlen(s);
    if (n < minLen || n > maxLen) return 0;
    for (size_t i = 0; i < n; i++) {
        unsigned char c = (unsigned char)s[i];
        if (!(isalnum(c) || c == '_' || c == '-')) return 0;
    }
    return 1;
}

static int random_bytes(unsigned char *out, size_t len) {
    if (!out || len == 0) return 0;
    if (BCryptGenRandom(NULL, out, (ULONG)len, BCRYPT_USE_SYSTEM_PREFERRED_RNG) == 0) {
        return 1;
    }
    return 0;
}

static void to_hex(const unsigned char *bytes, size_t len, char *outHex, size_t outHexSize) {
    static const char *hex = "0123456789abcdef";
    size_t need = (len * 2) + 1;
    if (!bytes || !outHex || outHexSize < need) return;
    for (size_t i = 0; i < len; i++) {
        outHex[i * 2] = hex[(bytes[i] >> 4) & 0xF];
        outHex[i * 2 + 1] = hex[bytes[i] & 0xF];
    }
    outHex[len * 2] = '\0';
}

static void make_id(const char *prefix, char *out, size_t outSize, size_t randomLenBytes) {
    unsigned char buf[32];
    char hex[65];
    size_t prefLen = prefix ? strlen(prefix) : 0;
    if (!out || outSize == 0) return;
    out[0] = '\0';
    if (randomLenBytes > sizeof(buf)) randomLenBytes = sizeof(buf);
    if (!random_bytes(buf, randomLenBytes)) {
        for (size_t i = 0; i < randomLenBytes; i++) buf[i] = (unsigned char)(rand() & 0xFF);
    }
    to_hex(buf, randomLenBytes, hex, sizeof(hex));
    if (prefLen + strlen(hex) + 1 > outSize) return;
    if (prefLen) memcpy(out, prefix, prefLen);
    memcpy(out + prefLen, hex, strlen(hex) + 1);
}

static char *dup_range(const char *start, const char *end) {
    size_t n;
    char *out;
    if (!start || !end || end < start) return NULL;
    n = (size_t)(end - start);
    out = (char *)malloc(n + 1);
    if (!out) return NULL;
    memcpy(out, start, n);
    out[n] = '\0';
    return out;
}

static char *cookie_get(const char *cookieHeader, const char *name) {
    size_t nameLen;
    const char *p;
    if (!cookieHeader || !name) return NULL;
    nameLen = strlen(name);
    p = cookieHeader;
    while (*p) {
        while (*p == ' ' || *p == '\t' || *p == ';') p++;
        if (!*p) break;
        if (strncmp(p, name, nameLen) == 0 && p[nameLen] == '=') {
            const char *vStart = p + nameLen + 1;
            const char *vEnd = vStart;
            while (*vEnd && *vEnd != ';') vEnd++;
            return dup_range(vStart, vEnd);
        }
        while (*p && *p != ';') p++;
    }
    return NULL;
}

static int ensure_dir(const char *path) {
    DWORD attrs;
    if (!path || !*path) return 0;
    attrs = GetFileAttributesA(path);
    if (attrs != INVALID_FILE_ATTRIBUTES && (attrs & FILE_ATTRIBUTE_DIRECTORY)) return 1;
    if (CreateDirectoryA(path, NULL)) return 1;
    return 0;
}

static void path_join(char *out, size_t outSize, const char *a, const char *b) {
    size_t al;
    if (!out || outSize == 0) return;
    out[0] = '\0';
    if (!a) a = "";
    if (!b) b = "";
    al = strlen(a);
    if (al + 1 + strlen(b) + 1 > outSize) return;
    strcpy(out, a);
    if (al > 0 && a[al - 1] != '\\' && a[al - 1] != '/') strcat(out, "\\");
    strcat(out, b);
}

static char *read_stdin_body(void) {
    const char *cl = env_str("CONTENT_LENGTH");
    long n;
    char *buf;
    size_t readTotal = 0;
    if (!cl) return NULL;
    errno = 0;
    n = strtol(cl, NULL, 10);
    if (errno != 0 || n <= 0 || n > (1024 * 1024)) return NULL;
    buf = (char *)malloc((size_t)n + 1);
    if (!buf) return NULL;
    while (readTotal < (size_t)n) {
        size_t r = fread(buf + readTotal, 1, (size_t)n - readTotal, stdin);
        if (r == 0) break;
        readTotal += r;
    }
    buf[readTotal] = '\0';
    return buf;
}

static char *json_get_string_field(const char *json, const char *field) {
    const char *p;
    size_t fl;
    if (!json || !field) return NULL;
    fl = strlen(field);
    p = json;
    while ((p = strstr(p, "\"")) != NULL) {
        const char *kStart = p + 1;
        const char *kEnd = strstr(kStart, "\"");
        if (!kEnd) break;
        if ((size_t)(kEnd - kStart) == fl && strncmp(kStart, field, fl) == 0) {
            const char *colon = strchr(kEnd + 1, ':');
            const char *v;
            if (!colon) return NULL;
            v = colon + 1;
            while (*v && isspace((unsigned char)*v)) v++;
            if (*v != '"') return NULL;
            v++;
            const char *vEnd = v;
            while (*vEnd && *vEnd != '"') {
                if (*vEnd == '\\' && vEnd[1]) vEnd += 2;
                else vEnd++;
            }
            if (*vEnd != '"') return NULL;
            return dup_range(v, vEnd);
        }
        p = kEnd + 1;
    }
    return NULL;
}

static void iso8601_utc(char *out, size_t outSize) {
    time_t t = time(NULL);
    struct tm g;
    if (!out || outSize < 21) return;
    memset(&g, 0, sizeof(g));
    gmtime_s(&g, &t);
    snprintf(out, outSize, "%04d-%02d-%02dT%02d:%02d:%02d+00:00",
             g.tm_year + 1900, g.tm_mon + 1, g.tm_mday, g.tm_hour, g.tm_min, g.tm_sec);
}

static char *file_read_all(const char *path) {
    FILE *f;
    long n;
    char *buf;
    size_t r;
    if (!path) return NULL;
    f = fopen(path, "rb");
    if (!f) return NULL;
    if (fseek(f, 0, SEEK_END) != 0) {
        fclose(f);
        return NULL;
    }
    n = ftell(f);
    if (n < 0 || n > (1024 * 1024)) {
        fclose(f);
        return NULL;
    }
    if (fseek(f, 0, SEEK_SET) != 0) {
        fclose(f);
        return NULL;
    }
    buf = (char *)malloc((size_t)n + 1);
    if (!buf) {
        fclose(f);
        return NULL;
    }
    r = fread(buf, 1, (size_t)n, f);
    fclose(f);
    buf[r] = '\0';
    return buf;
}

static int file_write_atomic(const char *path, const char *data) {
    char tmpPath[MAX_PATH];
    size_t pl;
    FILE *f;
    if (!path || !data) return 0;
    pl = strlen(path);
    if (pl + 5 >= sizeof(tmpPath)) return 0;
    snprintf(tmpPath, sizeof(tmpPath), "%s.tmp", path);
    f = fopen(tmpPath, "wb");
    if (!f) return 0;
    fwrite(data, 1, strlen(data), f);
    fclose(f);
    if (!MoveFileExA(tmpPath, path, MOVEFILE_REPLACE_EXISTING)) {
        DeleteFileA(tmpPath);
        return 0;
    }
    return 1;
}

typedef struct SessionState {
    int authed;
    char osId[96];
} SessionState;

static void session_init(SessionState *s) {
    if (!s) return;
    s->authed = 0;
    s->osId[0] = '\0';
}

static void session_load(SessionState *s, const char *sessionDir, const char *sid) {
    char filePath[MAX_PATH];
    char fileName[128];
    char *raw;
    if (!s || !sessionDir || !sid) return;
    snprintf(fileName, sizeof(fileName), "tvos_%s.json", sid);
    path_join(filePath, sizeof(filePath), sessionDir, fileName);
    raw = file_read_all(filePath);
    if (!raw) return;
    if (strstr(raw, "\"authed\":true") != NULL) s->authed = 1;
    {
        char *os = json_get_string_field(raw, "osId");
        if (os) {
            if (is_valid_id(os, 10, 80)) {
                strncpy(s->osId, os, sizeof(s->osId) - 1);
                s->osId[sizeof(s->osId) - 1] = '\0';
            }
            free(os);
        }
    }
    free(raw);
}

static void session_save(const SessionState *s, const char *sessionDir, const char *sid) {
    char filePath[MAX_PATH];
    char fileName[128];
    char buf[256];
    if (!s || !sessionDir || !sid) return;
    snprintf(fileName, sizeof(fileName), "tvos_%s.json", sid);
    path_join(filePath, sizeof(filePath), sessionDir, fileName);
    snprintf(buf, sizeof(buf), "{\"authed\":%s,\"osId\":\"%s\"}", s->authed ? "true" : "false", s->osId);
    file_write_atomic(filePath, buf);
}

static void write_set_cookie(const char *name, const char *value, int secure, int maxAgeSeconds) {
    char hdr[512];
    if (!name || !value) return;
    if (maxAgeSeconds < 0) {
        snprintf(hdr, sizeof(hdr), "%s=%s; Path=/; HttpOnly; SameSite=Lax%s", name, value, secure ? "; Secure" : "");
    } else {
        snprintf(hdr, sizeof(hdr), "%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax%s", name, value, maxAgeSeconds, secure ? "; Secure" : "");
    }
    write_header("Set-Cookie", hdr);
}

static int is_https(void) {
    const char *https = getenv("HTTPS");
    const char *scheme = getenv("REQUEST_SCHEME");
    if (https && (str_eq(https, "on") || str_eq(https, "ON") || str_eq(https, "1"))) return 1;
    if (scheme && str_eq(scheme, "https")) return 1;
    return 0;
}

static int require_auth_for_endpoint(const char *endpoint) {
    if (!endpoint) return 0;
    if (str_eq(endpoint, "/apps")) return 1;
    if (str_eq(endpoint, "/channels")) return 1;
    return 0;
}

static void send_apps(void) {
    json_ok_start();
    fputs("{\"apps\":[", stdout);
    fputs("{\"id\":\"live\",\"title\":\"Live TV\",\"icon\":\"\xF0\x9F\x93\xBA\",\"iconName\":\"live_tv\",\"route\":\"live\"},", stdout);
    fputs("{\"id\":\"movies\",\"title\":\"Movies\",\"icon\":\"\xF0\x9F\x8E\xAC\",\"iconName\":\"movie\",\"route\":\"movies\"},", stdout);
    fputs("{\"id\":\"apps\",\"title\":\"Apps\",\"icon\":\"\xF0\x9F\xA7\xA9\",\"iconName\":\"apps\",\"route\":\"apps\"},", stdout);
    fputs("{\"id\":\"settings\",\"title\":\"Settings\",\"icon\":\"\xE2\x9A\x99\xEF\xB8\x8F\",\"iconName\":\"settings\",\"route\":\"settings\"}", stdout);
    fputs("]}}", stdout);
}

static void send_channels_demo(void) {
    json_ok_start();
    fputs("{\"channels\":[", stdout);
    fputs("{\"id\":\"demo-1\",\"name\":\"Demo Channel (HLS)\",\"logoUrl\":null,\"streamUrl\":\"https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8\"},", stdout);
    fputs("{\"id\":\"demo-2\",\"name\":\"Big Buck Bunny (MP4)\",\"logoUrl\":null,\"streamUrl\":\"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4\"}", stdout);
    fputs("]}}", stdout);
}

static void send_movies_demo(void) {
    json_ok_start();
    fputs("{\"movies\":[", stdout);
    fputs("{\"id\":\"movie-1\",\"title\":\"Big Buck Bunny\",\"year\":2008,\"posterUrl\":null,\"streamUrl\":\"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4\"},", stdout);
    fputs("{\"id\":\"movie-2\",\"title\":\"Sintel\",\"year\":2010,\"posterUrl\":null,\"streamUrl\":\"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4\"},", stdout);
    fputs("{\"id\":\"movie-3\",\"title\":\"Tears of Steel\",\"year\":2012,\"posterUrl\":null,\"streamUrl\":\"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4\"}", stdout);
    fputs("]}}", stdout);
}

int main(void) {
    const char *method = getenv("REQUEST_METHOD");
    const char *uri = getenv("REQUEST_URI");
    const char *cookieHeader = getenv("HTTP_COOKIE");
    const char *pinRequired = env_str("TVOS_PIN");
    const char *sessionDir = env_str("TVOS_SESSION_PATH");
    char sessionDirBuf[MAX_PATH];
    char mangoOsId[96];
    char sid[96];
    int secure = is_https();
    char *cookieOs = NULL;
    char *cookieSid = NULL;
    SessionState session;
    const char *endpoint = NULL;
    char endpointBuf[256];
    char healthTs[64];
    int statusCode = 200;
    const char *statusReason = "OK";
    enum {
        RESP_HEALTH,
        RESP_ME,
        RESP_LOGIN_OK,
        RESP_LOGIN_INVALID_PIN,
        RESP_LOGOUT_OK,
        RESP_APPS,
        RESP_CHANNELS,
        RESP_MOVIES,
        RESP_SERVER,
        RESP_UNAUTHORIZED,
        RESP_NOT_FOUND
    } respKind = RESP_NOT_FOUND;

    if (!method) method = "GET";
    if (!uri) uri = "/";

    srand((unsigned int)time(NULL));

    if (!sessionDir) {
        DWORD n = GetTempPathA((DWORD)sizeof(sessionDirBuf), sessionDirBuf);
        if (n == 0 || n >= sizeof(sessionDirBuf)) {
            strcpy(sessionDirBuf, ".");
        } else {
            size_t l = strlen(sessionDirBuf);
            if (l > 0 && (sessionDirBuf[l - 1] == '\\' || sessionDirBuf[l - 1] == '/')) sessionDirBuf[l - 1] = '\0';
        }
        sessionDir = sessionDirBuf;
    }
    ensure_dir(sessionDir);

    cookieOs = cookie_get(cookieHeader, "mango_os_id");
    if (cookieOs && is_valid_id(cookieOs, 10, 80)) {
        strncpy(mangoOsId, cookieOs, sizeof(mangoOsId) - 1);
        mangoOsId[sizeof(mangoOsId) - 1] = '\0';
    } else {
        make_id("mgo_", mangoOsId, sizeof(mangoOsId), 16);
    }

    cookieSid = cookie_get(cookieHeader, "tvos_sid");
    if (cookieSid && is_valid_id(cookieSid, 16, 80)) {
        strncpy(sid, cookieSid, sizeof(sid) - 1);
        sid[sizeof(sid) - 1] = '\0';
    } else {
        make_id("sid_", sid, sizeof(sid), 16);
    }

    session_init(&session);
    session_load(&session, sessionDir, sid);
    if (session.osId[0] == '\0') {
        strncpy(session.osId, mangoOsId, sizeof(session.osId) - 1);
        session.osId[sizeof(session.osId) - 1] = '\0';
    }

    if (starts_with(uri, "/api/")) {
        snprintf(endpointBuf, sizeof(endpointBuf), "/%s", uri + 5);
        endpoint = endpointBuf;
        {
            char *q = strchr(endpointBuf, '?');
            if (q) *q = '\0';
        }
    } else if (starts_with(uri, "/api")) {
        strcpy(endpointBuf, "/");
        endpoint = endpointBuf;
    } else {
        statusCode = 404;
        statusReason = "Not Found";
        respKind = RESP_NOT_FOUND;
        goto respond;
    }

    if (pinRequired && require_auth_for_endpoint(endpoint) && !session.authed) {
        statusCode = 401;
        statusReason = "Unauthorized";
        respKind = RESP_UNAUTHORIZED;
        goto respond;
    }

    if (str_eq(method, "GET") && str_eq(endpoint, "/health")) {
        iso8601_utc(healthTs, sizeof(healthTs));
        respKind = RESP_HEALTH;
        goto respond;
    }

    if (str_eq(method, "GET") && str_eq(endpoint, "/me")) {
        respKind = RESP_ME;
        goto respond;
    }

    if (str_eq(method, "POST") && str_eq(endpoint, "/login")) {
        char *body = read_stdin_body();
        char *pin = json_get_string_field(body ? body : "", "pin");
        if (pinRequired && (!pin || !str_eq(pinRequired, pin))) {
            statusCode = 400;
            statusReason = "Bad Request";
            respKind = RESP_LOGIN_INVALID_PIN;
            if (body) free(body);
            if (pin) free(pin);
            goto respond;
        }
        session.authed = 1;
        respKind = RESP_LOGIN_OK;
        if (body) free(body);
        if (pin) free(pin);
        goto respond;
    }

    if (str_eq(method, "POST") && str_eq(endpoint, "/logout")) {
        session.authed = 0;
        respKind = RESP_LOGOUT_OK;
        goto respond;
    }

    if (str_eq(method, "GET") && str_eq(endpoint, "/apps")) {
        respKind = RESP_APPS;
        goto respond;
    }

    if (str_eq(method, "GET") && str_eq(endpoint, "/channels")) {
        respKind = RESP_CHANNELS;
        goto respond;
    }

    if (str_eq(method, "GET") && str_eq(endpoint, "/movies")) {
        respKind = RESP_MOVIES;
        goto respond;
    }

    if (str_eq(method, "GET") && str_eq(endpoint, "/server")) {
        respKind = RESP_SERVER;
        goto respond;
    }

    statusCode = 404;
    statusReason = "Not Found";
    respKind = RESP_NOT_FOUND;

respond:
    write_status(statusCode, statusReason);
    write_header("Content-Type", "application/json; charset=utf-8");
    write_header("Cache-Control", "no-store");
    write_set_cookie("mango_os_id", mangoOsId, secure, 60 * 60 * 24 * 365);
    write_set_cookie("tvos_sid", sid, secure, 60 * 60 * 24 * 30);
    fputs("\r\n", stdout);

    if (respKind == RESP_UNAUTHORIZED) {
        json_err("UNAUTHORIZED", "Login required");
        goto done;
    }
    if (respKind == RESP_NOT_FOUND) {
        json_err("NOT_FOUND", "Unknown endpoint");
        goto done;
    }
    if (respKind == RESP_LOGIN_INVALID_PIN) {
        json_err("INVALID_PIN", "Invalid PIN");
        goto done;
    }
    if (respKind == RESP_HEALTH) {
        json_ok_start();
        fputs("{\"time\":", stdout);
        write_json_escape(healthTs);
        fputs("}}", stdout);
        goto done;
    }
    if (respKind == RESP_ME) {
        json_ok_start();
        fputs("{\"authenticated\":", stdout);
        fputs((pinRequired && !session.authed) ? "false" : "true", stdout);
        fputs(",\"osId\":", stdout);
        write_json_escape(session.osId[0] ? session.osId : mangoOsId);
        fputs("}}", stdout);
        goto done;
    }
    if (respKind == RESP_LOGIN_OK) {
        json_ok_start();
        fputs("{\"authenticated\":true}}", stdout);
        goto done;
    }
    if (respKind == RESP_LOGOUT_OK) {
        json_ok_start();
        fputs("{\"authenticated\":false}}", stdout);
        goto done;
    }
    if (respKind == RESP_APPS) {
        send_apps();
        goto done;
    }
    if (respKind == RESP_CHANNELS) {
        send_channels_demo();
        goto done;
    }
    if (respKind == RESP_MOVIES) {
        send_movies_demo();
        goto done;
    }
    if (respKind == RESP_SERVER) {
        int dbConfigured = env_str("DB_HOST") && env_str("DB_NAME") && env_str("DB_USER");
        json_ok_start();
        printf("{\"pinEnabled\":%s,\"dbConfigured\":%s,\"dbConnected\":false}}",
               (pinRequired && *pinRequired) ? "true" : "false",
               dbConfigured ? "true" : "false");
        goto done;
    }

done:
    session_save(&session, sessionDir, sid);
    if (cookieOs) free(cookieOs);
    if (cookieSid) free(cookieSid);
    return 0;
}
