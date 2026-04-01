// --- Theme Management ---
const THEME_STORAGE_KEY = 'clockinapp_theme';

function resolveInitialTheme() {
    const savedTheme = localStorage.getItem(THEME_STORAGE_KEY);
    if (savedTheme === 'dark' || savedTheme === 'light') return savedTheme;
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    return prefersDark ? 'dark' : 'light';
}

function applyTheme(theme) {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', nextTheme);
    localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    updateThemeToggleIcon(nextTheme);

    if (typeof hoursChart !== 'undefined' && hoursChart) {
        loadHoursChart();
    }
}

function updateThemeToggleIcon(theme) {
    const toggle = document.getElementById('theme-toggle');
    if (!toggle) return;
    const dark = theme === 'dark';
    toggle.textContent = dark ? '☀️' : '🌙';
    toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
}

function initThemeToggle() {
    const currentTheme = resolveInitialTheme();
    applyTheme(currentTheme);

    const toggle = document.getElementById('theme-toggle');
    if (!toggle) return;

    toggle.addEventListener('click', () => {
        const active = document.documentElement.getAttribute('data-theme') || 'dark';
        applyTheme(active === 'dark' ? 'light' : 'dark');
    });
}

// --- Base API Configuration ---
const APP_BASE = (() => {
    const path = window.location.pathname.toLowerCase();
    const pagesIdx = path.indexOf('/pages/');
    if (pagesIdx >= 0) return window.location.pathname.substring(0, pagesIdx);
    if (path.startsWith('/clockinapp')) return '/ClockinAPP';
    return '';
})();

function appUrl(path) {
    const normalized = path.startsWith('/') ? path : `/${path}`;
    return `${APP_BASE}${normalized}`;
}

const API_BASE_URL = appUrl('/api/v1');

// --- Global State ---
let qrScanner = null;
let hoursChart = null;
let userLocationsMap = null;
let qrLocationMap = null;
let qrMapMarker = null;
let geocoder = null;
let timerInterval = null;
let timerSyncInterval = null; // Para el polling del timer del usuario
let activeTimersInterval = null; // Para el polling del panel de admin
let activeTimersTicker = null;
const activeTimersState = new Map();
let dashboardMapMarkers = [];
let dashboardMapPolylines = [];

// Estado de usuario (no redeclarar luego)
let userId = null;
let userRole = null;
let username = null;
let specialUsersLoaded = false;
let attendanceAllRecords = [];
let attendanceEditingRecord = null;
let attendancePage = 1;
let attendanceFilterText = '';
let attendanceCalendarDate = new Date();
let attendanceCalendarViewMode = 'week';
let attendanceMatrixCache = [];
let attendanceModalRecordsById = new Map();
let attendancePendingDeleteRecordId = null;
const ATTENDANCE_PAGE_SIZE = 10;

function normalizeRole(role) {
    if (!role) return '';
    const lower = role.toLowerCase();
    if (lower === 'special user' || lower === 'special-user') {
        return 'special';
    }
    return lower;
}

function enhanceTemporalInputIcons() {
    const inputs = document.querySelectorAll('input[type="date"], input[type="time"], input[type="datetime-local"]');
    if (!inputs.length) return;

    const calendarSvg = `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <rect x="3.5" y="5.5" width="17" height="15" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/>
            <line x1="3.5" y1="9.5" x2="20.5" y2="9.5" stroke="currentColor" stroke-width="1.8"/>
            <line x1="8" y1="3.5" x2="8" y2="7.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <line x1="16" y1="3.5" x2="16" y2="7.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <rect x="7.2" y="12" width="2.2" height="2.2" rx="0.5" fill="currentColor"/>
            <rect x="11" y="12" width="2.2" height="2.2" rx="0.5" fill="currentColor"/>
            <rect x="14.8" y="12" width="2.2" height="2.2" rx="0.5" fill="currentColor"/>
        </svg>`;

    const clockSvg = `
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/>
            <line x1="12" y1="12" x2="12" y2="7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <line x1="12" y1="12" x2="15.5" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>`;

    inputs.forEach((input) => {
        if (input.dataset.iconEnhanced === '1') return;
        const parent = input.parentElement;
        if (!parent) return;

        const wrapper = document.createElement('span');
        wrapper.className = 'temporal-input-wrap';

        parent.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const icon = document.createElement('span');
        icon.className = 'temporal-input-icon';

        const type = input.getAttribute('type');
        if (type === 'date') {
            icon.classList.add('icon-calendar');
            icon.innerHTML = calendarSvg;
        } else {
            icon.classList.add('icon-clock');
            icon.innerHTML = clockSvg;
        }

        wrapper.appendChild(icon);
        input.dataset.iconEnhanced = '1';
    });
}

// Timer key dinámico (antes era constante con userId nulo)
function getTimerKey() {
    const uid = sessionStorage.getItem('user_id');
    return uid ? `attendanceTimerState_${uid}` : null;
}

// --- Utility Functions ---
async function apiFetch(endpoint, method = 'GET', data = null, contentType = 'json') {
    const url = `${API_BASE_URL}/${endpoint}`;
    const options = { method, credentials: 'same-origin' };

    if (data) {
        if (contentType === 'json') {
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(data);
        } else if (contentType === 'form') {
            options.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            options.body = new URLSearchParams(data).toString();
        }
    }

    if (!options.headers) options.headers = {};

    // Agregar CSRF token a requests de escritura
    if (method !== 'GET' && method !== 'HEAD') {
        const csrfToken = sessionStorage.getItem('csrf_token') || '';
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
    }

    try {
        const response = await fetch(url, options);
        let payload = null;
        try {
            payload = await response.json();
        } catch (_) {
            payload = null;
        }
        if (!response.ok || !payload || payload.ok !== true) {
            const message = payload?.error?.message || `HTTP error! Status: ${response.status}`;
            const err = new Error(message);
            err.code = payload?.error?.code;
            err.details = payload?.error?.details;
            throw err;
        }
        return { ...payload.data, success: true };
    } catch (error) {
        console.error('API Fetch Error:', error);
        throw error;
    }
}

function formatUtcAsLocal(utcString) {
    if (!utcString) return { date: 'N/A', time: 'N/A' };
    // Ensure 'Z' is appended if timezone is missing, to treat it as UTC
    const date = new Date(utcString.includes('Z') ? utcString : utcString.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) return { date: 'Invalid Date', time: 'Invalid Time' };

    const pad = (v) => String(v).padStart(2, '0');
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());
    const seconds = pad(date.getSeconds());

    return {
        date: `${year}-${month}-${day}`,
        time: `${hours}:${minutes}:${seconds}`
    };
}

function formatLocalDateTimeString(value) {
    if (!value) return { date: 'N/A', time: 'N/A' };
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    if (isNaN(date.getTime())) return { date: 'Invalid Date', time: 'Invalid Time' };

    const pad = (v) => String(v).padStart(2, '0');
    return {
        date: `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
        time: `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
    };
}

function formatRecordDateTime(record, value) {
    // Manual entries are captured in local wall-clock time; avoid UTC shift on display.
    if (record && record.entry_source === 'manual') {
        return formatLocalDateTimeString(value);
    }
    return formatUtcAsLocal(value);
}

function formatDurationHours(totalMinutes) {
    if (totalMinutes === null || totalMinutes === undefined || totalMinutes === '') {
        return 'N/A';
    }

    const minutesValue = Number(totalMinutes);
    if (!Number.isFinite(minutesValue)) {
        return 'N/A';
    }

    const safeMinutes = Math.max(0, Math.round(minutesValue));
    if (safeMinutes === 0) {
        return '0m';
    }

    const hours = Math.floor(safeMinutes / 60);
    const minutes = safeMinutes % 60;

    if (hours === 0) {
        return `${minutes}m`;
    }

    if (minutes === 0) {
        return `${hours}h`;
    }

    return `${hours}h ${minutes}m`;
}

function formatLocalDateTime(date) {
    const pad = (value) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

function formatClockFromSeconds(totalSeconds) {
    const safeSeconds = Number.isFinite(totalSeconds) ? Math.max(0, Math.floor(totalSeconds)) : 0;
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;
    return [hours, minutes, seconds].map((unit) => String(unit).padStart(2, '0')).join(':');
}

function stopActiveTimersTicker() {
    if (activeTimersTicker) {
        clearInterval(activeTimersTicker);
        activeTimersTicker = null;
    }
    activeTimersState.clear();
}

// --- Sincronización del Timer Personal ---
async function pollUserTimer() {
    try {
        const data = await apiFetch('timers/me');
        syncLocalTimerWithBackend(data.timer || null);
    } catch (error) {
        console.error('Timer sync poll failed:', error);
    }
}

function startTimerSyncLoop() {
    const role = normalizeRole(sessionStorage.getItem('user_role'));
    if (role === 'user' || role === 'special') {
        stopTimerSyncLoop();
        pollUserTimer();
        timerSyncInterval = setInterval(pollUserTimer, 5000);
    }
}

function startActiveTimersTicker(timers, serverTimeIsoString) {
    stopActiveTimersTicker();
    if (!timers || timers.length === 0) return;

    const syncTimestamp = Date.now();

    timers.forEach((timer) => {
        const id = Number(timer.id);
        if (!Number.isFinite(id)) return;

        const baseSeconds = Number(timer.duration_seconds) || 0;
        const status = Number(timer.status);

        activeTimersState.set(id, {
            baseSeconds,
            status,
            syncTimestamp,
        });

        const row = document.querySelector(`[data-timer-row="${id}"]`);
        if (!row) return;

        const durationCell = row.querySelector('[data-field="duration"]');
        if (durationCell) {
            const initialDisplay = timer.duration_display || formatClockFromSeconds(baseSeconds);
            durationCell.textContent = initialDisplay;
        }
    });

    activeTimersTicker = setInterval(() => {
        const now = Date.now();
        activeTimersState.forEach((state, id) => {
            const row = document.querySelector(`[data-timer-row="${id}"]`);
            if (!row) return;

            const durationCell = row.querySelector('[data-field="duration"]');
            if (!durationCell) return;

            let displaySeconds = state.baseSeconds;
            if (state.status === 1) {
                const elapsedSinceSync = Math.max(0, Math.floor((now - state.syncTimestamp) / 1000));
                displaySeconds = state.baseSeconds + elapsedSinceSync;
            }

            durationCell.textContent = formatClockFromSeconds(displaySeconds);
        });
    }, 1000);
}

// --- Authentication ---
function checkAuthAndRedirect() {
    const uid = sessionStorage.getItem('user_id');
    const path = window.location.pathname.toLowerCase();
    if (!uid && !path.includes('/pages/login/login.html')) {
        window.location.href = appUrl('/pages/login/login.html');
    }
}

// --- Logout ---
function stopTimerSyncLoop() {
    if (timerSyncInterval) {
        clearInterval(timerSyncInterval);
        timerSyncInterval = null;
    }
}

// --- Polling para el panel de Timers Activos (Admin/Special) ---
function stopActiveTimersPolling() {
    if (activeTimersInterval) {
        clearInterval(activeTimersInterval);
        activeTimersInterval = null;
    }
}

function startActiveTimersPolling() {
    const role = normalizeRole(sessionStorage.getItem('user_role'));
    if (role === 'admin' || role === 'special') {
        stopActiveTimersPolling();
        activeTimersInterval = setInterval(loadActiveTimers, 7000);
    }
}

function logout() {
    sessionStorage.clear();
    if (timerInterval) clearInterval(timerInterval);
    stopTimerSyncLoop();
    stopActiveTimersPolling();
    window.location.href = appUrl('/pages/');
}


// --- UI Management ---
function showSection(sectionId) {
    let target = sectionId;
    const role = normalizeRole(sessionStorage.getItem('user_role'));

    document.querySelectorAll('.sidebar a[id^="nav-"]').forEach((link) => {
        link.classList.remove('active');
    });

    if (role === 'admin' && (sectionId === 'scan-section' || sectionId === 'special-user-records-section')) {
        target = 'dashboard-section';
    }

    ['scan-section', 'records-section', 'special-user-records-section', 'dashboard-section', 'admin-tools-section', 'users-section', 'projects-section', 'special-tools-section', 'report-absence-section', 'absence-records-section', 'manual-attendance-section']
        .forEach((id) => {
            const section = document.getElementById(id);
            if (section) section.style.display = (id === target) ? 'block' : 'none';
        });

    if (target === 'scan-section' && !qrScanner) {
        // Scanner se inicia manualmente
    } else if (target !== 'scan-section' && qrScanner) {
        stopQrScanner();
    }

    stopActiveTimersTicker();
    stopActiveTimersPolling();

    const navMap = {
        'scan-section': role === 'special' ? 'nav-scan-special' : 'nav-scan',
        'records-section': role === 'special' ? 'nav-my-records-special' : (role === 'admin' ? 'nav-all-records' : 'nav-my-records'),
        'special-user-records-section': 'nav-user-records-special',
        'dashboard-section': 'nav-dashboard',
        'admin-tools-section': 'nav-admin-tools',
        'users-section': 'nav-users',
        'projects-section': 'nav-projects',
        'special-tools-section': role === 'admin' ? 'nav-timer-control' : 'nav-special-tools',
        'report-absence-section': role === 'special' ? 'nav-report-absence-special' : 'nav-report-absence',
        'absence-records-section': 'nav-absence-records',
        'manual-attendance-section': 'nav-manual-attendance'
    };
    const activeNav = document.getElementById(navMap[target] || '');
    if (activeNav) activeNav.classList.add('active');

    if (target === 'dashboard-section') {
        loadDashboardData();
    } else if (target === 'records-section') {
        if (role === 'admin') loadUsersForAdminFilter();
    } else if (target === 'users-section') {
        loadUsers();
    } else if (target === 'projects-section') {
        loadProjects();
    } else if (target === 'admin-tools-section') {
        loadProjectsForQrGeneration();
        loadGeneratedQRs();
    } else if (target === 'special-tools-section') {
        loadActiveTimers();
        startActiveTimersPolling();
    } else if (target === 'special-user-records-section') {
        prepareSpecialUserRecordsView();
    } else if (target === 'report-absence-section') {
        if (typeof loadProjectsForAbsenceForm === 'function') loadProjectsForAbsenceForm();
        if (typeof loadUserAbsenceHistory === 'function') loadUserAbsenceHistory();
    } else if (target === 'absence-records-section') {
        if (typeof loadUsersForAbsenceFilter === 'function') loadUsersForAbsenceFilter();
        if (typeof loadProjectsForAbsenceFilter === 'function') loadProjectsForAbsenceFilter();
        if (typeof loadAdminAbsences === 'function') loadAdminAbsences();
        if (typeof loadAbsenceSummary === 'function') loadAbsenceSummary();
    } else if (target === 'manual-attendance-section') {
        if (typeof initManualAttendance === 'function') initManualAttendance();
        if (typeof populateManualDropdowns === 'function') populateManualDropdowns();
    }

    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.style.display = 'none';
        document.body.classList.remove('sidebar-open');
    }
}

