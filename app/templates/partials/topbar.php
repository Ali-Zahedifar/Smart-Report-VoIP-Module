<header class="topbar">
    <button type="button" class="icon-btn nav-toggle" id="navToggle" aria-label="<?= e(t('nav.open_menu')) ?>">
        <span></span><span></span><span></span>
    </button>
    <h1 class="page-title"><?= e($title ?? '') ?></h1>
    <div class="topbar-actions">
        <?php if (!empty($languages) && count($languages) > 1): ?>
            <label class="lang-switch">
                <span class="muted"><?= e(t('topbar.language')) ?></span>
                <select id="langSelect" name="lang" aria-label="<?= e(t('topbar.language')) ?>">
                    <?php foreach ($languages as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $code === $currentLanguage ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if (!empty($user)): ?>
            <div class="user-chip">
                <span class="user-name"><?= e($user['display_name'] !== '' ? $user['display_name'] : $user['username']) ?></span>
                <span class="badge badge-<?= e($user['role']) ?>"><?= e(t('settings.users.role_' . $user['role'])) ?></span>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/settings/profile')) ?>"><?= e(t('nav.profile')) ?></a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('nav.logout')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>