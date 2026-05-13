/**
 * absences.js — Absence reporting & management module for ClockinAPP
 */

// Reason labels for display
const REASON_LABELS = {
    familiar: 'Family',
    enfermedad: 'Illness',
    vacaciones: 'Vacation',
    sin_justificacion: 'Unexcused'
};

const REASON_COLORS = {
    familiar: '#3b82f6',        /* Azul (Active Employees) */
    enfermedad: '#ef4444',      /* Rojo (Today's Absences) */
    vacaciones: '#10b981',      /* Verde (Today's Clock-ins) */
    sin_justificacion: '#94a3b8' /* Gris neutro (Card box) */
};

let absenceSummaryChart = null;
let absenceStatusUpdateSeq = 0;
const ABSENCE_STATUS_OPTIONS = ['aprobado', 'rechazado', 'pendiente'];

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (ch) => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[ch]));
}

function ensureAbsenceEditModal() {
    let modal = document.getElementById('absence-edit-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'absence-edit-modal';
    modal.className = 'attendance-modal-overlay';
    modal.style.display = 'none';
    modal.innerHTML = `
      <div class="attendance-modal-content" style="max-width: 640px; width:min(96vw,640px); max-height:88vh; overflow:auto;">
        <div class="attendance-modal-header"><h3>Edit Absence</h3><button type="button" data-action="close-edit-absence" class="close-button">&times;</button></div>
        <form id="absence-edit-form" class="absence-form" style="padding:14px;">
          <input type="hidden" id="absence-edit-id" />
          <div class="form-row"><div class="form-group"><label>Start Date</label><input id="absence-edit-date-start" type="date" required></div><div class="form-group"><label>End Date</label><input id="absence-edit-date-end" type="date" required></div></div>
          <div class="form-row"><div class="form-group"><label>Reason</label><select id="absence-edit-reason" required><option value="familiar">Family</option><option value="enfermedad">Illness</option><option value="vacaciones">Vacation</option><option value="sin_justificacion">Unexcused</option></select></div><div class="form-group"><label>Status</label><select id="absence-edit-status" required><option value="pendiente">Pending</option><option value="aprobado">Approved</option><option value="rechazado">Rejected</option></select></div></div>
          <div class="form-group"><label>Notes</label><textarea id="absence-edit-notes" rows="3"></textarea></div>
          <p id="absence-edit-feedback" class="attendance-modal-feedback"></p>
          <div style="display:flex;justify-content:flex-end;gap:8px;"><button type="button" data-action="close-edit-absence" class="btn">Cancel</button><button type="submit" class="btn">Save</button></div>
        </form>
      </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.dataset.action === 'close-edit-absence') closeAbsenceEditModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.style.display === 'flex') closeAbsenceEditModal(); });
    modal.querySelector('#absence-edit-form').addEventListener('submit', submitAbsenceEditForm);
    return modal;
}

function openAbsenceEditModal(absence) {
    const modal = ensureAbsenceEditModal();
    document.getElementById('absence-edit-id').value = absence.id;
    document.getElementById('absence-edit-date-start').value = absence.date_start || '';
    document.getElementById('absence-edit-date-end').value = absence.date_end || absence.date_start || '';
    document.getElementById('absence-edit-reason').value = absence.reason || 'sin_justificacion';
    document.getElementById('absence-edit-status').value = absence.status || 'pendiente';
    document.getElementById('absence-edit-notes').value = absence.notes || '';
    const feedback = document.getElementById('absence-edit-feedback');
    feedback.textContent = '';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeAbsenceEditModal(){ const m=document.getElementById('absence-edit-modal'); if(m)m.style.display='none'; document.body.style.overflow=''; }

async function submitAbsenceEditForm(e){
  e.preventDefault();
  const form=e.target; const btn=form.querySelector('button[type="submit"]'); if(btn?.disabled)return;
  const id=document.getElementById('absence-edit-id').value;
  const payload={date_start:document.getElementById('absence-edit-date-start').value,date_end:document.getElementById('absence-edit-date-end').value,reason:document.getElementById('absence-edit-reason').value,status:document.getElementById('absence-edit-status').value,notes:document.getElementById('absence-edit-notes').value.trim()};
  const fb=document.getElementById('absence-edit-feedback');
  btn.disabled=true; btn.textContent='Saving...';
  try{ await apiFetch(`absences/${id}`,'PUT',payload); fb.textContent='Saved.'; fb.className='attendance-modal-feedback success'; await loadAdminAbsences(); await loadAbsenceSummary(); closeAbsenceEditModal(); }
  catch(err){ fb.textContent=err.message||'Update failed'; fb.className='attendance-modal-feedback error'; }
  finally{ btn.disabled=false; btn.textContent='Save'; }
}

async function updateAbsenceStatusInline(absenceId, status, triggerBtn) {
    if (!absenceId || !status || triggerBtn?.dataset.loading === '1') return;
    const seq = ++absenceStatusUpdateSeq;
    if (triggerBtn) { triggerBtn.dataset.loading = '1'; triggerBtn.disabled = true; }
    try {
        await apiFetch(`absences/${absenceId}/review`, 'PUT', { status });
        if (seq === absenceStatusUpdateSeq) { await loadAdminAbsences(); await loadAbsenceSummary(); }
    } catch (error) {
        appAlert('Error updating status: ' + (error.message || 'Unknown error'), 'Update Failed', 'error');
    } finally {
        if (triggerBtn) { triggerBtn.dataset.loading = '0'; triggerBtn.disabled = false; }
    }
}

// ========================================================
// User: Report Absence
// ========================================================

/**
 * Load projects into the absence form project selector.
 */
async function loadProjectsForAbsenceForm() {
    const select = document.getElementById('absence-project-select');
    if (!select) return;

    try {
        const data = await apiFetch('projects');
        if (data.projects) {
            select.innerHTML = '<option value="">— Select project —</option>' +
                data.projects.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        }
    } catch (err) {
        select.innerHTML = '<option value="">Error loading projects</option>';
    }
}

/**
 * Submit the absence report form.
 */
async function submitAbsenceReport(e) {
    e.preventDefault();

    const form = e.target;
    const submitBtn = form.querySelector('.submit-btn');
    const messageEl = document.getElementById('absence-form-message');

    const projectId = document.getElementById('absence-project-select').value;
    const dateStart = document.getElementById('absence-date-start').value;
    const dateEnd = document.getElementById('absence-date-end').value || dateStart;
    const reason = document.getElementById('absence-reason').value;
    const notes = document.getElementById('absence-notes').value;
    const fileInput = document.getElementById('absence-evidence');

    if (!dateStart || !reason) {
        if (messageEl) {
            messageEl.textContent = 'Date and reason are required.';
            messageEl.className = 'error-message';
        }
        return;
    }

    // Use FormData for multipart (file upload support)
    const formData = new FormData();
    if (projectId) formData.append('project_id', projectId);
    formData.append('date_start', dateStart);
    formData.append('date_end', dateEnd);
    formData.append('reason', reason);
    if (notes) formData.append('notes', notes);
    if (fileInput && fileInput.files.length > 0) {
        formData.append('evidence', fileInput.files[0]);
    }

    if (submitBtn) submitBtn.disabled = true;

    try {
        const url = `${API_BASE_URL}/absences`;
        const csrfToken = sessionStorage.getItem('csrf_token') || '';
        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData
        });

        const payload = await response.json();
        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload?.error?.message || 'Error submitting report.');
        }

        if (messageEl) {
            messageEl.textContent = payload.data.message || 'Report submitted successfully.';
            messageEl.className = 'success-message';
        }

        // Reset form
        form.reset();

        // Reload user's absence history
        loadUserAbsenceHistory();
        if (typeof loadNotifications === 'function') loadNotifications();

    } catch (error) {
        if (messageEl) {
            messageEl.textContent = error.message;
            messageEl.className = 'error-message';
        }
    } finally {
        if (submitBtn) submitBtn.disabled = false;
    }
}

/**
 * Load the current user's absence history table.
 */
async function loadUserAbsenceHistory() {
    const tbody = document.getElementById('user-absences-body');
    if (!tbody) return;

    try {
        const data = await apiFetch('absences');
        const absences = data.absences || [];

        if (absences.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">You have no absence reports.</td></tr>';
            return;
        }

        tbody.innerHTML = absences.map(a => {
            const dateRange = a.date_start === a.date_end
                ? a.date_start
                : `${a.date_start} → ${a.date_end}`;
            const projectName = a.project_name || 'No project';
            const reasonLabel = REASON_LABELS[a.reason] || a.reason;

            return `
            <tr>
                <td data-label="Date">${dateRange}</td>
                <td data-label="Project">${projectName}</td>
                <td data-label="Reason"><span class="reason-badge ${a.reason}">${reasonLabel}</span></td>
                <td data-label="Status"><span class="status-badge ${a.status}">${a.status}</span></td>
                <td data-label="Notes">${a.notes || '—'}</td>
                <td data-label="Actions">
                    <div class="absence-actions-cell">
                        ${a.evidence_path ? `<a href="${appUrl('/' + a.evidence_path)}" target="_blank" class="evidence-link"><i class="fas fa-paperclip"></i></a>` : ''}
                        ${a.status === 'pendiente' ? `<button class="delete-btn" data-action="delete-absence" data-absence-id="${a.id}"><i class="fas fa-trash-alt"></i></button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6">Error: ${error.message}</td></tr>`;
    }
}


// ========================================================
// Admin: Absence Records
// ========================================================

/**
 * Load all absences with admin filters.
 */
async function loadAdminAbsences() {
    const tbody = document.getElementById('admin-absences-body');
    const messageEl = document.getElementById('admin-absence-message');
    if (!tbody) return;

    // Collect filters
    const params = new URLSearchParams();
    const userId = document.getElementById('absence-filter-user')?.value;
    const projectId = document.getElementById('absence-filter-project')?.value;
    const reason = document.getElementById('absence-filter-reason')?.value;
    const status = document.getElementById('absence-filter-status')?.value;
    const dateFrom = document.getElementById('absence-filter-date-from')?.value;
    const dateTo = document.getElementById('absence-filter-date-to')?.value;

    if (userId) params.set('user_id', userId);
    if (projectId) params.set('project_id', projectId);
    if (reason) params.set('reason', reason);
    if (status) params.set('status', status);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    try {
        const qs = params.toString();
        const data = await apiFetch(`absences${qs ? '?' + qs : ''}`);
        const absences = data.absences || [];

        if (absences.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9">No absence reports found.</td></tr>';
            return;
        }

        tbody.innerHTML = absences.map(a => {
            const dateRange = a.date_start === a.date_end
                ? a.date_start
                : `${a.date_start} → ${a.date_end}`;
            const projectName = a.project_name || 'No project';
            const reasonLabel = REASON_LABELS[a.reason] || a.reason;
            const isPending = a.status === 'pendiente';

            return `
            <tr>
                <td data-label="User">${a.username}</td>
                <td data-label="Date">${dateRange}</td>
                <td data-label="Project">${projectName}</td>
                <td data-label="Reason"><span class="reason-badge ${a.reason}">${reasonLabel}</span></td>
                <td data-label="Status">
                    <div class="absence-status-inline" data-absence-id="${a.id}">
                        <button class="status-badge ${a.status} status-inline-trigger" data-action="toggle-status-menu" data-absence-id="${a.id}" aria-expanded="false">${a.status}</button>
                        <div class="status-inline-menu" hidden>
                            ${ABSENCE_STATUS_OPTIONS.map(s => `<button type="button" data-action="set-status-inline" data-absence-id="${a.id}" data-status="${s}" class="${s===a.status?'active':''}">${escapeHtml(s)}</button>`).join('')}
                        </div>
                    </div>
                </td>
                <td data-label="Notes">${a.notes || '—'}</td>
                <td data-label="Evidence">
                    ${a.evidence_path ? `<a href="${appUrl('/' + a.evidence_path)}" target="_blank" class="evidence-link">📎 View</a>` : '—'}
                </td>
                <td data-label="Reported At">${new Date(a.created_at).toLocaleDateString()}</td>
                <td data-label="Actions">
                    <div class="absence-actions-cell">
                        ${isPending ? `
                            <button class="approve-btn" data-action="approve-absence" data-absence-id="${a.id}"><i class="fas fa-check"></i></button>
                            <button class="reject-btn" data-action="reject-absence" data-absence-id="${a.id}"><i class="fas fa-times"></i></button>
                        ` : `<span style="opacity:0.5; font-size:0.8rem;">${a.reviewed_by_username ? 'by ' + a.reviewed_by_username : ''}</span>`}
                        <button class="edit-btn" data-action="edit-absence" data-absence='${encodeURIComponent(JSON.stringify(a))}'><i class="fas fa-edit"></i></button>
                        <button class="delete-btn" data-action="delete-absence" data-absence-id="${a.id}"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');

    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="9">Error: ${error.message}</td></tr>`;
    }
}

/**
 * Approve or reject an absence.
 */
async function reviewAbsence(absenceId, status) {
    try {
        await apiFetch(`absences/${absenceId}/review`, 'PUT', { status });
        loadAdminAbsences();
        loadAbsenceSummary(); // refresh summary
        if (typeof loadNotifications === 'function') loadNotifications();
    } catch (error) {
        appAlert('Error: ' + error.message, 'Action Failed', 'error');
    }
}



async function editAbsence(absence) {
    if (!absence || !absence.id) return;
    openAbsenceEditModal(absence);
}

/**
 * Delete an absence.
 */
async function deleteAbsence(absenceId) {
    appConfirm('Delete this absence report?', 'Delete Absence', async () => {
        try {
            await apiFetch(`absences/${absenceId}`, 'DELETE');
            // Reload whichever view is active
            const adminSection = document.getElementById('absence-records-section');
            if (adminSection && adminSection.style.display === 'block') {
                loadAdminAbsences();
                loadAbsenceSummary();
            } else {
                loadUserAbsenceHistory();
            }
        } catch (error) {
            appAlert('Error: ' + error.message, 'Deletion Error', 'error');
        }
    });
}

/**
 * Load users into the admin absence filter dropdown.
 */
async function loadUsersForAbsenceFilter() {
    const select = document.getElementById('absence-filter-user');
    if (!select || select.options.length > 1) return; // already loaded

    try {
        const data = await apiFetch('users');
        const opts = (data.users || []).map(u => `<option value="${u.id}">${u.username}</option>`).join('');
        select.innerHTML = '<option value="">All</option>' + opts;
    } catch (_) {}
}

/**
 * Load projects into the admin absence filter dropdown.
 */
async function loadProjectsForAbsenceFilter() {
    const select = document.getElementById('absence-filter-project');
    if (!select || select.options.length > 1) return;

    try {
        const data = await apiFetch('projects');
        const opts = (data.projects || []).map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        select.innerHTML = '<option value="">All</option>' + opts;
    } catch (_) {}
}




function getSelectedAdminAbsenceUserIds() {
    return Array.from(document.querySelectorAll('.admin-absence-employee-checkbox:checked'))
        .map(el => parseInt(el.value, 10))
        .filter(Number.isFinite);
}

function updateAdminAbsenceSelectedCount() {
    const selectedCountEl = document.getElementById('admin-absence-selected-count');
    if (!selectedCountEl) return;
    const count = document.querySelectorAll('.admin-absence-employee-checkbox:checked').length;
    selectedCountEl.textContent = `${count} selected`;
}

function selectAllAdminAbsenceUsers() {
    document.querySelectorAll('.admin-absence-employee-checkbox').forEach((cb) => {
        cb.checked = true;
    });
    updateAdminAbsenceSelectedCount();
}

function clearAdminAbsenceUsers() {
    document.querySelectorAll('.admin-absence-employee-checkbox').forEach((cb) => {
        cb.checked = false;
    });
    updateAdminAbsenceSelectedCount();
}

async function loadUsersForAdminAbsenceCreate() {
    const employeeList = document.getElementById('admin-absence-employee-list');
    if (!employeeList || employeeList.children.length > 0) return;

    try {
        const data = await apiFetch('users');
        const users = (data.users || []).filter(u => String(u.role || '').toLowerCase() !== 'admin');
        employeeList.innerHTML = '';

        users.forEach(u => {
            const item = document.createElement('label');
            item.className = 'manual-employee-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'admin-absence-employee-checkbox';
            checkbox.value = String(u.id);
            checkbox.addEventListener('change', updateAdminAbsenceSelectedCount);

            const text = document.createElement('span');
            text.textContent = u.username;

            item.appendChild(checkbox);
            item.appendChild(text);
            employeeList.appendChild(item);
        });

        updateAdminAbsenceSelectedCount();
    } catch (_) {}
}

async function createAbsenceByAdmin(e) {
    if (e) e.preventDefault();

    const userIds = getSelectedAdminAbsenceUserIds();
    const date = document.getElementById('admin-absence-date')?.value;
    const reason = document.getElementById('admin-absence-reason')?.value || 'sin_justificacion';
    const notesInput = document.getElementById('admin-absence-notes');
    const messageEl = document.getElementById('admin-absence-create-message');
    const notes = notesInput?.value?.trim() || '';

    if (!userIds.length || !date) {
        if (messageEl) {
            messageEl.textContent = 'Select at least one employee and the date.';
            messageEl.className = 'error-message';
        }
        return;
    }

    try {
        for (const uid of userIds) {
            await apiFetch('absences', 'POST', {
                user_id: Number(uid),
                date_start: date,
                date_end: date,
                reason,
                notes
            });
        }

        if (notesInput) notesInput.value = '';
        clearAdminAbsenceUsers();

        if (messageEl) {
            messageEl.textContent = `✅ ${userIds.length} absence(s) registered successfully.`;
            messageEl.className = 'success-message';
        }

        loadAdminAbsences();
        loadAbsenceSummary();
        if (typeof loadNotifications === 'function') loadNotifications();
    } catch (error) {
        if (messageEl) {
            messageEl.textContent = 'Error: ' + error.message;
            messageEl.className = 'error-message';
        }
    }
}

async function runAutoAbsences() {
    const date = document.getElementById('auto-absence-date')?.value;
    if (!date) {
        appAlert('Select the date to process.', 'Missing Info', 'warning');
        return;
    }

    try {
        const resp = await apiFetch('absences/auto-mark', 'POST', { date });
        appAlert(resp.message || 'Auto-absences processed.', 'Process Complete', 'success');
        loadAdminAbsences();
        loadAbsenceSummary();
    } catch (error) {
        appAlert('Error: ' + error.message, 'Process Error', 'error');
    }
}

// ========================================================
// Absence Summary / Report
// ========================================================

async function loadAbsenceSummary() {
    const container = document.getElementById('absence-summary-container');
    if (!container) return;

    // Collect same filters as the main list
    const params = new URLSearchParams();
    params.set('summary', '1');

    const userId = document.getElementById('absence-filter-user')?.value;
    const dateFrom = document.getElementById('absence-filter-date-from')?.value;
    const dateTo = document.getElementById('absence-filter-date-to')?.value;

    if (userId) params.set('user_id', userId);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    try {
        const data = await apiFetch(`absences?${params.toString()}`);
        const summaries = data.summary || [];

        if (summaries.length === 0) {
            container.innerHTML = '<p style="opacity:0.5">No absence data for the selected period.</p>';
            return;
        }

        let html = '';
        summaries.forEach(s => {
            const reasonsHtml = Object.entries(s.reasons).map(([reason, days]) => {
                const label = REASON_LABELS[reason] || reason;
                const color = REASON_COLORS[reason] || '#999';
                return `
                    <div class="reason-item">
                        <span class="reason-badge ${reason}">${label}</span>
                        <span class="reason-count" style="color:${color}">${days}</span>
                        <span>day${days !== 1 ? 's' : ''}</span>
                    </div>`;
            }).join('');

            html += `
                <div class="absence-summary-card">
                    <h5><i class="fas fa-user-circle" style="margin-right: 6px;"></i>${s.username}</h5>
                    <div class="reason-breakdown">${reasonsHtml}</div>
                    <div class="total-line">Total absences: ${s.total_days} day${s.total_days !== 1 ? 's' : ''}</div>
                    <div class="absence-summary-chart-container">
                        <canvas id="absence-chart-${s.user_id}" width="300" height="200"></canvas>
                    </div>
                </div>`;
        });

        container.innerHTML = html;

        // Render donut charts
        summaries.forEach(s => {
            const canvas = document.getElementById(`absence-chart-${s.user_id}`);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            const labels = Object.keys(s.reasons).map(r => REASON_LABELS[r] || r);
            const values = Object.values(s.reasons);
            const colors = Object.keys(s.reasons).map(r => REASON_COLORS[r] || '#999');

            // Only render if there's data
            if (values.every(v => v === 0)) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.map(c => c + '40'),
                        borderColor: colors,
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#ccc', font: { size: 11 } }
                        }
                    }
                }
            });
        });

    } catch (error) {
        container.innerHTML = `<p class="error-message">${error.message}</p>`;
    }
}