function configureRoleUI(role) {
    const resolved = normalizeRole(role);

    const toggle = (selector, visible) => {
        document.querySelectorAll(selector).forEach(el => {
            el.style.display = visible ? '' : 'none';
        });
    };

    toggle('.admin-only', resolved === 'admin');
    toggle('.special-only', resolved === 'special');
    toggle('.user-only', resolved === 'user');
}

// --- Timer Management ---
function getTimerState() {
    const key = getTimerKey();
    const state = key ? localStorage.getItem(key) : null;
    const parsedState = state ? JSON.parse(state) : {
        baseDurationSeconds: 0,
        lastSyncTimestamp: 0,
        status: 3,
        timerId: null
    };
    return parsedState;
}

function saveTimerState(state) {
    const key = getTimerKey();
    if (!key) return;
    localStorage.setItem(key, JSON.stringify(state));
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    const key = getTimerKey();
    if (key) localStorage.removeItem(key);

    const disp = document.getElementById('timer-display');
    if (disp) disp.style.display = 'none';

    const clock = document.getElementById('timer-clock');
    if (clock) clock.textContent = '00:00:00';
}

function updateTimerDisplay() {
    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        const state = getTimerState();
        if (state.status !== 1) {
            clearInterval(timerInterval);
            return;
        }

        const elapsedSinceSync = (Date.now() - state.lastSyncTimestamp) / 1000;
        const currentDuration = state.baseDurationSeconds + elapsedSinceSync;

        const el = document.getElementById('timer-clock');
        if (el) {
            el.textContent = formatClockFromSeconds(currentDuration);
        } else {
            clearInterval(timerInterval);
        }
    }, 1000);
}

function applyLocalTimerAction() {
    pollUserTimer();
}

function syncLocalTimerWithBackend(timer) {
    const role = normalizeRole(sessionStorage.getItem('user_role'));
    if (role !== 'user' && role !== 'special') return;

    const uidRaw = sessionStorage.getItem('user_id');
    const uid = uidRaw ? Number(uidRaw) : null;
    if (!uid) return;

    const timerDisplay = document.getElementById('timer-display');
    const timerClock = document.getElementById('timer-clock');

    const timerUserId = timer && timer.user_id ? Number(timer.user_id) : null;
    if (!timer || timerUserId !== uid) {
        stopTimer();
        return;
    }

    const status = Number(timer.status);
    const durationSeconds = Number(timer.duration_seconds) || 0;

    const state = {
        baseDurationSeconds: durationSeconds,
        lastSyncTimestamp: Date.now(),
        status: status,
        timerId: timer.id ?? null,
    };
    saveTimerState(state);

    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    if (status === 1) { // Running
        if (timerDisplay) timerDisplay.style.display = 'block';
        if (timerClock) timerClock.textContent = formatClockFromSeconds(durationSeconds);
        updateTimerDisplay();
    } else if (status === 2) { // Paused
        if (timerDisplay) timerDisplay.style.display = 'block';
        if (timerClock) timerClock.textContent = formatClockFromSeconds(durationSeconds);
    } else { // Status is 3 (stopped) or anything else
        stopTimer();
    }
}


// --- QR Scanner ---
function stopQrScanner() {
    try {
        if (qrScanner) {
            qrScanner.stop();
            qrScanner.destroy();
        }
    } catch (_) { }
    qrScanner = null;
}

function initQrScanner() {
    const video = document.getElementById('qr-video');
    const scanResultElement = document.getElementById('scan-result');

    if (video) {
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        video.setAttribute('muted', '');
        video.muted = true;
    }

    if (!video) {
        console.error("QR video element not found.");
        if (scanResultElement) scanResultElement.textContent = 'Error: QR video element not found.';
        return;
    }

    const startScanner = () => {
        if (qrScanner) {
            qrScanner.stop();
            qrScanner.destroy();
            qrScanner = null;
        }

        try {
            qrScanner = new ZXing.BrowserMultiFormatReader();
            qrScanner.decodeFromVideoDevice(null, video, (result, err) => {
                if (result) {
                    qrScanner.reset();
                    if (scanResultElement) scanResultElement.textContent = `QR Scanned. Processing...`;
                    try {
                        const qrData = JSON.parse(result.text);
                        handleQrScan(qrData);
                    } catch (e) {
                        const msgEl = document.getElementById('register-message');
                        if (msgEl) {
                            msgEl.textContent = 'Error: Invalid QR content.';
                            msgEl.className = 'error-message';
                        }
                    }
                    setTimeout(() => {
                        if (scanResultElement) scanResultElement.textContent = 'Waiting for scan...';
                        const msg = document.getElementById('register-message');
                        if (msg) msg.textContent = '';
                    }, 3000);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error('Error decoding QR:', err);
                    if (scanResultElement) scanResultElement.textContent = 'Error decoding QR. Please try again.';
                }
            });
        } catch (e) {
            console.error('Error during ZXing constructor or decodeFromVideoDevice:', e);
            if (scanResultElement) scanResultElement.textContent = 'Error: Failed to configure QR scanner.';
        }
    };

    if (typeof ZXing === 'undefined' || typeof ZXing.BrowserMultiFormatReader === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/@zxing/library@latest/umd/index.min.js';
        script.onload = startScanner;
        script.onerror = () => {
            console.error('Failed to load ZXing library.');
            if (scanResultElement) scanResultElement.textContent = 'Error: Could not load QR scanner library.';
        };
        document.head.appendChild(script);
    } else {
        startScanner();
    }
}

async function handleQrScan(qrData) {
    const { project_id, project_qr_id } = qrData || {};
    const messageEl = document.getElementById('register-message');

    if (!project_id && !project_qr_id) {
        if (messageEl) {
            messageEl.textContent = 'Invalid QR code. Project information is missing.';
            messageEl.className = 'error-message';
        }
        return;
    }

    const projectInput = document.getElementById('scanned-project-id');
    const projectQrInput = document.getElementById('scanned-project-qr-id');
    if (projectInput) projectInput.value = project_id ?? '';
    if (projectQrInput) projectQrInput.value = project_qr_id ?? '';

    const modal = document.getElementById('action-selection-modal');
    if (modal) modal.style.display = 'block';
}

async function registerAttendance(action) {
    const modal = document.getElementById('action-selection-modal');
    if (modal) modal.style.display = 'none';

    const messageEl = document.getElementById('register-message');
    const uid = sessionStorage.getItem('user_id');
    const projectIdInput = document.getElementById('scanned-project-id');
    const projectQrIdInput = document.getElementById('scanned-project-qr-id');
    const projectIdRaw = projectIdInput ? projectIdInput.value : '';
    const projectQrIdRaw = projectQrIdInput ? projectQrIdInput.value : '';
    const projectId = projectIdRaw !== '' ? Number(projectIdRaw) : null;
    const projectQrId = projectQrIdRaw !== '' ? Number(projectQrIdRaw) : null;

    // --- Geofence: get user location first ---
    let userCoords = null;
    try {
        userCoords = await getUserLocation();
    } catch (locError) {
        // Show overlay error for location issues
        if (typeof showLocationRequiredError === 'function') {
            showLocationRequiredError();
        }
        if (messageEl) {
            messageEl.textContent = 'La ubicación es obligatoria para registrar asistencia. Activa la geolocalización e intenta de nuevo.';
            messageEl.className = 'error-message';
        }
        return;
    }

    const userLocation = `${userCoords.lat},${userCoords.lng}`;

    // Fetch project geofence info for mini-map display
    let projectGeo = null;
    const resolvedProjectId = projectId || null;
    if (resolvedProjectId) {
        try {
            const pData = await apiFetch(`projects/${resolvedProjectId}`);
            if (pData.project && pData.project.latitude && pData.project.longitude) {
                projectGeo = {
                    lat: parseFloat(pData.project.latitude),
                    lng: parseFloat(pData.project.longitude),
                    radius: parseInt(pData.project.geofence_radius, 10) || 100,
                };
            }
        } catch (_) { /* non-critical */ }
    }

    const now = new Date();
    const payload = {
        user_id: uid,
        location: userLocation,
        type: action,
        project_qr_id: projectQrId,
        project_id: projectId,
        client_time_local: formatLocalDateTime(now),
        client_time_iso: now.toISOString(),
        client_timestamp: now.getTime(),
        client_timezone_offset: now.getTimezoneOffset()
    };

    try {
        let response = null;
        try {
            response = await apiFetch('attendance', 'POST', payload);
        } catch (error) {
            if (action === 'entry' && error.code === 'late_reason_required') {
                const reason = prompt('Llegaste tarde. Escribe una justificación para continuar:');
                if (!reason || !reason.trim()) {
                    if (messageEl) {
                        messageEl.textContent = 'Debes escribir una justificación para registrar una entrada tardía.';
                        messageEl.className = 'error-message';
                    }
                    return;
                }
                payload.late_reason = reason.trim();
                response = await apiFetch('attendance', 'POST', payload);
            } else {
                throw error;
            }
        }

        const successMsg = response.message || 'Asistencia registrada exitosamente.';
        if (typeof showClockInSuccess === 'function') {
            showClockInSuccess(
                successMsg,
                { lat: userCoords.lat, lng: userCoords.lng },
                projectGeo
            );
        }

        if (messageEl) {
            messageEl.textContent = successMsg;
            messageEl.className = 'success-message';
        }

        applyLocalTimerAction();

        const recordsSection = document.getElementById('records-section');
        if (recordsSection && recordsSection.style.display === 'block') {
            const normalizedRole = normalizeRole(sessionStorage.getItem('user_role'));
            loadAttendanceRecords(normalizedRole === 'admin' ? null : uid);
        }
    } catch (error) {
        if (error.code === 'outside_geofence' && typeof showGeofenceError === 'function') {
            showGeofenceError(error.message, error.details || null);
        } else if (error.code === 'geofence_error' && typeof showLocationRequiredError === 'function') {
            showGeofenceError(error.message, null);
        } else {
            if (messageEl) {
                messageEl.textContent = error.message;
                messageEl.className = 'error-message';
            }
        }
    }
}



