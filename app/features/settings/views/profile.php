<?= partial(':features/settings/views/_nav') ?>

<div class="page">
    <div class="card card-narrow">
        <h2 class="card-title"><?= e(t('settings.profile.title')) ?></h2>
        <p class="card-sub"><?= e(t('settings.profile.subtitle')) ?></p>
        <form method="post" action="<?= e(url('/settings/profile')) ?>" class="form form-stack">
            <?= csrf_field() ?>
            <div class="field">
                <label for="p_current"><?= e(t('settings.profile.current')) ?></label>
                <input type="password" id="p_current" name="current_password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label for="p_new"><?= e(t('settings.profile.new')) ?></label>
                <input type="password" id="p_new" name="new_password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="p_confirm"><?= e(t('settings.profile.confirm')) ?></label>
                <input type="password" id="p_confirm" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
            </div>
        </form>
    </div>
</div>