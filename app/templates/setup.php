<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? 'Smart-Report') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="setup-body">
<div class="setup-card">
    <h1 class="setup-title"><?= e($title ?? 'Smart-Report') ?></h1>
    <p><?= e(t('not_installed.body')) ?></p>
    <pre class="setup-command">cd <?= e(SMR_ROOT) . PHP_EOL ?>sudo php install/installer.php</pre>
    <p class="muted"><?= e(t('settings.info_app')) ?>: <code><?= e(\SmartReport\Core\App::reason() ?? '') ?></code></p>
</div>
</body>
</html>