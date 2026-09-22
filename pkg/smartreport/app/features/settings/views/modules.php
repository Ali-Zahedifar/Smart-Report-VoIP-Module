<?= partial(':features/settings/views/_nav') ?>

<div class="page">
    <form method="post" action="<?= e(url('/settings/modules')) ?>" class="form-stack">
        <?= csrf_field() ?>

        <section class="card">
            <h2 class="card-title"><?= e(t('settings.modules.branding')) ?></h2>
            <div class="field">
                <label for="b_name"><?= e(t('settings.modules.branding_name')) ?></label>
                <input type="text" id="b_name" name="branding_app_name" value="<?= e($brandName) ?>">
                <p class="field-hint"><?= e(t('settings.modules.branding_name_hint')) ?></p>
            </div>
        </section>

        <section class="card">
            <h2 class="card-title"><?= e(t('settings.modules.registered')) ?></h2>
            <p class="card-sub"><?= e(t('settings.modules.subtitle')) ?></p>

            <div class="feature-list">
                <?php 
                $currentUser = isset($user) ? $user : null;
                $isRoot = $currentUser !== null && $currentUser['role'] === 'root';
                foreach ($features as $feature): ?>
                    <div class="feature-row">
                        <label class="check">
                            <input type="checkbox" name="enabled[]" value="<?= e($feature['id']) ?>"
                                <?= $feature['enabled'] ? ' checked' : '' ?>
                                <?= ($feature['locked'] && !$isRoot) || in_array($feature['id'], ['dashboard', 'settings'], true) ? ' disabled' : '' ?>>
                            <span class="feature-name"><?= e($feature['name']) ?></span>
                        </label>
                        <div class="feature-meta">
                            <span class="badge badge-<?= $feature['enabled'] ? 'success' : 'neutral' ?>"><?= e($feature['enabled'] ? t('settings.modules.enabled') : t('settings.modules.disabled')) ?></span>
                            <?php if ($feature['locked']): ?>
                                <span class="badge badge-info"><?= e(t('settings.modules.locked')) ?></span>
                            <?php endif; ?>
                            <?php if ($feature['requires_ami']): ?>
                                <span class="badge badge-warning"><?= e(t('settings.modules.requires_ami')) ?></span>
                            <?php endif; ?>
                            <?php if (in_array($feature['id'], ['dashboard', 'settings'], true)): ?>
                                <span class="badge badge-info"><?= e(t('settings.modules.core')) ?></span>
                            <?php endif; ?>
                            <span class="text-muted">v<?= e($feature['version']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </section>

        <?php $isRoot = isset($isRoot) ? $isRoot : false; ?>
        <?php if ($isRoot): ?>
        <section class="card">
            <h2 class="card-title"><?= e(t('settings.modules.expert')) ?></h2>
            <label class="check field-check">
                <input type="checkbox" name="show_legs" value="1"<?= !empty($showLegs) ? ' checked' : '' ?>>
                <span><?= e(t('settings.modules.show_legs')) ?></span>
            </label>
            <p class="field-hint"><?= e(t('settings.modules.show_legs_hint')) ?></p>
        </section>
        <?php endif; ?>

        <div class="filters-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
        </div>
    </form>
</div>