// ========================================================
// Init & Event Wiring (called from DOMContentLoaded in script.js)
// ========================================================

function initAbsencesModule() {
    const today = new Date().toISOString().slice(0, 10);
    const adminDate = document.getElementById('admin-absence-date');
    const autoDate = document.getElementById('auto-absence-date');
    if (adminDate && !adminDate.value) adminDate.value = today;
    if (autoDate && !autoDate.value) autoDate.value = today;
    // Absence form submit
    const absenceForm = document.getElementById('absence-report-form');
    if (absenceForm) {
        absenceForm.addEventListener('submit', submitAbsenceReport);
    }

    // Admin filter button
    const filterBtn = document.getElementById('absence-filter-btn');


    const autoRunBtn = document.getElementById('auto-absence-run-btn');
    if (autoRunBtn) {
        autoRunBtn.addEventListener('click', (e) => {
            e.preventDefault();
            runAutoAbsences();
        });
    }
    if (filterBtn) {
        filterBtn.addEventListener('click', () => {
            loadAdminAbsences();
            loadAbsenceSummary();
        });
    }

    // Admin search
    const adminSearch = document.getElementById('admin-absences-search');
    if (adminSearch) {
        adminSearch.addEventListener('keyup', () => {
            filterTable('admin-absences-search', 'admin-absences-body');
        });
    }

    // Delegated event listeners for action buttons
    const userAbsencesBody = document.getElementById('user-absences-body');
    if (userAbsencesBody) {
        userAbsencesBody.addEventListener('click', handleAbsenceAction);
    }

    const adminAbsencesBody = document.getElementById('admin-absences-body');
    if (adminAbsencesBody) {
        adminAbsencesBody.addEventListener('click', handleAbsenceAction);
    }
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.absence-status-inline')) {
            document.querySelectorAll('.status-inline-menu').forEach((el) => { el.hidden = true; });
            document.querySelectorAll('.status-inline-trigger').forEach((el) => el.setAttribute('aria-expanded', 'false'));
        }
    });
}

