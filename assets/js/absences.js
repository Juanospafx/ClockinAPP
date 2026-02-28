/**
 * absences.js — Absence reporting & management module for ClockinAPP
 */

// Reason labels for display
const REASON_LABELS = {
    familiar: 'Familiar',
    enfermedad: 'Enfermedad',
    vacaciones: 'Vacaciones',
    sin_justificacion: 'Sin justificación'
};

const REASON_COLORS = {
    familiar: '#9b59b6',
    enfermedad: '#e74c3c',
    vacaciones: '#3498db',
    sin_justificacion: '#95a5a6'
};

let absenceSummaryChart = null;

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
            select.innerHTML = '<option value="">— Seleccionar proyecto —</option>' +
                data.projects.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        }
    } catch (err) {
        select.innerHTML = '<option value="">Error cargando proyectos</option>';
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
            messageEl.textContent = 'La fecha y la razón son obligatorias.';
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
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        const payload = await response.json();
        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload?.error?.message || 'Error al enviar el reporte.');
        }

        if (messageEl) {
            messageEl.textContent = payload.data.message || 'Reporte enviado.';
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
            tbody.innerHTML = '<tr><td colspan="6">No tienes reportes de ausencia.</td></tr>';
            return;
        }

        tbody.innerHTML = absences.map(a => {
            const dateRange = a.date_start === a.date_end
                ? a.date_start
                : `${a.date_start} → ${a.date_end}`;
            const projectName = a.project_name || 'Sin proyecto';
            const reasonLabel = REASON_LABELS[a.reason] || a.reason;

            return `
            <tr>
                <td data-label="Fecha">${dateRange}</td>
                <td data-label="Proyecto">${projectName}</td>
                <td data-label="Razón"><span class="reason-badge ${a.reason}">${reasonLabel}</span></td>
                <td data-label="Estado"><span class="status-badge ${a.status}">${a.status}</span></td>
                <td data-label="Notas">${a.notes || '—'}</td>
                <td data-label="Acciones">
                    ${a.evidence_path ? `<a href="${appUrl('/' + a.evidence_path)}" target="_blank" class="evidence-link">📎 Ver</a>` : ''}
                    ${a.status === 'pendiente' ? `<button class="small-button" data-action="delete-absence" data-absence-id="${a.id}">Eliminar</button>` : ''}
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
            tbody.innerHTML = '<tr><td colspan="9">No se encontraron reportes de ausencia.</td></tr>';
            return;
        }

        tbody.innerHTML = absences.map(a => {
            const dateRange = a.date_start === a.date_end
                ? a.date_start
                : `${a.date_start} → ${a.date_end}`;
            const projectName = a.project_name || 'Sin proyecto';
            const reasonLabel = REASON_LABELS[a.reason] || a.reason;
            const isPending = a.status === 'pendiente';

            return `
            <tr>
                <td data-label="Usuario">${a.username}</td>
                <td data-label="Fecha">${dateRange}</td>
                <td data-label="Proyecto">${projectName}</td>
                <td data-label="Razón"><span class="reason-badge ${a.reason}">${reasonLabel}</span></td>
                <td data-label="Estado"><span class="status-badge ${a.status}">${a.status}</span></td>
                <td data-label="Notas">${a.notes || '—'}</td>
                <td data-label="Evidencia">
                    ${a.evidence_path ? `<a href="${appUrl('/' + a.evidence_path)}" target="_blank" class="evidence-link">📎 Ver</a>` : '—'}
                </td>
                <td data-label="Reportado">${new Date(a.created_at).toLocaleDateString()}</td>
                <td data-label="Acciones">
                    <div class="absence-actions-cell">
                        ${isPending ? `
                            <button class="approve-btn" data-action="approve-absence" data-absence-id="${a.id}">✓ Aprobar</button>
                            <button class="reject-btn" data-action="reject-absence" data-absence-id="${a.id}">✗ Rechazar</button>
                        ` : `<span style="opacity:0.5; font-size:0.8rem;">${a.reviewed_by_username ? 'por ' + a.reviewed_by_username : ''}</span>`}
                        <button class="delete-btn" data-action="delete-absence" data-absence-id="${a.id}">🗑</button>
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
        alert('Error: ' + error.message);
    }
}

/**
 * Delete an absence.
 */
async function deleteAbsence(absenceId) {
    if (!confirm('¿Eliminar este reporte de ausencia?')) return;
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
        alert('Error: ' + error.message);
    }
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
        select.innerHTML = '<option value="">Todos</option>' + opts;
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
        select.innerHTML = '<option value="">Todos</option>' + opts;
    } catch (_) {}
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
            container.innerHTML = '<p style="opacity:0.5">Sin datos de ausencia para el período seleccionado.</p>';
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
                        <span>día${days !== 1 ? 's' : ''}</span>
                    </div>`;
            }).join('');

            html += `
                <div class="absence-summary-card">
                    <h5>👤 ${s.username}</h5>
                    <div class="reason-breakdown">${reasonsHtml}</div>
                    <div class="total-line">Total ausencias: ${s.total_days} día${s.total_days !== 1 ? 's' : ''}</div>
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
    // Absence form submit
    const absenceForm = document.getElementById('absence-report-form');
    if (absenceForm) {
        absenceForm.addEventListener('submit', submitAbsenceReport);
    }

    // Admin filter button
    const filterBtn = document.getElementById('absence-filter-btn');
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
}

function handleAbsenceAction(e) {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    e.preventDefault();

    const action = btn.dataset.action;
    const absenceId = Number(btn.dataset.absenceId);
    if (!absenceId) return;

    if (action === 'approve-absence') {
        reviewAbsence(absenceId, 'aprobado');
    } else if (action === 'reject-absence') {
        reviewAbsence(absenceId, 'rechazado');
    } else if (action === 'delete-absence') {
        deleteAbsence(absenceId);
    }
}
