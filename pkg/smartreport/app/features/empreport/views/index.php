<div class="page">
    <p class="page-subtitle"><?= e(t('empreport.subtitle')) ?></p>

    <?php if (!empty($sync['error'])): ?>
        <div class="alert alert-error"><span><?= e(t('empreport.sync_failed')) ?>: <?= e($sync['error']) ?></span></div>
    <?php endif; ?>

    <section class="card filters">
        <form method="get" action="<?= e(url('/employee-report')) ?>" class="form filters-grid">
            <input type="hidden" name="route" value="/employee-report">
            <div class="field">
                <label for="date_from"><?= e(t('calls.date_from')) ?></label>
                <input type="date" id="date_from" name="date_from" value="<?= e($date_from) ?>">
            </div>
            <div class="field">
                <label for="date_to"><?= e(t('calls.date_to')) ?></label>
                <input type="date" id="date_to" name="date_to" value="<?= e($date_to) ?>">
            </div>
            <div class="field">
                <label for="department"><?= e(t('empreport.department')) ?></label>
                <select id="department" name="department">
                    <option value=""><?= e(t('empreport.department_all')) ?></option>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?= e($dep) ?>"<?= $department === $dep ? ' selected' : '' ?>><?= e($dep) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('calls.apply')) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/employee-report')) ?>"><?= e(t('calls.reset')) ?></a>
                <a class="btn btn-outline" href="<?= e(url('/employee-report/export?route=/employee-report/export&date_from=' . rawurlencode($date_from) . '&date_to=' . rawurlencode($date_to))) ?>"><?= e(t('extreport.export_csv')) ?></a>
                <?php if ($is_root): ?>
                    <a class="btn btn-primary" href="<?= e(url('/employee-report/manage')) ?>"><?= e(t('empreport.manage')) ?></a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if (empty($rows)): ?>
        <section class="card">
            <p class="empty-state"><?= e(t('empreport.empty_hint')) ?></p>
        </section>
    <?php else: ?>
        <section class="card chart-card">
            <div class="card-head">
                <h3><?= e(t('extreport.hourly_talk')) ?></h3>
            </div>
            <div class="chart-wrap">
                <canvas id="empHourChart"></canvas>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2><?= e(t('empreport.table_title')) ?></h2>
                <span class="muted"><?= e(t('empreport.sync_note')) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('empreport.col_code')) ?></th>
                        <th><?= e(t('empreport.col_name')) ?></th>
                        <th><?= e(t('empreport.col_ext')) ?></th>
                        <th><?= e(t('empreport.col_department')) ?></th>
                        <th><?= e(t('empreport.col_status')) ?></th>
                        <th><?= e(t('empreport.col_sessions')) ?></th>
                        <th><?= e(t('empreport.col_work')) ?></th>
                        <th><?= e(t('empreport.col_calls')) ?></th>
                        <th><?= e(t('empreport.col_talk')) ?></th>
                        <th><?= e(t('empreport.col_talkph')) ?></th>
                        <th><?= e(t('empreport.col_missed')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="nowrap"><strong><?= e($row['code']) ?></strong></td>
                            <td><?= e($row['name']) ?></td>
                            <td class="nowrap"><?= e($row['extension'] !== '' ? $row['extension'] : '–') ?></td>
                            <td><?= e($row['department'] !== '' ? $row['department'] : '–') ?></td>
                            <td class="nowrap">
                                <?php if ($row['active']): ?>
                                    <span class="badge badge-<?= $row['clocked_in'] ? 'success' : 'neutral' ?>"><?= e(t($row['clocked_in'] ? 'empreport.st_in' : 'empreport.st_out')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-neutral"><?= e(t('empreport.st_inactive')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(number_format($row['sessions'])) ?></td>
                            <td class="nowrap"><?= e(format_duration($row['work_seconds'])) ?></td>
                            <td><?= e(number_format($row['calls'])) ?></td>
                            <td class="nowrap"><?= e(format_duration($row['talk'])) ?></td>
                            <td class="nowrap"><?= e($row['talk_per_hour']) ?></td>
                            <td><?= $row['missed'] > 0 ? '<span class="badge badge-danger">' . e(number_format($row['missed'])) . '</span>' : e(number_format($row['missed'])) ?></td>
                            <td class="nowrap">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/employee-report/detail?route=/employee-report/detail&date_from=' . rawurlencode($date_from) . '&date_to=' . rawurlencode($date_to) . '&id=' . (int) $row['id'])) ?>"><?= e(t('extreport.view_detail')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
    window.SMR_EMP_HOURLY = <?= json_encode($hourly) ?>;
</script>
<script src="<?= e(asset('js/vendor/chart.min.js')) ?>"></script>
<script src="<?= e(asset('js/empreport.js')) ?>"></script>
