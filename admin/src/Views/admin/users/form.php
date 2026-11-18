<?php
/**
 * Add or edit one account.
 *
 * @var array<string, mixed>|null $user
 * @var int|null $me
 */

$isEdit = $user !== null;
$id = $isEdit ? (int) $user['id'] : 0;
$isSelf = $isEdit && $me !== null && $id === $me;

$name = old('name', (string) ($user['name'] ?? ''));
$email = old('email', (string) ($user['email'] ?? ''));
$role = old('role', (string) ($user['role'] ?? 'staff'));

$action = $isEdit ? '/admin/users/' . $id : '/admin/users';
?>

<div class="form-page">
    <form method="post" action="<?= e($action) ?>" class="card">
        <?= csrf_field() ?>

        <h2 class="card__title">Their details</h2>
        <p class="card__sub">
            <?= $isEdit
                ? 'Change the name, email or access. Nothing is saved until you press Save.'
                : 'The new account works the moment you save it.' ?>
        </p>

        <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" required maxlength="120"
                   autocomplete="off" autofocus>
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required maxlength="190"
                   autocomplete="off">
            <p class="field__hint">This is what they type to sign in, and where their test messages go.</p>
        </div>

        <fieldset class="field">
            <legend>Access</legend>

            <?php if ($isSelf): ?>
                <input type="hidden" name="role" value="<?= e($role) ?>">
                <p class="field__hint">
                    This is your own account, so your access stays as it is. Another administrator can
                    change it.
                </p>
                <p><span class="badge badge--blue"><?= $role === 'admin' ? 'Administrator' : 'Staff' ?></span></p>
            <?php else: ?>
                <div class="role-choice">
                    <label class="role-option">
                        <input type="radio" name="role" value="staff" <?= $role === 'staff' ? 'checked' : '' ?>>
                        <span>
                            <strong>Staff</strong>
                            <span class="cell-sub">Bookings, messages, services, gallery and reviews.</span>
                        </span>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                        <span>
                            <strong>Administrator</strong>
                            <span class="cell-sub">Everything staff can do, plus this screen and settings.</span>
                        </span>
                    </label>
                </div>
            <?php endif; ?>
        </fieldset>

        <div class="field">
            <label for="password"><?= $isEdit ? 'New password' : 'Password' ?></label>
            <input type="password" id="password" name="password" <?= $isEdit ? '' : 'required' ?>
                   minlength="10" maxlength="200" autocomplete="new-password">
            <p class="field__hint">
                <?= $isEdit
                    ? 'Leave this blank to keep their current password.'
                    : 'At least 10 characters. Share it with them privately.' ?>
            </p>
        </div>

        <div class="field">
            <label for="password_confirm">Repeat password</label>
            <input type="password" id="password_confirm" name="password_confirm" <?= $isEdit ? '' : 'required' ?>
                   minlength="10" maxlength="200" autocomplete="new-password">
        </div>

        <div class="form-actions">
            <a class="btn btn--ghost" href="/admin/users">Cancel</a>
            <button type="submit" class="btn btn--primary"><?= $isEdit ? 'Save changes' : 'Create account' ?></button>
        </div>
    </form>

    <aside class="side-stack">
        <section class="card">
            <h2 class="card__title"><?= $isEdit ? 'Careful with access' : 'Who should get an account?' ?></h2>
            <p class="card__sub">
                <?= $isEdit
                    ? 'Changing someone from administrator to staff takes away settings and this screen immediately.'
                    : 'Give accounts to people who work at the salon. Everyone signs in with their own email, so the audit trail names the right person.' ?>
            </p>
            <?php if ($isSelf): ?>
                <p class="card__note">
                    You are editing yourself. Your own role cannot be lowered, so nobody can lock
                    the salon out of its own panel by accident.
                </p>
            <?php endif; ?>
        </section>

        <?php if ($isEdit): ?>
            <section class="card">
                <h2 class="card__title">Switching off</h2>
                <p class="card__sub">
                    To stop someone signing in without losing their history, use
                    <strong>Switch off</strong> on the team list.
                </p>
            </section>
        <?php endif; ?>
    </aside>
</div>
