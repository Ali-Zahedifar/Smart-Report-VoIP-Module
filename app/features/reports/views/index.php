<div class="page">
    <section class="card">
        <div class="card-head">
            <h2><?= e(t('reports.title')) ?></h2>
            <span class="muted"><?= e(t('reports.subtitle')) ?></span>
        </div>
        <div class="filters-row">
            <form id="reportFilters" class="filters-form" data-fetch-url="<?= e(url('/reports/data')) ?>">
                <div class="filter-group">
                    <label><?= e(t('reports.date_from')) ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?= e(date('Y-m-d', strtotime('-30 days'))) ?>">
                </div>
                <div class="filter-group">
                    <label><?= e(t('reports.date_to')) ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="filter-group">
                    <label><?= e(t('reports.queue_filter')) ?></label>
                    <select name="queue" id="queue">
                        <option value=""><?= e(t('reports.all_queues')) ?></option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" id="refreshBtn"><?= e(t('reports.refresh')) ?></button>
            </form>
            <div class="export-buttons">
                <button type="button" class="btn btn-secondary" id="exportPdf"><?= e(t('reports.download_pdf')) ?></button>
                <button type="button" class="btn btn-secondary" id="exportPng"><?= e(t('reports.download_png')) ?></button>
            </div>
        </div>
        <div id="reportError" class="alert alert-error" style="display: none;"></div>
        <div id="reportLoading" class="loading" style="display: none; text-align: center; padding: 2rem;">
            <div class="spinner"></div>
            <p><?= e(t('reports.loading')) ?></p>
        </div>
    </section>

    <div class="charts-grid">
        <!-- Calls by Direction -->
        <section class="card chart-card" data-chart="dirChart">
            <div class="card-head">
                <h3><?= e(t('reports.calls_by_direction')) ?></h3>
                <button type="button" class="btn btn-ghost btn-sm chart-download" data-chart="dirChart"><?= e(t('reports.download_png')) ?></button>
            </div>
            <div class="chart-wrap">
                <canvas id="dirChart"></canvas>
            </div>
        </section>

        <!-- Calls by Hour -->
        <section class="card chart-card" data-chart="hourChart">
            <div class="card-head">
                <h3><?= e(t('reports.calls_by_hour')) ?></h3>
                <button type="button" class="btn btn-ghost btn-sm chart-download" data-chart="hourChart"><?= e(t('reports.download_png')) ?></button>
            </div>
            <div class="chart-wrap">
                <canvas id="hourChart"></canvas>
            </div>
        </section>

        <!-- Missed Calls Trend -->
        <section class="card chart-card wide" data-chart="missedChart">
            <div class="card-head">
                <h3><?= e(t('reports.missed_trend')) ?></h3>
                <button type="button" class="btn btn-ghost btn-sm chart-download" data-chart="missedChart"><?= e(t('reports.download_png')) ?></button>
            </div>
            <div class="chart-wrap">
                <canvas id="missedChart"></canvas>
            </div>
        </section>

        <!-- Talk Time Distribution -->
        <section class="card chart-card" data-chart="talkChart">
            <div class="card-head">
                <h3><?= e(t('reports.talk_time_dist')) ?></h3>
                <button type="button" class="btn btn-ghost btn-sm chart-download" data-chart="talkChart"><?= e(t('reports.download_png')) ?></button>
            </div>
            <div class="chart-wrap">
                <canvas id="talkChart"></canvas>
            </div>
        </section>

        <!-- Queue Performance -->
        <section class="card chart-card wide" data-chart="queueChart">
            <div class="card-head">
                <h3><?= e(t('reports.queue_performance')) ?></h3>
                <button type="button" class="btn btn-ghost btn-sm chart-download" data-chart="queueChart"><?= e(t('reports.download_png')) ?></button>
            </div>
            <div class="chart-wrap">
                <canvas id="queueChart"></canvas>
            </div>
        </section>

        <!-- Agent Performance -->
        <section class="card chart-card wide" data-chart="agentChart">
            <div class="card-head">
                <h3><?= e(t('reports.agent_performance')) ?></h3>
                <button type="button" class="btn btn-ghost btn-sm chart-download" data-chart="agentChart"><?= e(t('reports.download_png')) ?></button>
            </div>
            <div class="chart-wrap">
                <canvas id="agentChart"></canvas>
            </div>
        </section>
    </div>

    <?php if (!empty($is_root)): ?>
    <section class="card">
        <div class="card-head">
            <h3><?= e(t('reports.chart_toggles')) ?></h3>
            <span class="muted"><?= e(t('reports.chart_toggles_hint')) ?></span>
        </div>
        <div id="chartToggles" class="toggles-grid"></div>
        <div class="card-foot">
            <button type="button" id="saveChartToggles" class="btn btn-primary"><?= e(t('reports.save_chart_toggles')) ?></button>
            <span id="toggleSaved" class="muted" style="display:none;"><?= e(t('reports.toggles_saved')) ?></span>
        </div>
    </section>
    <?php endif; ?>

    <div id="reportConfig"
         data-enabled-charts="<?= e(json_encode(isset($enabled_charts) ? $enabled_charts : [])) ?>"
         data-chart-labels="<?= e((function () {
            return json_encode([
                'dirChart' => t('reports.calls_by_direction'),
                'hourChart' => t('reports.calls_by_hour'),
                'missedChart' => t('reports.missed_trend'),
                'talkChart' => t('reports.talk_time_dist'),
                'queueChart' => t('reports.queue_performance'),
                'agentChart' => t('reports.agent_performance'),
            ]);
        })()) ?>"
         data-save-url="<?= e(url('/reports/charts')) ?>"
         data-queue-detail-base="<?= e(url('/queue-report/detail')) ?>"
         data-csrf="<?= e(\SmartReport\Core\Csrf::token()) ?>"
         class="report-config"></div>
</div>

<script src="<?= e(asset('js/vendor/chart.min.js')) ?>"></script>
<script src="<?= e(asset('js/vendor/jspdf.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/reports.js')) ?>"></script>
