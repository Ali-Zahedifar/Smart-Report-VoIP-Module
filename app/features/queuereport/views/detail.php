<?= partial(':features/calls/views/_filters', ['filters' => $filters, 'dispositions' => \SmartReport\Features\Calls\Models\CdrModel::dispositions()]) ?>

<div class="page-header">
    <h2><?= e(t('queue_report.detail_title', ['queue' => $queue])) ?></h2>
    <a class="btn btn-ghost" href="<?= e(url('/queue-report?' . $queryString)) ?>">&larr; <?= e(t('common.back')) ?></a>
</div>

<!-- Summary Cards -->
<section class="stat-grid">
    <div class="stat-card primary">
        <div class="stat-value"><?= number_format($stats['offered']) ?></div>
        <div class="stat-label"><?= t('queue_report.offered') ?></div>
    </div>
    <div class="stat-card success">
        <div class="stat-value"><?= number_format($stats['answered']) ?></div>
        <div class="stat-label"><?= t('queue_report.answered') ?></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-value"><?= number_format($stats['missed']) ?></div>
        <div class="stat-label"><?= t('queue_report.missed') ?></div>
    </div>
    <div class="stat-card danger">
        <div class="stat-value"><?= number_format($stats['abandoned']) ?></div>
        <div class="stat-label"><?= t('queue_report.abandoned') ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-value"><?= $stats['abandonment_rate'] ?>%</div>
        <div class="stat-label"><?= t('queue_report.abandonment_rate') ?></div>
    </div>
    <div class="stat-card primary">
        <div class="stat-value"><?= format_duration($stats['avg_wait']) ?></div>
        <div class="stat-label"><?= t('queue_report.avg_wait') ?></div>
    </div>
    <div class="stat-card success">
        <div class="stat-value"><?= format_duration($stats['avg_talk']) ?></div>
        <div class="stat-label"><?= t('queue_report.avg_talk') ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-value"><?= $stats['service_level_20'] ?>%</div>
        <div class="stat-label"><?= t('queue_report.service_level_20') ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-value"><?= $stats['service_level_30'] ?>%</div>
        <div class="stat-label"><?= t('queue_report.service_level_30') ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-value"><?= $stats['service_level_60'] ?>%</div>
        <div class="stat-label"><?= t('queue_report.service_level_60') ?></div>
    </div>
</section>

<!-- Hourly Chart -->
<section class="card">
    <div class="card-head">
        <h3><?= t('queue_report.hourly_distribution') ?></h3>
    </div>
    <div class="chart-wrap" style="height: 300px;">
        <canvas id="hourlyChart"></canvas>
    </div>
</section>

<!-- Agent Performance Table -->
<section class="card">
    <div class="card-head">
        <h3><?= t('queue_report.agent_performance') ?></h3>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/queue-report/export?queue=' . $queue . '&' . $queryString)) ?>">
            <?= t('queue_report.export_csv') ?>
        </a>
    </div>
    
    <?php if (empty($agents)): ?>
        <p class="empty-state"><?= t('queue_report.no_agent_data') ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th><?= t('queue_report.extension') ?></th>
                    <th><?= t('queue_report.total_calls') ?></th>
                    <th><?= t('queue_report.answered') ?></th>
                    <th><?= t('queue_report.missed') ?></th>
                    <th><?= t('queue_report.avg_talk') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($agents as $agent): ?>
                    <tr>
                        <td class="nowrap"><strong>Ext <?= e($agent['extension']) ?></strong></td>
                        <td><?= number_format($agent['total_calls']) ?></td>
                        <td><span class="badge badge-success"><?= number_format($agent['answered']) ?></span></td>
                        <td><span class="badge badge-warning"><?= number_format($agent['missed']) ?></span></td>
                        <td class="nowrap"><?= format_duration($agent['avg_talk']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script src="<?= e(asset('js/vendor/chart.min.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hourly chart
    const hourly = <?= json_encode($hourly) ?>;
    const ctx = document.getElementById('hourlyChart').getContext('2d');
    const labels = hourly.map((_, i) => String(i).padStart(2, '0') + ':00');
    const offered = hourly.map(h => h.offered);
    const answered = hourly.map(h => h.answered);
    const missed = hourly.map(h => h.missed);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
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
});
</script>
<script src="<?= e(asset('js/vendor/chart.min.js')) ?>"></script>