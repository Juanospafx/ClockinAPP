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

        // Set max date to today
        const dateInput = document.getElementById('manual-date');
        if (dateInput) {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            dateInput.max = `${yyyy}-${mm}-${dd}`;
        }

        if (form.dataset.manualInit === '1') return;
        form.addEventListener('submit', handleManualSubmit);
        form.dataset.manualInit = '1';
    }

    async function populateManualDropdowns() {
        const role = normalizeRole(sessionStorage.getItem('user_role'));
        if (role !== 'admin') return;

        // Populate employees
        const employeeSelect = document.getElementById('manual-employee');
        if (employeeSelect && employeeSelect.options.length <= 1) {
            try {
                const data = await apiFetch('users');
                const users = data.users || [];
                users.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = u.username;
                    employeeSelect.appendChild(opt);
                });
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

        const payload = {
            user_id: parseInt(document.getElementById('manual-employee').value, 10),
            project_id: document.getElementById('manual-project').value ? parseInt(document.getElementById('manual-project').value, 10) : null,
            type: document.getElementById('manual-type').value,
            date: document.getElementById('manual-date').value,
            time: document.getElementById('manual-time').value,
            reason: document.getElementById('manual-reason').value.trim(),
        };

        if (!payload.user_id || !payload.type || !payload.date || !payload.time || !payload.reason) {
            if (msg) { msg.textContent = 'Please fill all required fields.'; msg.className = 'error'; }
            return;
        }

        try {
            const response = await submitManualEntry(payload);

            if (response.warning) {
                // No entry found warning — ask admin to confirm
                const proceed = confirm(response.error.message);
                if (proceed) {
                    payload.force = true;
                    const finalResponse = await submitManualEntry(payload);
                    showResult(msg, finalResponse);
                } else {
                    if (msg) { msg.textContent = 'Operation cancelled.'; msg.className = 'warning'; }
                }
            } else {
                showResult(msg, response);
            }
        } catch (err) {
            if (msg) { msg.textContent = err.message || 'Error creating manual record.'; msg.className = 'error'; }
        }
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

    function showResult(msg, response) {
        if (!msg) return;
        if (response.ok) {
            msg.textContent = '✅ ' + (response.data?.message || 'Record created successfully.');
            msg.className = 'success';
            // Reset form
            document.getElementById('manual-attendance-form').reset();
        } else {
            msg.textContent = '❌ ' + (response.error?.message || 'Unknown error.');
            msg.className = 'error';
        }
    }

    // Expose for script.js navigation
    window.populateManualDropdowns = populateManualDropdowns;
    window.initManualAttendance = initManualAttendance;
})();
