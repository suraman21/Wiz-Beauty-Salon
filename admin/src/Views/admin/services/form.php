<?php
/**
 * Add / edit a service.
 *
 * @var array<string, mixed>|null $service
 * @var list<array<string, mixed>> $categories
 * @var \Wiz\Services\Uploader $uploads
 * @var string|null $error
 */

$isEdit = $service !== null;
$action = $isEdit ? '/admin/services/' . (int) $service['id'] : '/admin/services';
$image = $isEdit && is_string($service['image_path'] ?? null) ? $service['image_path'] : null;
$price = $isEdit && $service['price_cents'] !== null
    ? rtrim(rtrim(number_format(((int) $service['price_cents']) / 100, 2, '.', ''), '0'), '.')
    : '';
?>

<div class="form-page">
    <section class="card">
        <?php if (!empty($error)): ?>
            <div class="alert alert--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="name">Service name</label>
                <input type="text" id="name" name="name" required maxlength="160"
                       value="<?= e($service['name'] ?? '') ?>"
                       placeholder="e.g. Silk press">
            </div>

            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">No category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (int) ($service['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="short_description">Short description</label>
                <input type="text" id="short_description" name="short_description" maxlength="255"
                       value="<?= e($service['short_description'] ?? '') ?>"
                       placeholder="One line, shown under the name in the list">
            </div>

            <div class="field">
                <label for="description">Full description <span class="field__hint">optional</span></label>
                <textarea id="description" name="description" rows="5" maxlength="4000"
                          placeholder="What the service includes, and who it suits."><?= e($service['description'] ?? '') ?></textarea>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="price">Price in ETB <span class="field__hint">leave empty for “on request”</span></label>
                    <input type="text" id="price" name="price" inputmode="decimal"
                           value="<?= e($price) ?>" placeholder="1200">
                </div>

                <div class="field">
                    <label for="duration">Duration in minutes</label>
                    <input type="text" id="duration" name="duration" inputmode="numeric"
                           value="<?= e((string) ($service['duration_minutes'] ?? '')) ?>" placeholder="60">
                </div>
            </div>

            <div class="field">
                <label for="image">Photo <span class="field__hint">JPG, PNG or WebP, up to 5 MB</span></label>

                <?php if ($image !== null): ?>
                    <div class="current-image">
                        <img src="<?= e($uploads->url($image)) ?>" alt="" width="72" height="72">
                        <span>Current photo — choose a new file to replace it.</span>
                    </div>
                <?php endif; ?>

                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="checks">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($service === null || (int) ($service['is_active'] ?? 0) === 1) ? 'checked' : '' ?>>
                    Visible on the website
                </label>

                <label class="check">
                    <input type="checkbox" name="is_featured" value="1" <?= (int) ($service['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Feature this service
                </label>
            </div>

            <div class="form-actions">
                <a class="btn btn--ghost" href="/admin/services">Cancel</a>
                <button type="submit" class="btn btn--primary"><?= $isEdit ? 'Save changes' : 'Add service' ?></button>
            </div>
        </form>
    </section>

    <?php if ($isEdit): ?>
        <aside class="side-note">
            <h2 class="side-note__title">About this service</h2>
            <p>Its web address is <code>/services/<?= e((string) $service['slug']) ?></code>. It was set when the service was created and does not change when you rename it, so existing links keep working.</p>
        </aside>
    <?php endif; ?>
</div>
