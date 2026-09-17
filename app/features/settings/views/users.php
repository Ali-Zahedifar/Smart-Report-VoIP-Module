<?= partial(':features/settings/views/_nav') ?>

<div class="page">
    <section class="card">
        <h2 class="card-title"><?= e(t('settings.users.add')) ?></h2>
        <form method="post" action="<?= e(url('/settings/users')) ?>" class="form form-inline-grid">
            <?= csrf_field() ?>
            <div class="field">
                <label for="u_username"><?= e(t('settings.users.username')) ?></label>
                <input type="text" id="u_username" name="username" required>
            </div>
            <div class="field">
                <label for="u_display"><?= e(t('settings.users.display')) ?></label>
                <input type="text" id="u_display" name="display_name">
            </div>
            <div class="field">
                <label for="u_role"><?= e(t('settings.users.role')) ?></label>
                <select id="u_role" name="role">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role) ?>"><?= e(t('settings.users.role_' . $role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="u_password"><?= e(t('settings.users.password')) ?></label>
                <input type="password" id="u_password" name="password" required minlength="8">
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('settings.users.add')) ?></button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2 class="card-title"><?= e(t('settings.users.title')) ?></h2>
        <?php if (empty($users)): ?>
            <p class="empty-state"><?= e(t('calls.empty')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('settings.users.username')) ?></th>
                        <th><?= e(t('settings.users.display')) ?></th>
                        <th><?= e(t('settings.users.role')) ?></th>
                        <th><?= e(t('settings.users.active')) ?></th>
                        <th><?= e(t('settings.users.last_login')) ?></th>
                        <th><?= e(t('settings.users.actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e($user['username']) ?></td>
                            <td><?= e($user['display_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= e($user['role']) ?>"><?= e(t('settings.users.role_' . $user['role'])) ?></span>
                            </td>
                            <td>
                                <?php if ($user['active'] && (int) $user['active'] === 1): ?>
                                    <span class="badge badge-success"><?= e(t('settings.users.enabled')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-neutral"><?= e(t('settings.users.disabled')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= e($user['last_login_at'] ?: '-') ?></td>
                            <td class="nowrap">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/settings/users/' . (int) $user['id'])) ?>"><?= e(t('settings.users.edit')) ?></a>
                                <?php if ($user['role'] !== 'root'): ?>
                                    <form method="post" action="<?= e(url('/settings/users/' . (int) $user['id'] . '/toggle')) ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm" type="submit"><?= e((int) $user['active'] === 1 ? t('settings.users.disabled') : t('settings.users.enabled')) ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('/settings/users/' . (int) $user['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(t('settings.users.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-danger btn-sm" type="submit"><?= e(t('settings.users.delete')) ?></button>
                                    </form>
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