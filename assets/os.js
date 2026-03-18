/* Mango OS UI (TV remote friendly) - Vanilla JS */

const shell = document.querySelector('.webos');
const base = shell?.dataset?.base ?? '';
const pinEnabled = (shell?.dataset?.pinEnabled ?? '0') === '1';

const launcherEl = document.getElementById('launcher');
const overviewEl = document.getElementById('overview');
const overviewRowEl = document.getElementById('overviewRow');
const menuBtnEl = document.getElementById('menuBtn');
const quickMenuEl = document.getElementById('quickMenu');
const quickMenuScrimEl = document.getElementById('quickMenuScrim');
const quickMenuListEl = document.getElementById('quickMenuList');
const authPillEl = document.getElementById('authPill');
const netStatusEl = document.getElementById('netStatus');
const clockEl = document.getElementById('clock');
const toastEl = document.getElementById('toast');

const cardTitleEl = document.getElementById('cardTitle');
const cardSubEl = document.getElementById('cardSub');
const cardHintEl = document.getElementById('cardHint');

const liveViewEl = document.getElementById('liveView');
const listEl = document.getElementById('list');
const channelCountEl = document.getElementById('channelCount');
const videoEl = document.getElementById('video');
const nowTitleEl = document.getElementById('nowTitle');
const nowSubEl = document.getElementById('nowSub');

const moviesViewEl = document.getElementById('moviesView');
const moviesListEl = document.getElementById('moviesList');
const movieCountEl = document.getElementById('movieCount');
const movieVideoEl = document.getElementById('movieVideo');
const movieNowTitleEl = document.getElementById('movieNowTitle');
const movieNowSubEl = document.getElementById('movieNowSub');

const appsViewEl = document.getElementById('appsView');
const appsGridEl = document.getElementById('appsGrid');
const appUrlInputEl = document.getElementById('appUrlInput');
const appUrlBtnEl = document.getElementById('appUrlBtn');
const googleQueryInputEl = document.getElementById('googleQueryInput');
const googleSearchBtnEl = document.getElementById('googleSearchBtn');

const browserViewEl = document.getElementById('browserView');
const browserFrameEl = document.getElementById('browserFrame');
const browserUrlInputEl = document.getElementById('browserUrlInput');
const browserGoBtnEl = document.getElementById('browserGoBtn');
const browserBackBtnEl = document.getElementById('browserBackBtn');
const browserForwardBtnEl = document.getElementById('browserForwardBtn');
const browserReloadBtnEl = document.getElementById('browserReloadBtn');
const browserOpenNewTabBtnEl = document.getElementById('browserOpenNewTabBtn');
const browserCloseBtnEl = document.getElementById('browserCloseBtn');

const settingsViewEl = document.getElementById('settingsView');
const settingsListEl = document.getElementById('settingsList');

const placeholderViewEl = document.getElementById('placeholderView');
const phIconEl = document.getElementById('phIcon');
const phTitleEl = document.getElementById('phTitle');
const phSubEl = document.getElementById('phSub');

const loginModalEl = document.getElementById('loginModal');
const pinInputEl = document.getElementById('pinInput');
const pinBtnEl = document.getElementById('pinBtn');
const pinErrEl = document.getElementById('pinErr');

const state = {
    apps: [],
    channels: [],
    movies: [],
    server: null,
    focusMode: 'launcher',
    appIndex: 0,
    overviewIndex: 0,
    menuIndex: 0,
    channelIndex: 0,
    movieIndex: 0,
    appsIndex: 0,
    settingsIndex: 0,
    activeRoute: 'live',
    playing: null,
    hls: null,
    authed: false,
    osId: null,
    settings: null,
    settingsItems: [],
};

const SETTINGS_KEY = 'tvos_settings_v1';
const LAST_PLAY_KEY = 'tvos_last_play_v1';
const FEATURED_APPS = [
    { id: 'open-url', title: 'Open URL', sub: 'Launch any website', iconName: 'public', action: 'openUrl' },
    { id: 'google-search', title: 'Google Search', sub: 'Search the web', iconName: 'search', action: 'googleSearch' },
    { id: 'google-products', title: 'Google Products', sub: 'Browse Google apps', iconName: 'apps', url: 'https://about.google/products/' },
    { id: 'youtube', title: 'YouTube', sub: 'Video platform', iconName: 'smart_display', url: 'https://www.youtube.com/' },
    { id: 'peertube', title: 'PeerTube', sub: 'Open source video (fediverse)', iconName: 'takeout_dining', url: 'https://joinpeertube.org/' },
    { id: 'jellyfin-demo', title: 'Jellyfin Demo', sub: 'Open source media server UI', iconName: 'movie', url: 'https://demo.jellyfin.org/stable/web/' },
    { id: 'openverse', title: 'Openverse', sub: 'Creative Commons media search', iconName: 'search', url: 'https://openverse.org/' },
    { id: 'radio-browser', title: 'Radio Browser', sub: 'Free internet radio directory', iconName: 'radio', url: 'https://www.radio-browser.info/' },
    { id: 'jamendo', title: 'Jamendo', sub: 'Free music streaming', iconName: 'music_note', url: 'https://www.jamendo.com/' },
    { id: 'gmail', title: 'Gmail', sub: 'Email', iconName: 'mail', url: 'https://mail.google.com/' },
    { id: 'drive', title: 'Google Drive', sub: 'Cloud storage', iconName: 'cloud', url: 'https://drive.google.com/' },
    { id: 'maps', title: 'Google Maps', sub: 'Maps', iconName: 'map', url: 'https://maps.google.com/' },
    { id: 'calendar', title: 'Google Calendar', sub: 'Calendar', iconName: 'calendar_month', url: 'https://calendar.google.com/' },
    { id: 'photos', title: 'Google Photos', sub: 'Photos', iconName: 'photo_library', url: 'https://photos.google.com/' },
    { id: 'docs', title: 'Google Docs', sub: 'Documents', iconName: 'description', url: 'https://docs.google.com/' },
    { id: 'wikipedia', title: 'Wikipedia', sub: 'Free encyclopedia', iconName: 'language', url: 'https://www.wikipedia.org/' },
    { id: 'openstreetmap', title: 'OpenStreetMap', sub: 'Free world map', iconName: 'map', url: 'https://www.openstreetmap.org/' },
    { id: 'archive', title: 'Internet Archive', sub: 'Free library of media', iconName: 'inventory_2', url: 'https://archive.org/' },
];

function apiUrl(path) {
    return `${base}${path}`;
}

function showToast(message, ms = 2400) {
    toastEl.textContent = message;
    toastEl.style.display = 'block';
    window.clearTimeout(showToast._t);
    showToast._t = window.setTimeout(() => {
        toastEl.style.display = 'none';
    }, ms);
}

