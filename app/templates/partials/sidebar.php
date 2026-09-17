<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark"><?= icon('dashboard', 22) ?></span>
        <span class="brand-name"><?= e($brand) ?></span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menuItems as $item): ?>
            <?php
            $active = $currentRoute === $item['route']
                || ($item['route'] !== '/' && starts($currentRoute, $item['route']));
            ?>
            <a class="nav-item<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['route'])) ?>">
                <?= icon($item['icon']) ?>
                <span><?= e(t('nav.' . $item['label'])) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <span class="muted"><?= e(t('sidebar.version')) ?> <?= e($appVersion) ?></span>
        <button type="button" class="nav-toggle-mobile" id="navCloseBtn" aria-label="<?= e(t('nav.open_menu')) ?>">&times;</button>
    </div>
</aside>