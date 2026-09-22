<div class="page">
    <p class="page-subtitle"><?= e(t('extreport.subtitle')) ?></p>

    <?php if (!$external): ?>
        <div class="alert alert-error">
            <span><?= e(t('calls.need_external')) ?></span>
            <?php if (!empty($error)): ?><code class="alert-detail"><?= e($error) ?></code><?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="card filters">
        <form method="get" action="<?= e(url('/ext-report')) ?>" class="form filters-grid">
            <input type="hidden" name="route" value="/ext-report">
            <div class="field">
                <label for="date_from"><?= e(t('calls.date_from')) ?></label>
                <input type="date" id="date_from" name="date_from" value="<?= e($date_from) ?>">
            </div>
            <div class="field">
                <label for="date_to"><?= e(t('calls.date_to')) ?></label>
                <input type="date" id="date_to" name="date_to" value="<?= e($date_to) ?>">
            </div>
            <div class="field">
                <label for="department"><?= e(t('extreport.department')) ?></label>
                <input type="text" id="department" name="department" value="<?= e($department) ?>" placeholder="<?= e(t('extreport.department_all')) ?>">
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('calls.apply')) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/ext-report')) ?>"><?= e(t('calls.reset')) ?></a>
                <a class="btn btn-outline" href="<?= e(url('/ext-report/export?route=/ext-report/export&date_from=' . rawurlencode($date_from) . '&date_to=' . rawurlencode($date_to) . '&department=' . rawurlencode($department))) ?>"><?= e(t('extreport.export_csv')) ?></a>
            </div>
        </form>
    </section>

    <section class="stat-grid">
        <div class="stat-card is-primary">
            <span class="stat-value"><?= e(number_format($totals['calls'])) ?></span>
            <span class="stat-label"><?= e(t('extreport.total_calls')) ?></span>
        </div>
        <div class="stat-card is-info">
            <span class="stat-value"><?= e(format_duration($totals['talk'])) ?></span>
            <span class="stat-label"><?= e(t('extreport.total_talk')) ?></span>
        </div>
        <div class="stat-card is-warning">
            <span class="stat-value"><?= e(number_format($totals['missed'])) ?></span>
            <span class="stat-label"><?= e(t('extreport.total_missed')) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?= e(number_format(count($rows))) ?></span>
            <span class="stat-label"><?= e(t('extreport.extensions')) ?></span>
        </div>
    </section>

    <section class="card chart-card">
        <div class="card-head">
            <h3><?= e(t('extreport.hourly_talk')) ?></h3>
        </div>
        <div class="chart-wrap">
            <canvas id="extHourChart"></canvas>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2><?= e(t('extreport.by_extension')) ?></h2>
            <span class="muted"><?= e(t('extreport.sort_hint')) ?></span>
        </div>
        <?php if (empty($rows)): ?>
            <p class="empty-state"><?= e(t('extreport.empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('extreport.col_ext')) ?></th>
                        <th><?= e(t('extreport.col_calls')) ?></th>
                        <th><?= e(t('extreport.col_in')) ?></th>
                        <th><?= e(t('extreport.col_out')) ?></th>
                        <th><?= e(t('extreport.col_int')) ?></th>
                        <th><?= e(t('extreport.col_talk')) ?></th>
                        <th><?= e(t('extreport.col_avg')) ?></th>
                        <th><?= e(t('extreport.col_missed')) ?></th>
                        <th><?= e(t('extreport.col_first')) ?></th>
                        <th><?= e(t('extreport.col_last')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="nowrap"><strong><?= e($row['extension']) ?></strong></td>
                            <td><?= e(number_format($row['calls'])) ?></td>
                            <td><?= e(number_format($row['in'])) ?></td>
                            <td><?= e(number_format($row['out'])) ?></td>
                            <td><?= e(number_format($row['int'])) ?></td>
                            <td class="nowrap"><?= e(format_duration($row['talk'])) ?></td>
                            <td class="nowrap"><?= e(format_duration($row['avg_talk'])) ?></td>
                            <td><?= $row['missed'] > 0 ? '<span class="badge badge-danger">' . e(number_format($row['missed'])) . '</span>' : e(number_format($row['missed'])) ?></td>
                            <td class="text-muted nowrap"><?= e($row['first'] !== '' ? $row['first'] : '–') ?></td>
                            <td class="text-muted nowrap"><?= e($row['last'] !== '' ? $row['last'] : '–') ?></td>
                            <td class="nowrap">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/ext-report/detail?route=/ext-report/detail&date_from=' . rawurlencode($date_from) . '&date_to=' . rawurlencode($date_to) . '&ext=' . rawurlencode($row['extension']))) ?>"><?= e(t('extreport.view_detail')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="<?= e(asset('js/vendor/chart.min.js')) ?>"></script>
<script src="<?= e(asset('js/extreport.js')) ?>"></script>
