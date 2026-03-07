/**
 * Manual Attendance / Absence (Admin Only)
 */
(function () {
    'use strict';

    let currentManualMode = 'attendance';

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
            if (selectAllBtn) selectAllBtn.addEventListener('click', selectAllUsers);
            if (clearBtn) clearBtn.addEventListener('click', clearSelectedUsers);

            attendanceForm.dataset.manualInit = '1';
        }

        if (absenceForm.dataset.manualInit !== '1') {
            absenceForm.addEventListener('submit', handleManualAbsenceSubmit);
            absenceForm.dataset.manualInit = '1';
        }

        setManualMode(currentManualMode);
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

    async function populateManualDropdowns() {
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (role !== 'admin') return;

        try {
            const data = await apiFetch('users');
            const users = data.users || [];

            const employeeList = document.getElementById('manual-employee-list');
            if (employeeList && employeeList.children.length === 0) {
                employeeList.innerHTML = '';
                users.forEach(u => {
                    const item = document.createElement('label');
                    item.className = 'manual-employee-item';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'manual-employee-checkbox';
                    checkbox.value = String(u.id);
                    checkbox.addEventListener('change', updateSelectedCount);

                    const text = document.createElement('span');
                    text.textContent = u.username;

                    item.appendChild(checkbox);
                    item.appendChild(text);
                    employeeList.appendChild(item);
                });
                updateSelectedCount();
            }

            const absenceUserSelect = document.getElementById('manual-absence-user');
            if (absenceUserSelect && absenceUserSelect.options.length <= 1) {
                const opts = users
                    .filter(u => String(u.role || '').toLowerCase() !== 'admin')
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
            if (attendanceProject && attendanceProject.options.length <= 1) {
                projects.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    attendanceProject.appendChild(opt);
                });
            }

            const absenceProject = document.getElementById('manual-absence-project');
            if (absenceProject && absenceProject.options.length <= 1) {
                projects.forEach(p => {
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

    async function handleManualAttendanceSubmit(e) {
        e.preventDefault();
        const msg = document.getElementById('manual-attendance-message');

        const payload = {
            user_ids: getSelectedUserIds(),
            project_id: document.getElementById('manual-project').value ? parseInt(document.getElementById('manual-project').value, 10) : null,
            date: document.getElementById('manual-date').value,
            entry_time: document.getElementById('manual-entry-time').value,
            exit_time: document.getElementById('manual-exit-time').value,
            reason: document.getElementById('manual-reason').value.trim(),
            lunch_start: document.getElementById('manual-lunch-start')?.value || null,
            lunch_end: document.getElementById('manual-lunch-end')?.value || null,
        };

        if (!payload.user_ids.length || !payload.date || !payload.entry_time || !payload.exit_time || !payload.reason) {
            if (msg) { msg.textContent = 'Please fill all required fields and select at least one employee.'; msg.className = 'error'; }
            return;
        }

        try {
            const response = await submitManualEntry(payload);

            if (response.warning) {
                const usersWithWarning = response.error?.details?.user_ids || [];
                const warningPrefix = usersWithWarning.length
                    ? `Users with no Clock In: ${usersWithWarning.join(', ')}.\n\n`
                    : '';

                const proceed = confirm(warningPrefix + (response.error?.message || 'Do you want to continue anyway?'));
                if (proceed) {
                    payload.force = true;
                    const finalResponse = await submitManualEntry(payload);
                    showResult(msg, finalResponse, payload.user_ids.length, 'attendance');
                } else if (msg) {
                    msg.textContent = 'Operation cancelled.';
                    msg.className = 'warning';
                }
            } else {
                showResult(msg, response, payload.user_ids.length, 'attendance');
            }
        } catch (err) {
            if (msg) { msg.textContent = err.message || 'Error creating manual record.'; msg.className = 'error'; }
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

    function getSelectedUserIds() {
        return Array.from(document.querySelectorAll('.manual-employee-checkbox:checked'))
            .map(el => parseInt(el.value, 10))
            .filter(Number.isFinite);
    }

    function selectAllUsers() {
        document.querySelectorAll('.manual-employee-checkbox').forEach((cb) => { cb.checked = true; });
        updateSelectedCount();
    }

    function clearSelectedUsers() {
        document.querySelectorAll('.manual-employee-checkbox').forEach((cb) => { cb.checked = false; });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const selectedCountEl = document.getElementById('manual-selected-count');
        if (!selectedCountEl) return;
        const count = document.querySelectorAll('.manual-employee-checkbox:checked').length;
        selectedCountEl.textContent = `${count} selected`;
    }

    async function submitManualEntry(payload) {
        const url = `${API_BASE_URL}/attendance/manual`;
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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

    function showResult(msg, response, userCount, mode) {
        if (!msg) return;
        if (response.ok) {
            const count = response.data?.count || userCount || 1;
            msg.textContent = mode === 'attendance'
                ? `✅ ${count} manual ${count === 1 ? 'record' : 'records'} created successfully.`
                : '✅ Manual absence created successfully.';
            msg.className = 'success';

            const attendanceForm = document.getElementById('manual-attendance-form');
            if (attendanceForm) attendanceForm.reset();
            clearSelectedUsers();
        } else {
            msg.textContent = '❌ ' + (response.error?.message || 'Unknown error.');
            msg.className = 'error';
        }
    }

    // Expose for script.js navigation
    window.populateManualDropdowns = populateManualDropdowns;
    window.initManualAttendance = initManualAttendance;
    window.setManualMode = setManualMode;
})();
