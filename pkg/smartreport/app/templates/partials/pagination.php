<?php
$total = (int) (isset($total) ? $total : 0);
$perPage = (int) (isset($perPage) ? $perPage : 1);
$page = (int) (isset($page) ? $page : 1);
$queryString = (string) (isset($queryString) ? $queryString : '');
$baseUrl = (string) (isset($baseUrl) ? $baseUrl : '/calls');
if ($perPage < 1) {
    $perPage = 1;
}
$totalPages = max(1, (int) ceil($total / $perPage));
if ($totalPages > 1):
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($totalPages, $page + $window);
    $href = function ($p) use ($queryString, $baseUrl) {
        $qs = trim($queryString, '?&');
        $suffix = $qs !== '' ? $qs . '&page=' . $p : 'page=' . $p;
        return e(url($baseUrl . '?' . $suffix));
    };
    ?>
    <nav class="pagination" aria-label="pagination">
        <a class="page-link<?= $page <= 1 ? ' is-disabled' : '' ?>"<?= $page > 1 ? ' href="' . $href($page - 1) . '"' : '' ?>><?= e(t('pagination.prev')) ?></a>
        <?php if ($start > 1): ?>
            <a class="page-link" href="<?= $href(1) ?>">1</a>
            <?php if ($start > 2): ?><span class="page-gap">&hellip;</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
            <a class="page-link<?= $p === $page ? ' is-active' : '' ?>" <?= $p !== $page ? 'href="' . $href($p) . '"' : '' ?>><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?><span class="page-gap">&hellip;</span><?php endif; ?>
            <a class="page-link" href="<?= $href($totalPages) ?>"><?= $totalPages ?></a>
        <?php endif; ?>
        <a class="page-link<?= $page >= $totalPages ? ' is-disabled' : '' ?>"<?= $page < $totalPages ? ' href="' . $href($page + 1) . '"' : '' ?>><?= e(t('pagination.next')) ?></a>
    </nav>
<?php endif; ?>