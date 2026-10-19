<?php
/**
 * Dashboard.
 *
 * Cards read from the database where the table already exists. Modules whose
 * tables arrive in a later phase show a dash and a "Soon" tag rather than a
 * fake zero, so the numbers on this page are always trustworthy.
 *
 * @var array<string, mixed> $user
 * @var array<string, int|null> $counts
 * @var array{total: int, admins: int} $team
 * @var list<array<string, mixed>> $activity
 */

$cards = [
    ['label' => "Today's bookings", 'key' => 'bookings_today', 'hint' => 'Appointments booked for today'],
    ['label' => 'Awaiting approval', 'key' => 'bookings_pending', 'hint' => 'Requests the salon has not confirmed'],
    ['label' => 'Unread messages', 'key' => 'messages_unread', 'hint' => 'New enquiries from the website'],
    ['label' => 'Reviews to approve', 'key' => 'reviews_pending', 'hint' => 'Waiting before they appear publicly'],
    ['label' => 'Active services', 'key' => 'services_active', 'hint' => 'Currently offered on the website'],
];
?>

<section class="stat-grid">
    <article class="stat stat--dark">
        <p class="stat__label">Team members</p>
        <p class="stat__value"><?= e((string) $team['total']) ?></p>
        <p class="stat__hint"><?= e((string) $team['admins']) ?> administrator<?= $team['admins'] === 1 ? '' : 's' ?></p>
    </article>

    <?php foreach ($cards as $card): ?>
        <?php $value = $counts[$card['key']] ?? null; ?>
        <article class="stat">
            <p class="stat__label"><?= e($card['label']) ?></p>
            <?php if ($value === null): ?>
                <p class="stat__value stat__value--empty">—</p>
                <p class="stat__hint">Available soon</p>
            <?php else: ?>
                <p class="stat__value"><?= e((string) $value) ?></p>
                <p class="stat__hint"><?= e($card['hint']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<div class="columns">
    <section class="card">
        <header class="card__head">
            <h2 class="card__title">Recent activity</h2>
            <p class="card__sub">Sign-ins and changes made in the admin panel.</p>
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
    </section>

    <section class="card">
        <header class="card__head">
            <h2 class="card__title">Getting set up</h2>
            <p class="card__sub">What is switched on today.</p>
        </header>

        <ul class="checklist">
            <li class="is-done">Secure sign-in with staff accounts</li>
            <li class="is-done">Audit trail of every sign-in</li>
            <li class="is-done">Automatic deployment</li>
            <li class="is-done">Services, gallery and reviews</li>
            <li class="is-done">Bookings and calendar</li>
            <li class="is-done">Contact form and message inbox</li>
            <li class="is-done">Team and email settings</li>
        </ul>

        <p class="card__note">
            The website reads bookings, messages and the gallery from this panel, so there is no
            second copy to keep in step.
        </p>
    </section>
</div>
