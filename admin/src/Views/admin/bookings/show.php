<?php
/**
 * One booking: what was asked for, what can be done next, and what happened.
 *
 * @var array<string, mixed> $booking
 * @var \Wiz\Domain\Enums\BookingStatus|null $status
 * @var list<\Wiz\Domain\Enums\BookingStatus> $transitions
 * @var list<array<string, mixed>> $history
 * @var list<string> $slots
 * @var string $slotDate
 * @var string|null $error
 */

use Wiz\Domain\Enums\BookingStatus;

$short = static fn (mixed $time): string => substr((string) $time, 0, 5);
$id = (int) $booking['id'];
$canMove = $status !== null && $status->occupiesSlot();
$date = (string) $booking['booking_date'];

$actionStyle = static function (BookingStatus $target): string {
    return match ($target) {
        BookingStatus::Confirmed => 'btn btn--primary',
        BookingStatus::Completed => 'btn btn--primary',
        default => 'btn btn--ghost',
    };
};
?>

<?php if (!empty($error)): ?>
    <div class="alert alert--error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="columns columns--detail">
    <section class="card">
        <div class="detail-head">
            <div>
                <p class="detail-when">
                    <?= e(date('l j F Y', (int) strtotime($date))) ?>
                    <strong><?= e($short($booking['start_time'])) ?>–<?= e($short($booking['end_time'])) ?></strong>
                </p>
                <p class="detail-service"><?= e($booking['service_name']) ?></p>
            </div>
            <span class="<?= e($status?->badgeClass() ?? 'badge badge--grey') ?>">
                <?= e($status?->label() ?? (string) $booking['status']) ?>
            </span>
        </div>

        <dl class="detail-grid">
            <div>
                <dt>Customer</dt>
                <dd><?= e($booking['customer_name']) ?></dd>
            </div>
            <div>
                <dt>Phone</dt>
                <dd><a href="tel:<?= e($booking['customer_phone']) ?>"><?= e($booking['customer_phone']) ?></a></dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>
                    <?php if (!empty($booking['customer_email'])): ?>
                        <a href="mailto:<?= e($booking['customer_email']) ?>"><?= e($booking['customer_email']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Price</dt>
                <dd><?= $booking['price_cents'] === null ? '—' : 'ETB ' . e(number_format(((int) $booking['price_cents']) / 100, 0)) ?></dd>
            </div>
            <div>
                <dt>Reference</dt>
                <dd><code><?= e($booking['reference']) ?></code></dd>
            </div>
            <div>
                <dt>Requested</dt>
                <dd><?= e(full_date(isset($booking['created_at']) ? (string) $booking['created_at'] : null)) ?></dd>
            </div>
        </dl>

        <?php if (!empty($booking['notes'])): ?>
            <div class="note-block">
                <p class="note-block__label">Customer note</p>
                <p><?= e($booking['notes']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($booking['admin_notes'])): ?>
            <div class="note-block">
                <p class="note-block__label">Staff note</p>
                <p><?= e($booking['admin_notes']) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($transitions !== []): ?>
            <div class="action-bar">
                <?php foreach ($transitions as $target): ?>
                    <form method="post" action="/admin/bookings/<?= $id ?>/status"
                          <?= in_array($target, [BookingStatus::Cancelled, BookingStatus::Declined], true) ? 'onsubmit="return confirm(\'This frees the time for someone else. Continue?\');"' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= e($target->value) ?>">
                        <button type="submit" class="<?= e($actionStyle($target)) ?>">
                            <?= e($target->label()) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty">This booking is finished — no further changes.</p>
        <?php endif; ?>
    </section>

    <div class="side-stack">
        <?php if ($canMove): ?>
            <section class="card">
                <h2 class="card__title">Move to another time</h2>
                <p class="card__sub">The current time is freed as soon as the new one is saved.</p>

                <form method="get" action="/admin/bookings/<?= $id ?>" class="move-form">
                    <div class="field">
                        <label for="date">Day</label>
                        <input type="date" id="date" name="date" value="<?= e($slotDate !== '' ? $slotDate : $date) ?>" required>
                    </div>
                    <button type="submit" class="btn btn--ghost btn--sm">Show times</button>
                </form>

                <?php if ($slotDate !== ''): ?>
                    <?php if ($slots === []): ?>
                        <p class="empty">No free times that day.</p>
                    <?php else: ?>
                        <form method="post" action="/admin/bookings/<?= $id ?>/reschedule">
                            <?= csrf_field() ?>
                            <input type="hidden" name="date" value="<?= e($slotDate) ?>">

                            <div class="slot-grid slot-grid--compact">
                                <?php foreach ($slots as $index => $slot): ?>
                                    <label class="slot">
                                        <input type="radio" name="time" value="<?= e($slot) ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                        <span><?= e($slot) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" class="btn btn--primary btn--block">Move booking</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2 class="card__title">Staff note</h2>
            <form method="post" action="/admin/bookings/<?= $id ?>/notes">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="admin_notes" class="sr-only">Staff note</label>
                    <textarea id="admin_notes" name="admin_notes" rows="3" maxlength="500"
                              placeholder="Only staff see this."><?= e($booking['admin_notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn--ghost btn--sm">Save note</button>
            </form>
        </section>

        <section class="card">
            <h2 class="card__title">History</h2>
            <ul class="history">
                <?php foreach ($history as $entry): ?>
                    <li>
                        <span class="history__dot" aria-hidden="true"></span>
                        <div>
                            <p class="history__text">
                                <?php if (!empty($entry['from_status']) && $entry['from_status'] !== $entry['to_status']): ?>
                                    <?= e(ucfirst(str_replace('_', ' ', (string) $entry['from_status']))) ?> →
                                <?php endif; ?>
                                <strong><?= e(ucfirst(str_replace('_', ' ', (string) $entry['to_status']))) ?></strong>
                            </p>
                            <?php if (!empty($entry['note'])): ?>
                                <p class="history__note"><?= e($entry['note']) ?></p>
                            <?php endif; ?>
                            <p class="history__meta">
                                <?= e(full_date(isset($entry['created_at']) ? (string) $entry['created_at'] : null)) ?>
                                <?= !empty($entry['user_name']) ? ' · ' . e($entry['user_name']) : '' ?>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
