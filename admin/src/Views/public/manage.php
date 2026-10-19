<?php
/**
 * The page behind the link we email a customer.
 *
 * No account, no password: the signed link is the key. Everything here is
 * written for somebody standing in a queue on a phone.
 *
 * @var array<string, mixed> $booking
 * @var string $token
 * @var list<array{date: string, open: bool, reason: string|null, slots: list<string>}> $days
 * @var string|null $error
 * @var string|null $success
 */

use Wiz\Domain\Enums\BookingStatus;

$status = BookingStatus::tryFrom((string) $booking['status']);
$date = strtotime((string) $booking['booking_date']);
$start = substr((string) $booking['start_time'], 0, 5);
$end = substr((string) $booking['end_time'], 0, 5);
$canManage = $status !== null && in_array($status, [BookingStatus::Requested, BookingStatus::Confirmed], true);
?>

<section class="card card--auth card--wide">
    <p class="overline-soft"><?= e((string) $booking['reference']) ?></p>
    <h1 class="card__title">
        <?= e($status?->label() ?? ucfirst((string) $booking['status'])) ?>
    </h1>
    <p class="card__sub">
        <?= e(date('l j F Y', $date === false ? time() : $date)) ?>
        &middot; <?= e($start) ?>–<?= e($end) ?>
        &middot; <?= e($booking['service_name']) ?>
    </p>

    <?php if (!empty($success)): ?>
        <div class="alert alert--success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <dl class="detail-grid detail-grid--tight">
        <div>
            <dt>Name</dt>
            <dd><?= e($booking['customer_name']) ?></dd>
        </div>
        <div>
            <dt>Phone</dt>
            <dd><?= e($booking['customer_phone']) ?></dd>
        </div>
        <?php if (!empty($booking['customer_email'])): ?>
            <div>
                <dt>Email</dt>
                <dd><?= e($booking['customer_email']) ?></dd>
            </div>
        <?php endif; ?>
    </dl>

    <?php if ($canManage): ?>
        <div class="form-subhead"><h2 class="card__title">Move it</h2></div>
        <p class="card__sub">Pick another day and time. The current one is freed as soon as you choose.</p>

        <?php $first = true; ?>
        <?php foreach ($days as $day): ?>
            <?php if ($day['slots'] === []) { continue; } ?>
            <form method="post" action="/booking/manage/<?= e($token) ?>/reschedule" class="manage-day">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= e($day['date']) ?>">

                <p class="day-head">
                    <?= e(date('l j F', (int) strtotime($day['date']))) ?>
                    <span class="day-head__count"><?= count($day['slots']) ?> times</span>
                </p>

                <div class="slot-grid slot-grid--compact">
                    <?php foreach ($day['slots'] as $index => $slot): ?>
                        <label class="slot">
                            <input type="radio" name="time" value="<?= e($slot) ?>"
                                   <?= $first && $index === 0 ? 'checked' : '' ?>>
                            <span><?= e($slot) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn--ghost btn--sm">Move to this time</button>
            </form>
            <?php $first = false; ?>
        <?php endforeach; ?>

        <?php if ($first): ?>
            <p class="empty">No other times are free in the next two weeks. Please call the salon.</p>
        <?php endif; ?>

        <div class="form-subhead"><h2 class="card__title">Cancel it</h2></div>
        <p class="card__sub">The time goes back to the salon. You can always book again.</p>

        <form method="post" action="/booking/manage/<?= e($token) ?>/cancel"
              onsubmit="return confirm('Cancel this appointment?');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost btn--block">Cancel this appointment</button>
        </form>
    <?php else: ?>
        <p class="empty">
            This appointment can no longer be changed here. Call the salon if something is wrong.
        </p>

        <p class="manage-again">
            <a class="btn btn--ghost btn--block" href="/booking">Book another time</a>
        </p>
    <?php endif; ?>
</section>
