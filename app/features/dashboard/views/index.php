<div class="page">
    <?php if (!$stats['external']): ?>
        <div class="alert alert-error">
            <span><?= e(t('dashboard.external_fail')) ?></span>
            <?php if (!empty($error)): ?>
                <code class="alert-detail"><?= e($error) ?></code>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="stat-grid">
        <?php
        $answeredPct = $stats['total'] > 0 ? round(100 * $stats['answered'] / $stats['total']) : 0;
        $missedPct = $stats['total'] > 0 ? round(100 * $stats['notAnswered'] / $stats['total']) : 0;
        ?>
        <?= partial('partials/stat_card', [
            'label' => t('dashboard.today_calls'),
            'value' => number_format($stats['total']),
            'sub' => date('Y-m-d'),
            'type' => 'primary',
        ]) ?>
        <?= partial('partials/stat_card', [
            'label' => t('dashboard.answered'),
            'value' => number_format($stats['answered']),
            'sub' => $answeredPct . '%',
            'type' => 'success',
        ]) ?>
        <?= partial('partials/stat_card', [
            'label' => t('dashboard.not_answered'),
            'value' => number_format($stats['notAnswered']),
            'sub' => $missedPct . '%',
            'type' => 'warning',
        ]) ?>
        <?= partial('partials/stat_card', [
            'label' => t('dashboard.avg_talk'),
            'value' => format_duration($stats['avgTalk']),
            'sub' => t('dashboard.answered'),
            'type' => 'info',
        ]) ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2><?= e(t('dashboard.recent')) ?></h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/calls')) ?>"><?= e(t('dashboard.view_all')) ?></a>
        </div>
        <?php if (empty($recent)): ?>
            <p class="empty-state"><?= e(t('dashboard.no_data')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('calls.col_date')) ?></th>
                        <th><?= e(t('calls.col_cid')) ?></th>
                        <th><?= e(t('calls.col_src')) ?></th>
                        <th><?= e(t('calls.col_dst')) ?></th>
                        <th><?= e(t('calls.col_duration')) ?></th>
                        <th><?= e(t('calls.col_status')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td class="text-muted"><?= e($row['calldate']) ?></td>
                            <td><?= e($row['clid']) ?></td>
                            <td><?= e($row['src']) ?></td>
                            <td><?= e($row['dst']) ?></td>
                            <td><?= e(format_duration((int) $row['duration'])) ?></td>
                            <td><span class="badge badge-<?= e(disposition_class($row['disposition'])) ?>"><?= e(t('status.' . $row['disposition'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>