async function apiFetch(path, options = {}) {
    const res = await fetch(apiUrl(path), {
        method: options.method ?? 'GET',
        headers: {
            'Content-Type': 'application/json',
            ...(options.headers ?? {}),
        },
        body: options.body ? JSON.stringify(options.body) : undefined,
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data || data.ok !== true) {
        const err = data?.error?.message ?? `Request failed (${res.status})`;
        throw new Error(err);
    }
    return data.data;
}

function escapeHtml(s) {
    return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttr(s) {
    return escapeHtml(s).replaceAll('`', '&#096;');
}

function materialIcon(name) {
    return `<span class="material-symbols-rounded" aria-hidden="true">${escapeHtml(name)}</span>`;
}

function loadSettings() {
    const fallback = {
        autoplayOnHighlight: false,
        rememberLast: true,
        showVideoControls: true,
        hlsLowLatency: true,
        backgroundPlayback: false,
        openLinksInOs: false,
        reduceMotion: false,
        clock24h: true,
        themePreset: 'aqua',
        wallpaperPreset: 'aurora',
        uiScale: 1,
        defaultVolume: 0.9,
    };
    try {
        const raw = localStorage.getItem(SETTINGS_KEY);
        if (!raw) return fallback;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return fallback;
        return { ...fallback, ...parsed };
    } catch {
        return fallback;
    }
}

function saveSettings(next) {
    state.settings = next;
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(next));
    applySettings();
}

function applySettings() {
    const s = state.settings ?? loadSettings();
    state.settings = s;
    document.documentElement.dataset.reduceMotion = s.reduceMotion ? '1' : '0';
    shell.dataset.wallpaper = String(s.wallpaperPreset ?? 'aurora');
    shell.style.setProperty('--ui-scale', String(s.uiScale ?? 1));

    const themes = {
        aqua: { accent: '#4AD6FF', accent2: '#9C5AFF', focus: '0 0 0 4px rgba(74,214,255,.35)' },
        purple: { accent: '#9C5AFF', accent2: '#4AD6FF', focus: '0 0 0 4px rgba(156,90,255,.35)' },
        mango: { accent: '#FFC44C', accent2: '#FF5EA4', focus: '0 0 0 4px rgba(255,196,76,.34)' },
        mono: { accent: 'rgba(255,255,255,.90)', accent2: '#4AD6FF', focus: '0 0 0 4px rgba(255,255,255,.18)' },
    };
    const themeKey = String(s.themePreset ?? 'aqua');
    const theme = themes[themeKey] ?? themes.aqua;
    document.documentElement.style.setProperty('--accent', theme.accent);
    document.documentElement.style.setProperty('--accent2', theme.accent2);
    document.documentElement.style.setProperty('--focus', theme.focus);

    if (s.showVideoControls) {
        videoEl.setAttribute('controls', '');
        movieVideoEl.setAttribute('controls', '');
    } else {
        videoEl.removeAttribute('controls');
        movieVideoEl.removeAttribute('controls');
    }
}

function stopAllPlayback() {
    if (state.hls) {
        try { state.hls.destroy(); } catch {}
        state.hls = null;
    }
    [videoEl, movieVideoEl].forEach((v) => {
        try { v.pause(); } catch {}
        v.removeAttribute('src');
        try { v.load(); } catch {}
    });
    state.playing = null;
    nowTitleEl.textContent = 'Nothing playing';
    nowSubEl.textContent = 'Select a channel';
    movieNowTitleEl.textContent = 'Nothing playing';
    movieNowSubEl.textContent = 'Select a movie';
}

function setFocusMode(mode) {
    state.focusMode = mode;
    shell.dataset.focus = mode;
    if (overviewEl) overviewEl.setAttribute('aria-hidden', mode === 'overview' ? 'false' : 'true');
    if (quickMenuEl) quickMenuEl.setAttribute('aria-hidden', mode === 'menu' ? 'false' : 'true');
}

function focusLauncher(idx) {
    const el = launcherEl.querySelector(`.appIcon[data-index="${idx}"]`);
    if (el) el.focus();
}

function focusOverview(idx) {
    const el = overviewRowEl?.querySelector(`.taskCard[data-index="${idx}"]`);
    if (el) el.focus();
}

function focusMenu(idx) {
    const el = quickMenuListEl?.querySelector(`.setItem[data-index="${idx}"]`);
    if (el) el.focus();
}

function focusChannel(idx) {
    const el = listEl.querySelector(`.chanItem[data-index="${idx}"]`);
    if (el) el.focus();
}

function setLauncherSelected(selectedIndex) {
    launcherEl.querySelectorAll('.appIcon[data-index]').forEach((el) => {
        el.setAttribute('aria-selected', el.dataset.index === String(selectedIndex) ? 'true' : 'false');
    });
}

function focusMovie(idx) {
    const el = moviesListEl.querySelector(`.chanItem[data-index="${idx}"]`);
    if (el) el.focus();
}

function focusAppTile(idx) {
    const el = appsGridEl.querySelector(`.appTile[data-index="${idx}"]`);
    if (el) el.focus();
}

function focusSetting(idx) {
    const el = settingsListEl.querySelector(`.setItem[data-index="${idx}"]`);
    if (el) el.focus();
}

function appColor(app) {
    const route = app?.route ?? '';
    if (route === 'live') return 'linear-gradient(135deg, rgba(74,214,255,.95), rgba(22,240,180,.70))';
    if (route === 'movies') return 'linear-gradient(135deg, rgba(255,196,76,.95), rgba(255,94,164,.80))';
    if (route === 'apps') return 'linear-gradient(135deg, rgba(156,90,255,.90), rgba(74,214,255,.75))';
    if (route === 'settings') return 'linear-gradient(135deg, rgba(255,255,255,.80), rgba(74,214,255,.65))';
    return 'linear-gradient(135deg, rgba(74,214,255,.95), rgba(156,90,255,.85))';
}

function renderLauncher() {
    launcherEl.innerHTML = '';
    state.apps.forEach((app, idx) => {
        const item = document.createElement('div');
        item.className = 'appIcon';
        item.tabIndex = 0;
        item.setAttribute('role', 'listitem');
        item.dataset.index = String(idx);
        item.setAttribute('aria-selected', idx === state.appIndex ? 'true' : 'false');

        const iconHtml = app?.iconName
            ? `<span class="material-symbols-rounded" aria-hidden="true">${escapeHtml(app.iconName)}</span>`
            : escapeHtml(app.icon ?? '⬚');

        item.innerHTML = `
            <div class="appGlyph" style="background:${escapeAttr(appColor(app))}" aria-hidden="true">${iconHtml}</div>
            <div class="appLabel">${escapeHtml(app.title ?? 'App')}</div>
        `;
        item.addEventListener('click', () => {
            state.appIndex = idx;
            openAppFromLauncher(true);
        });
        item.addEventListener('focus', () => {
            setFocusMode('launcher');
            state.appIndex = idx;
            setLauncherSelected(idx);
            syncActiveApp();
        });
        launcherEl.appendChild(item);
    });
    focusLauncher(state.appIndex);
}

function routeLabel(route) {
    if (route === 'live') return 'Live TV';
    if (route === 'movies') return 'Movies';
    if (route === 'apps') return 'Apps';
    if (route === 'settings') return 'Settings';
    return 'App';
}

function routeSub(route) {
    if (route === 'live') return 'Channels and streams';
    if (route === 'movies') return 'Local / demo VOD';
    if (route === 'apps') return 'Web apps and links';
    if (route === 'settings') return 'System preferences';
    return 'Open';
}

function renderOverview() {
    if (!overviewRowEl) return;
    overviewRowEl.innerHTML = '';
    state.apps.forEach((app, idx) => {
        const route = String(app?.route ?? '');
        const card = document.createElement('div');
        card.className = 'taskCard';
        card.tabIndex = 0;
        card.dataset.index = String(idx);
        card.dataset.active = idx === state.appIndex ? '1' : '0';
        card.setAttribute('role', 'option');
        card.setAttribute('aria-selected', idx === state.overviewIndex ? 'true' : 'false');

        const iconHtml = app?.iconName
            ? `<span class="material-symbols-rounded" aria-hidden="true">${escapeHtml(app.iconName)}</span>`
            : escapeHtml(app?.icon ?? '⬚');

        const isPlaying = (route === 'live' && state.playing?.type === 'channel') || (route === 'movies' && state.playing?.type === 'movie');
        const badge = isPlaying ? `Playing: ${escapeHtml(state.playing.title ?? '…')}` : routeLabel(route);

        card.innerHTML = `
            <div class="taskPreview">
                <div class="taskTop">
                    <div class="taskGlyph" style="background:${escapeAttr(appColor(app))}" aria-hidden="true">${iconHtml}</div>
                    <div class="taskTitleWrap">
                        <p class="taskTitle">${escapeHtml(app?.title ?? 'App')}</p>
                        <p class="taskSub">${escapeHtml(routeSub(route))}</p>
                    </div>
                </div>
                <div class="taskMetaRow">
                    <div class="taskBadge">${badge}</div>
                    <div class="taskActiveDot" aria-hidden="true"></div>
                </div>
            </div>
        `;

        card.addEventListener('focus', () => {
            setFocusMode('overview');
            state.overviewIndex = idx;
            overviewRowEl.querySelectorAll('.taskCard[data-index]').forEach((el) => {
                el.setAttribute('aria-selected', el.dataset.index === String(idx) ? 'true' : 'false');
            });
            card.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        });
        card.addEventListener('click', () => {
            state.overviewIndex = idx;
            state.appIndex = idx;
            syncActiveApp();
            setFocusMode('launcher');
            openAppFromLauncher(true);
        });
        overviewRowEl.appendChild(card);
    });
}

function openOverview() {
    state.overviewIndex = state.appIndex;
    setFocusMode('overview');
    renderOverview();
    focusOverview(state.overviewIndex);
}

function closeOverview() {
    setFocusMode('launcher');
    focusLauncher(state.appIndex);
}

function wallpaperOptions() {
    return [
        { label: 'Aurora', value: 'aurora' },
        { label: 'Mango', value: 'mango' },
        { label: 'Midnight', value: 'midnight' },
        { label: 'Sunset', value: 'sunset' },
        { label: 'Forest', value: 'forest' },
        { label: 'Nebula', value: 'nebula' },
    ];
}

function themeOptions() {
    return [
        { label: 'Aqua', value: 'aqua' },
        { label: 'Purple', value: 'purple' },
        { label: 'Mango', value: 'mango' },
        { label: 'Mono', value: 'mono' },
    ];
}

function cycleSetting(key, options) {
    const s = state.settings ?? loadSettings();
    state.settings = s;
    const values = options.map((o) => o.value);
    const cur = s[key];
    const idx = values.indexOf(cur);
    const next = values[(idx + 1 + values.length) % values.length];
    saveSettings({ ...s, [key]: next });
}

function toggleSetting(key) {
    const s = state.settings ?? loadSettings();
    state.settings = s;
    saveSettings({ ...s, [key]: !s[key] });
}

function buildQuickMenuItems() {
    const s = state.settings ?? loadSettings();
    state.settings = s;
    const wpLabel = wallpaperOptions().find((o) => o.value === s.wallpaperPreset)?.label ?? 'Aurora';
    const themeLabel = themeOptions().find((o) => o.value === s.themePreset)?.label ?? 'Aqua';
    const items = [
        { type: 'cycle', title: 'Wallpaper', meta: 'Change background', icon: 'wallpaper', value: wpLabel, action: 'cycle_wallpaper' },
        { type: 'cycle', title: 'Theme', meta: 'Accent colors', icon: 'palette', value: themeLabel, action: 'cycle_theme' },
        { type: 'toggle', title: 'Video controls', meta: 'Show/hide native controls', icon: 'tune', value: s.showVideoControls ? 'On' : 'Off', action: 'toggle_video_controls' },
        { type: 'toggle', title: 'Open links inside OS', meta: 'Built-in browser', icon: 'web', value: s.openLinksInOs ? 'On' : 'Off', action: 'toggle_open_links' },
        { type: 'toggle', title: 'Reduce motion', meta: 'Less animation', icon: 'motion_photos_off', value: s.reduceMotion ? 'On' : 'Off', action: 'toggle_reduce_motion' },
        { type: 'action', title: 'App switcher', meta: 'Show running apps', icon: 'window', value: 'Tab', action: 'open_switcher' },
        { type: 'action', title: 'Home', meta: 'Back to launcher', icon: 'home', value: 'Home', action: 'go_home' },
        { type: 'action', title: 'Stop playback', meta: 'Stop video/audio', icon: 'stop_circle', value: 'Backspace', action: 'stop_playback' },
    ];
    if (pinEnabled) {
        items.push({ type: 'action', title: 'Log out', meta: 'Lock this TV OS', icon: 'logout', value: 'Esc', action: 'logout' });
    }
    items.push({ type: 'action', title: 'Close menu', meta: 'Return to OS', icon: 'close', value: 'Esc', action: 'close_menu' });
    return items;
}

function renderQuickMenu() {
    if (!quickMenuListEl) return;
    const items = buildQuickMenuItems();
    quickMenuListEl.innerHTML = '';
    items.forEach((it, idx) => {
        const row = document.createElement('div');
        row.className = 'setItem';
        row.tabIndex = 0;
        row.dataset.index = String(idx);
        row.setAttribute('role', 'listitem');
        row.innerHTML = `
            <div class="setLeft">
                <div class="setIcon" aria-hidden="true">${materialIcon(it.icon)}</div>
                <div class="setText">
                    <p class="setName">${escapeHtml(it.title)}</p>
                    <p class="setMeta">${escapeHtml(it.meta)}</p>
                </div>
            </div>
            <div class="setValue">${escapeHtml(it.value ?? '')}</div>
        `;
        row.addEventListener('focus', () => {
            setFocusMode('menu');
            state.menuIndex = idx;
        });
        row.addEventListener('click', () => activateQuickMenuItem(idx));
        quickMenuListEl.appendChild(row);
    });
}

function openMenu() {
    state.menuIndex = 0;
    setFocusMode('menu');
    renderQuickMenu();
    focusMenu(state.menuIndex);
}

function closeMenu() {
    setFocusMode('launcher');
    focusLauncher(state.appIndex);
}

function activateQuickMenuItem(idx) {
    const items = buildQuickMenuItems();
    const it = items[idx];
    if (!it) return;
    if (it.action === 'cycle_wallpaper') {
        cycleSetting('wallpaperPreset', wallpaperOptions());
        renderQuickMenu();
        focusMenu(idx);
        return;
    }
    if (it.action === 'cycle_theme') {
        cycleSetting('themePreset', themeOptions());
        renderQuickMenu();
        focusMenu(idx);
        return;
    }
    if (it.action === 'toggle_video_controls') {
        toggleSetting('showVideoControls');
        renderQuickMenu();
        focusMenu(idx);
        return;
    }
    if (it.action === 'toggle_open_links') {
        toggleSetting('openLinksInOs');
        renderQuickMenu();
        focusMenu(idx);
        return;
    }
    if (it.action === 'toggle_reduce_motion') {
        toggleSetting('reduceMotion');
        renderQuickMenu();
        focusMenu(idx);
        return;
    }
    if (it.action === 'open_switcher') {
        setFocusMode('launcher');
        openOverview();
        return;
    }
    if (it.action === 'go_home') {
        closeMenu();
        return;
    }
    if (it.action === 'stop_playback') {
        stopPlayback();
        showToast('Stopped');
        renderQuickMenu();
        focusMenu(idx);
        return;
    }
    if (it.action === 'logout') {
        logout();
        return;
    }
    if (it.action === 'close_menu') {
        closeMenu();
    }
}

function renderChannels() {
    listEl.innerHTML = '';
    channelCountEl.textContent = String(state.channels.length);
    const s = state.settings ?? loadSettings();
    state.settings = s;

    if (state.channels.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'chanItem';
        empty.tabIndex = 0;
        empty.innerHTML = `
            <div class="chanAvatar" aria-hidden="true">⛔</div>
            <div class="chanText">
                <p class="chanName">No channels</p>
                <p class="chanMeta">Configure MySQL or use demo streams</p>
            </div>
        `;
        listEl.appendChild(empty);
        return;
    }

    state.channels.forEach((ch, idx) => {
        const item = document.createElement('div');
        item.className = 'chanItem';
        item.tabIndex = 0;
        item.setAttribute('role', 'listitem');
        item.dataset.index = String(idx);
        const avatar = ch.logoUrl ? `<img alt="" src="${escapeAttr(ch.logoUrl)}" />` : materialIcon('tv');
        item.innerHTML = `
            <div class="chanAvatar" aria-hidden="true">${avatar}</div>
            <div class="chanText">
                <p class="chanName">${escapeHtml(ch.name)}</p>
                <p class="chanMeta">${escapeHtml(ch.streamUrl)}</p>
            </div>
        `;
        item.addEventListener('click', () => playChannel(idx));
        item.addEventListener('focus', () => {
            setFocusMode('channels');
            state.channelIndex = idx;
            if (s.autoplayOnHighlight) playChannel(idx);
        });
        listEl.appendChild(item);
    });
}

function renderMovies() {
    moviesListEl.innerHTML = '';
    movieCountEl.textContent = String(state.movies.length);
    const s = state.settings ?? loadSettings();
    state.settings = s;

    if (state.movies.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'chanItem';
        empty.tabIndex = 0;
        empty.innerHTML = `
            <div class="chanAvatar" aria-hidden="true">${materialIcon('movie')}</div>
            <div class="chanText">
                <p class="chanName">No movies</p>
                <p class="chanMeta">Configure a movies table or keep demo content</p>
            </div>
        `;
        moviesListEl.appendChild(empty);
        return;
    }

    state.movies.forEach((mv, idx) => {
        const item = document.createElement('div');
        item.className = 'chanItem';
        item.tabIndex = 0;
        item.setAttribute('role', 'listitem');
        item.dataset.index = String(idx);
        const meta = mv.year ? String(mv.year) : String(mv.streamUrl ?? '');
        item.innerHTML = `
            <div class="chanAvatar" aria-hidden="true">${materialIcon('theaters')}</div>
            <div class="chanText">
                <p class="chanName">${escapeHtml(mv.title ?? '')}</p>
                <p class="chanMeta">${escapeHtml(meta)}</p>
            </div>
        `;
        item.addEventListener('click', () => playMovie(idx));
        item.addEventListener('focus', () => {
            setFocusMode('movies');
            state.movieIndex = idx;
            if (s.autoplayOnHighlight) playMovie(idx);
        });
        moviesListEl.appendChild(item);
    });
}

function renderApps() {
    appsGridEl.innerHTML = '';
    FEATURED_APPS.forEach((app, idx) => {
        const tile = document.createElement('div');
        tile.className = 'appTile';
        tile.tabIndex = 0;
        tile.setAttribute('role', 'listitem');
        tile.dataset.index = String(idx);
        tile.innerHTML = `
            <div class="appTileIcon" aria-hidden="true">${materialIcon(app.iconName)}</div>
            <div class="appTileText">
                <p class="appTileTitle">${escapeHtml(app.title)}</p>
                <p class="appTileSub">${escapeHtml(app.sub)}</p>
            </div>
        `;
        tile.addEventListener('focus', () => {
            setFocusMode('apps');
            state.appsIndex = idx;
        });
        tile.addEventListener('click', () => activateAppTile(idx));
        appsGridEl.appendChild(tile);
    });
}

function normalizeUrl(url) {
    const u = String(url ?? '').trim();
    if (u === '') return '';
    if (/^https?:\/\//i.test(u)) return u;
    return `https://${u}`;
}

function openInOsBrowser(url) {
    const finalUrl = normalizeUrl(url);
    if (finalUrl === '') {
        showToast('Enter a URL', 2200);
        return;
    }
    appsViewEl.style.display = 'none';
    browserViewEl.style.display = 'block';
    browserUrlInputEl.value = finalUrl;
    browserFrameEl.src = finalUrl;
    setFocusMode('browser');
    browserUrlInputEl.focus();
    browserUrlInputEl.select();
}

function openExternal(url) {
    const finalUrl = normalizeUrl(url);
    if (finalUrl === '') {
        showToast('Enter a URL', 2200);
        return;
    }
    const s = state.settings ?? loadSettings();
    state.settings = s;
    if (s.openLinksInOs) {
        openInOsBrowser(finalUrl);
        return;
    }
    window.open(finalUrl, '_blank', 'noopener,noreferrer');
}

function openGoogleSearch(query) {
    const q = String(query ?? '').trim();
    if (q === '') {
        openExternal('https://www.google.com/');
        return;
    }
    const url = `https://www.google.com/search?q=${encodeURIComponent(q)}`;
    openExternal(url);
}

function activateAppTile(idx) {
    const app = FEATURED_APPS[idx];
    if (!app) return;
    if (app.action === 'openUrl') {
        appUrlInputEl.focus();
        appUrlInputEl.select();
        return;
    }
    if (app.action === 'googleSearch') {
        googleQueryInputEl.focus();
        googleQueryInputEl.select();
        return;
    }
    if (app.url) openExternal(app.url);
}

function buildSettingsItems() {
    const s = state.settings ?? loadSettings();
    state.settings = s;
    let lastPlaybackValue = 'None';
    try {
        const raw = localStorage.getItem(LAST_PLAY_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            const title = typeof parsed?.title === 'string' ? parsed.title : '';
            const type = typeof parsed?.type === 'string' ? parsed.type : '';
            if (title !== '' && type !== '') {
                lastPlaybackValue = `${type}: ${title}`;
            } else if (type !== '') {
                lastPlaybackValue = type;
            }
        }
    } catch {
    }
    const items = [
        { type: 'toggle', title: 'Autoplay on highlight', meta: 'Play when selecting items', icon: 'play_circle', key: 'autoplayOnHighlight' },
        { type: 'toggle', title: 'Remember last playback', meta: 'Auto-resume on next load', icon: 'history', key: 'rememberLast' },
        { type: 'toggle', title: 'Video controls', meta: 'Show/hide native controls', icon: 'tune', key: 'showVideoControls' },
        { type: 'toggle', title: 'HLS low latency', meta: 'Hls.js low latency mode', icon: 'speed', key: 'hlsLowLatency' },
        { type: 'toggle', title: 'Background playback', meta: 'Keep playing when switching apps', icon: 'headphones', key: 'backgroundPlayback' },
        { type: 'toggle', title: 'Open links inside OS', meta: 'Use built-in browser (some sites may block embedding)', icon: 'web', key: 'openLinksInOs' },
        { type: 'toggle', title: 'Reduce motion', meta: 'Disable most animations', icon: 'motion_photos_off', key: 'reduceMotion' },
        { type: 'toggle', title: '24-hour clock', meta: 'Clock format', icon: 'schedule', key: 'clock24h' },
        {
            type: 'cycle',
            title: 'Theme',
            meta: 'Accent colors',
            icon: 'palette',
            key: 'themePreset',
            options: [
                { label: 'Aqua', value: 'aqua' },
                { label: 'Purple', value: 'purple' },
                { label: 'Mango', value: 'mango' },
                { label: 'Mono', value: 'mono' },
            ],
        },
        {
            type: 'cycle',
            title: 'Wallpaper',
            meta: 'Background style',
            icon: 'wallpaper',
            key: 'wallpaperPreset',
            options: [
                { label: 'Aurora', value: 'aurora' },
                { label: 'Mango', value: 'mango' },
                { label: 'Midnight', value: 'midnight' },
                { label: 'Sunset', value: 'sunset' },
                { label: 'Forest', value: 'forest' },
                { label: 'Nebula', value: 'nebula' },
            ],
        },
        {
            type: 'cycle',
            title: 'UI scale',
            meta: 'Card size',
            icon: 'aspect_ratio',
            key: 'uiScale',
            options: [
                { label: '80%', value: 0.8 },
                { label: '90%', value: 0.9 },
                { label: '100%', value: 1 },
                { label: '110%', value: 1.1 },
                { label: '120%', value: 1.2 },
            ],
        },
        {
            type: 'cycle',
            title: 'Default volume',
            meta: 'Applies when starting playback',
            icon: 'volume_up',
            key: 'defaultVolume',
            options: [
                { label: '100%', value: 1 },
                { label: '75%', value: 0.75 },
                { label: '50%', value: 0.5 },
                { label: '25%', value: 0.25 },
                { label: 'Mute', value: 0 },
            ],
        },
    ];

    if (state.osId) {
        items.push({ type: 'info', title: 'OS ID', meta: '', icon: 'badge', value: String(state.osId) });
        items.push({ type: 'action', title: 'Copy OS ID', meta: 'Copy to clipboard', icon: 'content_copy', action: 'copy_os_id' });
    }

    if (state.server) {
        items.push(
            { type: 'info', title: 'PIN lock', meta: '', icon: 'lock', value: state.server.pinEnabled ? 'Enabled' : 'Disabled' },
            { type: 'info', title: 'Database', meta: '', icon: 'database', value: state.server.dbConnected ? 'Connected' : (state.server.dbConfigured ? 'Not connected' : 'Not configured') },
        );
    }

    items.push(
        { type: 'info', title: 'Last playback', meta: '', icon: 'history_toggle_off', value: lastPlaybackValue },
        { type: 'info', title: 'Local storage', meta: '', icon: 'storage', value: `${localStorage.length} items` },
        { type: 'action', title: 'Check server health', meta: 'Call /api/health', icon: 'health_and_safety', action: 'health' },
        { type: 'action', title: 'Stop playback', meta: 'Stop all video', icon: 'stop_circle', action: 'stop' },
        { type: 'action', title: 'Export settings', meta: 'Copy settings JSON', icon: 'download', action: 'export_settings' },
        { type: 'action', title: 'Import settings', meta: 'Paste settings JSON', icon: 'upload', action: 'import_settings' },
        { type: 'action', title: 'Reset local settings', meta: 'Clear saved settings & last playback', icon: 'restart_alt', action: 'reset' },
    );

    if (pinEnabled) {
        items.push({ type: 'action', title: 'Log out', meta: 'Lock this TV OS', icon: 'logout', action: 'logout' });
    }

    return items;
}

function renderSettings() {
    state.settingsItems = buildSettingsItems();
    settingsListEl.innerHTML = '';
    state.settingsItems.forEach((it, idx) => {
        const el = document.createElement('div');
        el.className = 'setItem';
        el.tabIndex = 0;
        el.setAttribute('role', 'listitem');
        el.dataset.index = String(idx);

        let valueText = '';
        if (it.type === 'toggle') {
            valueText = (state.settings?.[it.key] ?? false) ? 'On' : 'Off';
        } else if (it.type === 'cycle') {
            const current = state.settings?.[it.key];
            const match = (it.options ?? []).find((o) => o.value === current);
            valueText = match ? match.label : 'Change';
        } else if (it.type === 'info') {
            valueText = it.value ?? '';
        } else {
            valueText = 'Run';
        }

        el.innerHTML = `
            <div class="setLeft">
                <div class="setIcon" aria-hidden="true">${materialIcon(it.icon)}</div>
                <div class="setText">
                    <p class="setName">${escapeHtml(it.title)}</p>
                    <p class="setMeta">${escapeHtml(it.meta ?? '')}</p>
                </div>
            </div>
            <div class="setValue">${escapeHtml(valueText)}</div>
        `;

        el.addEventListener('focus', () => {
            setFocusMode('settings');
            state.settingsIndex = idx;
        });
        el.addEventListener('click', () => activateSetting(idx));
        settingsListEl.appendChild(el);
    });
}

async function activateSetting(idx) {
    const it = state.settingsItems[idx];
    if (!it) return;
    if (it.type === 'toggle') {
        const s = state.settings ?? loadSettings();
        const next = { ...s, [it.key]: !s[it.key] };
        saveSettings(next);
        renderSettings();
        showToast(`${it.title}: ${next[it.key] ? 'On' : 'Off'}`);
        return;
    }
    if (it.type === 'cycle') {
        const s = state.settings ?? loadSettings();
        const options = it.options ?? [];
        if (options.length === 0) return;
        const current = s[it.key];
        const currentIdx = options.findIndex((o) => o.value === current);
        const nextOpt = options[(currentIdx + 1 + options.length) % options.length];
        const next = { ...s, [it.key]: nextOpt.value };
        saveSettings(next);
        renderSettings();
        showToast(`${it.title}: ${nextOpt.label}`);
        return;
    }
    if (it.type === 'action') {
        if (it.action === 'stop') {
            stopAllPlayback();
            showToast('Stopped');
            return;
        }
        if (it.action === 'copy_os_id') {
            const text = state.osId ? String(state.osId) : '';
            if (text === '') {
                showToast('OS ID not available', 2200);
                return;
            }
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(text)
                    .then(() => showToast('Copied', 1600))
                    .catch(() => showToast('Copy failed', 2200));
                return;
            }
            window.prompt('Copy OS ID', text);
            return;
        }
        if (it.action === 'export_settings') {
            const json = JSON.stringify(state.settings ?? loadSettings(), null, 2);
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(json)
                    .then(() => showToast('Settings copied', 1800))
                    .catch(() => showToast('Copy failed', 2200));
                return;
            }
            window.prompt('Copy settings JSON', json);
            return;
        }
        if (it.action === 'import_settings') {
            const raw = window.prompt('Paste settings JSON');
            if (!raw) return;
            try {
                const parsed = JSON.parse(raw);
                const baseSettings = loadSettings();
                const next = { ...baseSettings };
                if (typeof parsed?.autoplayOnHighlight === 'boolean') next.autoplayOnHighlight = parsed.autoplayOnHighlight;
                if (typeof parsed?.rememberLast === 'boolean') next.rememberLast = parsed.rememberLast;
                if (typeof parsed?.showVideoControls === 'boolean') next.showVideoControls = parsed.showVideoControls;
                if (typeof parsed?.hlsLowLatency === 'boolean') next.hlsLowLatency = parsed.hlsLowLatency;
                if (typeof parsed?.backgroundPlayback === 'boolean') next.backgroundPlayback = parsed.backgroundPlayback;
                if (typeof parsed?.reduceMotion === 'boolean') next.reduceMotion = parsed.reduceMotion;
                if (typeof parsed?.clock24h === 'boolean') next.clock24h = parsed.clock24h;
                if (typeof parsed?.themePreset === 'string') next.themePreset = parsed.themePreset;
                if (typeof parsed?.wallpaperPreset === 'string') next.wallpaperPreset = parsed.wallpaperPreset;
                if (typeof parsed?.uiScale === 'number') next.uiScale = parsed.uiScale;
                if (typeof parsed?.defaultVolume === 'number') next.defaultVolume = parsed.defaultVolume;
                saveSettings(next);
                renderSettings();
                showToast('Imported', 1800);
            } catch {
                showToast('Invalid JSON', 2200);
            }
            return;
        }
        if (it.action === 'reset') {
            localStorage.removeItem(SETTINGS_KEY);
            localStorage.removeItem(LAST_PLAY_KEY);
            state.settings = loadSettings();
            applySettings();
            renderSettings();
            showToast('Reset complete');
            return;
        }
        if (it.action === 'logout') {
            await logout();
            return;
        }
        if (it.action === 'health') {
            try {
                const d = await apiFetch('/api/health');
                showToast(`OK · ${d.time}`, 2800);
            } catch (e) {
                showToast(String(e?.message ?? e), 3200);
            }
        }
    }
}

