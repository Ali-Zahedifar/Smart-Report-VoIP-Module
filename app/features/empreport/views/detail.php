<div class="page">
    <div class="card-head">
        <h2><?= e(t('empreport.detail_heading')) ?> <strong><?= e($emp['name']) ?></strong> <span class="muted">(<?= e(t('empreport.col_code')) ?> <?= e($emp['code']) ?>)</span></h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/employee-report?route=/employee-report&date_from=' . rawurlencode($date_from) . '&date_to=' . rawurlencode($date_to))) ?>"><?= e(t('extreport.back')) ?></a>
    </div>

    <p class="page-subtitle"><?= e(t('extreport.detail_range')) ?> <?= e($date_from) ?> → <?= e($date_to) ?></p>

    <section class="card">
        <div class="card-head"><h3><?= e(t('empreport.sessions_title')) ?></h3></div>
        <?php if (empty($sessions)): ?>
            <p class="empty-state"><?= e(t('empreport.no_sessions')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('empreport.col_clock_in')) ?></th>
                        <th><?= e(t('empreport.col_clock_out')) ?></th>
                        <th><?= e(t('empreport.col_duration')) ?></th>
                        <th><?= e(t('empreport.col_from_ext')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <?php
                        $startTs = strtotime((string) $s['clock_in']);
                        $endTs = $s['clock_out'] !== null ? strtotime((string) $s['clock_out']) : time();
                        $dur = ($startTs !== false && $endTs !== false && $endTs > $startTs) ? $endTs - $startTs : 0;
                        ?>
                        <tr>
                            <td class="nowrap"><?= e($s['clock_in']) ?></td>
                            <td class="nowrap"><?= $s['clock_out'] !== null ? e($s['clock_out']) : '<span class="badge badge-success">' . e(t('empreport.st_in')) . '</span>' ?></td>
                            <td class="nowrap"><?= e(format_duration($dur)) ?></td>
                            <td class="nowrap text-muted"><?= e(isset($s['src_ext']) && $s['src_ext'] !== null && $s['src_ext'] !== '' ? $s['src_ext'] : '–') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><h3><?= e(t('empreport.calls_title')) ?> <?= e($emp['extension'] !== '' ? '(' . e($emp['extension']) . ')' : '') ?></h3></div>
        <?php if (empty($calls)): ?>
            <p class="empty-state"><?= e(t('empreport.no_calls')) ?></p>
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
                    <?php foreach ($calls as $row): ?>
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
                                    <button type="button" class="btn btn-ghost btn-sm" data-audio-url="<?= e(url('/calls/audio?uniqueid=' . rawurlencode($row['recordingUniqueid']) . '&mode=inline')) ?>" data-audio-title="<?= e($emp['name'] . ' — ' . $row['calldate']) ?>" title="<?= e(t('calls.play')) ?>"><?= icon('play', 14) ?></button>
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
