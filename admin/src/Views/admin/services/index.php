<?php
/**
 * Services hub: categories as cards, then drill into a category's services.
 *
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $services
 * @var list<array<string, mixed>> $selectedServices
 * @var array<string, mixed>|null $selectedCategory
 * @var int $uncategorisedCount
 * @var string $categoryParam
 * @var \Wiz\Services\Uploader $uploads
 * @var int $highlight
 */

$money = static function (mixed $cents): string {
    if ($cents === null || $cents === '') {
        return '—';
    }
    return 'ETB ' . number_format(((int) $cents) / 100, 0);
};

$totalCategories = count($categories);
$totalServices = count($services);
$isDetail = $selectedCategory !== null;
?>

<?php if ($isDetail === false): ?>
    <div class="toolbar">
        <span class="toolbar__count">
            <?= $totalCategories ?> categor<?= $totalCategories === 1 ? 'y' : 'ies' ?> · <?= $totalServices ?> service<?= $totalServices === 1 ? '' : 's' ?>
            <?php if ($uncategorisedCount > 0): ?>
                · <a href="/admin/services?category=uncategorised"><?= $uncategorisedCount ?> uncategorised</a>
            <?php endif; ?>
        </span>
        <span class="toolbar__actions">
            <a class="btn btn--ghost" href="/admin/categories">Manage categories</a>
            <a class="btn btn--primary" href="/admin/services/new">Add a service</a>
        </span>
    </div>

    <?php if ($categories === [] && $services === []): ?>
        <section class="card empty-state">
            <h2 class="card__title">No services yet</h2>
            <p class="card__sub">Create a category first — then add services inside it. You can also add a service without a category and it will appear as “uncategorised”.</p>
            <div style="display:flex;gap:10px;justify-content:center;margin-top:12px;flex-wrap:wrap">
                <a class="btn btn--ghost" href="/admin/categories">Create a category</a>
                <a class="btn btn--primary" href="/admin/services/new">Add a service</a>
            </div>
        </section>
    <?php else: ?>
        <div class="svc-admin-hub">
            <?php foreach ($categories as $index => $category): ?>
                <?php
                $id = (int) $category['id'];
                $active = (int) ($category['is_active'] ?? 0) === 1;
                $image = is_string($category['image_path'] ?? null) ? $category['image_path'] : null;
                $count = (int) ($category['service_count'] ?? 0);
                $amharic = is_string($category['amharic'] ?? null) ? trim($category['amharic']) : '';
                $desc = is_string($category['description'] ?? null) ? trim($category['description']) : '';
                ?>
                <article class="svc-admin-card">
                    <a class="svc-admin-card__media" href="/admin/services?category=<?= $id ?>" aria-label="Manage <?= e($category['name']) ?>">
                        <?php if ($image !== null): ?>
                            <img src="<?= e($uploads->url($image)) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="svc-admin-card__placeholder" aria-hidden="true"><?= e(mb_substr($category['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                        <span class="svc-admin-card__index">0<?= $index + 1 ?></span>
                        <?php if ($active === false): ?>
                            <span class="svc-admin-card__hidden-badge">Hidden</span>
                        <?php endif; ?>
                    </a>
                    <div class="svc-admin-card__body">
                        <h3 class="svc-admin-card__title">
                            <a href="/admin/services?category=<?= $id ?>"><?= e($category['name']) ?></a>
                            <?php if ($amharic !== ''): ?>
                                <span class="svc-admin-card__am" lang="am"><?= e($amharic) ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php if ($desc !== ''): ?>
                            <p class="svc-admin-card__desc"><?= e($desc) ?></p>
                        <?php endif; ?>
                        <div class="svc-admin-card__meta">
                            <span class="svc-admin-pill">
                                <span class="svc-admin-pill__dot" aria-hidden="true"></span>
                                <?= $count ?> <?= $count === 1 ? 'service' : 'services' ?>
                            </span>
                            <span class="badge <?= $active ? 'badge--ok' : 'badge--muted' ?>"><?= $active ? 'Visible' : 'Hidden' ?></span>
                        </div>
                        <div class="svc-admin-card__actions">
                            <a class="btn btn--primary btn--sm svc-admin-card__open" href="/admin/services?category=<?= $id ?>">Open · Manage →</a>
                            <a class="btn btn--ghost btn--sm" href="/admin/categories?edit=<?= $id ?>">Edit category</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($uncategorisedCount > 0): ?>
                <article class="svc-admin-card svc-admin-card--uncategorised">
                    <a class="svc-admin-card__media svc-admin-card__media--empty" href="/admin/services?category=uncategorised" aria-label="Manage uncategorised services">
                        <span class="svc-admin-card__placeholder" aria-hidden="true">…</span>
                        <span class="svc-admin-card__index">•</span>
                    </a>
                    <div class="svc-admin-card__body">
                        <h3 class="svc-admin-card__title"><a href="/admin/services?category=uncategorised">Uncategorised</a></h3>
                        <p class="svc-admin-card__desc">Services without a category. Give them a home so customers can find them inside a card.</p>
                        <div class="svc-admin-card__meta">
                            <span class="svc-admin-pill"><?= $uncategorisedCount ?> <?= $uncategorisedCount === 1 ? 'service' : 'services' ?></span>
                            <span class="badge badge--muted">Needs sorting</span>
                        </div>
                        <div class="svc-admin-card__actions">
                            <a class="btn btn--primary btn--sm" href="/admin/services?category=uncategorised">Open →</a>
                        </div>
                    </div>
                </article>
            <?php endif; ?>
        </div>
        <p class="svc-admin-hint">Tip: categories are the cards customers tap first — keep names short, add an Amharic line, and use a 4:3 photo like the public site does. Reorder categories in <a href="/admin/categories">Manage categories</a>.</p>
    <?php endif; ?>

