<?php
/**
 * Panel settings: email delivery.
 *
 * @var array{driver: string, host: string, port: int, encryption: string, username: string, from_name: string, from_email: string, salon_email: string, password_saved: bool, readiness: array{state: string, missing: list<string>}} $mail
 * @var string $test_recipient
 * @var int $test_left
 */

// The chosen driver is not the whole story: "mail server" with a blank host
// or password cannot send anything, so the screen reports the effective state
// rather than the intention.
$driver = match ($mail['readiness']['state']) {
    'smtp' => ['label' => 'Sending through your mail server (SMTP)', 'class' => 'badge--green'],
    'mail' => ['label' => "Sending through the web host's mail service", 'class' => 'badge--warn'],
    default => [
        'label' => 'Not set up yet — missing ' . implode(', ', $mail['readiness']['missing']),
        'class' => 'badge--warn',
    ],
};

$canTest = $test_left > 0 && $mail['readiness']['state'] !== 'incomplete';
?>

<div class="columns columns--detail">
    <div class="side-stack">
        <form method="post" action="/admin/settings/mail" class="card">
            <?= csrf_field() ?>

            <h2 class="card__title">Email delivery</h2>
            <p class="card__sub">
                Booking confirmations, cancellations and website messages are emailed from here.
                Changes take effect immediately — no deploy, no restart.
            </p>

            <div class="field-row">
                <div class="field">
                    <label for="driver">How email is sent</label>
                    <select id="driver" name="driver">
                        <option value="smtp" <?= $mail['driver'] === 'smtp' ? 'selected' : '' ?>>Mail server (recommended)</option>
                        <option value="mail" <?= $mail['driver'] === 'mail' ? 'selected' : '' ?>>Web host's mail service</option>
                    </select>
                    <p class="field__hint">Use the mail server for real delivery. The web-host option only works if the host allows it.</p>
                </div>

                <div class="field">
                    <label for="encryption">Encryption</label>
                    <select id="encryption" name="encryption">
                        <option value="tls" <?= $mail['encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
                        <option value="ssl" <?= $mail['encryption'] === 'ssl' ? 'selected' : '' ?>>Implicit TLS (port 465)</option>
                        <option value="none" <?= $mail['encryption'] === 'none' ? 'selected' : '' ?>>No encryption</option>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="host">Mail server</label>
                    <input type="text" id="host" name="host" value="<?= e($mail['host']) ?>"
                           maxlength="253" placeholder="mail.example.com" autocomplete="off">
                </div>

                <div class="field">
                    <label for="port">Port</label>
                    <input type="number" id="port" name="port" value="<?= (int) $mail['port'] ?>"
                           min="1" max="65535" required>
                </div>
            </div>

            <div class="field">
                <label for="username">Mailbox username</label>
                <input type="text" id="username" name="username" value="<?= e($mail['username']) ?>"
                       maxlength="254" placeholder="mailbox@example.com" autocomplete="off">
            </div>

            <div class="field">
                <label for="password">Mailbox password</label>
                <input type="password" id="password" name="password" maxlength="4096" autocomplete="new-password"
                       placeholder="<?= $mail['password_saved'] ? '••••••••••••' : 'Not saved yet' ?>">
                <p class="field__hint">
                    <?= $mail['password_saved']
                        ? 'A password is saved. Leave this blank to keep it, or type a new one to replace it.'
                        : 'Stored encrypted, never as plain text. It is used only when sending.' ?>
                </p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="from_name">From name</label>
                    <input type="text" id="from_name" name="from_name" value="<?= e($mail['from_name']) ?>"
                           required maxlength="100">
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
                <p class="field__hint">Where new bookings and website messages are sent — usually the salon's own inbox.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save email settings</button>
                <a class="btn btn--ghost" href="/admin/email-templates">Design the emails</a>
            </div>
        </form>

        <section class="card">
            <h2 class="card__title">Send a test</h2>
            <p class="card__sub">
                A test reports what the mail server said. It cannot prove the message reached an inbox —
                check the inbox and the junk folder after sending.
            </p>

            <p><span class="badge <?= e($driver['class']) ?>"><?= e($driver['label']) ?></span></p>

            <p class="field__hint">
                Tests go to <strong><?= e($test_recipient) ?></strong>, the address on your own account.
                Each attempt gets a reference, so you can match the panel, the log and the message.
            </p>

            <?php if ($mail['readiness']['state'] === 'incomplete'): ?>
                <p class="field__hint">
                    Fill in the missing details above first — a test now would fail.
                </p>
            <?php endif; ?>

            <form method="post" action="/admin/settings/test">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary" <?= $canTest ? '' : 'disabled' ?>>
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
                Check three things, in this order: the mail server answers a test, the password still
                works in the mailbox's own webmail, and the From address belongs to the salon's domain.
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
                whether it is set. Nothing here is written to a page, a log or an audit record.
            </p>
        </section>

        <section class="card">
            <h2 class="card__title">Before a launch</h2>
            <p class="card__sub">
                Delivery is only proven by a real message arriving. Send one to a mailbox you control,
                then read the SPF, DKIM and DMARC results from its headers.
            </p>
        </section>
    </aside>
</div>
