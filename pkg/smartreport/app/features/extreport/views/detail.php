<div class="page">
    <div class="card-head">
        <h2><?= e(t('extreport.detail_heading')) ?> <strong><?= e($ext) ?></strong></h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/ext-report?route=/ext-report&date_from=' . rawurlencode($date_from) . '&date_to=' . rawurlencode($date_to))) ?>"><?= e(t('extreport.back')) ?></a>
    </div>

    <p class="page-subtitle"><?= e(t('extreport.detail_range')) ?> <?= e($date_from) ?> → <?= e($date_to) ?></p>

    <?php if (!$external): ?>
        <div class="alert alert-error">
            <span><?= e(t('calls.need_external')) ?></span>
            <?php if (!empty($error)): ?><code class="alert-detail"><?= e($error) ?></code><?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <?php if (empty($rows)): ?>
            <p class="empty-state"><?= e(t('extreport.empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('calls.col_date')) ?></th>
                        <th><?= e(t('calls.col_direction')) ?></th>
                        <th><?= e(t('calls.col_cid')) ?></th>
                        <th><?= e(t('calls.col_src')) ?></th>
                        <th><?= e(t('calls.col_dst')) ?></th>
                        <th><?= e(t('calls.col_talk')) ?></th>
                        <th><?= e(t('calls.col_status')) ?></th>
                        <th><?= e(t('calls.col_recording')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-muted nowrap"><?= e($row['calldate']) ?></td>
                            <td class="nowrap"><span class="badge badge-<?= e(direction_badge_class(isset($row['direction']) ? $row['direction'] : 'unknown')) ?>"><?= e(t('dir.' . (isset($row['direction']) ? $row['direction'] : 'unknown'))) ?></span></td>
                            <td title="<?= e(isset($row['clid']) ? $row['clid'] : '') ?>"><?= e(str_limit((string) (isset($row['clid']) ? $row['clid'] : ''), 30)) ?></td>
                            <td class="nowrap"><?= e(isset($row['src']) ? $row['src'] : '') ?></td>
                            <td class="nowrap"><?= e(isset($row['dst']) ? $row['dst'] : '') ?></td>
                            <td class="nowrap"><?= e(format_duration((int) (isset($row['talkTime']) ? $row['talkTime'] : 0))) ?></td>
                            <td><span class="badge badge-<?= e(disposition_class(isset($row['outcome']) ? $row['outcome'] : '')) ?>"><?= e(t('status.' . (isset($row['outcome']) ? $row['outcome'] : ''))) ?></span></td>
                            <td class="nowrap">
                                <?php if (!empty($row['hasRecording'])): ?>
                                    <button type="button" class="btn btn-ghost btn-sm" data-audio-url="<?= e(url('/calls/audio?uniqueid=' . rawurlencode($row['recordingUniqueid']) . '&mode=inline')) ?>" data-audio-title="<?= e(t('extreport.title') . ' — ' . $row['calldate']) ?>" title="<?= e(t('calls.play')) ?>"><?= icon('play', 14) ?></button>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/calls/audio?uniqueid=' . rawurlencode($row['recordingUniqueid']) . '&mode=download')) ?>" title="<?= e(t('calls.download')) ?>"><?= icon('download', 14) ?></a>
                                <?php else: ?>
                                    <span class="muted" title="<?= e(t('calls.no_recording')) ?>">&ndash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
