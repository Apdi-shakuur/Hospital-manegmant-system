/**
 * Hospital Management System (HMS) - Dashboard Charts & Real-time Stats Script
 */

document.addEventListener('DOMContentLoaded', () => {
    loadDashboardMetrics();
});

async function loadDashboardMetrics() {
    const res = await fetchAPI('../api/dashboard.php');
    if (res && res.success && res.data) {
        renderKPIs(res.data.kpi);
        renderDeptChart(res.data.department_stats);
        renderActivityFeed(res.data.recent_activity);
    }
}

function renderKPIs(kpi) {
    const formatCurr = (val) => '$' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    };

    setVal('kpi-total-patients', kpi.total_patients);
    setVal('kpi-total-doctors', kpi.total_doctors);
    setVal('kpi-total-nurses', kpi.total_nurses);
    setVal('kpi-today-appts', kpi.today_appointments);
    setVal('kpi-pending-appts', kpi.pending_appts);
    setVal('kpi-completed-appts', kpi.completed_appts);
    setVal('kpi-avail-meds', kpi.available_medicines);
    setVal('kpi-low-stock-meds', kpi.low_stock_medicines);
    setVal('kpi-pending-labs', kpi.pending_lab_tests);
    setVal('kpi-today-revenue', formatCurr(kpi.today_revenue));
}

function renderDeptChart(departments) {
    const container = document.getElementById('dept-chart-container');
    if (!container || !departments) return;

    container.innerHTML = '';
    const maxVal = Math.max(...departments.map(d => Number(d.doctor_count)), 1);

    departments.forEach(dept => {
        const count = Number(dept.doctor_count);
        const percent = Math.round((count / maxVal) * 100);

        const group = document.createElement('div');
        group.className = 'chart-bar-group';
        group.innerHTML = `
            <div class="chart-bar" style="height: ${Math.max(percent, 12)}%;">
                <span class="chart-bar-value">${count}</span>
            </div>
            <div class="chart-label">${escapeHTML(dept.name)}</div>
        `;
        container.appendChild(group);
    });
}

function renderActivityFeed(activities) {
    const container = document.getElementById('activity-feed-container');
    if (!container || !activities) return;

    if (activities.length === 0) {
        container.innerHTML = '<div style="color:var(--slate-400); text-align:center;">No recent activity logs.</div>';
        return;
    }

    container.innerHTML = activities.map(act => `
        <li class="activity-item">
            <div class="activity-dot">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="activity-content">
                <div class="activity-title"><strong>${escapeHTML(act.username || 'System')}:</strong> ${escapeHTML(act.action)}</div>
                <div style="font-size: 0.8rem; color: var(--slate-600); margin-top: 0.15rem;">${escapeHTML(act.description)}</div>
                <div class="activity-time">${escapeHTML(act.created_at)}</div>
            </div>
        </li>
    `).join('');
}
