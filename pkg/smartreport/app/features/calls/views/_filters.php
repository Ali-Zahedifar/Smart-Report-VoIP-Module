<?php
// Shared filter bar used by internal, missed and queue-report pages.
// Safe to render with only $filters and $dispositions in scope.
$filters = isset($filters) ? $filters : [];
$dispositions = isset($dispositions) ? $dispositions : [];
$route = current_path();
$limitChoices = [25, 50, 100, 250];
$currentLimit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
if (!in_array($currentLimit, $limitChoices, true)) {
    $currentLimit = 50;
}
?>
<section class="card filters">
    <form method="get" action="<?= e(url($route)) ?>" class="form filters-grid">
        <div class="field">
            <label for="date_from"><?= e(t('calls.date_from')) ?></label>
            <input type="date" id="date_from" name="date_from" value="<?= e(isset($filters['date_from']) ? $filters['date_from'] : '') ?>">
        </div>
        <div class="field">
            <label for="date_to"><?= e(t('calls.date_to')) ?></label>
            <input type="date" id="date_to" name="date_to" value="<?= e(isset($filters['date_to']) ? $filters['date_to'] : '') ?>">
        </div>
        <div class="field">
            <label for="src"><?= e(t('calls.src')) ?></label>
            <input type="text" id="src" name="src" value="<?= e(isset($filters['src']) ? $filters['src'] : '') ?>" placeholder="…">
        </div>
        <div class="field">
            <label for="dst"><?= e(t('calls.dst')) ?></label>
            <input type="text" id="dst" name="dst" value="<?= e(isset($filters['dst']) ? $filters['dst'] : '') ?>" placeholder="…">
        </div>
        <div class="field">
            <label for="clid"><?= e(t('calls.clid')) ?></label>
            <input type="text" id="clid" name="clid" value="<?= e(isset($filters['clid']) ? $filters['clid'] : '') ?>" placeholder="…">
        </div>
        <div class="field">
            <label for="disposition"><?= e(t('calls.disposition')) ?></label>
            <select id="disposition" name="disposition">
                <option value=""><?= e(t('calls.disposition_any')) ?></option>
                <?php foreach ($dispositions as $disp): ?>
                    <option value="<?= e($disp) ?>"<?= isset($filters['disposition']) && $filters['disposition'] === $disp ? ' selected' : '' ?>><?= e(t('status.' . $disp)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="limit"><?= e(t('calls.limit')) ?></label>
            <select id="limit" name="limit">
                <?php foreach ($limitChoices as $choice): ?>
                    <option value="<?= e((string) $choice) ?>"<?= $choice === $currentLimit ? ' selected' : '' ?>><?= e(number_format($choice)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filters-actions">
            <button class="btn btn-primary" type="submit"><?= e(t('calls.apply')) ?></button>
            <a class="btn btn-ghost" href="<?= e(url($route)) ?>"><?= e(t('calls.reset')) ?></a>
        </div>
    </form>
</section>