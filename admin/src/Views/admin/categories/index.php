<?php
/**
 * Categories: the list on the left, the add/edit form on the right.
 *
 * @var list<array<string, mixed>> $categories
 * @var array<string, mixed>|null $editing
 * @var string|null $error
 */

$isEdit = $editing !== null;
$action = $isEdit ? '/admin/categories/' . (int) $editing['id'] : '/admin/categories';
?>

<div class="columns columns--form">
    <section class="card card--flush">
        <?php if ($categories === []): ?>
            <div class="empty-state empty-state--plain">
                <h2 class="card__title">No categories yet</h2>
                <p class="card__sub">Add one on the right — “Hair”, “Nails”, “Skin”.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th class="table__order" aria-label="Order"></th>
                        <th>Category</th>
                        <th class="table__num">Services</th>
                        <th>Status</th>
                        <th class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <?php $id = (int) $category['id']; ?>
                    <tr>
                        <td class="table__order">
                            <form method="post" action="/admin/categories/<?= $id ?>/move" class="order-buttons">
                                <?= csrf_field() ?>
                                <input type="hidden" name="direction" value="up">
                                <button class="icon-btn" type="submit" title="Move up" aria-label="Move <?= e($category['name']) ?> up">↑</button>
                            </form>
                            <form method="post" action="/admin/categories/<?= $id ?>/move" class="order-buttons">
                                <?= csrf_field() ?>
                                <input type="hidden" name="direction" value="down">
                                <button class="icon-btn" type="submit" title="Move down" aria-label="Move <?= e($category['name']) ?> down">↓</button>
                            </form>
                        </td>

                        <td>
                            <span class="cell-title"><?= e($category['name']) ?></span>
                            <?php if (!empty($category['description'])): ?>
                                <p class="cell-sub"><?= e($category['description']) ?></p>
                            <?php endif; ?>
                        </td>

                        <td class="table__num"><?= (int) $category['service_count'] ?></td>

                        <td>
                            <span class="badge <?= (int) $category['is_active'] === 1 ? 'badge--ok' : 'badge--muted' ?>">
                                <?= (int) $category['is_active'] === 1 ? 'Visible' : 'Hidden' ?>
                            </span>
                        </td>

                        <td class="table__actions">
                            <a class="btn btn--ghost btn--sm" href="/admin/categories?edit=<?= $id ?>">Edit</a>
                            <form method="post" action="/admin/categories/<?= $id ?>/delete"
                                  onsubmit="return confirm('Delete “<?= e($category['name']) ?>”? Its services stay, but become uncategorised.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2 class="card__title"><?= $isEdit ? 'Edit category' : 'Add a category' ?></h2>
        <p class="card__sub"><?= $isEdit ? 'Changes apply as soon as you save.' : 'Group the services list into sections customers recognise.' ?></p>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required maxlength="120"
                       value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. Hair">
            </div>

            <div class="field">
                <label for="description">Description <span class="field__hint">optional</span></label>
                <input type="text" id="description" name="description" maxlength="255"
                       value="<?= e($editing['description'] ?? '') ?>" placeholder="Shown under the category name">
            </div>

            <label class="check">
                <input type="checkbox" name="is_active" value="1"
                    <?= ($editing === null || (int) ($editing['is_active'] ?? 0) === 1) ? 'checked' : '' ?>>
                Visible on the website
            </label>

            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a class="btn btn--ghost" href="/admin/categories">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn btn--primary"><?= $isEdit ? 'Save' : 'Add category' ?></button>
            </div>
        </form>
    </section>
</div>
