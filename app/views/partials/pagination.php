<?php
/**
 * Partial paginasi.
 *
 * @var array{page:int, per_page:int, total:int, pages:int, offset:int} $paginator
 * @var string $paginationPath
 * @var array  $paginationQuery
 * @var string $paginationPageKey Nama parameter halaman (default: page)
 * @var string $paginationFragment Anchor opsional, mis. #histori
 */
$pager = $paginator ?? null;

if ($pager === null || $pager['pages'] <= 1) {
    return;
}

$path     = $paginationPath ?? '';
$query    = $paginationQuery ?? [];
$pageKey  = $paginationPageKey ?? 'page';
$fragment = $paginationFragment ?? '';
$cur   = $pager['page'];
$pages = $pager['pages'];
$from  = max(1, $cur - 2);
$to    = min($pages, $cur + 2);
?>
<nav class="pagination" aria-label="Navigasi halaman">
    <?php if ($cur > 1): ?>
        <a class="page-link" href="<?= e(page_url($path, $query, $cur - 1, $pageKey) . $fragment) ?>">&laquo; Sebelumnya</a>
    <?php endif; ?>

    <?php if ($from > 1): ?>
        <a class="page-link" href="<?= e(page_url($path, $query, 1, $pageKey) . $fragment) ?>">1</a>
        <?php if ($from > 2): ?><span class="page-dots">&hellip;</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($p = $from; $p <= $to; $p++): ?>
        <?php if ($p === $cur): ?>
            <span class="page-link is-active"><?= e($p) ?></span>
        <?php else: ?>
            <a class="page-link" href="<?= e(page_url($path, $query, $p, $pageKey) . $fragment) ?>"><?= e($p) ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($to < $pages): ?>
        <?php if ($to < $pages - 1): ?><span class="page-dots">&hellip;</span><?php endif; ?>
        <a class="page-link" href="<?= e(page_url($path, $query, $pages, $pageKey) . $fragment) ?>"><?= e($pages) ?></a>
    <?php endif; ?>

    <?php if ($cur < $pages): ?>
        <a class="page-link" href="<?= e(page_url($path, $query, $cur + 1, $pageKey) . $fragment) ?>">Berikutnya &raquo;</a>
    <?php endif; ?>

    <span class="page-info">Total <?= e($pager['total']) ?> data</span>
</nav>
