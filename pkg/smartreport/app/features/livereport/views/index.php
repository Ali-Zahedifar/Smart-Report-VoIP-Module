<div class="page">
    <p class="page-subtitle"><?= e(t('live.subtitle')) ?></p>

    <?php if (!$amiEnabled): ?>
        <div class="alert alert-warning">
            <span><?= e(t('live.ami_off')) ?></span>
        </div>
        <?php if ($isRoot): ?>
            <section class="card">
                <div class="card-head"><h3><?= e(t('live.setup_title')) ?></h3></div>
                <p class="card-sub"><?= e(t('live.setup_intro')) ?></p>
                <ol class="setup-steps">
                    <li><?= e(t('live.setup_step1')) ?></li>
                    <li><?= e(t('live.setup_step2')) ?></li>
                    <li><?= e(t('live.setup_step3')) ?></li>
                    <li><?= e(t('live.setup_step4')) ?></li>
                </ol>
                <pre class="code-block">[smartreport]
secret = CHANGE_ME_STRONG_SECRET
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan
write = system,call,log,verbose,command,agent,user,config,command,reporting,originate</pre>
                <ol class="setup-steps" start="5">
                    <li><?= e(t('live.setup_step5')) ?></li>
                    <li><?= e(t('live.setup_step6')) ?></li>
                </ol>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <section class="stat-grid" id="liveSummary">
            <div class="stat-card is-primary">
                <span class="stat-value" id="sumActive">–</span>
                <span class="stat-label"><?= e(t('live.active_calls')) ?></span>
            </div>
            <div class="stat-card is-success">
                <span class="stat-value" id="sumInUse">–</span>
                <span class="stat-label"><?= e(t('live.talking')) ?></span>
            </div>
            <div class="stat-card is-warning">
                <span class="stat-value" id="sumRinging">–</span>
                <span class="stat-label"><?= e(t('live.ringing')) ?></span>
            </div>
            <div class="stat-card is-info">
                <span class="stat-value" id="sumWaiting">–</span>
                <span class="stat-label"><?= e(t('live.waiting')) ?></span>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2><?= e(t('live.calls_title')) ?></h2>
                <span class="muted"><span class="live-dot"></span> <?= e(t('live.auto_refresh')) ?></span>
            </div>
            <?php if ($canSpy): ?>
                <p class="card-sub"><?= e(t('live.spy_hint')) ?> <?= e(t('live.spy_mode_' . (string) \SmartReport\Core\App::setting('live.spy_mode', 'listen'))) ?></p>
            <?php endif; ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><?= e(t('live.col_channel')) ?></th>
                        <th><?= e(t('live.col_caller')) ?></th>
                        <th><?= e(t('live.col_context')) ?></th>
                        <th><?= e(t('live.col_ext')) ?></th>
                        <th><?= e(t('live.col_state')) ?></th>
                        <th><?= e(t('live.col_duration')) ?></th>
                        <?php if ($canSpy): ?><th><?= e(t('live.col_listen')) ?></th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody id="liveCallsBody">
                    <tr><td colspan="7" class="text-muted"><?= e(t('live.loading')) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h2><?= e(t('live.queues_title')) ?></h2>
            </div>
            <div id="liveQueues"></div>
        </section>
    <?php endif; ?>

    <?php if ($isRoot): ?>
    <section class="card">
        <div class="card-head">
            <h3><?= e(t('live.options_title')) ?></h3>
        </div>
        <form id="liveOptions" class="form filters-grid">
            <div class="field field-check">
                <label class="check">
                    <input type="checkbox" name="spy_enabled" value="1"<?= $canSpy ? ' checked' : '' ?>>
                    <span><?= e(t('live.spy_enabled')) ?></span>
                </label>
            </div>
            <div class="field">
                <label for="spy_mode"><?= e(t('live.spy_mode_label')) ?></label>
                <select id="spy_mode" name="spy_mode">
                    <option value="listen"<?= \SmartReport\Core\App::setting('live.spy_mode', 'listen') === 'listen' ? ' selected' : '' ?>><?= e(t('live.spy_mode_listen')) ?></option>
                    <option value="whisper"<?= \SmartReport\Core\App::setting('live.spy_mode', 'listen') === 'whisper' ? ' selected' : '' ?>><?= e(t('live.spy_mode_whisper')) ?></option>
                    <option value="barge"<?= \SmartReport\Core\App::setting('live.spy_mode', 'listen') === 'barge' ? ' selected' : '' ?>><?= e(t('live.spy_mode_barge')) ?></option>
                </select>
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary" type="submit"><?= e(t('common.save')) ?></button>
                <span id="liveSaved" class="muted" style="display:none;"><?= e(t('live.saved')) ?></span>
            </div>
        </form>
        <p class="field-hint"><?= e(t('live.spy_note')) ?></p>
    </section>
    <?php endif; ?>
</div>

<script>
    window.SMR_LIVE = {
        pollUrl: <?= json_encode(url('/live/data')) ?>,
        spyUrl: <?= json_encode(url('/live/spy')) ?>,
        settingsUrl: <?= json_encode(url('/live/settings')) ?>,
        csrf: <?= json_encode(\SmartReport\Core\Csrf::token()) ?>,
        canSpy: <?= $canSpy ? 'true' : 'false' ?>,
        pollMs: 5000,
        i18n: {
            empty: <?= json_encode(t('live.no_calls')) ?>,
            waiting: <?= json_encode(t('live.waiting')) ?>,
            listen: <?= json_encode(t('live.btn_listen')) ?>,
            whisper: <?= json_encode(t('live.btn_whisper')) ?>,
            barge: <?= json_encode(t('live.btn_barge')) ?>,
            confirm: <?= json_encode(t('live.spy_confirm')) ?>
        }
    };
</script>
<script src="<?= e(asset('js/live.js')) ?>"></script>
