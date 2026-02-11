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

// Estado de usuario (no redeclarar luego)
let userId = null;
let userRole = null;
let username = null;
let specialUsersLoaded = false;

function normalizeRole(role) {
    if (!role) return '';
    const lower = role.toLowerCase();
    if (lower === 'special user' || lower === 'special-user') {
        return 'special';
    }
    return lower;
}

// Timer key dinámico (antes era constante con userId nulo)
function getTimerKey() {
    const uid = sessionStorage.getItem('user_id');
    return uid ? `attendanceTimerState_${uid}` : null;
}

// --- Utility Functions ---
async function apiFetch(endpoint, method = 'GET', data = null, contentType = 'json') {
    const url = `${API_BASE_URL}/${endpoint}`;
    const options = { method };

    if (data) {
        if (contentType === 'json') {
            options.headers = { 'Content-Type': 'application/json' };
            options.body = JSON.stringify(data);
        } else if (contentType === 'form') {
            options.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
            options.body = new URLSearchParams(data).toString();
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

function formatDurationHours(totalMinutes) {
    if (totalMinutes === null || totalMinutes === undefined) {
        return 'N/A';
    }
    return (totalMinutes / 60).toFixed(2);
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

    if (role === 'admin' && (sectionId === 'scan-section' || sectionId === 'special-user-records-section')) {
        target = 'dashboard-section';
    }

    ['scan-section', 'records-section', 'special-user-records-section', 'dashboard-section', 'admin-tools-section', 'users-section', 'projects-section', 'special-tools-section']
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

    if (target === 'dashboard-section') {
        loadDashboardData();
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

    if (!navigator.geolocation) {
        if (messageEl) {
            messageEl.textContent = 'Geolocation is not supported by your browser.';
            messageEl.className = 'error-message';
        }
        return;
    }

    navigator.geolocation.getCurrentPosition(async (position) => {
        const userLocation = `${position.coords.latitude},${position.coords.longitude}`;

        try {
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

            const response = await apiFetch('attendance', 'POST', payload);
            if (messageEl) {
                messageEl.textContent = response.message || 'Attendance registered successfully.';
                messageEl.className = 'success-message';
            }

            applyLocalTimerAction();

            const recordsSection = document.getElementById('records-section');
            if (recordsSection && recordsSection.style.display === 'block') {
                const normalizedRole = normalizeRole(sessionStorage.getItem('user_role'));
                loadAttendanceRecords(normalizedRole === 'admin' ? null : uid);
            }
        } catch (error) {
            if (messageEl) {
                messageEl.textContent = error.message;
                messageEl.className = 'error-message';
            }
        }
    }, (error) => {
        console.error('Geolocation error:', error);
        if (messageEl) {
            messageEl.textContent = `Error getting location: ${error.message}`;
            messageEl.className = 'error-message';
        }
    });
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
    if (!projectId) {
        if (qrMessage) qrMessage.textContent = 'Please select a project.';
        return;
    }

    try {
        const response = await apiFetch('qrs', 'POST', { project_id: projectId });
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
async function loadAttendanceRecords(uid = null) {
    const recordsBody = document.getElementById('attendance-records-body');
    if (!recordsBody) return;

    const role = normalizeRole(sessionStorage.getItem('user_role'));
    const endpoint = role === 'admin' && !uid ? 'attendance?all=true' : `attendance?user_id=${uid || sessionStorage.getItem('user_id')}`;

    try {
        const data = await apiFetch(endpoint);
        // It is assumed that the header row is static in the HTML and has been updated to include a "Lunch Duration" column.
        recordsBody.innerHTML = (data.records || []).map(record => {
            const localTime = formatUtcAsLocal(record.original_time);
            const localRoundedTime = formatUtcAsLocal(record.rounded_time);
            const projectNameRaw = (record.project_name ?? '').trim();
            const projectName = projectNameRaw !== '' ? projectNameRaw : 'Sin proyecto';

            // Display total_duration for entry/exit, and lunch_duration for end_lunch
            const sessionDuration = (record.type === 'entry' || record.type === 'exit') ? formatDurationHours(record.total_duration) : 'N/A';
            const lunchDuration = record.type === 'end_lunch' ? formatDurationHours(record.lunch_duration) : 'N/A';

            const actionsHtml = role === 'admin' ? `
                <td data-label="Actions">
                    <button data-action="edit-record" data-record-id="${Number(record.id)}" data-rounded-time="${record.rounded_time}" data-total-duration="${record.total_duration || 0}">Edit</button>
                    <button data-action="delete-record" data-record-id="${Number(record.id)}">Delete</button>
                </td>
            ` : '';

            return `
        <tr>
          <td data-label="User">${record.username}</td>
          <td data-label="Date">${localTime.date}</td>
          <td data-label="Location">${record.location}</td>
          <td data-label="Type">${record.type}</td>
          <td data-label="Original Time">${localTime.time}</td>
          <td data-label="Rounded Time">${localRoundedTime.time}</td>
          <td data-label="Work Duration (hours)">${sessionDuration}</td>
          <td data-label="Lunch Duration (hours)">${lunchDuration}</td>
          <td data-label="Project">${projectName}</td>
          ${actionsHtml}
        </tr>
      `;
        }).join('');
    } catch (_) {
        recordsBody.innerHTML = `<tr><td colspan="10">Error loading records.</td></tr>`;
    }
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
            tbody.innerHTML = '<tr><td colspan="8">Select a user to view records.</td></tr>';
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
            tbody.innerHTML = '<tr><td colspan="8">No records found.</td></tr>';
            return;
        }

        tbody.innerHTML = data.records.map(record => {
            const localTime = formatUtcAsLocal(record.original_time);
            const localRoundedTime = formatUtcAsLocal(record.rounded_time);
            const projectNameRaw = (record.project_name ?? '').trim();
            const projectName = projectNameRaw !== '' ? projectNameRaw : 'Sin proyecto';
            return `
        <tr>
          <td data-label="User">${record.username}</td>
          <td data-label="Date">${localTime.date}</td>
          <td data-label="Location">${record.location}</td>
          <td data-label="Type">${record.type}</td>
          <td data-label="Original Time">${localTime.time}</td>
          <td data-label="Rounded Time">${localRoundedTime.time}</td>
          <td data-label="Duration (hours)">${formatDurationHours(record.total_duration)}</td>
          <td data-label="Project">${projectName}</td>
        </tr>
      `;
        }).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="8">${error.message || 'Error loading records.'}</td></tr>`;
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
        selectElement.innerHTML = '<option value="">Select User</option>' + data.users.map(user => `<option value="${user.id}">${user.username}</option>`).join('');
    } catch (_) { }
}

async function loadDashboardData() {
    loadHoursChart();
    loadUserLocationsAndDisplayOnMap();
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
            hoursChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.summary.labels,
                    datasets: [{
                        label: 'Hours Worked',
                        data: data.summary.data,
                        backgroundColor: 'rgba(230, 57, 70, 0.2)',
                        borderColor: 'rgba(230, 57, 70, 1)',
                        borderWidth: 1
                    }]
                },
                options: { scales: { y: { beginAtZero: true } } }
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

async function loadUserLocationsAndDisplayOnMap() {
    if (!userLocationsMap) return;

    try {
        const data = await apiFetch('locations/users');
        if (data.locations.length > 0) {
            const bounds = new google.maps.LatLngBounds();

            data.locations.forEach(loc => {
                const position = { lat: parseFloat(loc.latitude), lng: parseFloat(loc.longitude) };
                new google.maps.Marker({
                    position,
                    map: userLocationsMap,
                    title: `${loc.username} - Last seen: ${new Date(loc.timestamp).toLocaleString()}`
                });
                bounds.extend(position);
            });

            if (data.locations.length > 1) {
                userLocationsMap.fitBounds(bounds);
            } else {
                userLocationsMap.setCenter(bounds.getCenter());
                userLocationsMap.setZoom(15);
            }
        } else {
            const msg = document.getElementById('map-message');
            if (msg) msg.textContent = 'No recent user locations to display.';
        }
    } catch (error) {
        const msg = document.getElementById('map-message');
        if (msg) msg.textContent = 'Error loading user locations.';
    }
}

async function displayUserLocationHistory(uid, startDate, endDate) {
    if (!userLocationsMap) return;

    try {
        const endpoint = `locations/history?user_id=${uid}&start_date=${startDate}&end_date=${endDate}`;
        const data = await apiFetch(endpoint);

        if (data.history.length > 0) {
            const pathCoordinates = data.history.map(p => ({ lat: parseFloat(p.latitude), lng: parseFloat(p.longitude) }));

            const userPath = new google.maps.Polyline({
                path: pathCoordinates,
                geodesic: true,
                strokeColor: '#FF0000',
                strokeOpacity: 1.0,
                strokeWeight: 2
            });

            userPath.setMap(userLocationsMap);

            const bounds = new google.maps.LatLngBounds();
            pathCoordinates.forEach(coord => bounds.extend(coord));
            userLocationsMap.fitBounds(bounds);

        } else {
            alert('No location history found for the selected user and date range.');
        }
    } catch (error) {
        alert('Error loading location history.');
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

function openEditModal(recordId, roundedTime, totalDuration) {
    document.getElementById('edit-record-id').value = recordId;
    document.getElementById('edit-rounded-time').value = roundedTime.replace(' ', 'T').slice(0, 16);
    document.getElementById('edit-total-duration').value = (totalDuration / 60).toFixed(2) || '';
    document.getElementById('edit-record-modal').style.display = 'block';
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
            projectsTableBody.innerHTML = data.projects.map(project => `
        <tr>
          <td data-label="ID">${project.id}</td>
          <td data-label="Name">${project.name}</td>
          <td data-label="Creation Date">${new Date(project.created_at).toLocaleDateString()}</td>
          <td data-label="Actions">
            <button data-action="delete-project" data-project-id="${project.id}">Delete</button>
          </td>
        </tr>
      `).join('');
        } else {
            projectsTableBody.innerHTML = `<tr><td colspan="4">Error loading projects: ${data.message}</td></tr>`;
        }
    } catch (error) {
        projectsTableBody.innerHTML = `<tr><td colspan="4">Connection error loading projects.</td></tr>`;
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
    } else {
        if (formTitle) formTitle.textContent = 'Edit';
        if (projectIdInput) projectIdInput.value = project.id;
        if (projectNameInput) projectNameInput.value = project.name;
    }
}

async function saveProject(e) {
    e.preventDefault();
    const projectId = document.getElementById('project-id').value;
    const projectName = document.getElementById('project-name').value;
    const messageEl = document.getElementById('project-form-message');

    const action = projectId ? 'update' : 'create';
    const payload = { action, name: projectName };
    if (projectId) payload.id = projectId;

    try {
        const response = await apiFetch('projects', 'POST', payload);
        if (messageEl) {
            messageEl.textContent = response.message;
            messageEl.className = response.success ? 'success-message' : 'error-message';
        }
        if (response.success) {
            document.getElementById('project-form-container').style.display = 'none';
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
                sessionStorage.setItem('token', 'dummy_token_123');
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

    let defaultSection = 'scan-section';
    if (userRole === 'admin') {
        defaultSection = 'dashboard-section';
    } else if (userRole === 'special') {
        defaultSection = 'scan-section';
    }
    showSection(defaultSection);

    const greet = document.getElementById('user-greeting');
    if (greet && username) greet.textContent = `Hello, ${username}!`;

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
    if (logoutLink) logoutLink.addEventListener('click', logout);

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
            if (!uidSel || !startDate || !endDate) {
                alert('Please select a user and a date range to view the history.');
                return;
            }
            displayUserLocationHistory(uidSel, startDate, endDate);
        });
    }

    // Modals
    const editModal = document.getElementById('edit-record-modal');
    if (editModal) {
        const closeBtn = editModal.querySelector('.close-button');
        if (closeBtn) closeBtn.addEventListener('click', () => editModal.style.display = 'none');

        const editForm = document.getElementById('edit-record-form');
        if (editForm) {
            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const recordId = document.getElementById('edit-record-id').value;
                const newTime = document.getElementById('edit-rounded-time').value;
                const newDuration = parseFloat(document.getElementById('edit-total-duration').value) * 60;
                try {
                    await apiFetch(`attendance/${recordId}`, 'PUT', {
                        rounded_time: newTime.replace('T', ' '),
                        total_duration: newDuration,
                        user_id: userId,
                        location: 'N/A',
                        type: 'N/A',
                        original_time: newTime.replace('T', ' '),
                        project_qr_id: null
                    });
                    editModal.style.display = 'none';
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

    const attendanceSearchInput = document.getElementById('attendance-table-search');
    if (attendanceSearchInput) attendanceSearchInput.addEventListener('keyup', () => filterTable('attendance-table-search', 'attendance-records-body'));

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
                    const roundedTime = target.dataset.roundedTime;
                    const totalDuration = Number(target.dataset.totalDuration);
                    if (recordId) {
                        openEditModal(recordId, roundedTime, totalDuration);
                    }
                } else if (action === 'delete-record') {
                    if (recordId) {
                        deleteRecord(recordId);
                    }
                }
            }
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
                if (tbody) tbody.innerHTML = '<tr><td colspan="8">Select a user to view records.</td></tr>';
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
});
