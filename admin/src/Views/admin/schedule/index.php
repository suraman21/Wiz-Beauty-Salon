<?php
/**
 * Opening hours and closures.
 *
 * @var list<array<string, mixed>> $hours
 * @var array<int, string> $days
 * @var list<array<string, mixed>> $closures
 * @var int $grid
 * @var int $lead
 * @var int $horizon
 * @var string|null $error
 */

$byDay = [];

foreach ($hours as $row) {
    $byDay[(int) $row['day_of_week']] = $row;
}

$short = static fn (mixed $time): string => $time === null ? '' : substr((string) $time, 0, 5);
?>

<?php if (!empty($error)): ?>
    <div class="alert alert--error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="columns columns--detail">
    <section class="card">
        <h2 class="card__title">Weekly hours</h2>
        <p class="card__sub">Bookable times are generated from these hours.</p>

        <form method="post" action="/admin/schedule/hours">
            <?= csrf_field() ?>

            <table class="hours-table">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Opens</th>
                        <th>Closes</th>
                        <th>Closed</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($days as $dayNumber => $label): ?>
                    <?php
                    $row = $byDay[$dayNumber] ?? null;
                    $isClosed = (int) ($row['is_closed'] ?? 1) === 1;
                    ?>
                    <tr>
                        <th scope="row"><?= e($label) ?></th>
                        <td>
                            <input type="time" name="opens_<?= $dayNumber ?>" value="<?= e($short($row['opens_at'] ?? null)) ?>"
                                   aria-label="<?= e($label) ?> opening time">
                        </td>
                        <td>
                            <input type="time" name="closes_<?= $dayNumber ?>" value="<?= e($short($row['closes_at'] ?? null)) ?>"
                                   aria-label="<?= e($label) ?> closing time">
                        </td>
                        <td class="hours-table__closed">
                            <input type="checkbox" name="closed_<?= $dayNumber ?>" value="1" <?= $isClosed ? 'checked' : '' ?>
                                   aria-label="<?= e($label) ?> closed">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save hours</button>
            </div>
        </form>

        <p class="card__note card__note--left">
            Appointment slots are <?= $grid ?> minutes apart · bookings need <?= $lead ?> minutes notice ·
            the calendar opens <?= $horizon ?> days ahead.
        </p>
    </section>

    <div class="side-stack">
        <section class="card">
            <h2 class="card__title">Close a day</h2>
            <p class="card__sub">Holidays, training days, private events.</p>

            <form method="post" action="/admin/schedule/closures" autocomplete="off">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="start_date">From</label>
                    <input type="date" id="start_date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required>
                </div>

                <div class="field">
                    <label for="end_date">To <span class="field__hint">same day is fine</span></label>
                    <input type="date" id="end_date" name="end_date" value="<?= e(date('Y-m-d')) ?>" required>
                </div>

                <div class="field">
                    <label for="reason">Reason <span class="field__hint">optional</span></label>
                    <input type="text" id="reason" name="reason" maxlength="160" placeholder="e.g. Public holiday">
                </div>

                <button type="submit" class="btn btn--primary btn--block">Close these days</button>
            </form>
        </section>

        <section class="card">
            <h2 class="card__title">Upcoming closures</h2>

            <?php if ($closures === []): ?>
                <p class="empty">None. The salon follows its weekly hours.</p>
            <?php else: ?>
                <ul class="closure-list">
                    <?php foreach ($closures as $closure): ?>
                        <li>
                            <div>
                                <p class="closure-list__dates">
                                    <?= e(date('j M Y', (int) strtotime((string) $closure['start_date']))) ?>
                                    <?php if ($closure['end_date'] !== $closure['start_date']): ?>
                                        → <?= e(date('j M Y', (int) strtotime((string) $closure['end_date']))) ?>
                                    <?php endif; ?>
                                </p>
                                <p class="cell-sub"><?= e($closure['reason'] ?? 'Closed') ?></p>
                            </div>

                            <form method="post" action="/admin/schedule/closures/<?= (int) $closure['id'] ?>/delete"
                                  onsubmit="return confirm('Reopen these days?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--ghost btn--sm">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