function handleAbsenceAction(e) {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    e.preventDefault();

    const action = btn.dataset.action;
    const absenceId = Number(btn.dataset.absenceId);

    if (action === 'approve-absence') {
        if (!absenceId) return;
        reviewAbsence(absenceId, 'aprobado');
    } else if (action === 'reject-absence') {
        if (!absenceId) return;
        reviewAbsence(absenceId, 'rechazado');
    } else if (action === 'toggle-status-menu') {
        const inline = btn.closest('.absence-status-inline');
        if (!inline) return;
        const menu = inline.querySelector('.status-inline-menu');
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        document.querySelectorAll('.status-inline-menu').forEach((el) => { el.hidden = true; });
        document.querySelectorAll('.status-inline-trigger').forEach((el) => el.setAttribute('aria-expanded', 'false'));
        menu.hidden = expanded;
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    } else if (action === 'set-status-inline') {
        const status = btn.dataset.status;
        updateAbsenceStatusInline(absenceId, status, btn);
    } else if (action === 'edit-absence') {
        const encoded = btn.dataset.absence || '';
        if (!encoded) return;
        try {
            const absence = JSON.parse(decodeURIComponent(encoded));
            editAbsence(absence);
        } catch (_) {}
    } else if (action === 'delete-absence') {
        deleteAbsence(absenceId);
    }
}
