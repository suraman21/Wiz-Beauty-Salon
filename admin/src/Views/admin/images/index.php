<?php
/**
 * Website images: one card per place the design shows a photograph.
 *
 * @var list<array<string, mixed>> $images
 * @var \Wiz\Services\Uploader $uploads
 * @var int $empty
 * @var string|null $error
 * @var string|null $success
 */

if (!empty($success)): ?>
    <div class="alert alert--success" role="status"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert--error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="card">
    <h2 class="card__title">Where these appear</h2>
    <p class="card__sub">
        Every photograph on the public website comes from this page. Replace one and it changes on
        the next visit — no developer, no deploy. A picture with no file falls back to the design's
        built-in image, so nothing ever breaks.
    </p>
    <?php if ($empty > 0): ?>
        <p class="card__sub"><strong><?= $empty ?></strong> slot<?= $empty === 1 ? ' is' : 's are' ?> still using the built-in image.</p>
    <?php endif; ?>
</section>

<div class="photo-grid">
    <?php foreach ($images as $image): ?>
        <?php
        $id = (int) $image['id'];
        $path = is_string($image['image_path'] ?? null) ? $image['image_path'] : '';
        ?>
        <figure class="photo">
            <div class="photo__frame">
                <?php if ($path !== ''): ?>
                    <img src="<?= e($uploads->url($path)) ?>" alt="<?= e($image['alt'] ?? '') ?>" loading="lazy">
                <?php else: ?>
                    <span class="photo__flag">Built-in image</span>
                <?php endif; ?>
            </div>

            <figcaption class="photo__body">
                <p class="photo__title"><?= e($image['label']) ?></p>

                <form method="post" action="/admin/images/<?= $id ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="field">
                        <label for="image-<?= $id ?>">Photo <span class="field__hint">JPG, PNG or WebP · 5 MB</span></label>
                        <input type="file" id="image-<?= $id ?>" name="image" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="field">
                        <label for="alt-<?= $id ?>">Description <span class="field__hint">for screen readers</span></label>
                        <input type="text" id="alt-<?= $id ?>" name="alt" maxlength="190"
                               value="<?= e($image['alt'] ?? '') ?>" placeholder="e.g. A client's braided hair">
                    </div>

                    <div class="photo__actions">
                        <button type="submit" class="btn btn--primary btn--sm">Save</button>
                    </div>
                </form>
            </figcaption>
        </figure>
    <?php endforeach; ?>
</div>

<?php if ($images === []): ?>
    <section class="card empty-state">
        <h2 class="card__title">No image slots yet</h2>
        <p class="card__sub">Run the database migrations to create them.</p>
    </section>
<?php endif; ?>