function syncActiveApp() {
    const app = state.apps[state.appIndex];
    const title = app?.title ?? 'Home';
    const route = app?.route ?? 'apps';
    const prevRoute = state.activeRoute;
    const s = state.settings ?? loadSettings();
    state.settings = s;
    if (prevRoute !== route && !s.backgroundPlayback) {
        stopAllPlayback();
    }
    state.activeRoute = route;

    cardTitleEl.textContent = title;
    cardHintEl.textContent = route === 'live' ? 'Live TV' : route === 'movies' ? 'Movies' : route === 'apps' ? 'Apps' : route === 'settings' ? 'Settings' : 'Home';
    cardSubEl.textContent = route === 'live'
        ? 'Up/Enter: channels · Left: launcher'
        : route === 'movies'
            ? 'Up/Enter: movies · Left: launcher'
            : route === 'apps'
                ? 'Open URLs and web apps'
                : route === 'settings'
                    ? 'Toggles and diagnostics'
                    : 'Launcher';

    liveViewEl.style.display = route === 'live' ? 'grid' : 'none';
    moviesViewEl.style.display = route === 'movies' ? 'grid' : 'none';
    appsViewEl.style.display = route === 'apps' ? 'block' : 'none';
    settingsViewEl.style.display = route === 'settings' ? 'block' : 'none';
    placeholderViewEl.style.display = (route !== 'live' && route !== 'movies' && route !== 'apps' && route !== 'settings') ? 'flex' : 'none';

    if (route === 'apps') {
        renderApps();
    }
    if (route === 'settings') {
        renderSettings();
    }

    phIconEl.textContent = app?.icon ?? '🧩';
    phTitleEl.textContent = title;
    phSubEl.textContent = route === 'settings'
        ? (pinEnabled ? 'Esc logs out. Backspace stops playback.' : 'PIN lock is disabled. Set TVOS_PIN to enable.')
        : 'This is a webOS-style UI shell.';
}