async function generateQrCode() {
    const projectSelectElement = document.getElementById('project-select');
    const qrCodeDisplay = document.getElementById('qr-code-display');
    const qrMessage = document.getElementById('qr-message');

    if (!projectSelectElement) {
        if (qrMessage) qrMessage.textContent = 'Error: Project selection element not found.';
        return;
    }

    const projectId = projectSelectElement.value;
    const entryTimeRequired = document.getElementById('qr-entry-time')?.value || '';
    const exitTimeOptional = document.getElementById('qr-exit-time')?.value || '';

    if (!projectId) {
        if (qrMessage) qrMessage.textContent = 'Please select a project.';
        return;
    }
    if (!entryTimeRequired) {
        if (qrMessage) qrMessage.textContent = 'Entry time is required.';
        return;
    }

    try {
        const response = await apiFetch('qrs', 'POST', {
            project_id: projectId,
            entry_time_required: entryTimeRequired,
            exit_time_optional: exitTimeOptional || null
        });
        if (qrCodeDisplay) qrCodeDisplay.innerHTML = '';
        new QRCode(qrCodeDisplay, {
            text: response.qr_content,
            width: 256,
            height: 256,
        });
        if (qrMessage) qrMessage.textContent = 'QR code generated successfully!';
        const projectName = projectSelectElement.options[projectSelectElement.selectedIndex].text;
        downloadSpecificQr(response.qr_content, `${projectName}_QR`);
    } catch (error) {
        if (qrMessage) qrMessage.textContent = `Connection error: ${error.message}`;
    }
}

// --- Data Loading and Display ---
function getAttendanceMockRecords() {
    const now = new Date();
    return [{
        id: -1,
        username: 'demo-user',
        type: 'entry',
        entry_time: now.toISOString(),
        original_time: now.toISOString(),
        created_at: now.toISOString()
    }];
}

async function loadAttendanceRecords(uid = null) {
    const recordsBody = document.getElementById('attendance-records-body');
    if (!recordsBody) return;

    const role = normalizeRole(sessionStorage.getItem('user_role'));
    const endpoint = role === 'admin' && !uid ? 'attendance?all=true' : `attendance?user_id=${uid || sessionStorage.getItem('user_id')}`;

    try {
        const data = await apiFetch(endpoint);
        attendanceAllRecords = Array.isArray(data.records) ? data.records : [];
        attendancePage = 1;
        if (attendanceAllRecords.length) {
            const withDates = attendanceAllRecords
                .map(r => new Date(r.entry_time || r.original_time || r.created_at || Date.now()))
                .filter(d => !Number.isNaN(d.getTime()))
                .sort((a, b) => b - a);
            if (withDates.length) {
                const latest = withDates[0];
                attendanceCalendarDate = new Date(latest.getFullYear(), latest.getMonth(), latest.getDate());
            }
            renderAttendanceCalendar(attendanceAllRecords);
        } else {
            const mock = getAttendanceMockRecords();
            attendanceAllRecords = mock;
            attendanceCalendarDate = new Date(mock[0].entry_time);
            renderAttendanceCalendar(attendanceAllRecords);
        }
        renderAttendancePage();
    } catch (_) {
        recordsBody.innerHTML = `<tr><td colspan="11">Error loading records.</td></tr>`;
        const mock = getAttendanceMockRecords();
        attendanceAllRecords = mock;
        attendanceCalendarDate = new Date(mock[0].entry_time);
        renderAttendancePage();
    }
}

function getAttendanceStatusBadge(record) {
    if (record.type === 'absence') return '<span class="status-pill status-absence">Ausencia</span>';
    if (record.type !== 'entry') return '<span class="status-pill status-neutral">—</span>';
    if (record.late_reason) return '<span class="status-pill status-late">Tardanza</span>';
    return '<span class="status-pill status-ontime">A tiempo</span>';
}

function renderAttendancePage() {
    let filteredRecords = attendanceAllRecords;
    if (attendanceFilterText) {
        filteredRecords = attendanceAllRecords.filter(record => {
            const searchable = [
                record.username,
                record.location,
                record.type,
                record.project_name || '',
                record.entry_source || ''
            ].join(' ').toUpperCase();
            return searchable.includes(attendanceFilterText);
        });
    }

    renderAttendanceCalendar(filteredRecords);
}

function buildAttendanceDatesForMonth(year, month) {
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const dates = [];
    for (let day = 1; day <= daysInMonth; day += 1) {
        dates.push(new Date(year, month, day));
    }
    return dates;
}

function getStartOfWeek(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = (day + 6) % 7; // Monday start
    d.setDate(d.getDate() - diff);
    d.setHours(0, 0, 0, 0);
    return d;
}

function formatRecordValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    return String(value);
}

function openAttendanceRecordModal(records) {
    const modal = document.getElementById('attendance-record-modal');
    const body = document.getElementById('attendance-record-modal-body');
    const feedback = document.getElementById('attendance-record-modal-feedback');
    if (!modal || !body) return;

    attendanceModalRecordsById = new Map();
    (records || []).forEach((record) => {
        if (record && record.id !== undefined && record.id !== null) {
            attendanceModalRecordsById.set(String(record.id), record);
        }
    });

    if (feedback) {
        feedback.textContent = '';
        feedback.className = 'attendance-modal-feedback';
    }

    body.innerHTML = (records || []).map((record, idx) => {
        const entry = formatRecordDateTime(record, record.entry_time || record.original_time);
        const exit = formatRecordDateTime(record, record.exit_time || record.rounded_time);
        const evidence = record.evidence_url || record.evidence || record.photo_url || record.image_url || '';
        const recordId = record?.id !== undefined && record?.id !== null ? String(record.id) : '';

        return `
            <div class="attendance-record-group" data-record-id="${recordId}">
                <h4>Record ${idx + 1}</h4>
                <div class="attendance-record-row"><div class="attendance-record-key">Employee</div><div class="attendance-record-value">${formatRecordValue(record.username)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Date</div><div class="attendance-record-value">${formatRecordValue(entry.date)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Entry Time</div><div class="attendance-record-value">${formatRecordValue(entry.time)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Exit Time</div><div class="attendance-record-value">${formatRecordValue(exit.time)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Duration</div><div class="attendance-record-value">${formatDurationHours(record.total_duration)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Location</div><div class="attendance-record-value">${formatRecordValue(record.location)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Type</div><div class="attendance-record-value">${formatRecordValue(record.type)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Project</div><div class="attendance-record-value">${formatRecordValue(record.project_name)}</div></div>
                <div class="attendance-record-row"><div class="attendance-record-key">Evidence</div><div class="attendance-record-value">${evidence ? `<a href="${evidence}" target="_blank" rel="noopener noreferrer">Open evidence</a>` : '—'}</div></div>
                <div class="attendance-record-actions">
                    <button type="button" class="attendance-action-btn attendance-action-edit" data-action="edit-record-modal" data-record-id="${recordId}" ${recordId ? '' : 'disabled'}>Edit</button>
                    <button type="button" class="attendance-action-btn attendance-action-delete" data-action="delete-record-modal" data-record-id="${recordId}" ${recordId ? '' : 'disabled'}>Delete</button>
                </div>
            </div>
        `;
    }).join('');

    modal.style.display = 'flex';
}

function getAttendanceRange() {
    const focus = new Date(attendanceCalendarDate);
    focus.setHours(0, 0, 0, 0);
    if (attendanceCalendarViewMode === 'day') {
        return { start: focus, days: 1 };
    }
    return { start: getStartOfWeek(focus), days: 7 };
}

function attendanceDateKey(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function getVisibleAttendanceDates(start, days) {
    const dates = [];
    for (let i = 0; i < days; i += 1) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        d.setHours(0, 0, 0, 0);
        dates.push(d);
    }
    return dates;
}

function transformAttendanceRecordsToMatrix(records, visibleDates) {
    const validRecords = Array.isArray(records) ? records : [];
    const dayKeys = visibleDates.map((d) => attendanceDateKey(d));
    const dayKeySet = new Set(dayKeys);
    const mapByEmployee = new Map();

    validRecords.forEach((record) => {
        const employeeName = (record.username || 'Unknown').trim() || 'Unknown';
        const employeeId = record.user_id || employeeName;
        const base = record.entry_time || record.original_time || record.created_at || record.date;
        const d = base ? new Date(base) : null;
        if (!d || Number.isNaN(d.getTime())) return;
        const dayKey = attendanceDateKey(d);
        if (!dayKeySet.has(dayKey)) return;

        if (!mapByEmployee.has(employeeId)) {
            const dayBucket = {};
            dayKeys.forEach((k) => { dayBucket[k] = []; });
            mapByEmployee.set(employeeId, {
                employeeId,
                employeeName,
                days: dayBucket
            });
        }

        mapByEmployee.get(employeeId).days[dayKey].push(record);
    });

    const matrix = Array.from(mapByEmployee.values())
        .sort((a, b) => a.employeeName.localeCompare(b.employeeName));

    matrix.forEach((row) => {
        dayKeys.forEach((k) => {
            row.days[k].sort((a, b) => {
                const da = new Date(a.entry_time || a.original_time || a.created_at || 0).getTime();
                const db = new Date(b.entry_time || b.original_time || b.created_at || 0).getTime();
                return da - db;
            });
        });
    });

    return matrix;
}

