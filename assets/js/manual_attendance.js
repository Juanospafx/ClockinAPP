/**
 * Manual Attendance Entry (Admin Only)
 * Handles the form submission and populates dropdowns.
 */
(function () {
    'use strict';

    function initManualAttendance() {
        const form = document.getElementById('manual-attendance-form');
        if (!form) return;

        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (role !== 'admin') return;

        if (form.dataset.manualInit === '1') return;
        form.addEventListener('submit', handleManualSubmit);

        const selectAllBtn = document.getElementById('manual-select-all-users');
        const clearBtn = document.getElementById('manual-clear-users');
        if (selectAllBtn) selectAllBtn.addEventListener('click', selectAllUsers);
        if (clearBtn) clearBtn.addEventListener('click', clearSelectedUsers);

        form.dataset.manualInit = '1';
    }

    async function populateManualDropdowns() {
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (role !== 'admin') return;

        // Populate employees (checkbox list)
        const employeeList = document.getElementById('manual-employee-list');
        if (employeeList && employeeList.children.length === 0) {
            try {
                const data = await apiFetch('users');
                const users = data.users || [];
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
            } catch (e) {
                console.error('Error loading users for manual attendance:', e);
            }
        }

        // Populate projects
        const projectSelect = document.getElementById('manual-project');
        if (projectSelect && projectSelect.options.length <= 1) {
            try {
                const data = await apiFetch('projects');
                const projects = data.projects || [];
                projects.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    projectSelect.appendChild(opt);
                });
            } catch (e) {
                console.error('Error loading projects for manual attendance:', e);
            }
        }
    }

    async function handleManualSubmit(e) {
        e.preventDefault();
        const msg = document.getElementById('manual-attendance-message');

        const selectedUserIds = getSelectedUserIds();
        const payload = {
            user_ids: selectedUserIds,
            project_id: document.getElementById('manual-project').value ? parseInt(document.getElementById('manual-project').value, 10) : null,
            type: document.getElementById('manual-type').value,
            date: document.getElementById('manual-date').value,
            time: document.getElementById('manual-time').value,
            reason: document.getElementById('manual-reason').value.trim(),
        };

        if (!payload.user_ids.length || !payload.type || !payload.date || !payload.time || !payload.reason) {
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
                    showResult(msg, finalResponse, payload.user_ids.length);
                } else {
                    if (msg) { msg.textContent = 'Operation cancelled.'; msg.className = 'warning'; }
                }
            } else {
                showResult(msg, response, payload.user_ids.length);
            }
        } catch (err) {
            if (msg) { msg.textContent = err.message || 'Error creating manual record.'; msg.className = 'error'; }
        }
    }

    function getSelectedUserIds() {
        return Array.from(document.querySelectorAll('.manual-employee-checkbox:checked'))
            .map(el => parseInt(el.value, 10))
            .filter(Number.isFinite);
    }

    function selectAllUsers() {
        document.querySelectorAll('.manual-employee-checkbox').forEach((cb) => {
            cb.checked = true;
        });
        updateSelectedCount();
    }

    function clearSelectedUsers() {
        document.querySelectorAll('.manual-employee-checkbox').forEach((cb) => {
            cb.checked = false;
        });
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

    function showResult(msg, response, userCount) {
        if (!msg) return;
        if (response.ok) {
            const count = response.data?.count || userCount || 1;
            msg.textContent = `✅ ${count} manual ${count === 1 ? 'record' : 'records'} created successfully.`;
            msg.className = 'success';
            document.getElementById('manual-attendance-form').reset();
            clearSelectedUsers();
        } else {
            msg.textContent = '❌ ' + (response.error?.message || 'Unknown error.');
            msg.className = 'error';
        }
    }

    // Expose for script.js navigation
    window.populateManualDropdowns = populateManualDropdowns;
    window.initManualAttendance = initManualAttendance;
})();