function openAppFromLauncher(moveFocus) {
    syncActiveApp();
    if (!moveFocus) return;
    if (state.activeRoute === 'live') {
        setFocusMode('channels');
        focusChannel(state.channelIndex);
        return;
    }
    if (state.activeRoute === 'movies') {
        setFocusMode('movies');
        focusMovie(state.movieIndex);
        return;
    }
    if (state.activeRoute === 'apps') {
        setFocusMode('apps');
        focusAppTile(state.appsIndex);
        return;
    }
    if (state.activeRoute === 'settings') {
        setFocusMode('settings');
        focusSetting(state.settingsIndex);
        return;
    }
    setFocusMode('launcher');
    focusLauncher(state.appIndex);
}

function stopPlayback() {
    stopAllPlayback();
}

function isHlsUrl(url) {
    return /\.m3u8(\?.*)?$/i.test(url);
}

function playUrl(url, targetVideo = videoEl) {
    stopAllPlayback();
    if (!url) return;
    const s = state.settings ?? loadSettings();
    state.settings = s;
    const vol = Number(s.defaultVolume);
    if (Number.isFinite(vol)) {
        targetVideo.volume = Math.min(1, Math.max(0, vol));
    }
    if (isHlsUrl(url)) {
        if (targetVideo.canPlayType('application/vnd.apple.mpegurl')) {
            targetVideo.src = url;
            targetVideo.play().catch(() => {});
            return;
        }
        if (window.Hls && window.Hls.isSupported()) {
            state.hls = new window.Hls({ enableWorker: true, lowLatencyMode: !!s.hlsLowLatency });
            state.hls.loadSource(url);
            state.hls.attachMedia(targetVideo);
            state.hls.on(window.Hls.Events.MANIFEST_PARSED, () => {
                targetVideo.play().catch(() => {});
            });
            state.hls.on(window.Hls.Events.ERROR, (_evt, data) => {
                if (data?.fatal) showToast('Playback error: stream not supported', 3200);
            });
            return;
        }
        showToast('HLS not supported in this browser (need Hls.js)', 3200);
        return;
    }
    targetVideo.src = url;
    targetVideo.play().catch(() => {});
}