function attendanceCellSummary(records) {
    const count = Array.isArray(records) ? records.length : 0;
    if (!count) return '';
    if (count === 1) return 'record';
    return `${count} records`;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderAttendanceCalendar(records) {
    const grid = document.getElementById('attendance-calendar-grid');
    const title = document.getElementById('attendance-calendar-title');
    const dateInput = document.getElementById('attendance-focus-date');
    if (!grid || !title) return;

    const { start, days } = getAttendanceRange();
    const visibleDates = getVisibleAttendanceDates(start, days);
    const end = new Date(start);
    end.setDate(end.getDate() + days);

    if (dateInput) {
        dateInput.value = attendanceDateKey(attendanceCalendarDate);
    }

    const titleStart = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const titleEndBase = new Date(end);
    titleEndBase.setDate(titleEndBase.getDate() - 1);
    const titleEnd = titleEndBase.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    title.textContent = attendanceCalendarViewMode === 'day' ? titleStart : `${titleStart} - ${titleEnd}`;

    attendanceMatrixCache = transformAttendanceRecordsToMatrix(records, visibleDates);

    if (!attendanceMatrixCache.length) {
        grid.innerHTML = '<div class="attendance-calendar-empty">No records for selected range.</div>';
        return;
    }

    const todayKey = attendanceDateKey(new Date());
    const headers = visibleDates.map((d) => {
        const key = attendanceDateKey(d);
        const isToday = key === todayKey;
        const shortDate = d.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric' });
        const shortDay = d.toLocaleDateString('en-US', { weekday: 'short' });
        return `
            <div class="attendance-matrix-col attendance-matrix-col-header ${isToday ? 'is-today' : ''}" data-day-key="${key}">
                <div class="attendance-matrix-date">${shortDate}</div>
                <div class="attendance-matrix-day">${shortDay}</div>
            </div>
        `;
    }).join('');

    const rows = attendanceMatrixCache.map((employee) => {
        const cells = visibleDates.map((d) => {
            const dayKey = attendanceDateKey(d);
            const dayRecords = employee.days[dayKey] || [];
            const summary = attendanceCellSummary(dayRecords);
            const isToday = dayKey === todayKey;
            const cls = [
                'attendance-matrix-col',
                'attendance-matrix-cell',
                isToday ? 'is-today' : '',
                dayRecords.length ? 'has-records' : 'is-empty'
            ].filter(Boolean).join(' ');

            return `
                <button
                    type="button"
                    class="${cls}"
                    data-attendance-cell="1"
                    data-employee-id="${escapeHtml(employee.employeeId)}"
                    data-day-key="${dayKey}"
                    ${dayRecords.length ? '' : 'disabled'}
                >
                    ${summary ? `<span class="attendance-chip">${summary}</span>` : '<span class="attendance-chip attendance-chip-empty">—</span>'}
                </button>
            `;
        }).join('');

        return `
            <div class="attendance-matrix-row" data-employee-id="${escapeHtml(employee.employeeId)}">
                <div class="attendance-matrix-col attendance-matrix-employee">${escapeHtml(employee.employeeName)}</div>
                ${cells}
            </div>
        `;
    }).join('');

    grid.innerHTML = `
        <div class="attendance-matrix" role="grid" aria-label="Attendance matrix" style="--attendance-days:${days};">
            <div class="attendance-matrix-row attendance-matrix-head">
                <div class="attendance-matrix-col attendance-matrix-employee attendance-matrix-employee-head">Employee</div>
                ${headers}
            </div>
            <div class="attendance-matrix-body">${rows}</div>
        </div>
    `;
}

function exportAttendanceCsv() {
    const rows = attendanceAllRecords || [];
    if (!rows.length) return;

    const headers = ['User', 'Date', 'Location', 'Type', 'Status', 'Entry Time', 'Exit Time', 'Work Duration', 'Lunch Duration', 'Project'];
    const body = rows.map(record => {
        const entryTimeRaw = record.entry_time || record.original_time;
        const exitTimeRaw = record.exit_time || record.rounded_time;
        const localEntryTime = formatRecordDateTime(record, entryTimeRaw);
        const localExitTime = formatRecordDateTime(record, exitTimeRaw);
        const statusText = record.type === 'entry' ? (record.late_reason ? 'Tardanza' : 'A tiempo') : (record.type === 'absence' ? 'Ausencia' : '-');
        return [
            record.username,
            localEntryTime.date,
            record.location,
            record.type,
            statusText,
            localEntryTime.time,
            localExitTime.time,
            formatDurationHours(record.total_duration),
            formatDurationHours(record.lunch_duration),
            record.project_name || 'Sin proyecto'
        ];
    });

    const csv = [headers, ...body].map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_records.csv';
    a.click();
    URL.revokeObjectURL(url);
}

async function loadActiveTimers() {
    const tbody = document.getElementById('active-timers-body');
    const messageEl = document.getElementById('timer-control-message');
    const template = document.getElementById('active-timer-row-template');

    if (!tbody || !template) {
        console.error('Required elements for active timers not found.');
        return;
    }

    try {
        const data = await apiFetch('timers/active');
        if (messageEl) {
            messageEl.textContent = '';
            messageEl.className = '';
        }

        const timers = Array.isArray(data.timers) ? data.timers : [];

        // Clear existing content
        tbody.innerHTML = '';

        if (timers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No active timers.</td></tr>';
            stopActiveTimersTicker();
            return;
        }

        timers.forEach((timer) => {
            const timerId = Number(timer.id);
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('tr');

            row.dataset.timerRow = timerId;

            const localStartTime = formatUtcAsLocal(timer.start_time_display);
            const projectName = (timer.project_name || '').trim() || 'Sin proyecto';
            const statusCode = Number(timer.status);
            const statusLabel = statusCode === 1 ? 'Running' : statusCode === 2 ? 'Paused' : 'Stopped';

            row.querySelector('[data-label="User"]').textContent = timer.username;
            row.querySelector('[data-label="Project"]').textContent = projectName;
            row.querySelector('[data-label="Start Time"]').textContent = `${localStartTime.date} ${localStartTime.time}`;
            row.querySelector('[data-label="Duration"]').textContent = timer.duration_display;
            row.querySelector('[data-label="Status"]').textContent = statusLabel;

            const pauseButton = row.querySelector('button[data-action="paused"]');
            const resumeButton = row.querySelector('button[data-action="resumed"]');
            const stopButton = row.querySelector('button[data-action="stopped"]');

            pauseButton.dataset.id = timerId;
            resumeButton.dataset.id = timerId;
            stopButton.dataset.id = timerId;

            pauseButton.disabled = statusCode !== 1;
            resumeButton.disabled = statusCode !== 2;

            tbody.appendChild(clone);
        });

        startActiveTimersTicker(timers, data.server_time);

    } catch (error) {
        if (messageEl) {
            messageEl.textContent = error.message || 'Error loading timers.';
            messageEl.className = 'error-message';
        }
        tbody.innerHTML = '<tr><td colspan="6">Error loading timers.</td></tr>';
        stopActiveTimersTicker();
    }
}

async function updateTimerStatus(timerId, status) {
    const messageEl = document.getElementById('timer-control-message');
    if (!timerId || !status) return;

    const row = document.querySelector(`[data-timer-row="${timerId}"]`);
    if (!row) return;

    const buttons = Array.from(row.querySelectorAll('button[data-action]'));
    const statusCell = row.querySelector('td[data-label="Status"]');
    const originalStatusText = statusCell ? statusCell.textContent : '';
    const originalOpacity = row.style.opacity;

    buttons.forEach((btn) => { btn.disabled = true; });

    if (status === 'stopped') {
        row.style.opacity = '0.5';
    } else if (status === 'paused') {
        if (statusCell) statusCell.textContent = 'Pausing...';
    } else if (status === 'resumed') {
        if (statusCell) statusCell.textContent = 'Resuming...';
    }

    try {
        const now = new Date();
        const payload = {
            status: status,
            client_time_iso: now.toISOString()
        };
        const response = await apiFetch(`timers/${timerId}/status`, 'POST', payload);

        const successMessage = response.message || (status === 'paused' ? 'Timer paused.' : status === 'resumed' ? 'Timer resumed.' : 'Timer stopped.');
        if (messageEl) {
            messageEl.textContent = successMessage;
            messageEl.className = 'success-message';
            setTimeout(() => {
                if (messageEl.textContent === successMessage) {
                    messageEl.textContent = '';
                    messageEl.className = '';
                }
            }, 3000);
        }

        await loadActiveTimers();
    } catch (error) {
        if (messageEl) {
            messageEl.textContent = error.message || 'An error occurred.';
            messageEl.className = 'error-message';
        }

        buttons.forEach((btn) => { btn.disabled = false; });
        row.style.opacity = originalOpacity;
        if (statusCell) statusCell.textContent = originalStatusText;
    }
}

async function populateSpecialUsersSelect(selectEl) {
    if (!selectEl) return;
    try {
        const data = await apiFetch('users');

        const normalUsers = (data.users || []).filter(user => {
            const role = (user.role || '').toString().toLowerCase();
            return role === 'user';
        });

        if (normalUsers.length === 0) {
            selectEl.innerHTML = '<option value="">No users available</option>';
            return;
        }

        selectEl.innerHTML =
            '<option value="">Select a user</option>' +
            normalUsers
                .map(user => `<option value="${user.id}">${user.username}</option>`)
                .join('');
    } catch (error) {
        selectEl.innerHTML = '<option value="">Error loading users</option>';
        console.error('Error loading users for special view:', error);
    }
}


async function prepareSpecialUserRecordsView() {
    const select = document.getElementById('special-users-filter');
    const tbody = document.getElementById('special-user-records-body');
    if (!select || !tbody) return;

    if (!specialUsersLoaded) {
        await populateSpecialUsersSelect(select);
        specialUsersLoaded = true;
    }

    if (!select.value) {
        if (select.options.length > 1) {
            select.selectedIndex = 1;
        } else {
            tbody.innerHTML = '<tr><td colspan="9">Select a user to view records.</td></tr>';
            return;
        }
    }

    loadSpecialUserRecords(select.value);
}

async function loadSpecialUserRecords(userId) {
    const tbody = document.getElementById('special-user-records-body');
    if (!tbody || !userId) return;

    try {
        const data = await apiFetch(`attendance?user_id=${userId}`);

        if (!data.records || data.records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9">No records found.</td></tr>';
            return;
        }

        tbody.innerHTML = data.records.map(record => {
            const entryTimeRaw = record.entry_time || record.original_time;
            const exitTimeRaw = record.exit_time || record.rounded_time;
            const localEntryTime = formatRecordDateTime(record, entryTimeRaw);
            const localExitTime = formatRecordDateTime(record, exitTimeRaw);
            const projectNameRaw = (record.project_name ?? '').trim();
            const projectName = projectNameRaw !== '' ? projectNameRaw : 'Sin proyecto';
            return `
        <tr>
          <td data-label="User">${record.username}</td>
          <td data-label="Date">${localEntryTime.date}</td>
          <td data-label="Location">${record.location}</td>
          <td data-label="Type">${record.type}</td>
          <td data-label="Entry Time">${localEntryTime.time}</td>
          <td data-label="Exit Time">${localExitTime.time}</td>
          <td data-label="Duration (hours)">${formatDurationHours(record.total_duration)}</td>
          <td data-label="Project">${projectName}</td>
        </tr>
      `;
        }).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="9">${error.message || 'Error loading records.'}</td></tr>`;
    }
}

async function loadUsersForAdminFilter() {
    const selectElement = document.getElementById('admin-user-filter');
    if (!selectElement) return;
    try {
        const data = await apiFetch('users');
        selectElement.innerHTML = '<option value="">All Users</option>' + data.users.map(user => `<option value="${user.id}">${user.username}</option>`).join('');
    } catch (_) { }
}

async function loadUsersForHistoryFilter() {
    const selectElement = document.getElementById('history-user-filter');
    if (!selectElement) return;
    try {
        const data = await apiFetch('users');
        selectElement.innerHTML = '<option value="">Select User</option><option value="__all__">All Users</option>' + data.users.map(user => `<option value="${user.id}">${user.username}</option>`).join('');
    } catch (_) { }
}

async function loadDashboardData() {
    loadHoursChart();
    loadAllAttendanceLocationsOnMap();
    loadDashboardMetrics();
    loadUsersForHistoryFilter();
}

async function loadDashboardMetrics() {
    try {
        const data = await apiFetch('attendance?dashboard=1');
        const m = data.metrics || {};
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        setText('metric-active-employees', m.active_employees_today ?? 0);
        setText('metric-clockins', m.clockins_today ?? 0);
        setText('metric-absences', m.absences_today ?? 0);
        setText('metric-avg-hours', Number(m.avg_hours_week ?? 0).toFixed(2));
    } catch (_) {}
}

async function loadNotifications() {
    try {
        const data = await apiFetch('notifications?limit=20');
        const badge = document.getElementById('notifications-badge');
        const list = document.getElementById('notifications-list');
        if (!badge || !list) return;

        const unread = Number(data.unread_count || 0);
        if (unread > 0) {
            badge.style.display = 'inline-flex';
            badge.textContent = unread > 99 ? '99+' : String(unread);
        } else {
            badge.style.display = 'none';
            badge.textContent = '0';
        }

        const items = data.notifications || [];
        list.innerHTML = items.length
            ? items.map(n => `<li class="notification-item ${Number(n.is_read) ? 'read' : 'unread'}" data-notification-id="${n.id}">${n.message}<small>${new Date(n.created_at).toLocaleString()}</small></li>`).join('')
            : '<li class="notification-item read">No notifications</li>';
    } catch (_) {}
}

async function markNotificationRead(id) {
    try {
        await apiFetch(`notifications/${id}/read`, 'PUT', {});
        loadNotifications();
    } catch (_) {}
}

async function loadHoursChart(uid = null) {
    const canvas = document.getElementById('hours-chart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const role = normalizeRole(sessionStorage.getItem('user_role'));
    let endpoint = 'attendance?summary=true';
    if (role !== 'admin') {
        endpoint += `&user_id=${sessionStorage.getItem('user_id')}`;
    }

    try {
        const data = await apiFetch(endpoint);
        if (data.success) {
            if (hoursChart) hoursChart.destroy();
            const styles = getComputedStyle(document.documentElement);
            const chartStroke = styles.getPropertyValue('--clr-e63946').trim() || '#e63946';
            const chartText = styles.getPropertyValue('--clr-d8e0f4').trim() || '#d8e0f4';
            const chartGrid = styles.getPropertyValue('--clr-rgba-159-176-205-0-2').trim() || 'rgba(159,176,205,0.2)';
            const chartSurface = styles.getPropertyValue('--clr-rgba-255-255-255-0-05').trim() || 'rgba(255,255,255,0.05)';

            const gradient = ctx.createLinearGradient(0, 0, 0, 280);
            gradient.addColorStop(0, chartStroke);
            gradient.addColorStop(1, chartSurface);

            hoursChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.summary.labels,
                    datasets: [{
                        label: 'Hours Worked',
                        data: data.summary.data,
                        backgroundColor: gradient,
                        borderColor: chartStroke,
                        borderWidth: 1.5,
                        borderRadius: 10,
                        borderSkipped: false,
                        maxBarThickness: 46
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: chartText },
                            grid: { color: chartGrid }
                        },
                        x: {
                            ticks: { color: chartText },
                            grid: { color: chartGrid }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: chartText,
                                usePointStyle: true,
                                pointStyle: 'rectRounded'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,0.92)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: chartGrid,
                            borderWidth: 1
                        }
                    }
                }
            });
        } else {
            console.error('Error loading chart data:', data.message);
        }
    } catch (error) {
        console.error('Error fetching chart data:', error);
    }
}

// --- Admin Functions / Google Maps ---
window.initMaps = function () {
    geocoder = new google.maps.Geocoder();
    initAdminMap();
    initQrCodeMap();
};

function initAdminMap() {
    const mapCanvas = document.getElementById('user-locations-map-canvas');
    if (mapCanvas) {
        userLocationsMap = new google.maps.Map(mapCanvas, { center: { lat: -34.397, lng: 150.644 }, zoom: 2 });
    }
}

function initQrCodeMap() {
    const qrMapCanvas = document.getElementById('qr-map-canvas');
    if (qrMapCanvas) {
        qrLocationMap = new google.maps.Map(qrMapCanvas, { center: { lat: -34.397, lng: 150.644 }, zoom: 8 });
        qrLocationMap.addListener('click', (e) => {
            placeQrMarker(e.latLng);
        });

        const qrMapSearchInput = document.getElementById('qr-map-search-input');
        const searchBox = new google.maps.places.SearchBox(qrMapSearchInput);
        qrLocationMap.controls[google.maps.ControlPosition.TOP_LEFT].push(qrMapSearchInput);

        searchBox.addListener('places_changed', () => {
            const places = searchBox.getPlaces();
            if (places.length === 0) return;
            const bounds = new google.maps.LatLngBounds();
            places.forEach(place => {
                if (!place.geometry || !place.geometry.location) return;
                placeQrMarker(place.geometry.location);
                if (place.geometry.viewport) bounds.union(place.geometry.viewport);
                else bounds.extend(place.geometry.location);
            });
            qrLocationMap.fitBounds(bounds);
        });

        const currentBtn = document.getElementById('qr-map-current-location-button');
        if (currentBtn) {
            currentBtn.addEventListener('click', () => {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const pos = { lat: position.coords.latitude, lng: position.coords.longitude };
                            placeQrMarker(pos);
                            qrLocationMap.setCenter(pos);
                            qrLocationMap.setZoom(15);
                        },
                        () => { alert('Error: Geolocation service failed.'); }
                    );
                } else {
                    alert('Error: Your browser does not support geolocation.');
                }
            });
        }
    }
}

function placeQrMarker(latLng) {
    if (qrMapMarker) qrMapMarker.setMap(null);
    qrMapMarker = new google.maps.Marker({ position: latLng, map: qrLocationMap });
    qrLocationMap.panTo(latLng);
}

function clearDashboardMapLayers() {
    dashboardMapMarkers.forEach(m => m.setMap(null));
    dashboardMapMarkers = [];
    dashboardMapPolylines.forEach(p => p.setMap(null));
    dashboardMapPolylines = [];
}

function parseLocationString(value) {
    if (!value || typeof value !== 'string') return null;
    const parts = value.split(',').map(v => parseFloat(v.trim()));
    if (parts.length !== 2 || Number.isNaN(parts[0]) || Number.isNaN(parts[1])) return null;
    return { lat: parts[0], lng: parts[1] };
}

async function loadAllAttendanceLocationsOnMap() {
    if (!userLocationsMap) return;

    clearDashboardMapLayers();

    try {
        const data = await apiFetch('attendance?all=true&limit=400');
        const records = Array.isArray(data.records) ? data.records : [];

        const points = records
            .map(r => ({ ...r, coords: parseLocationString(r.location) }))
            .filter(r => r.coords);

        if (!points.length) {
            const msg = document.getElementById('map-message');
            if (msg) msg.textContent = 'No hay ubicaciones de entradas/salidas para mostrar aún.';
            return;
        }

        const bounds = new google.maps.LatLngBounds();

        points.forEach(point => {
            const isEntry = point.type === 'entry';
            const marker = new google.maps.Marker({
                position: point.coords,
                map: userLocationsMap,
                title: `${point.username} • ${point.type} • ${new Date(point.original_time).toLocaleString()}`,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 6,
                    fillColor: isEntry ? '#2ecc71' : '#e74c3c',
                    fillOpacity: 0.95,
                    strokeColor: '#ffffff',
                    strokeWeight: 1.5,
                }
            });

            const info = new google.maps.InfoWindow({
                content: `<div style="min-width:180px"><strong>${point.username}</strong><br>${point.type.toUpperCase()}<br>${new Date(point.original_time).toLocaleString()}</div>`
            });

            marker.addListener('click', () => info.open(userLocationsMap, marker));
            dashboardMapMarkers.push(marker);
            bounds.extend(point.coords);
        });

        userLocationsMap.fitBounds(bounds);
        const msg = document.getElementById('map-message');
        if (msg) msg.textContent = '';
    } catch (error) {
        const msg = document.getElementById('map-message');
        if (msg) msg.textContent = 'Error loading map points.';
    }
}

async function displayUserLocationHistory(uid, startDate, endDate) {
    if (!userLocationsMap) return;

    try {
        clearDashboardMapLayers();

        const params = new URLSearchParams();
        if (uid && uid !== '__all__') params.set('user_id', uid);
        if (startDate) params.set('start_date', startDate);
        if (endDate) params.set('end_date', endDate);

        const endpoint = `locations/history${params.toString() ? '?' + params.toString() : ''}`;
        const data = await apiFetch(endpoint);
        const history = Array.isArray(data.history) ? data.history : [];

        if (history.length > 0) {
            const points = history
                .map((p) => ({
                    lat: parseFloat(p.latitude),
                    lng: parseFloat(p.longitude),
                    username: p.username || `User #${p.user_id ?? ''}`,
                    timestamp: p.timestamp,
                }))
                .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));

            if (!points.length) {
                const msg = document.getElementById('map-message');
                if (msg) msg.textContent = 'No hay coordenadas válidas para mostrar.';
                return;
            }

            const drawPath = !!(uid && uid !== '__all__');
            if (drawPath) {
                const userPath = new google.maps.Polyline({
                    path: points.map((p) => ({ lat: p.lat, lng: p.lng })),
                    geodesic: true,
                    strokeColor: '#5ca7ff',
                    strokeOpacity: 0.95,
                    strokeWeight: 3
                });
                userPath.setMap(userLocationsMap);
                dashboardMapPolylines.push(userPath);
            }

            const bounds = new google.maps.LatLngBounds();
            points.forEach((point) => {
                const marker = new google.maps.Marker({
                    position: { lat: point.lat, lng: point.lng },
                    map: userLocationsMap,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 4,
                        fillColor: '#5ca7ff',
                        fillOpacity: 0.95,
                        strokeColor: '#ffffff',
                        strokeWeight: 1
                    },
                    title: `${point.username} • ${new Date(point.timestamp).toLocaleString()}`
                });
                dashboardMapMarkers.push(marker);
                bounds.extend({ lat: point.lat, lng: point.lng });
            });
            userLocationsMap.fitBounds(bounds);
            const msg = document.getElementById('map-message');
            if (msg) msg.textContent = '';
        } else {
            const msg = document.getElementById('map-message');
            if (msg) msg.textContent = 'Sin historial para ese filtro. Mostrando todas las entradas/salidas recientes.';
            loadAllAttendanceLocationsOnMap();
        }
    } catch (error) {
        const msg = document.getElementById('map-message');
        if (msg) msg.textContent = 'Error loading history. Showing default map points.';
        loadAllAttendanceLocationsOnMap();
    }
}

