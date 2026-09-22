<?= partial(':features/settings/views/_nav') ?>

<div class="page">
    <div class="card">
        <h2 class="card-title"><?= e(t('settings.ami.title')) ?></h2>
        <dl class="info-list">
            <dt><?= e(t('settings.ami.status')) ?></dt>
            <dd>
                <span class="badge badge-<?= $ami['enabled'] ? 'success' : 'neutral' ?>"><?= e($ami['enabled'] ? t('settings.ami.enabled') : t('settings.ami.disabled')) ?></span>
                <?php if ($amiTestOk !== null): ?>
                    <span class="badge badge-<?= $amiTestOk ? 'success' : 'danger' ?>"><?= e($amiTestOk ? t('settings.ami.test_ok') : t('settings.ami.test_fail')) ?></span>
                    <?php if (!$amiTestOk && $amiTestError !== ''): ?><code class="alert-detail"><?= e($amiTestError) ?></code><?php endif; ?>
                <?php endif; ?>
            </dd>
            <dt><?= e(t('settings.ami.host')) ?></dt>
            <dd><code><?= e($ami['host'] !== '' ? $ami['host'] : '127.0.0.1') ?>:<?= e((string) (isset($ami['port']) ? $ami['port'] : 5038)) ?></code></dd>
            <dt><?= e(t('settings.ami.user')) ?></dt>
            <dd><code><?= e($ami['username'] !== '' ? $ami['username'] : t('settings.ami.not_set')) ?></code></dd>
            <dt><?= e(t('settings.ami.pass')) ?></dt>
            <dd><code><?= e($ami['password'] !== '' ? t('settings.ami.pass_set') : t('settings.ami.not_set')) ?></code></dd>
        </dl>
        <p class="field-hint"><?= e(t('settings.ami.config_note')) ?></p>

        <h3 class="card-title"><?= e(t('settings.ami.guide_title')) ?></h3>
        <ol class="setup-steps">
            <li><?= e(t('settings.ami.guide1')) ?></li>
            <li><?= e(t('settings.ami.guide2')) ?></li>
        </ol>
        <pre class="code-block">[smartreport]
secret = CHANGE_ME_STRONG_SECRET
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan
write = system,call,log,verbose,command,agent,user,config,command,reporting,originate</pre>
        <ol class="setup-steps" start="3">
            <li><?= e(t('settings.ami.guide3')) ?></li>
            <li><?= e(t('settings.ami.guide4')) ?></li>
            <li><?= e(t('settings.ami.guide5')) ?></li>
        </ol>
    </div>
</div>
