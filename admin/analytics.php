<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

requireLogin();

$range = $_GET['range'] ?? '7';

renderHeader('Analytics', 'analytics');
?>

<div class="filters-bar">
    <div class="filters-form">
        <a href="analytics.php?range=1" class="btn <?= $range === '1' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Today</a>
        <a href="analytics.php?range=7" class="btn <?= $range === '7' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">7 Days</a>
        <a href="analytics.php?range=30" class="btn <?= $range === '30' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">30 Days</a>
        <a href="analytics.php?range=90" class="btn <?= $range === '90' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">90 Days</a>
    </div>
</div>

<!-- Stats (loaded via JS) -->
<div class="stats-row" id="statsRow">
    <div class="stat-widget"><div class="stat-widget-icon blue"><svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg></div><div class="stat-widget-data"><span class="stat-widget-value" id="statViews">—</span><span class="stat-widget-label">Page Views</span></div></div>
    <div class="stat-widget"><div class="stat-widget-icon green"><svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg></div><div class="stat-widget-data"><span class="stat-widget-value" id="statVisitors">—</span><span class="stat-widget-label">Unique Visitors</span></div></div>
    <div class="stat-widget"><div class="stat-widget-icon orange"><svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg></div><div class="stat-widget-data"><span class="stat-widget-value" id="statToday">—</span><span class="stat-widget-label">Today</span></div></div>
    <div class="stat-widget"><div class="stat-widget-icon purple"><svg viewBox="0 0 20 20" fill="currentColor" width="22" height="22"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg></div><div class="stat-widget-data"><span class="stat-widget-value" id="statAvg">—</span><span class="stat-widget-label">Avg / Day</span></div></div>
</div>

<!-- Chart -->
<div class="card">
    <div class="card-header"><h2>Traffic Overview</h2></div>
    <div class="card-body">
        <canvas id="trafficChart" height="200"></canvas>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Top Pages -->
    <div class="card">
        <div class="card-header"><h2>Top Pages</h2></div>
        <div class="card-body no-pad">
            <div class="table-wrap">
                <table class="data-table" id="topPagesTable">
                    <thead><tr><th>Page</th><th>Views</th><th>Unique</th></tr></thead>
                    <tbody><tr><td colspan="3" class="empty-state">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Referrers -->
    <div class="card">
        <div class="card-header"><h2>Top Referrers</h2></div>
        <div class="card-body no-pad">
            <div class="table-wrap">
                <table class="data-table" id="referrersTable">
                    <thead><tr><th>Source</th><th>Visits</th></tr></thead>
                    <tbody><tr><td colspan="2" class="empty-state">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Devices -->
    <div class="card">
        <div class="card-header"><h2>Devices</h2></div>
        <div class="card-body" id="devicesPanel"><p class="empty-state">Loading...</p></div>
    </div>

    <!-- Browsers & OS -->
    <div class="card">
        <div class="card-header"><h2>Browsers & OS</h2></div>
        <div class="card-body" id="browserPanel"><p class="empty-state">Loading...</p></div>
    </div>
</div>

<script>
const range = <?= (int)$range ?>;
const API = 'api/analytics.php';

// Simple bar chart on canvas (no library needed)
function drawChart(canvas, data) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);

    const w = rect.width;
    const h = rect.height;
    const padding = { top: 20, right: 20, bottom: 40, left: 50 };
    const chartW = w - padding.left - padding.right;
    const chartH = h - padding.top - padding.bottom;

    if (!data.length) {
        ctx.fillStyle = '#555';
        ctx.font = '14px Inter';
        ctx.textAlign = 'center';
        ctx.fillText('No data yet', w / 2, h / 2);
        return;
    }

    const maxVal = Math.max(...data.map(d => d.views), 1);
    const barW = Math.max(4, (chartW / data.length) - 4);

    // Grid lines
    ctx.strokeStyle = 'rgba(255,255,255,0.04)';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (chartH / 4) * i;
        ctx.beginPath();
        ctx.moveTo(padding.left, y);
        ctx.lineTo(w - padding.right, y);
        ctx.stroke();

        ctx.fillStyle = '#555';
        ctx.font = '10px Inter';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxVal - (maxVal / 4) * i), padding.left - 8, y + 4);
    }

    // Bars
    data.forEach((d, i) => {
        const x = padding.left + (chartW / data.length) * i + 2;
        const barH = (d.views / maxVal) * chartH;
        const y = padding.top + chartH - barH;

        const gradient = ctx.createLinearGradient(x, y, x, y + barH);
        gradient.addColorStop(0, '#4FC3F7');
        gradient.addColorStop(1, 'rgba(79,195,247,0.2)');
        ctx.fillStyle = gradient;
        ctx.fillRect(x, y, barW, barH);

        // Visitor dots
        const visitorH = (d.visitors / maxVal) * chartH;
        const vy = padding.top + chartH - visitorH;
        ctx.beginPath();
        ctx.arc(x + barW / 2, vy, 3, 0, Math.PI * 2);
        ctx.fillStyle = '#F97316';
        ctx.fill();

        // Date label
        if (data.length <= 31 || i % Math.ceil(data.length / 10) === 0) {
            ctx.fillStyle = '#555';
            ctx.font = '9px Inter';
            ctx.textAlign = 'center';
            const dateStr = new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            ctx.fillText(dateStr, x + barW / 2, h - 8);
        }
    });

    // Legend
    ctx.fillStyle = '#4FC3F7';
    ctx.fillRect(w - 140, 8, 10, 10);
    ctx.fillStyle = '#9999aa';
    ctx.font = '10px Inter';
    ctx.textAlign = 'left';
    ctx.fillText('Views', w - 126, 17);

    ctx.beginPath();
    ctx.arc(w - 60, 13, 4, 0, Math.PI * 2);
    ctx.fillStyle = '#F97316';
    ctx.fill();
    ctx.fillStyle = '#9999aa';
    ctx.fillText('Visitors', w - 52, 17);
}

