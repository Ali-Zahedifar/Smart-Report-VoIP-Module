<div class="page">
    <div class="card-head">
        <h2><?= e(t('empreport.manage_title')) ?></h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/employee-report')) ?>"><?= e(t('extreport.back')) ?></a>
    </div>

    <?= partial('partials/flash', ['flash' => flash_messages()]) ?>

    <section class="card">
        <div class="card-head">
            <h3><?= e(t('empreport.add_title')) ?></h3>
            <span class="muted"><?= e(t('empreport.code_range')) ?>: <?= e($codeMin) ?>–<?= e($codeMax) ?> · <?= e(t('empreport.next_code')) ?>: <strong><?= e($nextCode !== '' ? $nextCode : t('empreport.codes_full')) ?></strong></span>
        </div>
        <form method="post" action="<?= e(url('/employee-report/save')) ?>" class="form filters-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="route" value="/employee-report/save">
            <input type="hidden" name="id" value="0">
            <div class="field">
                <label for="name"><?= e(t('empreport.col_name')) ?></label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="field">
                <label for="code"><?= e(t('empreport.col_code')) ?></label>
                <input type="text" id="code" name="code" value="<?= e($nextCode) ?>" required>
            </div>
            <div class="field">
                <label for="extension"><?= e(t('empreport.col_ext')) ?></label>
                <input type="text" id="extension" name="extension">
            </div>
            <div class="field">
                <label for="department"><?= e(t('empreport.col_department')) ?></label>
                <input type="text" id="department" name="department">
            </div>
            <div class="field field-check">
                <label class="check">
                    <input type="checkbox" name="active" value="1" checked>
                    <span><?= e(t('empreport.active')) ?></span>
                </label>
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('empreport.add')) ?></button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <h3><?= e(t('empreport.dialplan_title')) ?></h3>
        </div>
        <p class="card-sub"><?= e(t('empreport.dialplan_intro')) ?></p>
        <pre class="code-block"><?= e("[from-internal-custom]\nexten => _88[1-9]X,1,Answer()\n same => n,Wait(1)\n same => n,Playback(auth-thankyou)\n same => n,Wait(1)\n same => n,Hangup()") ?></pre>
        <p class="field-hint"><?= e(t('empreport.dialplan_note')) ?></p>
    </section>

    <section class="card">
        <div class="card-head">
            <h3><?= e(t('empreport.list_title')) ?></h3>
            <span class="muted"><?= e(t('empreport.manage_hint')) ?></span>
        </div>
        <?php if (empty($employees)): ?>
            <p class="empty-state"><?= e(t('empreport.empty_hint')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('empreport.col_code')) ?></th>
                        <th><?= e(t('empreport.col_name')) ?></th>
                        <th><?= e(t('empreport.col_ext')) ?></th>
                        <th><?= e(t('empreport.col_department')) ?></th>
                        <th><?= e(t('empreport.col_status')) ?></th>
                        <th><?= e(t('empreport.col_actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td class="nowrap"><strong><?= e($emp['code']) ?></strong></td>
                            <td>
                                <form method="post" action="<?= e(url('/employee-report/save')) ?>" class="form form-inline" style="display:flex; gap:.5rem; align-items:center;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="route" value="/employee-report/save">
                                    <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                                    <input type="text" name="name" value="<?= e($emp['name']) ?>" style="width:10rem;" required>
                                    <input type="text" name="code" value="<?= e($emp['code']) ?>" style="width:4.5rem;" required>
                                    <input type="text" name="extension" value="<?= e(isset($emp['extension']) ? $emp['extension'] : '') ?>" style="width:4.5rem;" placeholder="ext">
                                    <input type="text" name="department" value="<?= e(isset($emp['department']) ? $emp['department'] : '') ?>" style="width:7rem;" placeholder="dept">
                                    <label class="check" style="white-space:nowrap;">
                                        <input type="checkbox" name="active" value="1"<?= !empty($emp['active']) ? ' checked' : '' ?>>
                                        <span><?= e(t('empreport.active')) ?></span>
                                    </label>
                                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('common.save')) ?></button>
                                </form>
                            </td>
                            <td class="nowrap"><?= e(isset($emp['extension']) ? $emp['extension'] : '') ?></td>
                            <td><?= e(isset($emp['department']) ? $emp['department'] : '') ?></td>
                            <td class="nowrap">
                                <span class="badge badge-<?= $emp['clocked_in'] ? 'success' : 'neutral' ?>"><?= e(t($emp['clocked_in'] ? 'empreport.st_in' : 'empreport.st_out')) ?></span>
                            </td>
                            <td class="nowrap">
                                <form method="post" action="<?= e(url('/employee-report/delete')) ?>" onsubmit="return confirm('<?= e(t('empreport.confirm_delete')) ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="route" value="/employee-report/delete">
                                    <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit" title="<?= e(t('empreport.delete')) ?>">&times;</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
