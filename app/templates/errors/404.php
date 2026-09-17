<div class="error-page">
    <div class="error-code">404</div>
    <h2><?= e($title) ?></h2>
    <p><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= e(t('common.back')) ?></a>
</div>