<!doctype html>
<html lang="<?= e($currentLanguage) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script>window.SMR_BASE = <?= json_encode(SMR_BASE_URL) ?>;</script>
</body>
</html>