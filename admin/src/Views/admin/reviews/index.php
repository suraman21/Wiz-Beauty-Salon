<?php
/**
 * Reviews: pending first, then published.
 *
 * @var list<array<string, mixed>> $reviews
 * @var int $pending
 * @var list<array<string, mixed>> $services
 * @var string|null $error
 */

$stars = static function (int $rating): string {
    $rating = max(0, min(5, $rating));

    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
};
?>

<div class="toolbar">
    <span class="toolbar__count">
        <?php if ($pending > 0): ?>
            <?= $pending ?> waiting for approval
        <?php else: ?>
            Nothing waiting · <?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?> total
        <?php endif; ?>
    </span>
</div>

<section class="card">
    <details class="disclosure" <?= $reviews === [] ? 'open' : '' ?>>
        <summary class="disclosure__summary">Add a review the salon received in person</summary>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/admin/reviews" class="form-inline" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field-row">
                <div class="field">
                    <label for="customer_name">Customer name</label>
                    <input type="text" id="customer_name" name="customer_name" required maxlength="120" placeholder="e.g. Hanna T.">
                </div>

                <div class="field">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="service_id">Service <span class="field__hint">optional</span></label>
                    <select id="service_id" name="service_id">
                        <option value="">—</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="body">What they said</label>
                <textarea id="body" name="body" rows="3" required maxlength="1000"
                          placeholder="In their own words."></textarea>
            </div>

            <div class="form-actions form-actions--start">
                <button type="submit" class="btn btn--primary">Save review</button>
            </div>
        </form>
    </details>
</section>

<?php if ($reviews === []): ?>
    <section class="card empty-state">
        <h2 class="card__title">No reviews yet</h2>
        <p class="card__sub">Reviews from the website arrive here for approval.</p>
    </section>
<?php else: ?>
    <ul class="review-list">
        <?php foreach ($reviews as $review): ?>
            <?php
            $id = (int) $review['id'];
            $approved = (int) ($review['is_approved'] ?? 0) === 1;
            ?>
            <li class="card review<?= $approved ? '' : ' review--pending' ?>">
                <div class="review__head">
                    <div>
                        <p class="review__name"><?= e($review['customer_name']) ?></p>
                        <p class="review__meta">
                            <span class="review__stars" aria-label="<?= (int) $review['rating'] ?> out of 5"><?= e($stars((int) $review['rating'])) ?></span>
                            <?php if (!empty($review['service_name'])): ?>
                                · <?= e($review['service_name']) ?>
                            <?php endif; ?>
                            · <?= e(full_date(isset($review['created_at']) ? (string) $review['created_at'] : null)) ?>
                        </p>
                    </div>

                    <span class="badge <?= $approved ? 'badge--ok' : 'badge--warn' ?>">
                        <?= $approved ? 'Published' : 'Waiting' ?>
                    </span>
                </div>

                <p class="review__body">“<?= e($review['body']) ?>”</p>

                <div class="review__actions">
                    <form method="post" action="/admin/reviews/<?= $id ?>/approve">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn <?= $approved ? 'btn--ghost' : 'btn--primary' ?> btn--sm">
                            <?= $approved ? 'Hide from website' : 'Approve and publish' ?>
                        </button>
                    </form>

                    <form method="post" action="/admin/reviews/<?= $id ?>/delete"
                          onsubmit="return confirm('Delete this review permanently?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php
    $path = '/admin/reviews';
    $query = [];
    require __DIR__ . '/../../partials/pagination.php';
    ?>
<?php endif; ?>
