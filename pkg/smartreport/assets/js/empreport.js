// Employee report - hourly talk chart
(function () {
    function boot() {
        var canvas = document.getElementById('empHourChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }
        var payload = window.SMR_EMP_HOURLY || { labels: [], data: [] };
        var ctx = canvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: payload.labels || [],
                datasets: [{
                    label: 'Talk',
                    data: payload.data || [],
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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
