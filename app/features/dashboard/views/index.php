<div class="page">
    <?php if (!$stats['external']): ?>
        <div class="alert alert-error">
            <span><?= e(t('dashboard.external_fail')) ?></span>
            <?php if (!empty($error)): ?>
                <code class="alert-detail"><?= e($error) ?></code>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="card dir-switch">
        <div class="card-head">
            <h2><?= e(t('dashboard.direction')) ?></h2>
            <span class="muted"><?= e(t('dashboard.direction_hint')) ?></span>
        </div>
        <div class="dir-tabs">
            <a class="dir-tab<?= $direction === '' ? ' is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= e(t('calls.direction_all')) ?></a>
            <a class="dir-tab<?= $direction === 'in' ? ' is-active' : '' ?>" href="<?= e(url('/?dir=in')) ?>"><?= e(t('dir.in')) ?></a>
            <a class="dir-tab<?= $direction === 'out' ? ' is-active' : '' ?>" href="<?= e(url('/?dir=out')) ?>"><?= e(t('dir.out')) ?></a>
            <?php if ($internalEnabled): ?>
                <a class="dir-tab<?= $direction === 'int' ? ' is-active' : '' ?>" href="<?= e(url('/?dir=int')) ?>"><?= e(t('dir.int')) ?></a>
            <?php endif; ?>
            <?php if ($missedEnabled): ?>
                <a class="dir-tab<?= $direction === 'missed' ? ' is-active' : '' ?>" href="<?= e(url('/?dir=missed')) ?>"><?= e(t('dir.missed')) ?></a>
            <?php endif; ?>
        </div>
    </section>

    <section class="stat-grid">
        <?php
        $answeredPct = $stats['total'] > 0 ? round(100 * $stats['answered'] / $stats['total']) : 0;
        $missedPct = $stats['total'] > 0 ? round(100 * $stats['missed'] / $stats['total']) : 0;
        $buckets = $stats['missedBuckets'];
        $bktText = t('missed.noanswer') . ' ' . number_format($buckets['noanswer'])
            . ' · ' . t('missed.busy') . ' ' . number_format($buckets['busy'])
            . ' · ' . t('missed.cancelled') . ' ' . number_format($buckets['cancelled'])
            . ' · ' . t('missed.failed') . ' ' . number_format($buckets['failed'])
            . ' · ' . t('missed.voicemail') . ' ' . number_format($buckets['voicemail']);
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
            'label' => t('dashboard.missed_title'),
            'value' => number_format($stats['missed']),
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

    <?php if ($stats['external'] && $stats['missed'] > 0 && !$focusMissed): ?>
        <section class="card">
            <div class="card-head">
                <h2><?= e(t('dashboard.missed_list_title')) ?></h2>
                <span class="muted"><?= e($bktText) ?></span>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/calls?missed=1')) ?>"><?= e(t('dashboard.view_all')) ?></a>
            </div>
            <?php if (empty($missed)): ?>
                <p class="empty-state"><?= e(t('dashboard.no_data')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th><?= e(t('calls.col_date')) ?></th>
                            <th><?= e(t('calls.col_cid')) ?></th>
                            <th><?= e(t('calls.col_dst')) ?></th>
                            <th><?= e(t('calls.col_status')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($missed as $row): ?>
                            <tr>
                                <td class="text-muted nowrap"><?= e($row['calldate']) ?></td>
                                <td><?= e($row['clid']) ?></td>
                                <td class="nowrap"><?= e($row['dst']) ?></td>
                                <td><span class="badge badge-<?= e(missed_badge_class($row['missedReason'])) ?>"><?= e(t('missed.' . $row['missedReason'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="card-head">
            <h2><?= e($focusMissed ? t('dashboard.recent_missed') : t('dashboard.recent')) ?></h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(url($focusMissed ? '/calls?missed=1' : ('/calls' . ($direction !== '' ? '?dir=' . $direction : '')))) ?>"><?= e(t('dashboard.view_all')) ?></a>
        </div>
        <?php if (empty($recent)): ?>
            <p class="empty-state"><?= e(t('dashboard.no_data')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('calls.col_date')) ?></th>
                        <?php if (!$legacy): ?><th><?= e(t('calls.col_direction')) ?></th><?php endif; ?>
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
                            <?php if (!$legacy): ?>
                                <td class="nowrap"><span class="badge badge-<?= e(direction_badge_class(isset($row['direction']) ? $row['direction'] : 'unknown')) ?>"><?= e(t('dir.' . (isset($row['direction']) ? $row['direction'] : 'unknown'))) ?></span></td>
                            <?php endif; ?>
                            <td><?= e(isset($row['clid']) ? $row['clid'] : '') ?></td>
                            <td><?= e(isset($row['src']) ? $row['src'] : '') ?></td>
                            <td><?= e(isset($row['dst']) ? $row['dst'] : '') ?></td>
                            <td><?= e(format_duration((int) (isset($row['talkTime']) ? $row['talkTime'] : $row['duration']))) ?></td>
                            <td><span class="badge badge-<?= e(disposition_class(isset($row['outcome']) ? $row['outcome'] : $row['disposition'])) ?>"><?= e(t('status.' . (isset($row['outcome']) ? $row['outcome'] : $row['disposition']))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>