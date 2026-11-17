<?php
/**
 * Bookings list, grouped by day.
 *
 * @var list<array{date: string, label: string, items: list<array<string, mixed>>}> $groups
 * @var string $status
 * @var string $range
 * @var int $pending
 */

use Wiz\Domain\Enums\BookingStatus;

// "09:00:00" → "09:00": the stored Gregorian time, untouched. Every reading in
// the row is derived from this, never from another label — converting a label
// twice turns 9 o'clock into 3 o'clock at night.
$short = static fn (mixed $time): string => substr((string) $time, 0, 5);

$chip = static function (array $query, string $label, bool $active): string {
    $href = '/admin/bookings?' . http_build_query($query);

    return sprintf(
        '<a class="filter-chip%s" href="%s">%s</a>',
        $active ? ' is-active' : '',
        e($href),
        e($label),
    );
};
?>

<?php if ($pending > 0): ?>
    <div class="notice">
        <strong><?= $pending ?></strong> booking<?= $pending === 1 ? '' : 's' ?> still waiting for a yes.
        <a href="/admin/bookings?status=requested&amp;range=upcoming">Show them</a>
    </div>
<?php endif; ?>

<div class="toolbar toolbar--filters">
    <span class="filters">
        <?= $chip(['status' => 'open', 'range' => $range], 'Open', $status === 'open') ?>
        <?= $chip(['status' => 'requested', 'range' => $range], 'New requests', $status === 'requested') ?>
        <?= $chip(['status' => 'confirmed', 'range' => $range], 'Confirmed', $status === 'confirmed') ?>
        <?= $chip(['status' => 'all', 'range' => $range], 'All', $status === 'all') ?>
    </span>

    <span class="filters filters--right">
        <?= $chip(['status' => $status, 'range' => 'today'], 'Today', $range === 'today') ?>
        <?= $chip(['status' => $status, 'range' => 'upcoming'], 'Upcoming', $range === 'upcoming') ?>
        <?= $chip(['status' => $status, 'range' => 'past'], 'Past', $range === 'past') ?>
    </span>

    <a class="btn btn--primary" href="/admin/bookings/new">New booking</a>
</div>

<?php if ($groups === []): ?>
    <section class="card empty-state">
        <h2 class="card__title">Nothing here</h2>
        <p class="card__sub">No bookings match this filter. Try “All”, or add one for a customer on the phone.</p>
        <a class="btn btn--primary" href="/admin/bookings/new">New booking</a>
    </section>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <section class="day-group">
            <h2 class="day-head">
                <span><?= e($group['label']) ?></span>
                <span class="day-head__count"><?= count($group['items']) ?> appointment<?= count($group['items']) === 1 ? '' : 's' ?></span>
            </h2>

            <div class="card card--flush">
                <ul class="booking-list">
                    <?php foreach ($group['items'] as $booking): ?>
                        <?php
                        $state = BookingStatus::tryFrom((string) $booking['status']);
                        $id = (int) $booking['id'];
                        ?>
                        <li class="booking-row">
                            <?php
                            $startShort = $short($booking['start_time']);
                            $endShort = $short($booking['end_time']);
                            ?>
                            <span class="booking-row__time" title="<?= e(ethiopian_clock($startShort)) ?> – <?= e(ethiopian_clock($endShort)) ?>">
                                <?= e(ethiopian_clock_label($startShort)) ?>
                                <em><?= e(ethiopian_clock_label($endShort)) ?></em>
                                <span class="booking-row__time-gc">
                                    <?= e(\Wiz\Support\EthiopianDate::gregorianClockLabel($startShort)) ?> –
                                    <?= e(\Wiz\Support\EthiopianDate::gregorianClockLabel($endShort)) ?>
                                </span>
                            </span>

                            <span class="booking-row__who">
                                <a class="cell-title" href="/admin/bookings/<?= $id ?>"><?= e($booking['customer_name']) ?></a>
                                <span class="cell-sub"><?= e($booking['customer_phone']) ?></span>
                            </span>

                            <span class="booking-row__service">
                                <?= e($booking['service_name']) ?>
                                <?php if (!empty($booking['is_undecided'])): ?>
                                    <span class="cell-sub" style="color:#7c2d4b;font-weight:600">Consultation · 60 min · To be confirmed</span>
                                <?php elseif (!empty($booking['service_ids'])): ?>
                                    <?php
                                    $ids = json_decode((string) $booking['service_ids'], true);
                                    $count = is_array($ids) ? count($ids) : 0;
                                    $dur = isset($booking['duration_minutes']) && $booking['duration_minutes'] !== null ? (int) $booking['duration_minutes'] : 0;
                                    ?>
                                    <?php if ($count > 1): ?>
                                        <span class="cell-sub"><?= e($count) ?> services<?= $dur ? ' · ' . e($dur) . ' min' : '' ?></span>
                                    <?php elseif ($dur): ?>
                                        <span class="cell-sub"><?= e($dur) ?> min</span>
                                    <?php endif; ?>
                                <?php elseif (isset($booking['duration_minutes']) && $booking['duration_minutes'] !== null): ?>
                                    <span class="cell-sub"><?= e((int) $booking['duration_minutes']) ?> min</span>
                                <?php endif; ?>
                                <?php if ($booking['price_cents'] !== null): ?>
                                    <span class="cell-sub">ETB <?= e(number_format(((int) $booking['price_cents']) / 100, 0)) ?></span>
                                <?php elseif (!empty($booking['is_undecided'])): ?>
                                    <span class="cell-sub">Price to be confirmed</span>
                                <?php endif; ?>
                            </span>

                            <span class="booking-row__status">
                                <span class="<?= e($state?->badgeClass() ?? 'badge badge--grey') ?>">
                                    <?= e($state?->label() ?? (string) $booking['status']) ?>
                                </span>
                            </span>

                            <span class="booking-row__actions">
                                <a class="btn btn--ghost btn--sm" href="/admin/bookings/<?= $id ?>">Open</a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endforeach; ?>

    <?php
    $path = '/admin/bookings';
    require __DIR__ . '/../../partials/pagination.php';
    ?>
<?php endif; ?>
