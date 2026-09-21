<?= partial(':features/calls/views/_filters', ['filters' => $filters, 'dispositions' => \SmartReport\Features\Calls\Models\CdrModel::dispositions()]) ?>

<?php if (!$external): ?>
    <div class="alert alert-error">
        <span><?= e(t('calls.need_external')) ?></span>
        <?php if (!empty($error)): ?>
            <code class="alert-detail"><?= e($error) ?></code>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/calls/export?' . $queryString . '&export=summary')) ?>">
        <?= e(t('calls.export_summary')) ?>
    </a>
    <a class="btn btn-outline" href="<?= e(url('/calls/export?' . $queryString . '&export=full')) ?>">
        <?= e(t('calls.export_full')) ?>
    </a>
</div>

<?php if ($result['total'] > 0 && $mode !== 'legacy'): ?>
    <p class="results-info">
        <?= e(t('calls.showing')) ?> <strong><?= count($result['rows']) ?></strong> 
        <?= e(t('calls.of')) ?> <strong><?= number_format($result['total']) ?></strong> 
        <?= e(t('calls.calls')) ?>
    </p>
<?php endif; ?>

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
            <th><?= e(t('calls.col_actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $row): ?>
            <tr class="call-row" data-linkedid="<?= e($row['linkedid']) ?>">
                <td class="text-muted nowrap"><?= e($row['calldate']) ?></td>
                <?php if (!$legacy): ?>
                    <td class="nowrap"><span class="badge badge-<?= e(direction_badge_class(isset($row['direction']) ? $row['direction'] : 'unknown')) ?>"><?= e(t('dir.' . (isset($row['direction']) ? $row['direction'] : 'unknown'))) ?></span></td>
                <?php endif; ?>
                <td><?= e(isset($row['clid']) ? $row['clid'] : '') ?></td>
                <td><?= e(isset($row['src']) ? $row['src'] : '') ?></td>
                <td><?= e(isset($row['dst']) ? $row['dst'] : '') ?></td>
                <td class="nowrap"><?= e(format_duration((int) (isset($row['talkTime']) ? $row['talkTime'] : $row['duration']))) ?></td>
                <td><span class="badge badge-<?= e(disposition_class(isset($row['outcome']) ? $row['outcome'] : $row['disposition'])) ?>"><?= e(t('status.' . (isset($row['outcome']) ? $row['outcome'] : $row['disposition']))) ?></span></td>
                <td class="nowrap">
                    <?php if (!empty($row['recordingUniqueid'])): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/calls/audio?uniqueid=' . $row['recordingUniqueid'] . '&mode=inline')) ?>">
                            <svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="6 3 20 12 6 21 6 3"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ((isset($row['leg_count']) ? $row['leg_count'] : 1) > 1): ?>
                        <button type="button" class="btn btn-ghost btn-sm btn-legs" data-target="#legs-<?= e($row['linkedid']) ?>">
                            <svg class="icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/></svg>
                            <?= e((int) $row['leg_count']) ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ((isset($row['leg_count']) ? $row['leg_count'] : 1) > 1 && isset($legs[$row['linkedid']])): ?>
                <tr class="legs-row" id="legs-<?= e($row['linkedid']) ?>" style="display: none;">
                    <td colspan="9">
                        <div class="legs-inner">
                            <div class="card-head">
                                <h3><?= e(t('calls.legs_title')) ?> (<?= e(count($legs[$row['linkedid']])) ?>)</h3>
                            </div>
                            <table class="table table-legs">
                                <thead>
                                <tr>
                                    <th><?= e(t('calls.col_date')) ?></th>
                                    <th><?= e(t('calls.col_src')) ?></th>
                                    <th><?= e(t('calls.col_dst')) ?></th>
                                    <th><?= e(t('calls.col_dcontext')) ?></th>
                                    <th><?= e(t('calls.col_lastapp')) ?></th>
                                    <th><?= e(t('calls.col_duration')) ?></th>
                                    <th><?= e(t('calls.col_talk')) ?></th>
                                    <th><?= e(t('calls.col_status')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php $legsShown = array_slice($legs[$row['linkedid']], 0, 20); ?>
                                <?php foreach ($legsShown as $leg): ?>
                                    <tr>
                                        <td class="text-muted nowrap"><?= e($leg['calldate']) ?></td>
                                        <td class="nowrap"><?= e($leg['src']) ?></td>
                                        <td class="nowrap"><?= e($leg['dst']) ?><?php if (!empty($leg['dstchannel'])): ?> <span class="muted">(<?= e($leg['dstchannel']) ?>)</span><?php endif; ?></td>
                                        <td><?= e(isset($leg['dcontext']) ? $leg['dcontext'] : '') ?></td>
                                        <td><?= e(isset($leg['lastapp']) ? $leg['lastapp'] : '') ?></td>
                                        <td class="nowrap"><?= e(format_duration((int) $leg['duration'])) ?></td>
                                        <td class="nowrap"><?= e(format_duration((int) $leg['billsec'])) ?></td>
                                        <td><span class="badge badge-<?= e(disposition_class($leg['disposition'])) ?>"><?= e(t('status.' . $leg['disposition'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($legs[$row['linkedid']]) > 20): ?>
                                    <tr class="legs-more">
                                        <td colspan="8" class="text-muted"><?= e('+ ' . (count($legs[$row['linkedid']]) - 20) . ' ' . t('calls.legs_more')) ?></td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (empty($result['rows'])): ?>
    <p class="empty-state"><?= e(t('calls.empty')) ?></p>
<?php endif; ?>

<?= partial('partials/pagination', [
    'total' => $result['total'],
    'page' => $page,
    'perPage' => $perPage,
    'queryString' => $queryString,
]) ?>

<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-legs');
    if (btn) {
        const target = document.getElementById(btn.dataset.target);
        if (target) {
            target.style.display = target.style.display === 'none' ? '' : 'none';
        }
    }
});
</script>