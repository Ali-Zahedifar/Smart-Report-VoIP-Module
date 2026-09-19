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
                <label for="dir"><?= e(t('calls.direction')) ?></label>
                <select id="dir" name="dir">
                    <option value=""><?= e(t('calls.direction_all')) ?></option>
                    <?php foreach ($directions as $d): ?>
                        <option value="<?= e($d) ?>"<?= $filters['direction'] === $d ? ' selected' : '' ?>><?= e(t('dir.' . $d)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-check">
                <label for="missed" class="check">
                    <input type="checkbox" id="missed" name="missed" value="1"<?= !empty($filters['missed']) ? ' checked' : '' ?>>
                    <span><?= e(t('calls.missed_only')) ?></span>
                </label>
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
                <a class="btn btn-outline" href="<?= e(url('/calls/export?' . $queryString . '&export=summary')) ?>"><?= e(t('calls.export_summary')) ?></a>
                <a class="btn btn-outline" href="<?= e(url('/calls/export?' . $queryString . '&export=full')) ?>"><?= e(t('calls.export_full')) ?></a>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <h2><?= e(t('calls.title')) ?></h2>
            <span class="muted"><?= e(number_format($result['total'])) ?> <?= e(strtolower(t($mode === 'linkedid' ? 'calls.col_calls' : 'calls.col_date'))) ?></span>
        </div>

        <?php if ($hitCap): ?>
            <div class="alert alert-warning">
                <span><?= e(t('calls.too_many')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($result['rows'])): ?>
            <p class="empty-state"><?= e(t('calls.empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-calls">
                    <thead>
                    <tr>
                        <th><?= e(t('calls.col_date')) ?></th>
                        <?php if ($mode === 'linkedid'): ?>
                            <th><?= e(t('calls.col_direction')) ?></th>
                        <?php endif; ?>
                        <th><?= e(t('calls.col_cid')) ?></th>
                        <th><?= e(t('calls.col_src')) ?></th>
                        <th><?= e(t('calls.col_dst')) ?></th>
                        <th><?= e(t('calls.col_talk')) ?></th>
                        <th><?= e(t('calls.col_status')) ?></th>
                        <?php if ($mode === 'linkedid'): ?>
                            <th></th>
                        <?php endif; ?>
                        <th><?= e(t('calls.col_recording')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <?php
                        $audioUrl = '';
                        $hasRec = false;
                        $legsRows = [];
                        if ($mode === 'linkedid') {
                            $hasRec = !empty($row['recordingUniqueid']);
                            if ($hasRec) {
                                $audioUrl = url('/calls/audio?uniqueid=' . rawurlencode($row['recordingUniqueid']) . '&mode=inline');
                            }
                            $downloadUrl = url('/calls/audio?uniqueid=' . rawurlencode($row['recordingUniqueid']) . '&mode=download');
                            $legsRows = isset($row['legs']) && is_array($row['legs']) ? $row['legs'] : (isset($legs[$row['linkedid']]) ? $legs[$row['linkedid']] : []);
                        } else {
                            $hasRec = isset($row['recordingfile']) && $row['recordingfile'] !== '';
                            if ($hasRec) {
                                $audioUrl = url('/calls/audio?uniqueid=' . rawurlencode($row['uniqueid']) . '&mode=inline');
                                $downloadUrl = url('/calls/audio?uniqueid=' . rawurlencode($row['uniqueid']) . '&mode=download');
                            }
                        }
                        ?>
                        <tr class="call-row">
                            <td class="text-muted nowrap"><?= e($row['calldate']) ?></td>
                            <?php if ($mode === 'linkedid'): ?>
                                <td class="nowrap"><span class="badge badge-<?= e(direction_badge_class(isset($row['direction']) ? $row['direction'] : 'unknown')) ?>"><?= e(t('dir.' . (isset($row['direction']) ? $row['direction'] : 'unknown'))) ?></span></td>
                            <?php endif; ?>
                            <td title="<?= e(isset($row['clid']) ? $row['clid'] : '') ?>"><?= e(str_limit((string) (isset($row['clid']) ? $row['clid'] : ''), 40)) ?></td>
                            <td class="nowrap"><?= e(isset($row['src']) ? $row['src'] : '') ?></td>
                            <td class="nowrap"><?= e(isset($row['dst']) ? $row['dst'] : '') ?></td>
                            <td class="nowrap"><?= e(format_duration((int) (isset($row['talkTime']) ? $row['talkTime'] : $row['billsec']))) ?></td>
                            <td><span class="badge badge-<?= e(disposition_class(isset($row['outcome']) ? $row['outcome'] : $row['disposition'])) ?>"><?= e(t('status.' . (isset($row['outcome']) ? $row['outcome'] : $row['disposition']))) ?></span></td>
                            <?php if ($mode === 'linkedid'): ?>
                                <td class="nowrap">
                                    <?php if ((int) $row['leg_count'] > 1): ?>
                                        <button type="button" class="btn btn-ghost btn-sm btn-legs" data-target="#legs-<?= e($row['linkedid']) ?>"><?= icon('dot', 14) ?> <?= e((int) $row['leg_count']) ?></button>
                                    <?php else: ?>
                                        <span class="text-muted">&ndash;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="nowrap">
                                <?php if ($hasRec): ?>
                                    <div class="row-audio-player">
                                        <audio controls preload="none" src="<?= e($audioUrl) ?>"></audio>
                                        <a class="btn btn-ghost btn-sm" href="<?= e($downloadUrl) ?>" title="<?= e(t('calls.download')) ?>"><?= icon('download', 14) ?></a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($mode === 'linkedid' && count($legsRows) > 1): ?>
                            <tr class="legs-row" id="legs-<?= e($row['linkedid']) ?>">
                                <td colspan="10">
                                    <div class="legs-inner">
                                        <div class="card-head">
                                            <h3><?= e(t('calls.legs_title')) ?> (<?= e(count($legsRows)) ?>)</h3>
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
                                            <?php $legsShown = array_slice($legsRows, 0, 20); ?>
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
                                            <?php if (count($legsRows) > 20): ?>
                                                <tr class="legs-more">
                                                    <td colspan="8" class="text-muted"><?= e('+ ' . (count($legsRows) - 20) . ' ' . t('calls.legs_more')) ?></td>
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

            <?= partial('partials/pagination', [
                'total' => $result['total'],
                'perPage' => $perPage,
                'page' => $page,
                'queryString' => $queryString,
            ]) ?>
        <?php endif; ?>
    </section>
</div>