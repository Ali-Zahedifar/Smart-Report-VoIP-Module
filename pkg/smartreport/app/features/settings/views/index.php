<?= partial(':features/settings/views/_nav') ?>

<div class="page">
    <div class="card">
        <h2 class="card-title"><?= e(t('settings.system')) ?></h2>
        <dl class="info-list">
            <dt><?= e(t('settings.info_app')) ?></dt>
            <dd><?= e($info['app']) ?></dd>

            <dt><?= e(t('settings.info_version')) ?></dt>
            <dd><?= e($info['version']) ?></dd>

            <dt><?= e(t('settings.info_php')) ?></dt>
            <dd><?= e($info['php']) ?></dd>

            <dt><?= e(t('settings.info_php_ext')) ?></dt>
            <dd>
                <?php foreach ($info['extensions'] as $ext => $ok): ?>
                    <span class="badge badge-<?= $ok ? 'success' : 'danger' ?>"><?= e($ext) ?></span>
                <?php endforeach; ?>
            </dd>

            <dt><?= e(t('settings.info_db_main')) ?></dt>
            <dd>
                <span class="badge badge-<?= $info['db_main'] ? 'success' : 'danger' ?>"><?= e($info['db_main'] ? t('settings.info_ok') : t('settings.info_fail')) ?></span>
            </dd>

            <dt><?= e(t('settings.info_db_cdr')) ?></dt>
            <dd>
                <span class="badge badge-<?= $info['db_cdr'] ? 'success' : 'danger' ?>"><?= e($info['db_cdr'] ? t('settings.info_ok') : t('settings.info_fail')) ?></span>
            </dd>

            <dt><?= e(t('settings.info_db_asterisk')) ?></dt>
            <dd>
                <span class="badge badge-<?= $info['db_asterisk'] ? 'success' : 'danger' ?>"><?= e($info['db_asterisk'] ? t('settings.info_ok') : t('settings.info_fail')) ?></span>
            </dd>

            <dt><?= e(t('settings.info_monitor')) ?></dt>
            <dd>
                <code><?= e($info['monitor']['path'] !== '' ? $info['monitor']['path'] : t('settings.info_not_configured')) ?></code>
                <?php if ($info['monitor']['configured']): ?>
                    <span class="badge badge-<?= $info['monitor']['readable'] ? 'success' : 'danger' ?>"><?= e($info['monitor']['readable'] ? t('settings.info_ok') : t('settings.info_fail')) ?></span>
                <?php endif; ?>
            </dd>
        </dl>
    </div>
</div>