<?php else: ?>
    <?php
    $catId = (int) ($selectedCategory['id'] ?? 0);
    $isUncategorised = $catId === 0;
    $catImage = is_string($selectedCategory['image_path'] ?? null) ? $selectedCategory['image_path'] : null;
    $catAmharic = is_string($selectedCategory['amharic'] ?? null) ? trim($selectedCategory['amharic']) : '';
    $catDesc = is_string($selectedCategory['description'] ?? null) ? trim($selectedCategory['description']) : '';
    $catCount = (int) ($selectedCategory['service_count'] ?? count($selectedServices));
    $catActive = (int) ($selectedCategory['is_active'] ?? 1) === 1;
    $addHref = $isUncategorised ? '/admin/services/new' : '/admin/services/new?category=' . $catId;
    ?>

    <section class="card svc-admin-detail-hero">
        <div class="svc-admin-detail-hero__media">
            <?php if ($catImage !== null): ?>
                <img src="<?= e($uploads->url($catImage)) ?>" alt="">
            <?php else: ?>
                <span class="svc-admin-detail-hero__placeholder" aria-hidden="true"><?= $isUncategorised ? '…' : e(mb_substr($selectedCategory['name'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="svc-admin-detail-hero__copy">
            <div class="svc-admin-detail-hero__top">
                <span class="svc-admin-pill svc-admin-pill--blush"><?= $catCount ?> <?= $catCount === 1 ? 'service' : 'services' ?></span>
                <?php if ($isUncategorised === false): ?>
                    <span class="badge <?= $catActive ? 'badge--ok' : 'badge--muted' ?>"><?= $catActive ? 'Visible on site' : 'Hidden from site' ?></span>
                <?php endif; ?>
            </div>
            <h2 class="svc-admin-detail-hero__title">
                <?= e($selectedCategory['name']) ?>
                <?php if ($catAmharic !== ''): ?>
                    <span class="svc-admin-detail-hero__am" lang="am"><?= e($catAmharic) ?></span>
                <?php endif; ?>
            </h2>
            <?php if ($catDesc !== ''): ?>
                <p class="svc-admin-detail-hero__desc"><?= e($catDesc) ?></p>
            <?php elseif ($isUncategorised): ?>
                <p class="svc-admin-detail-hero__desc">These services have no category yet — assign one so they appear inside the right card on the public Services page.</p>
            <?php else: ?>
                <p class="svc-admin-detail-hero__desc">Manage every service inside “<?= e($selectedCategory['name']) ?>”. Customers see them as a clean list after tapping the card.</p>
            <?php endif; ?>
            <?php if ($isUncategorised === false): ?>
                <div class="svc-admin-detail-hero__actions">
                    <a class="btn btn--ghost btn--sm" href="/admin/categories?edit=<?= $catId ?>">Edit category</a>
                    <a class="btn btn--ghost btn--sm" href="/admin/services?category=<?= $catId ?>">Refresh</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="toolbar toolbar--spaced">
        <span class="toolbar__count">
            <?php if ($selectedServices === []): ?>
                No services in this category yet
            <?php else: ?>
                <?= count($selectedServices) ?> service<?= count($selectedServices) === 1 ? '' : 's' ?> in “<?= e($selectedCategory['name']) ?>”
            <?php endif; ?>
        </span>
        <span class="toolbar__actions">
            <a class="btn btn--primary" href="<?= e($addHref) ?>">Add a service<?= $isUncategorised ? '' : ' in ' . $selectedCategory['name'] ?></a>
        </span>
    </div>

    <?php if ($selectedServices === []): ?>
        <section class="card empty-state">
            <h2 class="card__title">Nothing here yet</h2>
            <p class="card__sub">Add the first service for this category — it appears on the public page as soon as it is visible.</p>
            <a class="btn btn--primary" href="<?= e($addHref) ?>">Add a service</a>
        </section>
    <?php else: ?>
        <section class="card card--flush">
            <table class="table">
                <thead>
                    <tr>
                        <th class="table__order" aria-label="Order"></th>
                        <th>Service</th>
                        <th class="table__num">Price</th>
                        <th class="table__num">Time</th>
                        <th>Status</th>
                        <th class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($selectedServices as $service): ?>
                    <?php
                    $id = (int) $service['id'];
                    $active = (int) ($service['is_active'] ?? 0) === 1;
                    $image = is_string($service['image_path'] ?? null) ? $service['image_path'] : null;
                    ?>
                    <tr<?= ($highlight ?? 0) === $id ? ' class="is-highlighted"' : '' ?>>
                        <td class="table__order">
                            <form method="post" action="/admin/services/<?= $id ?>/move" class="order-buttons">
                                <?= csrf_field() ?>
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="icon-btn" title="Move up">↑</button>
                            </form>
                            <form method="post" action="/admin/services/<?= $id ?>/move" class="order-buttons">
                                <?= csrf_field() ?>
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="icon-btn" title="Move down">↓</button>
                            </form>
                        </td>
                        <td>
                            <div class="cell-media">
                                <?php if ($image !== null): ?>
                                    <img class="thumb" src="<?= e($uploads->url($image)) ?>" alt="" width="44" height="44">
                                <?php else: ?>
                                    <span class="thumb thumb--empty" aria-hidden="true"></span>
                                <?php endif; ?>
                                <div>
                                    <a class="cell-title" href="/admin/services/<?= $id ?>/edit"><?= e($service['name']) ?></a>
                                    <?php if (!empty($service['short_description'])): ?>
                                        <p class="cell-sub"><?= e($service['short_description']) ?></p>
                                    <?php endif; ?>
                                    <?php if ((int) ($service['is_featured'] ?? 0) === 1): ?>
                                        <span class="pill pill--accent">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="table__num"><?= e($money($service['price_cents'] ?? null)) ?></td>
                        <td class="table__num"><?= !empty($service['duration_minutes']) ? e((string) $service['duration_minutes']) . ' min' : '—' ?></td>
                        <td>
                            <form method="post" action="/admin/services/<?= $id ?>/toggle">
                                <?= csrf_field() ?>
                                <button type="submit" class="badge <?= $active ? 'badge--ok' : 'badge--muted' ?>">
                                    <?= $active ? 'Visible' : 'Hidden' ?>
                                </button>
                            </form>
                        </td>
                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="/admin/services/<?= $id ?>/edit">Edit</a>
                            <form method="post" action="/admin/services/<?= $id ?>/delete" onsubmit="return confirm('Delete “<?= e($service['name']) ?>”? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
<?php endif; ?>
