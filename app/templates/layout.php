<!doctype html>
<html lang="<?= e($currentLanguage) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<!--app-version <?= e(\SmartReport\Core\App::version()) ?>-->
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(isset($title) && $title !== '' ? $title . ' · ' . $brand : $brand) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/reports.css')) ?>">
</head>
<body class="app-body">
<div class="app" id="app">
<?= partial('partials/sidebar', [
        'brand' => $brand,
        'menuItems' => $menuItems,
        'currentRoute' => $currentRoute,
        'appVersion' => $appVersion,
    ]) ?>
    <div class="app-main">
        <?= partial('partials/topbar', [
            'title' => isset($title) ? $title : '',
            'user' => $user,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
        ]) ?>
        <main class="app-content">
            <?= partial('partials/flash', ['flash' => $flash]) ?>
            <?= $content ?>
        </main>
    </div>
</div>

<div class="modal" id="audioModal" role="dialog" aria-modal="true" aria-label="<?= e(t('calls.play')) ?>">
    <div class="modal-box">
        <div class="modal-head">
            <h3 data-audio-title><?= e(t('calls.play')) ?></h3>
            <div class="modal-actions">
                <a class="btn btn-ghost btn-sm" data-audio-download href="#" download title="<?= e(t('calls.download')) ?>"><?= icon('download', 14) ?></a>
                <button type="button" class="btn btn-ghost btn-sm" data-audio-close aria-label="<?= e(t('common.close')) ?>"><?= e(t('common.close')) ?></button>
            </div>
        </div>
        <div class="modal-body">
            <audio controls preload="none"></audio>
            <p class="modal-empty" style="display: none;"><?= e(t('calls.no_recordings')) ?></p>
        </div>
    </div>
</div>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script>window.SMR_BASE = <?= json_encode(SMR_BASE_URL) ?>;</script>
</body>
</html>