async function deleteRecord(recordId) {
    if (!confirm('Are you sure you want to delete this record?')) return;
    try {
        const response = await apiFetch(`attendance/${recordId}`, 'DELETE');
        alert(response.message);
        if (response.success) {
            const uid = sessionStorage.getItem('user_id');
            const role = normalizeRole(sessionStorage.getItem('user_role'));
            loadAttendanceRecords(role === 'admin' ? null : uid);
        }
    } catch (error) {
        alert(`Error deleting: ${error.message}`);
    }
}

function openAttendanceDeleteConfirm(recordId) {
    const confirmModal = document.getElementById('attendance-delete-confirm-modal');
    const message = document.getElementById('attendance-delete-confirm-message');
    if (!confirmModal) return;

    attendancePendingDeleteRecordId = String(recordId || '');
    const record = attendanceModalRecordsById.get(attendancePendingDeleteRecordId);
    if (message) {
        const user = record?.username || 'this employee';
        message.textContent = `Delete record #${attendancePendingDeleteRecordId} for ${user}? This action cannot be undone.`;
    }

    confirmModal.style.display = 'flex';
}

function closeAttendanceDeleteConfirm() {
    const confirmModal = document.getElementById('attendance-delete-confirm-modal');
    if (confirmModal) confirmModal.style.display = 'none';
    attendancePendingDeleteRecordId = null;
}

async function confirmDeleteAttendanceRecord() {
    const feedback = document.getElementById('attendance-record-modal-feedback');
    const recordId = attendancePendingDeleteRecordId;
    if (!recordId) return;

    try {
        await apiFetch(`attendance/${recordId}`, 'DELETE');
        closeAttendanceDeleteConfirm();

        const detailModal = document.getElementById('attendance-record-modal');
        if (detailModal) detailModal.style.display = 'none';

        const uid = sessionStorage.getItem('user_id');
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        await loadAttendanceRecords(role === 'admin' ? null : uid);

        if (feedback) {
            feedback.textContent = 'Record deleted successfully.';
            feedback.className = 'attendance-modal-feedback success';
        }
    } catch (error) {
        closeAttendanceDeleteConfirm();
        if (feedback) {
            feedback.textContent = `Error deleting record: ${error.message}`;
            feedback.className = 'attendance-modal-feedback error';
        }
    }
}

async function openEditModal(recordData) {
    attendanceEditingRecord = recordData || null;
    if (!attendanceEditingRecord) return;

    document.getElementById('edit-record-id').value = attendanceEditingRecord.id;
    document.getElementById('edit-user-id').value = '';
    document.getElementById('edit-type').value = attendanceEditingRecord.type || 'entry';
    document.getElementById('edit-location').value = attendanceEditingRecord.location || '';
    const entryTimeEdit = (attendanceEditingRecord.entry_time || attendanceEditingRecord.original_time || '');
    const exitTimeEdit = (attendanceEditingRecord.exit_time || attendanceEditingRecord.rounded_time || '');
    document.getElementById('edit-entry-time').value = entryTimeEdit.replace(' ', 'T').slice(0, 16);
    document.getElementById('edit-exit-time').value = exitTimeEdit.replace(' ', 'T').slice(0, 16);
    document.getElementById('edit-total-duration').value = ((Number(attendanceEditingRecord.total_duration || 0)) / 60).toFixed(2);
    document.getElementById('edit-lunch-duration').value = ((Number(attendanceEditingRecord.lunch_duration || 0)) / 60).toFixed(2);

    await Promise.all([
        populateEditUserSelect(attendanceEditingRecord.user_id),
        populateEditProjectSelect(attendanceEditingRecord.project_id ?? null),
    ]);

    document.getElementById('edit-record-modal').style.display = 'block';
}

async function populateEditUserSelect(selectedUserId = null) {
    const userSelect = document.getElementById('edit-user-id');
    if (!userSelect) return;
    userSelect.innerHTML = '';

    try {
        const data = await apiFetch('users');
        const users = data.users || [];
        users.forEach((u) => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.username;
            userSelect.appendChild(opt);
        });
        if (selectedUserId) userSelect.value = String(selectedUserId);
    } catch (error) {
        console.error('Error loading users for edit modal:', error);
    }
}

async function populateEditProjectSelect(selectedProjectId = null) {
    const projectSelect = document.getElementById('edit-project-id');
    if (!projectSelect) return;

    projectSelect.innerHTML = '<option value="">— Sin proyecto —</option>';

    try {
        const data = await apiFetch('projects');
        const projects = data.projects || [];
        projects.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            projectSelect.appendChild(opt);
        });

        if (selectedProjectId) {
            projectSelect.value = String(selectedProjectId);
        }
    } catch (error) {
        console.error('Error loading projects for edit modal:', error);
    }
}

// --- User Management Functions ---
async function loadUsers() {
    const usersTableBody = document.getElementById('users-table-body');
    if (!usersTableBody) return;

    try {
        const data = await apiFetch('users');
        if (data.success) {
            usersTableBody.innerHTML = data.users.map((user) => {
                const userId = Number(user.id) || 0;
                const encodedUsername = encodeURIComponent(user.username ?? '');
                const roleId = (user.role_id !== undefined && user.role_id !== null)
                    ? String(user.role_id)
                    : '';
                return `
        <tr>
          <td data-label="ID">${userId}</td>
          <td data-label="Username">${user.username}</td>
          <td data-label="Role">${user.role}</td>
          <td data-label="Actions">
            <button data-action="edit-user" data-id="${userId}" data-username="${encodedUsername}" data-role-id="${roleId}">Edit</button>
            <button data-action="delete-user" data-id="${userId}">Delete</button>
          </td>
        </tr>
        `;
            }).join('');
        } else {
            usersTableBody.innerHTML = `<tr><td colspan="4">Error loading users.</td></tr>`;
        }
    } catch (error) {
        usersTableBody.innerHTML = `<tr><td colspan="4">Connection error loading users.</td></tr>`;
    }
}

