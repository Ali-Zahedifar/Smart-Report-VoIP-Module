<div class="stat-card<?= isset($type) && $type !== '' ? ' is-' . e($type) : '' ?>">
    <div class="stat-label"><?= e(isset($label) ? $label : '') ?></div>
    <div class="stat-value"><?= e(isset($value) ? $value : '') ?></div>
    <?php if (!empty($sub)): ?>
        <div class="stat-sub"><?= e($sub) ?></div>
    <?php endif; ?>
</div>