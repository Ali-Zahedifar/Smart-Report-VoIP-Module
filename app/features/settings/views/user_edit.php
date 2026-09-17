<?= partial(':features/settings/views/_nav') ?>

<div class="page">
    <div class="card">
        <h2 class="card-title"><?= e(t('settings.users.edit')) ?></h2>
        <form method="post" action="<?= e(url('/settings/users/' . (int) $user['id'])) ?>" class="form form-stack">
            <?= csrf_field() ?>
            <div class="field">
                <label for="e_username"><?= e(t('settings.users.username')) ?></label>
                <input type="text" id="e_username" name="username" value="<?= e($user['username']) ?>"<?= $user['role'] === 'root' ? ' disabled' : '' ?>>
            </div>
            <div class="field">
                <label for="e_display"><?= e(t('settings.users.display')) ?></label>
                <input type="text" id="e_display" name="display_name" value="<?= e($user['display_name']) ?>">
            </div>
            <div class="field">
                <label for="e_role"><?= e(t('settings.users.role')) ?></label>
                <select id="e_role" name="role"<?= $user['role'] === 'root' ? ' disabled' : '' ?>>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role) ?>"<?= $user['role'] === $role ? ' selected' : '' ?>><?= e(t('settings.users.role_' . $role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($user['role'] !== 'root'): ?>
                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="active" value="1"<?= (int) $user['active'] === 1 ? ' checked' : '' ?>>
                        <span><?= e(t('settings.users.enabled')) ?></span>
                    </label>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="e_password"><?= e(t('settings.users.password')) ?> <span class="text-muted">(<?= e(t('common.optional')) ?>)</span></label>
                <input type="password" id="e_password" name="password" minlength="8" autocomplete="new-password">
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
                <a class="btn btn-ghost" href="<?= e(url('/settings/users')) ?>"><?= e(t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>