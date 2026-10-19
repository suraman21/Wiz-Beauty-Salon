<?php
/**
 * Services list.
 *
 * @var list<array<string, mixed>> $services
 * @var list<array<string, mixed>> $categories
 * @var \Wiz\Services\Uploader $uploads
 */

$money = static function (mixed $cents): string {
    if ($cents === null || $cents === '') {
        return '—';
    }

    return 'ETB ' . number_format(((int) $cents) / 100, 0);
};
?>

<div class="toolbar">
    <span class="toolbar__count">
        <?= count($services) ?> service<?= count($services) === 1 ? '' : 's' ?>
        <?php if ($categories !== []): ?>
            in <?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?>
        <?php endif; ?>
    </span>

    <span class="toolbar__actions">
        <a class="btn btn--ghost" href="/admin/categories">Categories</a>
        <a class="btn btn--primary" href="/admin/services/new">Add a service</a>
    </span>
</div>

<?php if ($services === []): ?>
    <section class="card empty-state">
        <h2 class="card__title">No services yet</h2>
        <p class="card__sub">Add the first one — it appears on the services page as soon as it is saved.</p>
        <a class="btn btn--primary" href="/admin/services/new">Add a service</a>
    </section>
<?php else: ?>
    <section class="card card--flush">
        <table class="table">
            <thead>
                <tr>
                    <th class="table__order" aria-label="Order"></th>
                    <th>Service</th>
                    <th>Category</th>
                    <th class="table__num">Price</th>
                    <th class="table__num">Time</th>
                    <th>Status</th>
                    <th class="table__actions"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $service): ?>
                <?php
                $id = (int) $service['id'];
                $active = (int) ($service['is_active'] ?? 0) === 1;
                $image = is_string($service['image_path'] ?? null) ? $service['image_path'] : null;
                ?>
                <tr>
                    <td class="table__order">
                        <form method="post" action="/admin/services/<?= $id ?>/move" class="order-buttons">
                            <?= csrf_field() ?>
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="icon-btn" title="Move up" aria-label="Move <?= e($service['name']) ?> up">↑</button>
                        </form>
                        <form method="post" action="/admin/services/<?= $id ?>/move" class="order-buttons">
                            <?= csrf_field() ?>
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="icon-btn" title="Move down" aria-label="Move <?= e($service['name']) ?> down">↓</button>
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

                    <td><?= e($service['category_name'] ?? 'Uncategorised') ?></td>
                    <td class="table__num"><?= e($money($service['price_cents'] ?? null)) ?></td>
                    <td class="table__num">
                        <?= !empty($service['duration_minutes']) ? e((string) $service['duration_minutes']) . ' min' : '—' ?>
                    </td>

                    <td>
                        <form method="post" action="/admin/services/<?= $id ?>/toggle">
                            <?= csrf_field() ?>
                            <button type="submit" class="badge <?= $active ? 'badge--ok' : 'badge--muted' ?>" title="Click to <?= $active ? 'hide' : 'show' ?> on the website">
                                <?= $active ? 'Visible' : 'Hidden' ?>
                            </button>
                        </form>
                    </td>

                    <td class="table__actions">
                        <a class="btn btn--ghost btn--sm" href="/admin/services/<?= $id ?>/edit">Edit</a>
                        <form method="post" action="/admin/services/<?= $id ?>/delete"
                              onsubmit="return confirm('Delete “<?= e($service['name']) ?>”? This cannot be undone.');">
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
