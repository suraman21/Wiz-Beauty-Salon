<?php
/**
 * Gallery: upload at the top, photos below.
 *
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $categories
 * @var \Wiz\Services\Uploader $uploads
 * @var string|null $error
 */

$published = count(array_filter($items, static fn (array $i): bool => (int) ($i['is_published'] ?? 0) === 1));
?>

<section class="card upload-card">
    <div class="upload-card__head">
        <div>
            <h2 class="card__title">Add a photo</h2>
            <p class="card__sub">JPG, PNG or WebP, up to 5 MB. Photos stay hidden until you publish them.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/gallery" enctype="multipart/form-data" class="upload-form">
        <?= csrf_field() ?>

        <div class="field upload-form__file">
            <label for="image">Photo</label>
            <input type="file" id="image" name="image" required accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="field">
            <label for="title">Title <span class="field__hint">optional</span></label>
            <input type="text" id="title" name="title" maxlength="160" placeholder="e.g. Bridal styling">
        </div>

        <div class="field">
            <label for="caption">Caption <span class="field__hint">optional</span></label>
            <input type="text" id="caption" name="caption" maxlength="255" placeholder="Shown under the photo">
        </div>

        <div class="field">
            <label for="category_id">Category <span class="field__hint">optional</span></label>
            <select id="category_id" name="category_id">
                <option value="">No category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="upload-form__submit">
            <label class="check check--tight">
                <input type="checkbox" name="is_published" value="1" checked>
                Publish immediately
            </label>
            <button type="submit" class="btn btn--primary">Upload</button>
        </div>
    </form>
</section>

<div class="toolbar toolbar--spaced">
    <span class="toolbar__count">
        <?= count($items) ?> photo<?= count($items) === 1 ? '' : 's' ?>
        · <?= $published ?> published
    </span>
</div>

<?php if ($items === []): ?>
    <section class="card empty-state">
        <h2 class="card__title">No photos yet</h2>
        <p class="card__sub">Upload the first one above.</p>
    </section>
<?php else: ?>
    <div class="photo-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $id = (int) $item['id'];
            $isPublished = (int) ($item['is_published'] ?? 0) === 1;
            $showOnHome = (int) ($item['show_on_home'] ?? 0) === 1;
            $path = is_string($item['image_path'] ?? null) ? $item['image_path'] : '';
            ?>
            <figure class="photo">
                <div class="photo__frame">
                    <img src="<?= e($uploads->url($path)) ?>" alt="<?= e($item['title'] ?? 'Gallery photo') ?>" loading="lazy">

                    <?php if (!$isPublished): ?>
                        <span class="photo__flag">Hidden</span>
                    <?php endif; ?>
                    <?php if ($showOnHome): ?>
                        <span class="photo__flag photo__flag--home">On home page</span>
                    <?php endif; ?>
                </div>

                <figcaption class="photo__body">
                    <p class="photo__title"><?= e($item['title'] ?? 'Untitled') ?></p>
                    <?php if (!empty($item['caption'])): ?>
                        <p class="photo__caption"><?= e($item['caption']) ?></p>
                    <?php endif; ?>

                    <form method="post" action="/admin/gallery/<?= $id ?>/category" class="photo__category">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="cat-<?= $id ?>">Category for <?= e($item['title'] ?? 'this photo') ?></label>
                        <select id="cat-<?= $id ?>" name="category_id" onchange="this.form.submit()">
                            <option value="">No category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) ($item['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn--ghost btn--sm">Save category</button></noscript>
                    </form>

                    <div class="photo__actions">
                        <form method="post" action="/admin/gallery/<?= $id ?>/toggle">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= $isPublished ? 'Hide' : 'Publish' ?>
                            </button>
                        </form>

                        <form method="post" action="/admin/gallery/<?= $id ?>/home">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--ghost btn--sm">
                                <?= $showOnHome ? 'Remove from home' : 'Show on home page' ?>
                            </button>
                        </form>

                        <form method="post" action="/admin/gallery/<?= $id ?>/move">
                            <?= csrf_field() ?>
                            <input type="hidden" name="direction" value="up">
                            <button class="icon-btn" type="submit" title="Move earlier" aria-label="Move photo up">↑</button>
                        </form>

                        <form method="post" action="/admin/gallery/<?= $id ?>/move">
                            <?= csrf_field() ?>
                            <input type="hidden" name="direction" value="down">
                            <button class="icon-btn" type="submit" title="Move later" aria-label="Move photo down">↓</button>
                        </form>

                        <form method="post" action="/admin/gallery/<?= $id ?>/delete" class="photo__delete"
                              onsubmit="return confirm('Delete this photo? The file is removed from the server too.');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                        </form>
                    </div>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
