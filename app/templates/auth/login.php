<?php
$languages = \SmartReport\Core\Lang::languages();
$currentLanguage = \SmartReport\Core\Lang::current();
$isRtl = \SmartReport\Core\Lang::isRtl();
?>
<!doctype html>
<html lang="<?= e($currentLanguage ?? 'en') ?>" dir="<?= isset($isRtl) && $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? '') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-brand">
        <span class="brand-mark"><?= icon('dashboard', 26) ?></span>
        <span class="brand-name"><?= e(t('app.name')) ?></span>
    </div>
    <div class="auth-card">
        <h1 class="auth-title"><?= e(t('login.title')) ?></h1>
        <p class="auth-subtitle"><?= e(t('login.subtitle')) ?></p>

        <?= partial('partials/flash', ['flash' => flash_messages()]) ?>

        <form method="post" action="<?= e(url('/login')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="username"><?= e(t('login.username')) ?></label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password"><?= e(t('login.password')) ?></label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary btn-block" type="submit"><?= e(t('login.button')) ?></button>
        </form>

        <?php if (!empty($languages) && count($languages) > 1): ?>
            <div class="auth-lang">
                <span class="muted"><?= e(t('topbar.language')) ?></span>
                <select id="langSelect" name="lang">
                    <?php foreach ($languages as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $code === ($currentLanguage ?? 'en') ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script>window.SMR_BASE = <?= json_encode(SMR_BASE_URL) ?>;</script>
</body>
</html>