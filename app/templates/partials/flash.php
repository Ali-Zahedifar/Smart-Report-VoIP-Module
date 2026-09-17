<?php if (!empty($flash)): ?>
    <div class="flash-stack">
        <?php foreach ($flash as $item): ?>
            <div class="alert alert-<?= e($item['type'] ?? 'info') ?>">
                <span><?= e($item['message'] ?? '') ?></span>
                <button type="button" class="alert-close" aria-label="<?= e(t('common.close')) ?>">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>