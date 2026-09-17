<div class="page">
    <p class="page-subtitle"><?= e(t('calls.subtitle')) ?></p>

    <?php if (!$external): ?>
        <div class="alert alert-error">
            <span><?= e(t('calls.need_external')) ?></span>
            <?php if (!empty($error)): ?>
                <code class="alert-detail"><?= e($error) ?></code>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="card filters">
        <form method="get" action="<?= e(url('/calls')) ?>" class="form filters-grid">
            <div class="field">
                <label for="date_from"><?= e(t('calls.date_from')) ?></label>
                <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="field">
                <label for="date_to"><?= e(t('calls.date_to')) ?></label>
                <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="field">
                <label for="src"><?= e(t('calls.src')) ?></label>
                <input type="text" id="src" name="src" value="<?= e($filters['src']) ?>" placeholder="…">
            </div>
            <div class="field">
                <label for="dst"><?= e(t('calls.dst')) ?></label>
                <input type="text" id="dst" name="dst" value="<?= e($filters['dst']) ?>" placeholder="…">
            </div>
            <div class="field">
                <label for="clid"><?= e(t('calls.clid')) ?></label>
                <input type="text" id="clid" name="clid" value="<?= e($filters['clid']) ?>" placeholder="…">
            </div>
            <div class="field">
                <label for="disposition"><?= e(t('calls.disposition')) ?></label>
                <select id="disposition" name="disposition">
                    <option value=""><?= e(t('calls.disposition_any')) ?></option>
                    <?php foreach ($dispositions as $disp): ?>
                        <option value="<?= e($disp) ?>"<?= $filters['disposition'] === $disp ? ' selected' : '' ?>><?= e(t('status.' . $disp)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="limit"><?= e(t('calls.limit')) ?></label>
                <select id="limit" name="limit">
                    <?php foreach ($limitChoices as $choice): ?>
                        <option value="<?= e((string) $choice) ?>"<?= (int) $choice === $limit ? ' selected' : '' ?>><?= e(number_format($choice)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('calls.apply')) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/calls')) ?>"><?= e(t('calls.reset')) ?></a>
                <a class="btn btn-outline" href="<?= e(url('/calls/export?' . $queryString)) ?>"><?= e(t('calls.export')) ?></a>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <h2><?= e(t('calls.title')) ?></h2>
            <span class="muted"><?= e(number_format($result['total'])) ?> <?= e(strtolower(t('calls.col_date'))) ?></span>
        </div>

        <?php if (empty($result['rows'])): ?>
            <p class="empty-state"><?= e(t('calls.empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-calls">
                    <thead>
                    <tr>
                        <th><?= e(t('calls.col_date')) ?></th>
                        <th><?= e(t('calls.col_cid')) ?></th>
                        <th><?= e(t('calls.col_src')) ?></th>
                        <th><?= e(t('calls.col_dst')) ?></th>
                        <th><?= e(t('calls.col_duration')) ?></th>
                        <th><?= e(t('calls.col_talk')) ?></th>
                        <th><?= e(t('calls.col_status')) ?></th>
                        <th><?= e(t('calls.col_recording')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <?php
                        $audioUrl = url('/calls/audio?uniqueid=' . rawurlencode($row['uniqueid']) . '&mode=inline');
                        $downloadUrl = url('/calls/audio?uniqueid=' . rawurlencode($row['uniqueid']) . '&mode=download');
                        $hasRecording = $row['recordingfile'] ?? null;
                        ?>
                        <tr>
                            <td class="text-muted nowrap"><?= e($row['calldate']) ?></td>
                            <td title="<?= e($row['clid']) ?>"><?= e(str_limit((string) $row['clid'], 40)) ?></td>
                            <td class="nowrap"><?= e($row['src']) ?></td>
                            <td class="nowrap"><?= e($row['dst']) ?></td>
                            <td class="nowrap"><?= e(format_duration((int) $row['duration'])) ?></td>
                            <td class="nowrap"><?= e(format_duration((int) $row['billsec'])) ?></td>
                            <td><span class="badge badge-<?= e(disposition_class($row['disposition'])) ?>"><?= e(t('status.' . $row['disposition'])) ?></span></td>
                            <td class="nowrap">
                                <?php if ($hasRecording): ?>
                                    <button type="button" class="btn btn-ghost btn-sm btn-play" data-audio="<?= e($audioUrl) ?>"><?= icon('play', 14) ?> <?= e(t('calls.play')) ?></button>
                                    <a class="btn btn-ghost btn-sm" href="<?= e($downloadUrl) ?>" title="<?= e(t('calls.download')) ?>"><?= icon('download', 14) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= partial('partials/pagination', [
                'total' => $result['total'],
                'perPage' => $perPage,
                'page' => $page,
                'queryString' => $queryString,
            ]) ?>
        <?php endif; ?>
    </section>
</div>