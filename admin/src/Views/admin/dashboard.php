<?php
/**
 * Dashboard.
 *
 * The screen answers three questions in order: what needs me now, who is
 * coming in today, and what changed recently. Every card links to the list it
 * counts, so a number is never a dead end.
 *
 * @var array<string, mixed> $user
 * @var array<string, int|null> $counts
 * @var list<array{count: int, label: string, href: string, action: string}> $attention
 * @var list<array<string, mixed>> $today
 * @var array{total: int, admins: int} $team
 * @var list<array<string, mixed>> $activity
 */

use Wiz\Domain\Enums\BookingStatus;

$cards = [
    ['label' => "Today's bookings", 'key' => 'bookings_today', 'hint' => 'Appointments booked for today', 'href' => '/admin/bookings?range=today'],
    ['label' => 'Awaiting approval', 'key' => 'bookings_pending', 'hint' => 'Requests not confirmed yet', 'href' => '/admin/bookings?status=requested&range=upcoming'],
    ['label' => 'Unread messages', 'key' => 'messages_unread', 'hint' => 'New enquiries from the website', 'href' => '/admin/messages?filter=unread'],
    ['label' => 'Reviews to approve', 'key' => 'reviews_pending', 'hint' => 'Waiting before they appear', 'href' => '/admin/reviews'],
    ['label' => 'Active services', 'key' => 'services_active', 'hint' => 'Currently offered on the website', 'href' => '/admin/categories'],
];
?>

<?php if ($attention !== []): ?>
    <section class="card attention" aria-labelledby="attention-heading">
        <header class="card__head">
            <h2 class="card__title" id="attention-heading">Needs your attention</h2>
            <p class="card__sub">The things a customer is waiting on.</p>
        </header>

        <ul class="attention__list">
            <?php foreach ($attention as $item): ?>
                <li class="attention__item">
                    <span class="attention__count"><?= (int) $item['count'] ?></span>
                    <span class="attention__label"><?= e($item['label']) ?></span>
                    <a class="btn btn--ghost btn--sm" href="<?= e($item['href']) ?>"><?= e($item['action']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="stat-grid">
    <a class="stat stat--dark" href="/admin/users">
        <p class="stat__label">Team members</p>
        <p class="stat__value"><?= e((string) $team['total']) ?></p>
        <p class="stat__hint"><?= e((string) $team['admins']) ?> administrator<?= $team['admins'] === 1 ? '' : 's' ?></p>
    </a>

    <?php foreach ($cards as $card): ?>
        <?php $value = $counts[$card['key']] ?? null; ?>
        <a class="stat" href="<?= e($card['href']) ?>">
            <p class="stat__label"><?= e($card['label']) ?></p>
            <?php if ($value === null): ?>
                <p class="stat__value stat__value--empty">—</p>
                <p class="stat__hint">Could not be read · see logs</p>
            <?php else: ?>
                <p class="stat__value"><?= e((string) $value) ?></p>
                <p class="stat__hint"><?= e($card['hint']) ?></p>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</section>

<div class="columns">
    <section class="card">
        <header class="card__head">
            <h2 class="card__title">Today’s schedule</h2>
            <p class="card__sub"><?= e(date('l j F')) ?> · first six appointments</p>
        </header>

        <?php if ($today === []): ?>
            <div class="empty-state empty-state--compact">
                <p class="empty-state__title">Nothing booked today</p>
                <p class="empty-state__text">When someone books online, they appear here.</p>
                <a class="btn btn--primary btn--sm" href="/admin/bookings/new">Add a booking</a>
            </div>
        <?php else: ?>
            <ul class="timeline timeline--schedule">
                <?php foreach ($today as $appointment): ?>
                    <?php $state = BookingStatus::tryFrom((string) $appointment['status']); ?>
                    <li class="timeline__item">
                        <span class="schedule__time"><?= e(substr((string) $appointment['start_time'], 0, 5)) ?></span>
                        <div class="schedule__body">
                            <p class="timeline__text">
                                <a href="/admin/bookings/<?= (int) $appointment['id'] ?>"><?= e($appointment['customer_name']) ?></a>
                            </p>
                            <p class="timeline__time">
                                <?= e($appointment['service_name']) ?>
                                <span class="badge <?= e($state?->badgeClass() ?? 'badge badge--grey') ?>">
                                    <?= e($state?->label() ?? (string) $appointment['status']) ?>
                                </span>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="card__note">
                <a href="/admin/bookings?range=today">See the full day</a>
            </p>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head">
            <h2 class="card__title">Recent activity</h2>
            <p class="card__sub">Sign-ins and changes made in the panel.</p>
        </header>

        <?php if ($activity === []): ?>
            <p class="empty">Nothing recorded yet. Activity appears here after the first sign-in.</p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($activity as $entry): ?>
                    <li class="timeline__item">
                        <span class="timeline__dot" aria-hidden="true"></span>
                        <div>
                            <p class="timeline__text">
                                <strong><?= e($entry['user_name'] ?? 'System') ?></strong>
                                <?= e(activity_label((string) $entry['action'])) ?>
                            </p>
                            <p class="timeline__time"><?= e(full_date(isset($entry['created_at']) ? (string) $entry['created_at'] : null)) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <p class="card__note"><a href="/admin/activity">See the full audit trail</a></p>
        <?php endif; ?>
    </section>
</div>
