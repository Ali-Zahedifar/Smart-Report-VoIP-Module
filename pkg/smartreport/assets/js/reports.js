// Reports module - Chart.js initialization and export

let charts = {};

function formatDuration(seconds) {
    seconds = parseInt(seconds);
    if (seconds < 60) return seconds + 's';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (m < 60) return m + 'm ' + s + 's';
    const h = Math.floor(m / 60);
    return h + 'h ' + (m % 60) + 'm';
}

function formatNumber(n) {
    return n.toLocaleString();
}

function getColorPalette(count) {
    const colors = [
        'rgba(54, 162, 235, 0.8)',   // blue
        'rgba(75, 192, 192, 0.8)',   // teal
        'rgba(255, 206, 86, 0.8)',   // yellow
        'rgba(255, 99, 132, 0.8)',   // red
        'rgba(153, 102, 255, 0.8)',  // purple
        'rgba(255, 159, 64, 0.8)',   // orange
        'rgba(199, 199, 199, 0.8)',  // grey
        'rgba(83, 102, 255, 0.8)',   // indigo
    ];
    const borders = colors.map(c => c.replace('0.8', '1'));
    return { backgrounds: colors.slice(0, count), borders: borders.slice(0, count) };
}

async function fetchReportData() {
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const queue = document.getElementById('queue').value;

    const form = document.getElementById('reportFilters');
    const fetchUrl = form.dataset.fetchUrl;
    if (!fetchUrl) {
        throw new Error('Missing report data URL');
    }

    const params = new URLSearchParams({
        date_from: dateFrom,
        date_to: dateTo
    });
    if (queue) params.append('queue', queue);

    const res = await fetch(fetchUrl + (fetchUrl.includes('?') ? '&' : '?') + params.toString());
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: 'Failed to fetch report data' }));
        throw new Error(err.message || 'Failed to fetch report data');
    }
    return res.json();
}