function rememberLastPlayback(payload) {
    try {
        const s = state.settings ?? loadSettings();
        state.settings = s;
        if (!s.rememberLast) return;
        localStorage.setItem(LAST_PLAY_KEY, JSON.stringify({ ...payload, time: Date.now() }));
    } catch {
    }
}

function restoreLastPlayback() {
    const s = state.settings ?? loadSettings();
    state.settings = s;
    if (!s.rememberLast) return;
    try {
        const raw = localStorage.getItem(LAST_PLAY_KEY);
        if (!raw) return;
        const last = JSON.parse(raw);
        if (!last || typeof last !== 'object') return;
        if (last.type === 'channel') {
            const idx = state.channels.findIndex((c) => (c?.id ?? '') === (last.id ?? ''));
            if (idx >= 0) {
                const appIdx = state.apps.findIndex((a) => a?.route === 'live');
                if (appIdx >= 0) state.appIndex = appIdx;
                syncActiveApp();
                setFocusMode('channels');
                focusChannel(idx);
                playChannel(idx);
            }
            return;
        }
        if (last.type === 'movie') {
            const idx = state.movies.findIndex((m) => (m?.id ?? '') === (last.id ?? ''));
            if (idx >= 0) {
                const appIdx = state.apps.findIndex((a) => a?.route === 'movies');
                if (appIdx >= 0) state.appIndex = appIdx;
                syncActiveApp();
                setFocusMode('movies');
                focusMovie(idx);
                playMovie(idx);
            }
        }
    } catch {
    }
}

