<?php
$route = current_path();
$isIndex = $route === '/settings';
$isUsers = starts($route, '/settings/users');
$isModules = starts($route, '/settings/modules');
$isProfile = starts($route, '/settings/profile');
?>
<nav class="tabs" aria-label="settings tabs">
    <a class="tab<?= $isIndex ? ' is-active' : '' ?>" href="<?= e(url('/settings')) ?>"><?= e(t('settings.title')) ?></a>
    <?php if (in_array(\SmartReport\Core\Auth::role(), ['root', 'admin'], true)): ?>
        <a class="tab<?= $isUsers ? ' is-active' : '' ?>" href="<?= e(url('/settings/users')) ?>"><?= e(t('nav.users')) ?></a>
    <?php endif; ?>
    <?php if (\SmartReport\Core\Auth::role() === 'root'): ?>
        <a class="tab<?= $isModules ? ' is-active' : '' ?>" href="<?= e(url('/settings/modules')) ?>"><?= e(t('nav.modules')) ?></a>
    <?php endif; ?>
    <a class="tab<?= $isProfile ? ' is-active' : '' ?>" href="<?= e(url('/settings/profile')) ?>"><?= e(t('nav.profile')) ?></a>
</nav>