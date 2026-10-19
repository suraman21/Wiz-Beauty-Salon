<?php
/**
 * New booking.
 *
 * Two steps on one page: pick the service and the day, then pick a time from
 * the times that are actually free. Staff never have to guess whether a slot
 * is taken.
 *
 * @var list<array<string, mixed>> $services
 * @var string $date
 * @var int $serviceId
 * @var list<string> $slots
 * @var array{open: bool, reason: string|null} $dayStatus
 * @var string|null $error
 */

$chosen = null;

foreach ($services as $service) {
    if ((int) $service['id'] === $serviceId) {
        $chosen = $service;
    }
}
?>

<?php if (!empty($error)): ?>
    <div class="alert alert--error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="card">
    <h2 class="card__title">1. What and when</h2>
    <p class="card__sub">Choose the service and the day, then pick a free time below.</p>

    <form method="get" action="/admin/bookings/new" class="picker-form">
        <div class="field">
            <label for="service_id">Service</label>
            <select id="service_id" name="service_id" required>
                <option value="">Choose a service…</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= (int) $service['id'] ?>" <?= (int) $service['id'] === $serviceId ? 'selected' : '' ?>>
                        <?= e($service['name']) ?>
                        <?php if (!empty($service['duration_minutes'])): ?>
                            · <?= (int) $service['duration_minutes'] ?> min
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="date">Day</label>
            <input type="date" id="date" name="date" value="<?= e($date) ?>" min="<?= e(date('Y-m-d')) ?>" required>
        </div>

        <div class="field picker-form__submit">
            <button type="submit" class="btn btn--ghost">Show free times</button>
        </div>
    </form>

    <?php if ($services === []): ?>
        <p class="empty">There are no active services yet. <a href="/admin/services/new">Add a service first</a>.</p>
    <?php endif; ?>
</section>

<?php if ($serviceId > 0): ?>
    <section class="card">
        <h2 class="card__title">2. Pick a time</h2>

        <?php if (!$dayStatus['open']): ?>
            <p class="empty"><?= e((string) $dayStatus['reason']) ?></p>
        <?php elseif ($slots === []): ?>
            <p class="empty">Every slot that day is taken. Try another day.</p>
        <?php else: ?>
            <form method="post" action="/admin/bookings" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">

                <fieldset class="slot-fieldset">
                    <legend class="sr-only">Available times</legend>
                    <div class="slot-grid">
                        <?php foreach ($slots as $index => $slot): ?>
                            <label class="slot">
                                <input type="radio" name="time" value="<?= e($slot) ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                <span><?= e($slot) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <h3 class="card__title form-subhead">3. Customer</h3>

                <div class="field-row">
                    <div class="field">
                        <label for="customer_name">Name</label>
                        <input type="text" id="customer_name" name="customer_name" required maxlength="120" placeholder="Full name">
                    </div>

                    <div class="field">
                        <label for="customer_phone">Phone</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required maxlength="40" placeholder="09… or +251…">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="customer_email">Email <span class="field__hint">optional</span></label>
                        <input type="email" id="customer_email" name="customer_email" maxlength="190" placeholder="For the confirmation email">
                    </div>

                    <div class="field">
                        <label for="notes">Notes <span class="field__hint">optional</span></label>
                        <input type="text" id="notes" name="notes" maxlength="500" placeholder="Allergies, preferences…">
                    </div>
                </div>

                <div class="form-actions">
                    <a class="btn btn--ghost" href="/admin/bookings">Cancel</a>
                    <button type="submit" class="btn btn--primary">
                        Save booking<?= $chosen !== null ? ' · ' . e($chosen['name']) : '' ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
