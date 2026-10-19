<?php
/**
 * Panel settings: email delivery.
 *
 * @var array{enabled: bool, from_name: string, from_email: string, salon_email: string, password_saved: bool, smtp_username: string, smtp_host: string, driver: string} $mail
 * @var string $test_recipient
 * @var int $test_left
 */

$driver = match ($mail['driver']) {
    'smtp' => ['label' => 'Sending through the mail server', 'class' => 'badge--green'],
    'mail' => ['label' => 'Sending through the web host', 'class' => 'badge--green'],
    default => ['label' => 'Not sending — written to the log only', 'class' => 'badge--warn'],
};
?>

<div class="columns columns--detail">
    <div class="side-stack">
        <form method="post" action="/admin/settings/mail" class="card">
            <?= csrf_field() ?>

            <h2 class="card__title">Email delivery</h2>
            <p class="card__sub">
                Booking confirmations, cancellations and contact-form notes are emailed from here.
            </p>

            <label class="check">
                <input type="checkbox" name="enabled" value="1" <?= $mail['enabled'] ? 'checked' : '' ?>>
                Send emails
            </label>
            <p class="field__hint">
                While this is off, messages are written to the log file and nobody is notified.
                Nothing is lost — turn it back on when the mail account is ready.
            </p>

            <div class="field-row">
                <div class="field">
                    <label for="from_name">From name</label>
                    <input type="text" id="from_name" name="from_name" value="<?= e($mail['from_name']) ?>"
                           required maxlength="120">
                </div>

                <div class="field">
                    <label for="from_email">From address</label>
                    <input type="email" id="from_email" name="from_email" value="<?= e($mail['from_email']) ?>"
                           required maxlength="190">
                </div>
            </div>

            <div class="field">
                <label for="salon_email">Notification address</label>
                <input type="email" id="salon_email" name="salon_email" value="<?= e($mail['salon_email']) ?>"
                       required maxlength="190">
                <p class="field__hint">
                    Where new bookings and contact messages are sent. Usually the salon's own inbox.
                </p>
            </div>

            <?php if ($mail['smtp_host'] !== '' || $mail['smtp_username'] !== ''): ?>
                <div class="detail-grid detail-grid--tight">
                    <?php if ($mail['smtp_host'] !== ''): ?>
                        <div>
                            <span class="overline-soft">Mail server</span>
                            <p><?= e($mail['smtp_host']) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($mail['smtp_username'] !== ''): ?>
                        <div>
                            <span class="overline-soft">Account</span>
                            <p><?= e($mail['smtp_username']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="field__hint">
                    These come from the hosting setup. Ask whoever manages the hosting to change them.
                </p>
            <?php endif; ?>

            <div class="field">
                <label for="password">Email password</label>
                <input type="password" id="password" name="password" maxlength="200" autocomplete="new-password"
                       placeholder="<?= $mail['password_saved'] ? '••••••••••••' : 'Not saved yet' ?>">
                <p class="field__hint">
                    <?= $mail['password_saved']
                        ? 'A password is saved. Leave this blank to keep it, or type a new one to replace it.'
                        : 'The password for the mail account above. It is stored encrypted, never as plain text.' ?>
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save email settings</button>
            </div>
        </form>

        <section class="card">
            <h2 class="card__title">Send a test</h2>
            <p class="card__sub">
                The quickest way to know that email works is to send one to yourself.
            </p>

            <p>
                <span class="badge <?= e($driver['class']) ?>"><?= e($driver['label']) ?></span>
            </p>

            <p class="field__hint">
                Test messages go to <strong><?= e($test_recipient) ?></strong>, the address on your own
                account. Allow a minute, and look in the spam folder too.
            </p>

            <form method="post" action="/admin/settings/test">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary" <?= $test_left <= 0 ? 'disabled' : '' ?>>
                    Send a test email
                </button>
            </form>

            <p class="card__note">
                <?php if ($test_left <= 0): ?>
                    Three tests have been sent in the last hour. Try again a little later.
                <?php elseif ($test_left === 1): ?>
                    One test left this hour.
                <?php else: ?>
                    <?= (int) $test_left ?> tests left this hour.
                <?php endif; ?>
            </p>
        </section>
    </div>

    <aside class="side-stack">
        <section class="card">
            <h2 class="card__title">If email stops arriving</h2>
            <p class="card__sub">
                Check three things, in this order: the switch above is on, the password still works in
                the mail account's own webmail, and the From address belongs to the salon's domain.
            </p>
            <p class="card__note">
                A wrong password stops delivery without changing anything on this page — the test
                button reports the mail server's own answer.
            </p>
        </section>

        <section class="card">
            <h2 class="card__title">Kept private</h2>
            <p class="card__sub">
                The email password is encrypted before it is stored, and the screen only ever shows
                whether it is set. Nobody can read it back from this page.
            </p>
        </section>
    </aside>
</div>