function playChannel(idx) {
    const ch = state.channels[idx];
    if (!ch) return;
    state.channelIndex = idx;
    state.playing = { type: 'channel', id: ch.id, title: ch.name, url: ch.streamUrl };
    nowTitleEl.textContent = ch.name;
    nowSubEl.textContent = ch.streamUrl;
    rememberLastPlayback({ type: 'channel', id: ch.id, title: ch.name });
    playUrl(ch.streamUrl, videoEl);
}

function playMovie(idx) {
    const mv = state.movies[idx];
    if (!mv) return;
    state.movieIndex = idx;
    state.playing = { type: 'movie', id: mv.id, title: mv.title, url: mv.streamUrl };
    movieNowTitleEl.textContent = mv.title ?? 'Movie';
    movieNowSubEl.textContent = mv.year ? String(mv.year) : String(mv.streamUrl ?? '');
    rememberLastPlayback({ type: 'movie', id: mv.id, title: mv.title ?? 'Movie' });
    playUrl(mv.streamUrl, movieVideoEl);
}

function updateClock() {
    const now = new Date();
    const s = state.settings ?? loadSettings();
    state.settings = s;
    let hours = now.getHours();
    const mm = String(now.getMinutes()).padStart(2, '0');
    if (!s.clock24h) {
        const suffix = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        if (hours === 0) hours = 12;
        clockEl.textContent = `${String(hours).padStart(2, '0')}:${mm} ${suffix}`;
        return;
    }
    clockEl.textContent = `${String(hours).padStart(2, '0')}:${mm}`;
}