function renderDirectionChart(data) {
    const ctx = document.getElementById('dirChart').getContext('2d');
    const { labels, data: values } = data.calls_by_direction;
    const { backgrounds, borders } = getColorPalette(labels.length);

    if (charts.dirChart) charts.dirChart.destroy();
    charts.dirChart = new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: backgrounds, borderColor: borders, borderWidth: 2 }] },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.label}: ${formatNumber(ctx.raw)} (${((ctx.raw / values.reduce((a,b)=>a+b,0))*100).toFixed(1)}%)`
                    }
                }
            }
        }
    });
}

function renderHourChart(data) {
    const ctx = document.getElementById('hourChart').getContext('2d');
    const { labels, data: values } = data.calls_by_hour;

    if (charts.hourChart) charts.hourChart.destroy();
    charts.hourChart = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Calls', data: values, backgroundColor: 'rgba(54, 162, 235, 0.7)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 }] },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
    });
}

function renderMissedChart(data) {
    const ctx = document.getElementById('missedChart').getContext('2d');
    const { labels, data: values } = data.missed_trend;

    if (charts.missedChart) charts.missedChart.destroy();
    charts.missedChart = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{ label: 'Missed Calls', data: values, borderColor: 'rgba(255, 99, 132, 1)', backgroundColor: 'rgba(255, 99, 132, 0.1)', fill: true, tension: 0.3, pointRadius: 3 }] },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
    });
}

function renderTalkChart(data) {
    const ctx = document.getElementById('talkChart').getContext('2d');
    const { labels, data: values } = data.talk_time_dist;
    const { backgrounds, borders } = getColorPalette(labels.length);

    if (charts.talkChart) charts.talkChart.destroy();
    charts.talkChart = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Calls', data: values, backgroundColor: backgrounds, borderColor: borders, borderWidth: 1 }] },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            indexAxis: 'y',
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
    });
}

function renderQueueChart(data) {
    const ctx = document.getElementById('queueChart').getContext('2d');
    const queues = data.queue_performance;

    if (queues.length === 0) {
        ctx.font = '14px sans-serif';
        ctx.fillStyle = '#999';
        ctx.textAlign = 'center';
        ctx.fillText('No queue data', ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }

    const labels = queues.map(q => 'Queue ' + q.queue);
    const offered = queues.map(q => q.offered);
    const answered = queues.map(q => q.answered);
    const missed = queues.map(q => q.missed);
    const abandonment = queues.map(q => q.abandonment_rate);

    if (charts.queueChart) charts.queueChart.destroy();
    charts.queueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Offered', data: offered, backgroundColor: 'rgba(54, 162, 235, 0.7)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 },
                { label: 'Answered', data: answered, backgroundColor: 'rgba(75, 192, 192, 0.7)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 },
                { label: 'Missed', data: missed, backgroundColor: 'rgba(255, 99, 132, 0.7)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderAgentChart(data) {
    const ctx = document.getElementById('agentChart').getContext('2d');
    const agents = data.agent_performance;

    if (agents.length === 0) {
        ctx.font = '14px sans-serif';
        ctx.fillStyle = '#999';
        ctx.textAlign = 'center';
        ctx.fillText('No agent data', ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }

    const labels = agents.map(a => 'Ext ' + a.extension);
    const answered = agents.map(a => a.answered);
    const missed = agents.map(a => a.missed);
    const avgTalk = agents.map(a => a.avg_talk);

    if (charts.agentChart) charts.agentChart.destroy();
    charts.agentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Answered', data: answered, backgroundColor: 'rgba(75, 192, 192, 0.7)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 },
                { label: 'Missed', data: missed, backgroundColor: 'rgba(255, 99, 132, 0.7)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            indexAxis: 'y',
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function updateQueueFilter(queues) {
    const select = document.getElementById('queue');
    const current = select.value;
    select.innerHTML = '<option value="">All Queues</option>';
    queues.forEach(q => {
        const opt = document.createElement('option');
        opt.value = q.queue;
        opt.textContent = 'Queue ' + q.queue + ' (' + q.offered + ' calls)';
        select.appendChild(opt);
    });
    select.value = current;
}

function showLoading(show) {
    document.getElementById('reportLoading').style.display = show ? 'block' : 'none';
    document.getElementById('reportError').style.display = 'none';
}

function showError(message) {
    const errorEl = document.getElementById('reportError');
    errorEl.textContent = message;
    errorEl.style.display = 'block';
}

async function loadReports() {
    showLoading(true);
    try {
        const data = await fetchReportData();

        renderDirectionChart(data);
        renderHourChart(data);
        renderMissedChart(data);
        renderTalkChart(data);
        renderQueueChart(data);
        renderAgentChart(data);

        updateQueueFilter(data.queue_performance);
    } catch (e) {
        console.error('Report load error:', e);
        showError('Failed to load reports: ' + e.message);
    } finally {
        showLoading(false);
    }
}

// Export single chart as PNG
function downloadChartPNG(chartId) {
    const chart = charts[chartId];
    if (!chart) return;
    const link = document.createElement('a');
    link.download = chartId + '-' + new Date().toISOString().slice(0,10) + '.png';
    link.href = chart.toBase64Image();
    link.click();
}

// Export all charts as PDF
async function downloadAllChartsPDF() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: 'a4' });

    const chartIds = ['dirChart', 'hourChart', 'missedChart', 'talkChart', 'queueChart', 'agentChart'];
    const titles = {
        dirChart: 'Calls by Direction',
        hourChart: 'Calls by Hour',
        missedChart: 'Missed Calls Trend (30 Days)',
        talkChart: 'Talk Time Distribution',
        queueChart: 'Queue Performance',
        agentChart: 'Top Agent Performance'
    };

    let yOffset = 20;
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const chartWidth = pageWidth - 40;

    for (let i = 0; i < chartIds.length; i++) {
        const chart = charts[chartIds[i]];
        if (!chart) continue;

        // Add title
        pdf.setFontSize(16);
        pdf.text(titles[chartIds[i]] || chartIds[i], 20, yOffset);
        yOffset += 10;

        // Add chart image
        const imgData = chart.toBase64Image();
        const imgProps = pdf.getImageProperties(imgData);
        const imgHeight = (imgProps.height * chartWidth) / imgProps.width;

        if (yOffset + imgHeight > pageHeight - 20) {
            pdf.addPage();
            yOffset = 20;
        }

        pdf.addImage(imgData, 'PNG', 20, yOffset, chartWidth, imgHeight);
        yOffset += imgHeight + 20;
    }

    pdf.save('call-reports-' + new Date().toISOString().slice(0,10) + '.pdf');
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Set default dates to last 30 days
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    document.getElementById('date_from').value = thirtyDaysAgo.toISOString().slice(0,10);
    document.getElementById('date_to').value = today.toISOString().slice(0,10);

    document.getElementById('refreshBtn').addEventListener('click', loadReports);
    document.getElementById('exportPdf').addEventListener('click', downloadAllChartsPDF);
    document.getElementById('exportPng').addEventListener('click', () => {
        // Download all charts as separate PNGs
        Object.keys(charts).forEach(id => downloadChartPNG(id));
    });

    // Individual chart download buttons
    document.querySelectorAll('.chart-download').forEach(btn => {
        btn.addEventListener('click', () => downloadChartPNG(btn.dataset.chart));
    });

    loadReports();
});