function renderBar(label, value, max, color) {
    const pct = max > 0 ? (value / max * 100) : 0;
    return `<div class="analytics-bar"><div class="analytics-bar-header"><span>${label}</span><span style="color:${color}">${value}</span></div><div class="analytics-bar-track"><div class="analytics-bar-fill" style="width:${pct}%;background:${color}"></div></div></div>`;
}

// Load data
fetch(API + '?action=dashboard&range=' + range).then(r => r.json()).then(d => {
    document.getElementById('statViews').textContent = d.total_views.toLocaleString();
    document.getElementById('statVisitors').textContent = d.unique_visitors.toLocaleString();
    document.getElementById('statToday').textContent = d.today_views.toLocaleString();
    document.getElementById('statAvg').textContent = d.avg_per_day.toLocaleString();

    // Devices
    const maxDev = Math.max(...d.devices.map(x => x.cnt), 1);
    const devColors = { desktop: '#4FC3F7', mobile: '#F97316', tablet: '#a855f7' };
    document.getElementById('devicesPanel').innerHTML = d.devices.length ?
        d.devices.map(x => renderBar(x.device_type, x.cnt, maxDev, devColors[x.device_type] || '#4FC3F7')).join('') :
        '<p class="empty-state">No data yet</p>';

    // Browsers + OS
    const maxBr = Math.max(...d.browsers.map(x => x.cnt), 1);
    const brHtml = d.browsers.map(x => renderBar(x.browser, x.cnt, maxBr, '#4FC3F7')).join('');
    const maxOs = Math.max(...d.oses.map(x => x.cnt), 1);
    const osHtml = d.oses.map(x => renderBar(x.os, x.cnt, maxOs, '#F97316')).join('');
    document.getElementById('browserPanel').innerHTML = (d.browsers.length || d.oses.length) ?
        '<h4 style="font-size:0.8rem;color:var(--text-muted);margin-bottom:12px">BROWSERS</h4>' + (brHtml || '<p class="text-muted">No data</p>') +
        '<h4 style="font-size:0.8rem;color:var(--text-muted);margin:20px 0 12px">OS</h4>' + (osHtml || '<p class="text-muted">No data</p>') :
        '<p class="empty-state">No data yet</p>';
});

fetch(API + '?action=pages&range=' + range).then(r => r.json()).then(d => {
    const tbody = document.querySelector('#topPagesTable tbody');
    tbody.innerHTML = d.pages.length ? d.pages.map(p =>
        `<tr><td><strong>${p.page_title || p.page_path}</strong><br><code style="font-size:0.7rem;color:var(--text-muted)">${p.page_path}</code></td><td>${p.views}</td><td>${p.unique_views}</td></tr>`
    ).join('') : '<tr><td colspan="3" class="empty-state">No data yet</td></tr>';
});

fetch(API + '?action=referrers&range=' + range).then(r => r.json()).then(d => {
    const tbody = document.querySelector('#referrersTable tbody');
    tbody.innerHTML = d.referrers.length ? d.referrers.map(r => {
        let display = r.referrer;
        try { display = new URL(r.referrer).hostname; } catch(e) {}
        return `<tr><td><a href="${r.referrer}" target="_blank" style="color:var(--blue)">${display}</a></td><td>${r.cnt}</td></tr>`;
    }).join('') : '<tr><td colspan="2" class="empty-state">No referrer data yet</td></tr>';
});

fetch(API + '?action=chart&range=' + range).then(r => r.json()).then(d => {
    drawChart(document.getElementById('trafficChart'), d.chart);
});

window.addEventListener('resize', () => {
    fetch(API + '?action=chart&range=' + range).then(r => r.json()).then(d => {
        drawChart(document.getElementById('trafficChart'), d.chart);
    });
});
</script>

<?php renderFooter(); ?>
