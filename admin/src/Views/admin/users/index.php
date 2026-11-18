<?php
/**
 * The team list.
 *
 * @var list<array<string, mixed>> $users
 * @var int|null $me
 */

$active = array_values(array_filter($users, static fn (array $u): bool => (int) $u['is_active'] === 1));
$count = count($active);

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        if (mb_strlen($letters) === 2) {
            break;
        }
    }

    return $letters === '' ? '?' : $letters;
};
?>

<div class="toolbar toolbar--spaced">
    <p class="toolbar__count">
        <?php if ($count === 1): ?>
            1 person can sign in
        <?php else: ?>
            <?= $count ?> people can sign in
        <?php endif; ?>
        <?php if (count($users) > $count): ?>
            <span class="cell-sub"><?= count($users) - $count ?> switched off</span>
        <?php endif; ?>
    </p>

    <a class="btn btn--primary" href="/admin/users/new">Add a team member</a>
</div>

<div class="card card--flush">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Person</th>
                <th scope="col">Access</th>
                <th scope="col">Status</th>
                <th scope="col">Last sign-in</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <?php
            $id = (int) $user['id'];
            $isSelf = $me !== null && $id === $me;
            $isActive = (int) $user['is_active'] === 1;
            $isAdmin = (string) $user['role'] === 'admin';
            ?>
            <tr>
                <td>
                    <span class="person">
                        <span class="avatar" aria-hidden="true"><?= e($initials((string) $user['name'])) ?></span>
                        <span>
                            <span class="cell-title"><?= e($user['name']) ?><?= $isSelf ? ' <em class="pill">You</em>' : '' ?></span>
                            <span class="cell-sub"><?= e($user['email']) ?></span>
                        </span>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $isAdmin ? 'badge--blue' : 'badge--grey' ?>">
                        <?= $isAdmin ? 'Administrator' : 'Staff' ?>
                    </span>
                </td>
                <td>
                    <?php if ($isActive): ?>
                        <span class="badge badge--green">Active</span>
                    <?php else: ?>
                        <span class="badge badge--muted">Switched off</span>
                    <?php endif; ?>
                </td>
                <td class="cell-sub">
                    <?= $user['last_login_at'] === null
                        ? 'Never'
                        : e(full_date((string) $user['last_login_at'])) ?>
                </td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn--ghost btn--sm" href="/admin/users/<?= $id ?>/edit">Edit</a>

                        <?php if (!$isSelf): ?>
                            <?php if ($isActive): ?>
                                <form method="post" action="/admin/users/<?= $id ?>/deactivate"
                                      onsubmit="return confirm('Switch off <?= e($user['name']) ?>? They will not be able to sign in.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm">Switch off</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/admin/users/<?= $id ?>/activate">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn--ghost btn--sm">Switch on</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<section class="card note-block">
    <h2 class="card__title">What each role can do</h2>
    <p class="card__sub">
        <strong>Administrators</strong> see everything, including this screen and settings.
        <strong>Staff</strong> manage bookings, messages, services, gallery and reviews.
    </p>
    <p class="card__note">
        Accounts are switched off rather than deleted, so past bookings and the audit trail
        still show who did what. Switch someone back on when they return.
    </p>
</section>
