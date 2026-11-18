<?php
/**
 * My account: the signed-in person's own details and password.
 *
 * @var array<string, mixed> $user
 */

$name = (string) ($user['name'] ?? '');
$email = (string) ($user['email'] ?? '');
$role = (string) ($user['role'] ?? 'staff');
$lastLogin = isset($user['last_login_at']) ? (string) $user['last_login_at'] : null;
?>

<div class="columns columns--even">
    <section class="card">
        <header class="card__head">
            <h2 class="card__title">Your details</h2>
            <p class="card__sub">The name and address shown to the rest of the team.</p>
        </header>

        <form method="post" action="/admin/account/profile" class="form" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" required maxlength="120" autocomplete="name">
            </div>

            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email">
                <p class="field__hint">You sign in with this address, and the salon's notifications go to it when you are the contact.</p>
            </div>

            <div class="field">
                <span class="field__label">Access</span>
                <p class="cell-title"><?= $role === 'admin' ? 'Administrator' : 'Staff' ?></p>
                <p class="field__hint">
                    <?php if ($role === 'admin'): ?>
                        You can manage the team, settings and every part of the panel.
                    <?php else: ?>
                        An administrator can change your access if your role changes.
                    <?php endif; ?>
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save details</button>
            </div>
        </form>
    </section>

    <section class="card">
        <header class="card__head">
            <h2 class="card__title">Password</h2>
            <p class="card__sub">Change the password you use to sign in.</p>
        </header>

        <form method="post" action="/admin/account/password" class="form" autocomplete="off">
            <?= csrf_field() ?>

            <div class="field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="field">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
                <p class="field__hint">At least 10 characters. A short phrase you will remember beats a single word.</p>
            </div>

            <div class="field">
                <label for="password_confirm">Repeat the new password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="10" maxlength="200" autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Change password</button>
            </div>
        </form>

        <p class="card__note">
            <?php if ($lastLogin !== null): ?>
                Last sign-in: <?= e(full_date($lastLogin)) ?>.
            <?php endif; ?>
            If you have forgotten your password, another administrator can set a new one for you on the Team screen.
        </p>
    </section>
</div>