function updateNetworkStatus() {
    netStatusEl.textContent = navigator.onLine ? 'Online' : 'Offline';
}

function setAuthed(authed) {
    state.authed = authed;
    authPillEl.textContent = authed ? 'Unlocked' : (pinEnabled ? 'Locked' : 'Guest');
}

async function ensureAuth() {
    const me = await apiFetch('/api/me');
    state.osId = me.osId ?? null;
    setAuthed(!!me.authenticated);
    if (!state.authed && pinEnabled) {
        loginModalEl.style.display = 'flex';
        pinInputEl.focus();
    } else {
        loginModalEl.style.display = 'none';
    }
}

async function loginWithPin(pin) {
    pinErrEl.style.display = 'none';
    await apiFetch('/api/login', { method: 'POST', body: { pin } });
    setAuthed(true);
    loginModalEl.style.display = 'none';
    pinInputEl.value = '';
    showToast('Unlocked');
    await loadData();
}

async function logout() {
    try {
        await apiFetch('/api/logout', { method: 'POST' });
    } catch {}
    stopPlayback();
    setAuthed(false);
    if (pinEnabled) {
        loginModalEl.style.display = 'flex';
        pinInputEl.focus();
    }
}

async function loadData() {
    state.apps = (await apiFetch('/api/apps')).apps ?? [];
    state.channels = (await apiFetch('/api/channels')).channels ?? [];
    state.movies = (await apiFetch('/api/movies')).movies ?? [];
    try {
        state.server = await apiFetch('/api/server');
    } catch {
        state.server = null;
    }
    const liveIdx = state.apps.findIndex((a) => a?.route === 'live');
    state.appIndex = liveIdx >= 0 ? liveIdx : 0;
    state.settings = state.settings ?? loadSettings();
    applySettings();
    renderLauncher();
    renderChannels();
    renderMovies();
    syncActiveApp();
    restoreLastPlayback();
}

