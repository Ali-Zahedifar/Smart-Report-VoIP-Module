<?= partial(':features/calls/views/_filters', ['filters' => $filters, 'dispositions' => \SmartReport\Features\Calls\Models\CdrModel::dispositions()]) ?>

<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/queue-report/export?' . $queryString . '&export=csv')) ?>">
        <?= e(t('queue_report.export_csv')) ?>
    </a>
</div>

<?php if ($total > 0): ?>
    <p class="results-info">
        <?= e(t('queue_report.showing')) ?> <strong><?= count($queues) ?></strong> 
        <?= e(t('queue_report.of')) ?> <strong><?= number_format($total) ?></strong> 
        <?= e(t('queue_report.queues')) ?>
    </p>
<?php endif; ?>

<div class="table-wrap">
    <table class="table">
        <thead>
        <tr>
            <th><?= e(t('queue_report.queue')) ?></th>
            <th><?= e(t('queue_report.offered')) ?></th>
            <th><?= e(t('queue_report.answered')) ?></th>
            <th><?= e(t('queue_report.missed')) ?></th>
            <th><?= e(t('queue_report.abandoned')) ?></th>
            <th><?= e(t('queue_report.abandonment_rate')) ?></th>
            <th><?= e(t('queue_report.avg_wait')) ?></th>
            <th><?= e(t('queue_report.avg_talk')) ?></th>
            <th><?= e(t('queue_report.service_level_20')) ?></th>
            <th><?= e(t('queue_report.service_level_30')) ?></th>
            <th><?= e(t('queue_report.service_level_60')) ?></th>
            <th><?= e(t('queue_report.actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($queues as $queue): ?>
            <tr>
                <td class="nowrap"><strong>Queue <?= e($queue['queue']) ?></strong></td>
                <td><?= e(number_format($queue['offered'])) ?></td>
                <td><span class="badge badge-success"><?= e(number_format($queue['answered'])) ?></span></td>
                <td><span class="badge badge-warning"><?= e(number_format($queue['missed'])) ?></span></td>
                <td><span class="badge badge-danger"><?= e(number_format($queue['abandoned'])) ?></span></td>
                <td class="nowrap"><?= e($queue['abandonment_rate']) ?>%</td>
                <td class="nowrap"><?= e(format_duration($queue['avg_wait'])) ?></td>
                <td class="nowrap"><?= e(format_duration($queue['avg_talk'])) ?></td>
                <td class="nowrap"><?= e($queue['service_level_20']) ?>%</td>
                <td class="nowrap"><?= e($queue['service_level_30']) ?>%</td>
                <td class="nowrap"><?= e($queue['service_level_60']) ?>%</td>
                <td class="nowrap">
                    <?php // queue= goes last so it cannot be overridden by the pagination string ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/queue-report/detail?' . $queryString . '&queue=' . rawurlencode($queue['queue']))) ?>">
                        <?= e(t('queue_report.view_detail')) ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (empty($queues)): ?>
    <p class="empty-state"><?= e(t('queue_report.no_data')) ?></p>
<?php endif; ?>

<?= partial('partials/pagination', [
    'total' => $total,
    'page' => $page,
    'perPage' => $perPage,
    'queryString' => $queryString,
]) ?>