function showUserForm(action, user = {}) {
    const formContainer = document.getElementById('user-form-container');
    const formTitle = document.getElementById('user-form-title');
    const userIdInput = document.getElementById('user-id');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const roleInput = document.getElementById('role');

    if (!formContainer) return;

    formContainer.style.display = 'block';
    if (passwordInput) passwordInput.required = (action === 'create');

    loadRolesIntoSelect('role').then(() => {
        if (action === 'create') {
            if (formTitle) formTitle.textContent = 'Create';
            if (userIdInput) userIdInput.value = '';
            if (usernameInput) usernameInput.value = '';
            if (passwordInput) passwordInput.value = '';
            if (roleInput) roleInput.value = '';
        } else {
            if (formTitle) formTitle.textContent = 'Edit';
            if (userIdInput) userIdInput.value = user.id;
            if (usernameInput) usernameInput.value = user.username;
            if (passwordInput) passwordInput.value = '';
            if (roleInput) roleInput.value = user.role_id;
        }
    });
}

window.loadRolesIntoSelect = async function (selectId) {
    try {
        const data = await apiFetch('roles');
        if (data.success) {
            const roleSelect = document.getElementById(selectId);
            if (!roleSelect) return;
            roleSelect.innerHTML = '';
            data.roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                roleSelect.appendChild(option);
            });
        } else {
            console.error('Error loading roles:', data.message);
        }
    } catch (error) {
        console.error('Error fetching roles:', error);
    }
};

async function saveUser(e) {
    e.preventDefault();
    const uidInput = document.getElementById('user-id').value;
    const usernameInput = document.getElementById('username').value;
    const passwordInput = document.getElementById('password').value;
    const roleId = document.getElementById('role').value;
    const messageEl = document.getElementById('user-form-message');

    const payload = { username: usernameInput, role_id: roleId };
    if (passwordInput) payload.password = passwordInput;

    try {
        const response = uidInput
            ? await apiFetch(`users/${uidInput}`, 'PUT', payload)
            : await apiFetch('users', 'POST', payload);
        if (messageEl) {
            messageEl.textContent = response.message;
            messageEl.className = response.success ? 'success-message' : 'error-message';
        }
        if (response.success) {
            document.getElementById('user-form-container').style.display = 'none';
            loadUsers();
        }
    } catch (error) {
        if (messageEl) {
            messageEl.textContent = `Error: ${error.message}`;
            messageEl.className = 'error-message';
        }
    }
}

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    const messageEl = document.getElementById('user-form-message');
    try {
        const response = await apiFetch(`users/${id}`, 'DELETE');
        if (messageEl) {
            messageEl.textContent = response.message;
            messageEl.className = response.success ? 'success-message' : 'error-message';
        }
        if (response.success) loadUsers();
    } catch (error) {
        if (messageEl) {
            messageEl.textContent = `Error: ${error.message}`;
            messageEl.className = 'error-message';
        }
    }
}

// --- Project Management Functions ---
async function loadProjects() {
    const projectsTableBody = document.getElementById('projects-table-body');
    if (!projectsTableBody) return;

    try {
        const data = await apiFetch('projects');
        if (data.success) {
            projectsTableBody.innerHTML = data.projects.map(project => {
                const hasGeo = project.latitude && project.longitude;
                const geoStatus = hasGeo
                    ? `📍 ${project.geofence_radius}m`
                    : '<span style="opacity:0.5">Sin geocerca</span>';

                // Encode project data for edit button
                const projectData = encodeURIComponent(JSON.stringify({
                    id: project.id,
                    name: project.name,
                    latitude: project.latitude,
                    longitude: project.longitude,
                    geofence_radius: project.geofence_radius
                }));

                return `
        <tr>
          <td data-label="ID">${project.id}</td>
          <td data-label="Name">${project.name}</td>
          <td data-label="Geofence">${geoStatus}</td>
          <td data-label="Creation Date">${new Date(project.created_at).toLocaleDateString()}</td>
          <td data-label="Actions">
            <button data-action="edit-project" data-project-data="${projectData}">Edit</button>
            <button data-action="delete-project" data-project-id="${project.id}">Delete</button>
          </td>
        </tr>
      `;
            }).join('');
        } else {
            projectsTableBody.innerHTML = `<tr><td colspan="5">Error loading projects: ${data.message}</td></tr>`;
        }
    } catch (error) {
        projectsTableBody.innerHTML = `<tr><td colspan="5">Connection error loading projects.</td></tr>`;
    }
}

function showProjectForm(action, project = {}) {
    const formContainer = document.getElementById('project-form-container');
    const formTitle = document.getElementById('project-form-title');
    const projectIdInput = document.getElementById('project-id');
    const projectNameInput = document.getElementById('project-name');

    if (!formContainer) return;

    formContainer.style.display = 'block';

    if (action === 'create') {
        if (formTitle) formTitle.textContent = 'Create';
        if (projectIdInput) projectIdInput.value = '';
        if (projectNameInput) projectNameInput.value = '';
        // Init geofence map with no initial coordinates
        if (typeof initGeofenceConfigMap === 'function') {
            setTimeout(() => initGeofenceConfigMap(null), 100);
        }
    } else {
        if (formTitle) formTitle.textContent = 'Edit';
        if (projectIdInput) projectIdInput.value = project.id;
        if (projectNameInput) projectNameInput.value = project.name;
        // Init geofence map with existing coordinates
        const geoCoords = (project.latitude && project.longitude) ? {
            lat: parseFloat(project.latitude),
            lng: parseFloat(project.longitude),
            radius: parseInt(project.geofence_radius, 10) || 100
        } : null;
        if (typeof initGeofenceConfigMap === 'function') {
            setTimeout(() => initGeofenceConfigMap(geoCoords), 100);
        }
    }
}

async function saveProject(e) {
    e.preventDefault();
    const projectId = document.getElementById('project-id').value;
    const projectName = document.getElementById('project-name').value;
    const messageEl = document.getElementById('project-form-message');

    const payload = { name: projectName };

    // Include geofence config if available
    if (typeof getGeofenceConfig === 'function') {
        const geo = getGeofenceConfig();
        if (geo.latitude !== null && geo.longitude !== null) {
            payload.latitude = geo.latitude;
            payload.longitude = geo.longitude;
            payload.geofence_radius = geo.geofence_radius;
        }
    }

    try {
        let response;
        if (projectId) {
            // Update existing project via PUT
            response = await apiFetch(`projects/${projectId}`, 'PUT', payload);
        } else {
            // Create new project via POST
            response = await apiFetch('projects', 'POST', payload);
        }

        if (messageEl) {
            messageEl.textContent = response.message;
            messageEl.className = response.success ? 'success-message' : 'error-message';
        }
        if (response.success) {
            document.getElementById('project-form-container').style.display = 'none';
            if (typeof destroyGeofenceConfigMap === 'function') destroyGeofenceConfigMap();
            loadProjects();
            loadProjectsForQrGeneration();
        }
    } catch (error) {
        if (messageEl) {
            messageEl.textContent = `Error: ${error.message}`;
            messageEl.className = 'error-message';
        }
    }
}

async function deleteProject(id) {
    if (!confirm('Are you sure you want to delete this project? This will also delete associated QRs.')) return;
    const messageEl = document.getElementById('project-form-message');
    try {
        const response = await apiFetch(`projects/${id}`, 'DELETE');
        if (messageEl) {
            messageEl.textContent = response.message;
            messageEl.className = response.success ? 'success-message' : 'error-message';
        }
        if (response.success) {
            loadProjects();
            loadProjectsForQrGeneration();
        }
    } catch (error) {
        if (messageEl) {
            messageEl.textContent = `Error: ${error.message}`;
            messageEl.className = 'error-message';
        }
    }
}