document.addEventListener('keydown', (e) => {
    if (loginModalEl.style.display === 'flex') {
        if (e.key === 'Enter') pinBtnEl.click();
        if (e.key === 'Escape') pinInputEl.value = '';
        return;
    }

    const activeEl = document.activeElement;
    const activeTag = activeEl ? String(activeEl.tagName ?? '').toUpperCase() : '';
    const isTyping = activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeEl?.isContentEditable === true;
    if (isTyping) {
        if (activeEl === appUrlInputEl && e.key === 'Enter') {
            openExternal(appUrlInputEl.value);
            return;
        }
        if (activeEl === googleQueryInputEl && e.key === 'Enter') {
            openGoogleSearch(googleQueryInputEl.value);
            return;
        }
        if (activeEl === appUrlInputEl && e.key === 'Escape') {
            appUrlInputEl.blur();
            setFocusMode('apps');
            focusAppTile(state.appsIndex);
            return;
        }
        if (activeEl === googleQueryInputEl && e.key === 'Escape') {
            googleQueryInputEl.blur();
            setFocusMode('apps');
            focusAppTile(state.appsIndex);
            return;
        }
        return;
    }

    if (e.key === 'ContextMenu' || e.key === 'm' || e.key === 'M') {
        e.preventDefault();
        if (state.focusMode === 'menu') {
            closeMenu();
            return;
        }
        openMenu();
        return;
    }

    if (e.key === 'Home' || e.key === 'BrowserHome' || e.key === 'GoHome') {
        e.preventDefault();
        if (state.focusMode === 'menu') closeMenu();
        if (state.focusMode === 'overview') closeOverview();
        setFocusMode('launcher');
        focusLauncher(state.appIndex);
        return;
    }

    if (e.key === 'Tab') {
        e.preventDefault();
        if (state.focusMode === 'overview') {
            closeOverview();
            return;
        }
        openOverview();
        return;
    }

    if (e.key === 'Backspace') {
        e.preventDefault();
        stopPlayback();
        showToast('Stopped');
        return;
    }

    if (e.key === 'Escape') {
        if (state.focusMode === 'menu') {
            closeMenu();
            return;
        }
        if (state.focusMode === 'overview') {
            closeOverview();
            return;
        }
        if (pinEnabled) {
            logout();
            return;
        }
        if (state.focusMode !== 'launcher') {
            setFocusMode('launcher');
            focusLauncher(state.appIndex);
        }
        return;
    }

    if (e.key === 'Enter') {
        if (state.focusMode === 'menu') {
            activateQuickMenuItem(state.menuIndex);
            return;
        }
        if (state.focusMode === 'overview') {
            state.appIndex = state.overviewIndex;
            syncActiveApp();
            setFocusMode('launcher');
            openAppFromLauncher(true);
            return;
        }
        if (state.focusMode === 'launcher') {
            openAppFromLauncher(true);
            return;
        }
        if (state.focusMode === 'channels') {
            playChannel(state.channelIndex);
            return;
        }
        if (state.focusMode === 'movies') {
            playMovie(state.movieIndex);
            return;
        }
        if (state.focusMode === 'apps') {
            activateAppTile(state.appsIndex);
            return;
        }
        if (state.focusMode === 'settings') {
            activateSetting(state.settingsIndex);
        }
        return;
    }

    if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    e.preventDefault();

    if (state.focusMode === 'menu') {
        const max = Math.max(0, (quickMenuListEl?.querySelectorAll('.setItem[data-index]')?.length ?? 0) - 1);
        let next = state.menuIndex;
        if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
        if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            closeMenu();
            return;
        }
        state.menuIndex = next;
        focusMenu(next);
        return;
    }

    if (state.focusMode === 'overview') {
        const max = Math.max(0, state.apps.length - 1);
        let next = state.overviewIndex;
        if (e.key === 'ArrowRight') next = Math.min(max, next + 1);
        if (e.key === 'ArrowLeft') next = Math.max(0, next - 1);
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') return;
        state.overviewIndex = next;
        focusOverview(next);
        overviewRowEl?.querySelector(`.taskCard[data-index="${next}"]`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        return;
    }

    if (state.focusMode === 'launcher') {
        const max = Math.max(0, state.apps.length - 1);
        let next = state.appIndex;
        if (e.key === 'ArrowRight') next = Math.min(max, next + 1);
        if (e.key === 'ArrowLeft') next = Math.max(0, next - 1);
        if (e.key === 'ArrowUp') {
            openAppFromLauncher(true);
            return;
        }
        state.appIndex = next;
        syncActiveApp();
        focusLauncher(next);
        launcherEl.querySelector(`.appIcon[data-index="${next}"]`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        return;
    }

    if (state.focusMode === 'channels') {
        if (state.activeRoute !== 'live') return;
        const max = Math.max(0, state.channels.length - 1);
        let next = state.channelIndex;
        if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
        if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
        if (e.key === 'ArrowLeft') {
            setFocusMode('launcher');
            focusLauncher(state.appIndex);
            return;
        }
        if (e.key === 'ArrowRight') return;
        state.channelIndex = next;
        focusChannel(next);
        return;
    }

    if (state.focusMode === 'movies') {
        if (state.activeRoute !== 'movies') return;
        const max = Math.max(0, state.movies.length - 1);
        let next = state.movieIndex;
        if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
        if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
        if (e.key === 'ArrowLeft') {
            setFocusMode('launcher');
            focusLauncher(state.appIndex);
            return;
        }
        if (e.key === 'ArrowRight') return;
        state.movieIndex = next;
        focusMovie(next);
        return;
    }

    if (state.focusMode === 'apps') {
        if (state.activeRoute !== 'apps') return;
        const cols = window.innerWidth <= 980 ? 2 : 3;
        const max = Math.max(0, FEATURED_APPS.length - 1);
        let next = state.appsIndex;
        if (e.key === 'ArrowRight') next = Math.min(max, next + 1);
        if (e.key === 'ArrowLeft') {
            const isFirstCol = (next % cols) === 0;
            if (isFirstCol) {
                setFocusMode('launcher');
                focusLauncher(state.appIndex);
                return;
            }
            next = Math.max(0, next - 1);
        }
        if (e.key === 'ArrowDown') next = Math.min(max, next + cols);
        if (e.key === 'ArrowUp') next = Math.max(0, next - cols);
        state.appsIndex = next;
        focusAppTile(next);
        return;
    }

    if (state.focusMode === 'settings') {
        if (state.activeRoute !== 'settings') return;
        const max = Math.max(0, state.settingsItems.length - 1);
        let next = state.settingsIndex;
        if (e.key === 'ArrowDown') next = Math.min(max, next + 1);
        if (e.key === 'ArrowUp') next = Math.max(0, next - 1);
        if (e.key === 'ArrowLeft') {
            setFocusMode('launcher');
            focusLauncher(state.appIndex);
            return;
        }
        if (e.key === 'ArrowRight') return;
        state.settingsIndex = next;
        focusSetting(next);
    }
});

appUrlBtnEl.addEventListener('click', () => {
    openExternal(appUrlInputEl.value);
});

googleSearchBtnEl.addEventListener('click', () => {
    openGoogleSearch(googleQueryInputEl.value);
});

menuBtnEl?.addEventListener('click', () => {
    if (state.focusMode === 'menu') {
        closeMenu();
        return;
    }
    openMenu();
});
menuBtnEl?.addEventListener('focus', () => {
    setFocusMode('launcher');
});
menuBtnEl?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        openMenu();
    }
});
quickMenuScrimEl?.addEventListener('click', closeMenu);

function closeOsBrowser() {
    browserFrameEl.removeAttribute('src');
    browserViewEl.style.display = 'none';
    if (state.activeRoute === 'apps') {
        appsViewEl.style.display = 'block';
        setFocusMode('apps');
        focusAppTile(state.appsIndex);
        return;
    }
    setFocusMode('launcher');
    focusLauncher(state.appIndex);
}

function navigateBrowserTo(input) {
    const url = normalizeUrl(input);
    if (url === '') {
        showToast('Enter a URL', 2200);
        return;
    }
    browserUrlInputEl.value = url;
    browserFrameEl.src = url;
}

browserGoBtnEl.addEventListener('click', () => {
    navigateBrowserTo(browserUrlInputEl.value);
});
browserUrlInputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') navigateBrowserTo(browserUrlInputEl.value);
    if (e.key === 'Escape') closeOsBrowser();
});
browserBackBtnEl.addEventListener('click', () => {
    try { browserFrameEl.contentWindow.history.back(); } catch {}
});
browserForwardBtnEl.addEventListener('click', () => {
    try { browserFrameEl.contentWindow.history.forward(); } catch {}
});
browserReloadBtnEl.addEventListener('click', () => {
    try { browserFrameEl.contentWindow.location.reload(); } catch {}
});
browserOpenNewTabBtnEl.addEventListener('click', () => {
    openExternal(browserUrlInputEl.value);
});
browserCloseBtnEl.addEventListener('click', closeOsBrowser);

pinBtnEl.addEventListener('click', async () => {
    const pin = (pinInputEl.value ?? '').trim();
    try {
        await loginWithPin(pin);
    } catch {
        pinErrEl.style.display = 'block';
    }
});

pinInputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') pinBtnEl.click();
});

async function boot() {
    state.settings = loadSettings();
    applySettings();
    updateClock();
    updateNetworkStatus();
    window.setInterval(updateClock, 1000);
    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);

    try {
        await ensureAuth();
        if (state.authed || !pinEnabled) {
            await loadData();
        }
    } catch (e) {
        showToast(String(e?.message ?? e), 3600);
    }
}

boot();
