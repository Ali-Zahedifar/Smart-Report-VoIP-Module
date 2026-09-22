// Extension report - hourly talk chart (Chart.js v3 syntax)
(function () {
    function boot() {
        var canvas = document.getElementById('extHourChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }
        window.SMR_EXT_HOURLY = window.SMR_EXT_HOURLY || { labels: [], data: [] };
        var labels = window.SMR_EXT_HOURLY.labels || [];
        var data = window.SMR_EXT_HOURLY.data || [];
        var ctx = canvas.getContext('2d');
        window.extHourChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Talk',
                    data: data,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { display: false } }
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