async function loadProjectsForQrGeneration() {
    const selectElement = document.getElementById('project-select');
    if (!selectElement) return;

    try {
        const data = await apiFetch('projects');
        if (data.success) {
            selectElement.innerHTML = '<option value="">Select Project</option>' + data.projects.map(project => `<option value="${project.id}">${project.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Failed to load projects for QR generation', error);
    }
}

async function deleteQr(qrId) {
    if (!confirm('Are you sure you want to delete this QR?')) return;
    try {
        const response = await apiFetch(`qrs/${qrId}`, 'DELETE');
        alert(response.message);
        if (response.success) loadGeneratedQRs();
    } catch (error) {
        console.error('Error deleting QR:', error);
        alert(`Error deleting QR: ${error.message}`);
    }
}

async function loadGeneratedQRs() {
    const qrsTableBody = document.getElementById('generated-qrs-table-body');
    if (!qrsTableBody) return;

    try {
        const data = await apiFetch('qrs');
        if (data.success) {
            qrsTableBody.innerHTML = data.qrs.map(qr => `
        <tr>
          <td data-label="Project Name">${qr.project_name}</td>
          <td data-label="Action Type">${qr.action_type}</td>
          <td data-label="Location">${qr.location}</td>
          <td data-label="Generated At">${new Date(qr.created_at).toLocaleString()}</td>
          <td data-label="Actions">
            <button class="small-button copy-qr-btn" data-qr-content='${encodeURIComponent(qr.qr_content)}'>Copy</button>
            <button class="small-button download-qr-btn" data-qr-content='${encodeURIComponent(qr.qr_content)}' data-project-name='${qr.project_name}' data-action-type='${qr.action_type}'>Download</button>
            <button class="small-button delete-qr-btn" data-qr-id="${qr.id}">Delete</button>
          </td>
        </tr>
      `).join('');
        } else {
            qrsTableBody.innerHTML = `<tr><td colspan="5">Error loading generated QRs: ${data.message}</td></tr>`;
        }
    } catch (error) {
        qrsTableBody.innerHTML = `<tr><td colspan="5">Connection error loading generated QRs.</td></tr>`;
    }
}

function copyQrContent(content) {
    navigator.clipboard.writeText(content).then(() => {
        alert('QR Content copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy QR content: ', err);
        alert('Failed to copy QR content.');
    });
}

async function downloadSpecificQr(qrContent, filename) {
    const tempQrDiv = document.createElement('div');
    tempQrDiv.style.position = 'absolute';
    tempQrDiv.style.left = '-9999px';
    document.body.appendChild(tempQrDiv);

    if (typeof QRCode === 'undefined') {
        alert('QR Code library not loaded. Please try again.');
        tempQrDiv.remove();
        return;
    }

    new QRCode(tempQrDiv, { text: qrContent, width: 256, height: 256 });

    await new Promise(resolve => setTimeout(resolve, 100));
    const qrCanvas = tempQrDiv.querySelector('canvas');

    if (!qrCanvas) {
        console.error('QR code canvas not found in temp div.');
        alert('Error generating QR for download.');
        tempQrDiv.remove();
        return;
    }

    try {
        const imgData = qrCanvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.addImage(imgData, 'PNG', 10, 10, 60, 60);
        doc.save(`${filename}.pdf`);
    } catch (error) {
        console.error('Error during specific QR PDF generation:', error);
        alert('Error generating PDF: ' + error.message);
    } finally {
        tempQrDiv.remove();
    }
}

function handleCopyButtonClick(event) {
    const target = event.target;
    const qrContent = decodeURIComponent(target.dataset.qrContent);
    copyQrContent(qrContent);
}

function handleDownloadButtonClick(event) {
    const target = event.target;
    const qrContent = decodeURIComponent(target.dataset.qrContent);
    const projectName = target.dataset.projectName;
    const actionType = target.dataset.actionType;
    downloadSpecificQr(qrContent, `${projectName}_${actionType}`);
}

function filterTable(inputElementId, tableBodyId) {
    const input = document.getElementById(inputElementId);
    const filter = (input?.value || '').toUpperCase();
    const tableBody = document.getElementById(tableBodyId);
    if (!tableBody) return;
    const tr = tableBody.getElementsByTagName("tr");

    for (let i = 0; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName("td");
        let rowText = "";
        for (let j = 0; j < td.length; j++) {
            rowText += td[j].textContent || td[j].innerText;
        }
        tr[i].style.display = rowText.toUpperCase().indexOf(filter) > -1 ? "" : "none";
    }
}

// --- Location Tracking (evita registro fantasma) ---
function startLocationTracking() {
    const uid = sessionStorage.getItem('user_id');
    if (!uid) return;

    const path = window.location.pathname.toLowerCase();
    if (!path.includes('/pages/registros/registros.html') && !path.includes('/pages/users/admin/admin.html')) return;

    const THROTTLE_MINUTES = 5;
    const key = 'lastLocationLogTs';
    const now = Date.now();
    const last = Number(sessionStorage.getItem(key) || 0);
    if (last && (now - last) < THROTTLE_MINUTES * 60 * 1000) {
        // already logged recently
    }

    const logLocation = () => {
        const lastSend = Number(sessionStorage.getItem(key) || 0);
        if (lastSend && (Date.now() - lastSend) < THROTTLE_MINUTES * 60 * 1000) return;

        if (!navigator.geolocation) {
            console.error('Geolocation is not supported by this browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(async (position) => {
            const { latitude, longitude } = position.coords;
            try {
                await apiFetch('locations/log', 'POST', {
                    user_id: uid,
                    latitude,
                    longitude
                });
                sessionStorage.setItem(key, String(Date.now()));
            } catch (error) {
                console.error('Error logging location:', error);
            }
        }, (error) => {
            console.error('Geolocation error:', error);
        });
    };

    setTimeout(logLocation, 8000);
    setInterval(logLocation, 300000); // every 5 minutes
}

// --- Page Initialization ---
document.addEventListener('DOMContentLoaded', () => {
    checkAuthAndRedirect();
    enhanceTemporalInputIcons();

    const path = window.location.pathname.toLowerCase();

    // --- Login page ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm && path.includes('/pages/login/login.html')) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const usr = document.getElementById('username').value;
            const pass = document.getElementById('password').value;
            const messageEl = document.getElementById('loginMessage');
            try {
            const response = await apiFetch('auth/login', 'POST', { user: usr, pass: pass });
            if (response.success) {
                const user = response.user || {};
                const normalizedRole = normalizeRole(user.role);
                sessionStorage.setItem('user_id', user.id);
                sessionStorage.setItem('user_role', normalizedRole);
                sessionStorage.setItem('username', user.username);
                if (response.csrf_token) {
                    sessionStorage.setItem('csrf_token', response.csrf_token);
                }
                window.location.href = appUrl('/pages/registros/registros.html');
            } else {
                if (messageEl) messageEl.textContent = 'Login failed.';
            }
        } catch (error) {
            if (messageEl) messageEl.textContent = error.message;
        }
        });
        return;
    }

    // --- Internal Pages (Registros) ---
    userRole = normalizeRole(sessionStorage.getItem('user_role'));
    sessionStorage.setItem('user_role', userRole);
    userId = sessionStorage.getItem('user_id');
    username = sessionStorage.getItem('username');

    configureRoleUI(userRole);
    initThemeToggle();

    let defaultSection = 'scan-section';
    if (userRole === 'admin') {
        defaultSection = 'dashboard-section';
    } else if (userRole === 'special') {
        defaultSection = 'scan-section';
    }
    showSection(defaultSection);

    const greet = document.getElementById('user-greeting');
    if (greet && username) greet.textContent = `Hello, ${username}!`;
    const headerProfileName = document.getElementById('header-profile-name');
    if (headerProfileName && username) headerProfileName.textContent = username;

    const sidebar = document.querySelector('.sidebar');
    const openSidebarBtn = document.querySelector('.open-sidebar-button');
    const closeSidebarBtn = document.querySelector('.close-sidebar-button');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    if (openSidebarBtn && sidebar && sidebarBackdrop) {
        openSidebarBtn.addEventListener('click', () => {
            sidebar.classList.add('open');
            sidebarBackdrop.style.display = 'block';
            document.body.classList.add('sidebar-open');
        });
    }
    if (closeSidebarBtn && sidebar && sidebarBackdrop) {
        closeSidebarBtn.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sidebarBackdrop.style.display = 'none';
            document.body.classList.remove('sidebar-open');
        });
    }
    if (sidebarBackdrop && sidebar) {
        sidebarBackdrop.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sidebarBackdrop.style.display = 'none';
            document.body.classList.remove('sidebar-open');
        });
    }

    // --- Menu Event Listeners ---
    const logoutLink = document.getElementById('nav-logout');
    if (logoutLink) {
        logoutLink.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });
    }

    const dashboardLink = document.getElementById('nav-dashboard');
    if (dashboardLink) dashboardLink.addEventListener('click', (e) => { e.preventDefault(); showSection('dashboard-section'); });
    const allRecordsLink = document.getElementById('nav-all-records');
    if (allRecordsLink) allRecordsLink.addEventListener('click', (e) => { e.preventDefault(); showSection('records-section'); loadAttendanceRecords(); });
    const adminToolsLink = document.getElementById('nav-admin-tools');
    if (adminToolsLink) adminToolsLink.addEventListener('click', (e) => { e.preventDefault(); showSection('admin-tools-section'); });
    const usersLink = document.getElementById('nav-users');
    if (usersLink) usersLink.addEventListener('click', (e) => { e.preventDefault(); showSection('users-section'); });
    const projectsLink = document.getElementById('nav-projects');
    if (projectsLink) projectsLink.addEventListener('click', (e) => { e.preventDefault(); showSection('projects-section'); });
    const timerControlLink = document.getElementById('nav-timer-control');
    if (timerControlLink) timerControlLink.addEventListener('click', (e) => { e.preventDefault(); showSection('special-tools-section'); });

    const scanSpecialLink = document.getElementById('nav-scan-special');
    if (scanSpecialLink) scanSpecialLink.addEventListener('click', (e) => { e.preventDefault(); showSection('scan-section'); });

    const myRecordsSpecialLink = document.getElementById('nav-my-records-special');
    if (myRecordsSpecialLink) myRecordsSpecialLink.addEventListener('click', (e) => { e.preventDefault(); showSection('records-section'); loadAttendanceRecords(userId); });

    const userRecordsSpecialLink = document.getElementById('nav-user-records-special');
    if (userRecordsSpecialLink) userRecordsSpecialLink.addEventListener('click', (e) => { e.preventDefault(); showSection('special-user-records-section'); });

    const specialToolsLink = document.getElementById('nav-special-tools');
    if (specialToolsLink) specialToolsLink.addEventListener('click', (e) => { e.preventDefault(); showSection('special-tools-section'); });

    const scanLink = document.getElementById('nav-scan');
    if (scanLink) scanLink.addEventListener('click', (e) => { e.preventDefault(); showSection('scan-section'); });
    const myRecordsLink = document.getElementById('nav-my-records');
    if (myRecordsLink) myRecordsLink.addEventListener('click', (e) => { e.preventDefault(); showSection('records-section'); loadAttendanceRecords(userId); });

    // Absence nav links
    const reportAbsenceLink = document.getElementById('nav-report-absence');
    if (reportAbsenceLink) reportAbsenceLink.addEventListener('click', (e) => { e.preventDefault(); showSection('report-absence-section'); });
    const reportAbsenceSpecialLink = document.getElementById('nav-report-absence-special');
    if (reportAbsenceSpecialLink) reportAbsenceSpecialLink.addEventListener('click', (e) => { e.preventDefault(); showSection('report-absence-section'); });
    const absenceRecordsLink = document.getElementById('nav-absence-records');
    if (absenceRecordsLink) absenceRecordsLink.addEventListener('click', (e) => { e.preventDefault(); showSection('absence-records-section'); });
    const manualAttendanceLink = document.getElementById('nav-manual-attendance');
    if (manualAttendanceLink) {
        manualAttendanceLink.addEventListener('click', (e) => {
            e.preventDefault();
            showSection('manual-attendance-section');
            if (typeof setManualMode === 'function') setManualMode('attendance');
        });
    }

    const manualAbsenceLink = document.getElementById('nav-manual-absence');
    if (manualAbsenceLink) {
        manualAbsenceLink.addEventListener('click', (e) => {
            e.preventDefault();
            showSection('manual-attendance-section');
            if (typeof setManualMode === 'function') setManualMode('absence');
            document.querySelectorAll('.sidebar a[id^="nav-"]').forEach((link) => link.classList.remove('active'));
            manualAbsenceLink.classList.add('active');
        });
    }

    const startScannerBtn = document.getElementById('start-scanner-button');
    if (startScannerBtn && userRole !== 'admin') {
        startScannerBtn.addEventListener('click', () => {
            const sc = document.getElementById('scanner-container');
            if (sc) sc.style.display = 'block';
            startScannerBtn.style.display = 'none';
            initQrScanner();
        });
    }

    const genBtn = document.getElementById('generate-qr-button');
    if (genBtn) genBtn.addEventListener('click', generateQrCode);

    const generatedQrsTableBody = document.getElementById('generated-qrs-table-body');
    if (generatedQrsTableBody) {
        generatedQrsTableBody.addEventListener('click', (event) => {
            const target = event.target;
            if (target.classList.contains('copy-qr-btn')) {
                const qrContent = decodeURIComponent(target.dataset.qrContent);
                copyQrContent(qrContent);
            } else if (target.classList.contains('download-qr-btn')) {
                const qrContent = decodeURIComponent(target.dataset.qrContent);
                const projectName = target.dataset.projectName;
                const actionType = target.dataset.actionType;
                downloadSpecificQr(qrContent, `${projectName}_${actionType}`);
            } else if (target.classList.contains('delete-qr-btn')) {
                const qrId = target.dataset.qrId;
                deleteQr(qrId);
            }
        });
    }

    const adminFilter = document.getElementById('admin-user-filter');
    if (adminFilter) adminFilter.addEventListener('change', (e) => loadAttendanceRecords(e.target.value));

    const recalcBtn = document.getElementById('recalculate-duration-button');
    if (recalcBtn) {
        recalcBtn.addEventListener('click', async () => {
            const uidSel = document.getElementById('admin-user-filter').value;
            const date = document.getElementById('recalculate-date').value;
            if (!uidSel || !date) {
                alert('Please select a user and a date for recalculation.');
                return;
            }
            try {
                const response = await apiFetch('attendance/recalculate', 'POST', { user_id: uidSel, date });
                alert(response.message);
                if (response.success) loadAttendanceRecords(uidSel);
            } catch (error) {
                alert(`Error during recalculation: ${error.message}`);
            }
        });
    }

    const viewHistoryBtn = document.getElementById('view-history-button');
    if (viewHistoryBtn) {
        viewHistoryBtn.addEventListener('click', () => {
            const uidSel = document.getElementById('history-user-filter').value;
            const startDate = document.getElementById('history-start-date').value;
            const endDate = document.getElementById('history-end-date').value;
            if (!uidSel) {
                alert('Please select a user (or All Users).');
                return;
            }
            displayUserLocationHistory(uidSel, startDate, endDate);
        });
    }

    // Modals
    const attendanceRecordModal = document.getElementById('attendance-record-modal');
    if (attendanceRecordModal) {
        const closeAttendanceModalBtn = document.getElementById('attendance-record-modal-close');
        if (closeAttendanceModalBtn) {
            closeAttendanceModalBtn.addEventListener('click', () => {
                attendanceRecordModal.style.display = 'none';
            });
        }

        const attendanceModalBody = document.getElementById('attendance-record-modal-body');
        if (attendanceModalBody) {
            attendanceModalBody.addEventListener('click', (e) => {
                const actionBtn = e.target.closest('button[data-action]');
                if (!actionBtn) return;

                const action = actionBtn.dataset.action;
                const recordId = actionBtn.dataset.recordId;
                if (!recordId) return;

                if (action === 'edit-record-modal') {
                    const selectedRecord = attendanceModalRecordsById.get(String(recordId));
                    if (!selectedRecord) return;
                    attendanceRecordModal.style.display = 'none';
                    openEditModal(selectedRecord);
                } else if (action === 'delete-record-modal') {
                    openAttendanceDeleteConfirm(recordId);
                }
            });
        }

        attendanceRecordModal.addEventListener('click', (e) => {
            if (e.target === attendanceRecordModal) {
                attendanceRecordModal.style.display = 'none';
            }
        });
    }

    const attendanceDeleteConfirmModal = document.getElementById('attendance-delete-confirm-modal');
    if (attendanceDeleteConfirmModal) {
        const cancelDeleteBtn = document.getElementById('attendance-delete-cancel');
        const confirmDeleteBtn = document.getElementById('attendance-delete-confirm');
        const closeDeleteBtn = document.getElementById('attendance-delete-close');

        if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', closeAttendanceDeleteConfirm);
        if (closeDeleteBtn) closeDeleteBtn.addEventListener('click', closeAttendanceDeleteConfirm);
        if (confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', confirmDeleteAttendanceRecord);

        attendanceDeleteConfirmModal.addEventListener('click', (e) => {
            if (e.target === attendanceDeleteConfirmModal) {
                closeAttendanceDeleteConfirm();
            }
        });
    }

    const editModal = document.getElementById('edit-record-modal');
    if (editModal) {
        const closeBtn = editModal.querySelector('.close-button');
        if (closeBtn) closeBtn.addEventListener('click', () => editModal.style.display = 'none');

        const editForm = document.getElementById('edit-record-form');
        if (editForm) {
            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const recordId = document.getElementById('edit-record-id').value;
                const userIdEdit = parseInt(document.getElementById('edit-user-id').value, 10);
                const typeEdit = document.getElementById('edit-type').value;
                const locationEdit = document.getElementById('edit-location').value;
                const entryTimeEdit = document.getElementById('edit-entry-time').value;
                const exitTimeEdit = document.getElementById('edit-exit-time').value;
                const totalDurationMinutes = parseFloat(document.getElementById('edit-total-duration').value) * 60;
                const lunchDurationMinutes = parseFloat(document.getElementById('edit-lunch-duration').value) * 60;
                const isExitType = typeEdit === 'exit';
                const selectedProjectIdRaw = document.getElementById('edit-project-id')?.value || '';
                const selectedProjectId = selectedProjectIdRaw ? parseInt(selectedProjectIdRaw, 10) : null;

                try {
                    await apiFetch(`attendance/${recordId}`, 'PUT', {
                        user_id: Number.isFinite(userIdEdit) ? userIdEdit : null,
                        type: typeEdit,
                        location: locationEdit,
                        original_time: entryTimeEdit.replace('T', ' '),
                        rounded_time: exitTimeEdit.replace('T', ' '),
                        entry_time: entryTimeEdit.replace('T', ' '),
                        exit_time: exitTimeEdit.replace('T', ' '),
                        total_duration: isExitType ? null : (Number.isFinite(totalDurationMinutes) ? totalDurationMinutes : null),
                        lunch_duration: isExitType ? null : (Number.isFinite(lunchDurationMinutes) ? lunchDurationMinutes : null),
                        project_qr_id: null,
                        project_id: selectedProjectId
                    });
                    editModal.style.display = 'none';
                    attendanceEditingRecord = null;
                    loadAttendanceRecords(userRole === 'admin' ? null : userId);
                } catch (error) {
                    const msg = document.getElementById('edit-message');
                    if (msg) msg.textContent = error.message;
                }
            });
        }
    }

    const qrMapModal = document.getElementById('qr-map-modal');
    if (qrMapModal) {
        const openBtn = document.getElementById('open-qr-map-button');
        if (openBtn) {
            openBtn.addEventListener('click', () => {
                qrMapModal.style.display = 'block';
                if (qrLocationMap) {
                    google.maps.event.trigger(qrLocationMap, 'resize');
                }
            });
        }
        const closeBtn = qrMapModal.querySelector('.close-button');
        if (closeBtn) closeBtn.addEventListener('click', () => qrMapModal.style.display = 'none');

        const selectBtn = document.getElementById('select-qr-location-button');
        if (selectBtn) {
            selectBtn.addEventListener('click', () => {
                if (qrMapMarker) {
                    geocoder.geocode({ 'location': qrMapMarker.getPosition() }, (results, status) => {
                        if (status === 'OK' && results[0]) {
                            const out = document.getElementById('qr-location');
                            if (out) out.value = results[0].formatted_address;
                            qrMapModal.style.display = 'none';
                        } else {
                            alert('Could not get address. Please try again.');
                        }
                    });
                } else {
                    alert('Please select a location on the map.');
                }
            });
        }
    }

    // User management listeners
    const addUserButton = document.getElementById('add-user-button');
    if (addUserButton) addUserButton.addEventListener('click', () => showUserForm('create'));

    const userForm = document.getElementById('user-form');
    if (userForm) userForm.addEventListener('submit', saveUser);

    const cancelUserEditButton = document.getElementById('cancel-user-edit');
    if (cancelUserEditButton) {
        cancelUserEditButton.addEventListener('click', () => {
            document.getElementById('user-form-container').style.display = 'none';
        });
    }

    // Project management listeners
    const addProjectButton = document.getElementById('add-project-button');
    if (addProjectButton) addProjectButton.addEventListener('click', () => showProjectForm('create'));

    const projectForm = document.getElementById('project-form');
    if (projectForm) projectForm.addEventListener('submit', saveProject);

    const cancelProjectEditButton = document.getElementById('cancel-project-edit');
    if (cancelProjectEditButton) {
        cancelProjectEditButton.addEventListener('click', () => {
            document.getElementById('project-form-container').style.display = 'none';
            if (typeof destroyGeofenceConfigMap === 'function') destroyGeofenceConfigMap();
        });
    }

    // Action Modal Listeners
    const actionModal = document.getElementById('action-selection-modal');
    if (actionModal) {
        const close = actionModal.querySelector('.close-button');
        if (close) close.addEventListener('click', () => { actionModal.style.display = 'none'; });
        actionModal.querySelectorAll('.action-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                registerAttendance(action);
            });
        });
    }

    startLocationTracking();

    startTimerSyncLoop();

    // Initialize absences module
    if (typeof initAbsencesModule === 'function') initAbsencesModule();

    const attendanceSearchInput = document.getElementById('attendance-table-search');
    if (attendanceSearchInput) {
        attendanceSearchInput.addEventListener('keyup', () => {
            attendanceFilterText = attendanceSearchInput.value.toUpperCase();
            attendancePage = 1;
            renderAttendancePage();
        });
    }

    const prevPageBtn = document.getElementById('attendance-prev-page');
    if (prevPageBtn) prevPageBtn.addEventListener('click', () => { attendancePage = Math.max(1, attendancePage - 1); renderAttendancePage(); });

    const nextPageBtn = document.getElementById('attendance-next-page');
    if (nextPageBtn) nextPageBtn.addEventListener('click', () => { attendancePage += 1; renderAttendancePage(); });

    const prevMonthBtn = document.getElementById('attendance-calendar-prev');
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', () => {
            const jump = attendanceCalendarViewMode === 'day' ? 1 : 7;
            attendanceCalendarDate = new Date(attendanceCalendarDate.getFullYear(), attendanceCalendarDate.getMonth(), attendanceCalendarDate.getDate() - jump);
            renderAttendancePage();
        });
    }

    const nextMonthBtn = document.getElementById('attendance-calendar-next');
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', () => {
            const jump = attendanceCalendarViewMode === 'day' ? 1 : 7;
            attendanceCalendarDate = new Date(attendanceCalendarDate.getFullYear(), attendanceCalendarDate.getMonth(), attendanceCalendarDate.getDate() + jump);
            renderAttendancePage();
        });
    }

    const attendanceTodayBtn = document.getElementById('attendance-calendar-today');
    if (attendanceTodayBtn) {
        attendanceTodayBtn.addEventListener('click', () => {
            const now = new Date();
            attendanceCalendarDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            renderAttendancePage();
        });
    }

    const attendanceFocusDateInput = document.getElementById('attendance-focus-date');
    if (attendanceFocusDateInput) {
        attendanceFocusDateInput.addEventListener('change', (e) => {
            if (!e.target.value) return;
            attendanceCalendarDate = new Date(`${e.target.value}T00:00:00`);
            renderAttendancePage();
        });
    }

    const attendanceViewModeSelect = document.getElementById('attendance-view-mode');
    if (attendanceViewModeSelect) {
        attendanceViewModeSelect.value = attendanceCalendarViewMode;
        attendanceViewModeSelect.addEventListener('change', (e) => {
            attendanceCalendarViewMode = e.target.value === 'day' ? 'day' : 'week';
            renderAttendancePage();
        });
    }

    const exportBtn = document.getElementById('export-attendance-csv');
    if (exportBtn) exportBtn.addEventListener('click', exportAttendanceCsv);

    const notificationsToggle = document.getElementById('notifications-toggle');
    const notificationsPanel = document.getElementById('notifications-panel');
    if (notificationsToggle && notificationsPanel) {
        notificationsToggle.addEventListener('click', () => {
            const isOpen = notificationsPanel.style.display === 'block';
            notificationsPanel.style.display = isOpen ? 'none' : 'block';
        });
    }

    const notificationsList = document.getElementById('notifications-list');
    if (notificationsList) {
        notificationsList.addEventListener('click', (e) => {
            const item = e.target.closest('[data-notification-id]');
            if (!item) return;
            const id = Number(item.dataset.notificationId);
            if (id) markNotificationRead(id);
        });
    }

    loadNotifications();

    const generatedQrsSearchInput = document.getElementById('generated-qrs-table-search');
    if (generatedQrsSearchInput) generatedQrsSearchInput.addEventListener('keyup', () => filterTable('generated-qrs-table-search', 'generated-qrs-table-body'));

    const usersSearchInput = document.getElementById('users-table-search');
    if (usersSearchInput) usersSearchInput.addEventListener('keyup', () => filterTable('users-table-search', 'users-table-body'));

    const projectsSearchInput = document.getElementById('projects-table-search');
    if (projectsSearchInput) projectsSearchInput.addEventListener('keyup', () => filterTable('projects-table-search', 'projects-table-body'));

    const usersTableBody = document.getElementById('users-table-body');
    if (usersTableBody) {
        usersTableBody.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (!button) return;
            e.preventDefault();

            const action = button.dataset.action;
            const userId = Number(button.dataset.id);
            if (!userId) return;

            if (action === 'edit-user') {
                const username = decodeURIComponent(button.dataset.username || '');
                const roleIdValue = button.dataset.roleId ?? '';
                showUserForm('edit', {
                    id: userId,
                    username,
                    role_id: roleIdValue === '' ? '' : roleIdValue
                });
            } else if (action === 'delete-user') {
                deleteUser(userId);
            }
        });
    }

    const projectsTableBody = document.getElementById('projects-table-body');
    if (projectsTableBody) {
        projectsTableBody.addEventListener('click', (e) => {
            const target = e.target;
            if (target && target.matches('button[data-action="delete-project"]')) {
                e.preventDefault();
                const projectId = Number(target.dataset.projectId);
                if (projectId) {
                    deleteProject(projectId);
                }
            } else if (target && target.matches('button[data-action="edit-project"]')) {
                e.preventDefault();
                try {
                    const projectData = JSON.parse(decodeURIComponent(target.dataset.projectData));
                    showProjectForm('edit', projectData);
                } catch (err) {
                    console.error('Error parsing project data:', err);
                }
            }
        });
    }

    const attendanceRecordsBody = document.getElementById('attendance-records-body');
    if (attendanceRecordsBody) {
        attendanceRecordsBody.addEventListener('click', (e) => {
            const target = e.target;
            if (target && target.matches('button[data-action]')) {
                e.preventDefault();
                const action = target.dataset.action;
                const recordId = Number(target.dataset.recordId);

                console.log('Action triggered:', { action, recordId }); // Debugging line

                if (action === 'edit-record') {
                    if (recordId) {
                        let recordData = null;
                        try {
                            recordData = target.dataset.record ? JSON.parse(decodeURIComponent(target.dataset.record)) : null;
                        } catch (_) {
                            recordData = attendanceAllRecords.find(r => Number(r.id) === Number(recordId)) || null;
                        }
                        openEditModal(recordData);
                    }
                } else if (action === 'delete-record') {
                    if (recordId) {
                        deleteRecord(recordId);
                    }
                }
            }
        });
    }

    const attendanceMatrixGrid = document.getElementById('attendance-calendar-grid');
    if (attendanceMatrixGrid) {
        attendanceMatrixGrid.addEventListener('click', (e) => {
            const cell = e.target.closest('[data-attendance-cell="1"]');
            if (!cell || cell.disabled) return;

            const employeeId = cell.dataset.employeeId;
            const dayKey = cell.dataset.dayKey;
            if (!employeeId || !dayKey) return;

            const row = attendanceMatrixCache.find((item) => String(item.employeeId) === String(employeeId));
            if (!row) return;

            const dayRecords = row.days[dayKey] || [];
            if (!dayRecords.length) return;

            openAttendanceRecordModal(dayRecords);
        });
    }

    const specialUsersFilter = document.getElementById('special-users-filter');
    if (specialUsersFilter) {
        specialUsersFilter.addEventListener('change', (e) => {
            const selectedUser = e.target.value;
            if (selectedUser) {
                loadSpecialUserRecords(selectedUser);
            } else {
                const tbody = document.getElementById('special-user-records-body');
                if (tbody) tbody.innerHTML = '<tr><td colspan="9">Select a user to view records.</td></tr>';
            }
        });
    }

    const activeTimersBody = document.getElementById('active-timers-body');
    if (activeTimersBody) {
        activeTimersBody.addEventListener('click', (e) => {
            const target = e.target;
            if (target && target.matches('button[data-action]')) {
                e.preventDefault();
                const timerId = Number(target.getAttribute('data-id'));
                const action = target.getAttribute('data-action');
                if (timerId && action) {
                    updateTimerStatus(timerId, action);
                }
            }
        });
    }

    const attendanceGrid = document.getElementById('attendance-calendar-grid');
    if (attendanceGrid) {
        if (!attendanceAllRecords.length) {
            const mock = getAttendanceMockRecords();
            renderAttendanceCalendar(mock);
        }
        if (userRole === 'admin') {
            loadAttendanceRecords();
        } else if (userId) {
            loadAttendanceRecords(userId);
        }
    }
});
