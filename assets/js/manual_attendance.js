/**
 * Manual Attendance / Absence (Admin Only)
 */
(function () {
    'use strict';

    let currentManualMode = 'attendance';
    let entryMode = 'single';
    let manualUsersCache = [];
    let pendingBatchPayload = null;

    function initManualAttendance() {
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (role !== 'admin') return;

        const attendanceForm = document.getElementById('manual-attendance-form');
        const absenceForm = document.getElementById('manual-absence-form');
        if (!attendanceForm || !absenceForm) return;

        if (attendanceForm.dataset.manualInit !== '1') {
            attendanceForm.addEventListener('submit', handleManualAttendanceSubmit);

            const selectAllBtn = document.getElementById('manual-select-all-users');
            const clearBtn = document.getElementById('manual-clear-users');
            const searchInput = document.getElementById('manual-employee-search');
            const singleBtn = document.getElementById('manual-mode-single');
            const multiBtn = document.getElementById('manual-mode-multi');

            if (selectAllBtn) selectAllBtn.addEventListener('click', selectAllUsers);
            if (clearBtn) clearBtn.addEventListener('click', clearSelectedUsers);
            if (searchInput) searchInput.addEventListener('input', filterEmployeeList);
            if (singleBtn) singleBtn.addEventListener('click', () => setEntryMode('single'));
            if (multiBtn) multiBtn.addEventListener('click', () => setEntryMode('multi'));

            attendanceForm.dataset.manualInit = '1';
        }

        if (absenceForm.dataset.manualInit !== '1') {
            absenceForm.addEventListener('submit', handleManualAbsenceSubmit);
            absenceForm.dataset.manualInit = '1';
        }

        setupManualBatchModal();
        setEntryMode(entryMode);
        setManualMode(currentManualMode);
    }

    function setupManualBatchModal() {
        const modal = document.getElementById('manual-batch-confirm-modal');
        const closeBtn = document.getElementById('manual-batch-confirm-close');
        const cancelBtn = document.getElementById('manual-batch-cancel');
        const submitBtn = document.getElementById('manual-batch-submit');

        if (!modal || modal.dataset.bound === '1') return;

        const close = () => {
            modal.style.display = 'none';
            pendingBatchPayload = null;
        };

        if (closeBtn) closeBtn.addEventListener('click', close);
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        if (submitBtn) {
            submitBtn.addEventListener('click', async () => {
                if (!pendingBatchPayload) return;
                await runManualBatchSubmit(pendingBatchPayload);
                close();
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });

        modal.dataset.bound = '1';
    }

    function setEntryMode(mode) {
        entryMode = mode === 'multi' ? 'multi' : 'single';

        const singleBtn = document.getElementById('manual-mode-single');
        const multiBtn = document.getElementById('manual-mode-multi');
        const singleBlock = document.getElementById('manual-single-date-block');
        const rangeBlock = document.getElementById('manual-range-date-block');
        const dateInput = document.getElementById('manual-date');
        const startInput = document.getElementById('manual-date-start');
        const endInput = document.getElementById('manual-date-end');

        if (singleBtn) singleBtn.classList.toggle('active', entryMode === 'single');
        if (multiBtn) multiBtn.classList.toggle('active', entryMode === 'multi');
        if (singleBlock) singleBlock.style.display = entryMode === 'single' ? 'flex' : 'none';
        if (rangeBlock) rangeBlock.style.display = entryMode === 'multi' ? 'flex' : 'none';

        if (dateInput) dateInput.required = entryMode === 'single';
        if (startInput) startInput.required = entryMode === 'multi';
        if (endInput) endInput.required = entryMode === 'multi';
    }

    function setManualMode(mode) {
        currentManualMode = (mode === 'absence') ? 'absence' : 'attendance';

        const titleEl = document.getElementById('manual-section-title');
        const subtitleEl = document.getElementById('manual-section-subtitle');
        const attendanceForm = document.getElementById('manual-attendance-form');
        const absenceForm = document.getElementById('manual-absence-form');
        const messageEl = document.getElementById('manual-attendance-message');

        if (messageEl) {
            messageEl.textContent = '';
            messageEl.className = '';
        }

        if (currentManualMode === 'absence') {
            if (titleEl) titleEl.textContent = '📝 Manual Records';
            if (subtitleEl) subtitleEl.textContent = 'Register absence manually (user, absence type, date and notes).';
            if (attendanceForm) attendanceForm.style.display = 'none';
            if (absenceForm) absenceForm.style.display = 'block';
        } else {
            if (titleEl) titleEl.textContent = '📝 Manual Records';
            if (subtitleEl) subtitleEl.textContent = 'Register attendance entries manually for employees who forgot to clock in/out.';
            if (attendanceForm) attendanceForm.style.display = 'block';
            if (absenceForm) absenceForm.style.display = 'none';
        }
    }

    function renderEmployeeList(users) {
        const employeeList = document.getElementById('manual-employee-list');
        if (!employeeList) return;

        employeeList.innerHTML = '';
        users.forEach((u) => {
            const row = document.createElement('label');
            row.className = 'manual-employee-item';
            row.dataset.userId = String(u.id);
            row.dataset.username = (u.username || '').toLowerCase();

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'manual-employee-checkbox';
            checkbox.value = String(u.id);
            checkbox.addEventListener('change', () => {
                row.classList.toggle('is-selected', checkbox.checked);
                updateSelectedCount();
            });

            const text = document.createElement('span');
            text.className = 'manual-employee-name';
            text.textContent = u.username;

            row.appendChild(checkbox);
            row.appendChild(text);
            employeeList.appendChild(row);
        });

        updateSelectedCount();
    }

    function filterEmployeeList() {
        const q = (document.getElementById('manual-employee-search')?.value || '').trim().toLowerCase();
        const rows = document.querySelectorAll('.manual-employee-item');
        rows.forEach((row) => {
            const uname = row.dataset.username || '';
            row.hidden = q ? !uname.includes(q) : false;
        });
    }

    async function populateManualDropdowns() {
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (role !== 'admin') return;

        try {
            const data = await apiFetch('users');
            const users = data.users || [];
            manualUsersCache = users.filter(u => String(u.role || '').toLowerCase() !== 'admin');

            renderEmployeeList(manualUsersCache);

            const absenceUserSelect = document.getElementById('manual-absence-user');
            if (absenceUserSelect) {
                const opts = manualUsersCache
                    .map(u => `<option value="${u.id}">${u.username}</option>`)
                    .join('');
                absenceUserSelect.innerHTML = '<option value="">— Select user —</option>' + opts;
            }
        } catch (e) {
            console.error('Error loading users for manual forms:', e);
        }

        try {
            const data = await apiFetch('projects');
            const projects = data.projects || [];

            const attendanceProject = document.getElementById('manual-project');
            if (attendanceProject) {
                attendanceProject.innerHTML = '<option value="">— Select project —</option>';
                projects.forEach((p) => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    attendanceProject.appendChild(opt);
                });
            }

            const absenceProject = document.getElementById('manual-absence-project');
            if (absenceProject) {
                absenceProject.innerHTML = '<option value="">— Select project —</option>';
                projects.forEach((p) => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    absenceProject.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Error loading projects for manual forms:', e);
        }
    }

    function getSelectedUserIds() {
        return Array.from(document.querySelectorAll('.manual-employee-checkbox:checked'))
            .map(el => parseInt(el.value, 10))
            .filter(Number.isFinite);
    }

    function selectAllUsers() {
        document.querySelectorAll('.manual-employee-item').forEach((row) => {
            if (row.hidden) return;
            const cb = row.querySelector('.manual-employee-checkbox');
            if (!cb) return;
            cb.checked = true;
            row.classList.add('is-selected');
        });
        updateSelectedCount();
    }

    function clearSelectedUsers() {
        document.querySelectorAll('.manual-employee-item').forEach((row) => {
            const cb = row.querySelector('.manual-employee-checkbox');
            if (!cb) return;
            cb.checked = false;
            row.classList.remove('is-selected');
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const selectedCountEl = document.getElementById('manual-selected-count');
        if (!selectedCountEl) return;
        const count = document.querySelectorAll('.manual-employee-checkbox:checked').length;
        selectedCountEl.textContent = `${count} selected`;
    }

    function buildDateList() {
        const excludeWeekends = !!document.getElementById('manual-exclude-weekends')?.checked;

        if (entryMode === 'single') {
            const date = document.getElementById('manual-date')?.value;
            return date ? [date] : [];
        }

        const start = document.getElementById('manual-date-start')?.value;
        const end = document.getElementById('manual-date-end')?.value;
        if (!start || !end || start > end) return [];

        const dates = [];
        const cur = new Date(`${start}T00:00:00`);
        const lim = new Date(`${end}T00:00:00`);

        while (cur <= lim) {
            const day = cur.getDay();
            const iso = cur.toISOString().slice(0, 10);
            if (!excludeWeekends || (day !== 0 && day !== 6)) dates.push(iso);
            cur.setDate(cur.getDate() + 1);
        }

        return dates;
    }

    function validateTimeRange(entryTime, exitTime, lunchStart, lunchEnd) {
        if (!entryTime || !exitTime) return 'Entry and exit times are required.';
        if (entryTime >= exitTime) return 'Entry time must be earlier than exit time.';

        if (lunchStart && lunchEnd) {
            if (lunchStart >= lunchEnd) return 'Lunch start must be earlier than lunch end.';
            if (lunchStart < entryTime || lunchEnd > exitTime) return 'Lunch times must be inside entry/exit window.';
        }

        if ((lunchStart && !lunchEnd) || (!lunchStart && lunchEnd)) {
            return 'Provide both lunch start and lunch end, or leave both empty.';
        }

        return null;
    }

    function buildManualAttendancePayload() {
        const payload = {
            user_ids: getSelectedUserIds(),
            project_id: document.getElementById('manual-project').value ? parseInt(document.getElementById('manual-project').value, 10) : null,
            dates: buildDateList(),
            entry_time: document.getElementById('manual-entry-time').value,
            exit_time: document.getElementById('manual-exit-time').value,
            reason: document.getElementById('manual-reason').value.trim(),
            lunch_start: document.getElementById('manual-lunch-start')?.value || null,
            lunch_end: document.getElementById('manual-lunch-end')?.value || null,
            conflict_strategy: document.getElementById('manual-conflict-strategy')?.value || 'skip'
        };

        return payload;
    }

    function renderBatchSummary(payload) {
        const summary = document.getElementById('manual-batch-summary');
        if (!summary) return;

        const total = payload.user_ids.length * payload.dates.length;
        summary.innerHTML = `
            <div class="attendance-record-row"><div class="attendance-record-key">Employees</div><div class="attendance-record-value">${payload.user_ids.length}</div></div>
            <div class="attendance-record-row"><div class="attendance-record-key">Dates</div><div class="attendance-record-value">${payload.dates.length}</div></div>
            <div class="attendance-record-row"><div class="attendance-record-key">Total records to create</div><div class="attendance-record-value">${total}</div></div>
            <div class="attendance-record-row"><div class="attendance-record-key">Schedule</div><div class="attendance-record-value">${payload.entry_time} → ${payload.exit_time}${payload.lunch_start ? ` (Lunch ${payload.lunch_start} → ${payload.lunch_end})` : ''}</div></div>
            <div class="attendance-record-row"><div class="attendance-record-key">Conflict handling</div><div class="attendance-record-value">${payload.conflict_strategy}</div></div>
            <div class="attendance-record-row"><div class="attendance-record-key">Reason</div><div class="attendance-record-value">${payload.reason}</div></div>
        `;
    }

    function openBatchConfirmModal(payload) {
        const modal = document.getElementById('manual-batch-confirm-modal');
        if (!modal) return;

        pendingBatchPayload = payload;
        renderBatchSummary(payload);
        modal.style.display = 'flex';
    }

    async function handleManualAttendanceSubmit(e) {
        e.preventDefault();
        const msg = document.getElementById('manual-attendance-message');
        const payload = buildManualAttendancePayload();

        if (!payload.user_ids.length || !payload.dates.length || !payload.entry_time || !payload.exit_time || !payload.reason) {
            if (msg) { msg.textContent = 'Please fill required fields, select users and valid dates.'; msg.className = 'error'; }
            return;
        }

        const timeError = validateTimeRange(payload.entry_time, payload.exit_time, payload.lunch_start, payload.lunch_end);
        if (timeError) {
            if (msg) { msg.textContent = timeError; msg.className = 'error'; }
            return;
        }

        openBatchConfirmModal(payload);
    }

    async function runManualBatchSubmit(payload) {
        const msg = document.getElementById('manual-attendance-message');
        if (!msg) return;

        let successCount = 0;
        let failCount = 0;
        let skippedConflicts = 0;
        const failDetails = [];

        for (const date of payload.dates) {
            try {
                const reqPayload = {
                    user_ids: payload.user_ids,
                    project_id: payload.project_id,
                    date,
                    entry_time: payload.entry_time,
                    exit_time: payload.exit_time,
                    reason: payload.reason,
                    lunch_start: payload.lunch_start,
                    lunch_end: payload.lunch_end,
                    conflict_strategy: payload.conflict_strategy,
                    force: payload.conflict_strategy === 'overwrite'
                };

                const response = await submitManualEntry(reqPayload);
                if (response.ok) {
                    const created = Number(response.data?.count || 0);
                    successCount += created;
                    const requested = payload.user_ids.length;
                    if (created < requested) skippedConflicts += (requested - created);
                } else {
                    failCount += payload.user_ids.length;
                    failDetails.push(`${date}: ${response.error?.message || 'Unknown error'}`);
                }
            } catch (err) {
                failCount += payload.user_ids.length;
                failDetails.push(`${date}: ${err.message || 'Request failed'}`);
            }
        }

        const lines = [`✅ Created: ${successCount}`];
        if (skippedConflicts > 0) lines.push(`⚠️ Skipped conflicts: ${skippedConflicts}`);
        if (failCount > 0) lines.push(`❌ Failed: ${failCount}`);
        if (failDetails.length) lines.push(failDetails.slice(0, 5).join(' | '));

        msg.textContent = lines.join(' · ');
        msg.className = failCount > 0 ? 'warning' : 'success';

        const attendanceForm = document.getElementById('manual-attendance-form');
        if (attendanceForm) attendanceForm.reset();
        clearSelectedUsers();
        setEntryMode('single');

        const uid = sessionStorage.getItem('user_id');
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (typeof loadAttendanceRecords === 'function') {
            await loadAttendanceRecords(role === 'admin' ? null : uid);
        }
    }

    async function handleManualAbsenceSubmit(e) {
        e.preventDefault();
        const msg = document.getElementById('manual-attendance-message');

        const userId = parseInt(document.getElementById('manual-absence-user')?.value || '', 10);
        const projectId = document.getElementById('manual-absence-project')?.value ? parseInt(document.getElementById('manual-absence-project').value, 10) : null;
        const date = document.getElementById('manual-absence-date')?.value;
        const reason = document.getElementById('manual-absence-reason')?.value || 'sin_justificacion';
        const notes = (document.getElementById('manual-absence-notes')?.value || '').trim();

        if (!Number.isFinite(userId) || !date) {
            if (msg) { msg.textContent = 'Please select user and date.'; msg.className = 'error'; }
            return;
        }

        try {
            await apiFetch('absences', 'POST', {
                user_id: userId,
                project_id: projectId,
                date_start: date,
                date_end: date,
                reason,
                notes
            });

            const form = document.getElementById('manual-absence-form');
            if (form) form.reset();
            if (msg) {
                msg.textContent = '✅ Manual absence created successfully.';
                msg.className = 'success';
            }

            if (typeof loadAdminAbsences === 'function') loadAdminAbsences();
            if (typeof loadAbsenceSummary === 'function') loadAbsenceSummary();
            if (typeof loadNotifications === 'function') loadNotifications();
        } catch (err) {
            if (msg) { msg.textContent = err.message || 'Error creating manual absence.'; msg.className = 'error'; }
        }
    }

    async function submitManualEntry(payload) {
        const url = `${API_BASE_URL}/attendance/manual`;
        const csrfToken = sessionStorage.getItem('csrf_token') || '';
        const headers = { 'Content-Type': 'application/json' };
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        const res = await fetch(url, {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok && !data.warning) {
            const err = new Error(data.error?.message || 'Server error');
            err.code = data.error?.code;
            throw err;
        }

        return data;
    }

    // Expose for script.js navigation
    window.populateManualDropdowns = populateManualDropdowns;
    window.initManualAttendance = initManualAttendance;
    window.setManualMode = setManualMode;
})();
