<?php
/**
 * Page links for a list.
 *
 * Included from a view with:
 *   $paginator, $path (e.g. "/admin/messages"), $query (filters to keep)
 *
 * The partial is deliberately quiet: one line of text and a row of numbers.
 * It renders nothing when everything fits on one page.
 *
 * @var \Wiz\Support\Paginator $paginator
 * @var string $path
 * @var array<string, string> $query
 */

$query = $query ?? [];
$pageUrl = static function (int $page) use ($path, $query): string {
    $params = $query;
    $params['page'] = (string) $page;

    return $path . '?' . http_build_query($params);
};
?>

<?php if ($paginator->pages() > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <p class="pagination__range">
            <?= (int) $paginator->firstItem() ?>–<?= (int) $paginator->lastItem() ?>
            of <?= (int) $paginator->total ?>
        </p>

        <ul class="pagination__pages">
            <li>
                <?php if ($paginator->hasPrevious()): ?>
                    <a class="pagination__step" href="<?= e($pageUrl($paginator->previousPage())) ?>" rel="prev">← Previous</a>
                <?php else: ?>
                    <span class="pagination__step is-disabled" aria-hidden="true">← Previous</span>
                <?php endif; ?>
            </li>

            <?php foreach ($paginator->window() as $number): ?>
                <li>
                    <?php if ($number === null): ?>
                        <span class="pagination__gap" aria-hidden="true">…</span>
                    <?php elseif ($number === $paginator->page): ?>
                        <span class="pagination__page is-current" aria-current="page"><?= $number ?></span>
                    <?php else: ?>
                        <a class="pagination__page" href="<?= e($pageUrl($number)) ?>"><?= $number ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>

            <li>
                <?php if ($paginator->hasNext()): ?>
                    <a class="pagination__step" href="<?= e($pageUrl($paginator->nextPage())) ?>" rel="next">Next →</a>
                <?php else: ?>
                    <span class="pagination__step is-disabled" aria-hidden="true">Next →</span>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
<?php endif; ?>
