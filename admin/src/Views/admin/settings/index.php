<?php
/**
 * Panel settings: email delivery.
 *
 * The screen is written for the person who has to fix email at 9pm: one status
 * line at the top that says whether mail actually works, the three fields that
 * decide it marked when they are missing, and the test button where the
 * question "is it working?" is answered.
 *
 * @var array{driver: string, host: string, port: int, encryption: string, username: string, from_name: string, from_email: string, salon_email: string, password_saved: bool, readiness: array{state: string, missing: list<string>}} $mail
 * @var string $test_recipient
 * @var int $test_left
 * @var array{phone: string, whatsapp: string, email: string, instagram: string, tiktok: string, address: string} $salon
 */

$state = $mail['readiness']['state'];
$missing = $mail['readiness']['missing'];
$canTest = $test_left > 0 && $state !== 'incomplete';

/** True when this particular detail is the reason nothing can be sent. */
$isMissing = static fn (string $what): bool => in_array($what, $missing, true);
?>

<div class="columns columns--detail">
    <div class="side-stack">
        <form method="post" action="/admin/settings/mail" class="card">
            <?= csrf_field() ?>

            <h2 class="card__title">Email delivery</h2>
            <p class="card__sub">
                Booking confirmations, replies and website messages leave the salon from here.
            </p>

            <div class="mail-status mail-status--<?= $state === 'smtp' ? 'ok' : 'warn' ?>">
                <span class="mail-status__dot" aria-hidden="true"></span>
                <div>
                    <?php if ($state === 'smtp'): ?>
                        <strong>Email is set up.</strong>
                        Messages are sent through your mail server, <?= e($mail['host']) ?>.
                    <?php elseif ($state === 'mail'): ?>
                        <strong>The web host sends the mail.</strong>
                        This only works if the host allows it. A real mail server is the reliable choice.
                    <?php else: ?>
                        <strong>Email is not set up yet.</strong>
                        Still needed: <?= e(implode(', ', $missing)) ?>.
                        Until then nothing is delivered — each attempt is written to the log instead.
                    <?php endif; ?>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="driver">How email is sent</label>
                    <select id="driver" name="driver">
                        <option value="smtp" <?= $mail['driver'] === 'smtp' ? 'selected' : '' ?>>Mail server (recommended)</option>
                        <option value="mail" <?= $mail['driver'] === 'mail' ? 'selected' : '' ?>>Web host's mail service</option>
                    </select>
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
                <div class="field<?= $isMissing('mail server') ? ' field--missing' : '' ?>">
                    <label for="host">Mail server</label>
                    <input type="text" id="host" name="host" value="<?= e($mail['host']) ?>"
                           maxlength="253" placeholder="mail.wiz.arkeonethiopia.com" autocomplete="off">
                    <?php if ($isMissing('mail server')): ?>
                        <p class="field__hint field__hint--warn">Needed before anything can be sent.</p>
                    <?php else: ?>
                        <p class="field__hint">Usually the same host as your webmail.</p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="port">Port</label>
                    <input type="number" id="port" name="port" value="<?= (int) $mail['port'] ?>"
                           min="1" max="65535" required>
                    <p class="field__hint">587 with STARTTLS, 465 with SSL.</p>
                </div>
            </div>

            <div class="field<?= $isMissing('mailbox username') ? ' field--missing' : '' ?>">
                <label for="username">Mailbox username</label>
                <input type="text" id="username" name="username" value="<?= e($mail['username']) ?>"
                       maxlength="254" placeholder="admin@wiz.arkeonethiopia.com" autocomplete="off">
                <?php if ($isMissing('mailbox username')): ?>
                    <p class="field__hint field__hint--warn">The whole address, not just the part before @.</p>
                <?php endif; ?>
            </div>

            <div class="field<?= $isMissing('mailbox password') ? ' field--missing' : '' ?>">
                <label for="password">Mailbox password</label>
                <input type="password" id="password" name="password" maxlength="4096" autocomplete="new-password"
                       placeholder="<?= $mail['password_saved'] ? '••••••••••••' : 'Not saved yet' ?>">
                <p class="field__hint<?= $isMissing('mailbox password') ? ' field__hint--warn' : '' ?>">
                    <?php if ($mail['password_saved']): ?>
                        A password is saved. Leave this blank to keep it, or type a new one to replace it.
                    <?php elseif ($isMissing('mailbox password')): ?>
                        The password of the mailbox above. It is stored encrypted and never shown again.
                    <?php else: ?>
                        Stored encrypted, never as plain text.
                    <?php endif; ?>
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
                <p class="field__hint">Where new bookings and website messages are sent — the salon's own inbox.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save email settings</button>
                <a class="btn btn--ghost" href="/admin/email-templates">Design the emails</a>
            </div>
        </form>

        <form method="post" action="/admin/settings/booking" class="card" autocomplete="off">
            <?= csrf_field() ?>

            <h2 class="card__title">Booking rules</h2>
            <p class="card__sub">
                What the website's calendar offers. These are the only numbers behind every time a
                customer sees.
            </p>

            <div class="field-row">
                <div class="field">
                    <label for="slot_minutes">Gap between times <span class="field__hint">minutes</span></label>
                    <input type="number" id="slot_minutes" name="slot_minutes" min="5" max="120" step="5"
                           value="<?= (int) $rules['slot_minutes'] ?>" required>
                    <p class="field__hint">
                        Times are offered every <?= (int) $rules['slot_minutes'] ?> minutes, from opening
                        until the last one that still fits before closing.
                    </p>
                </div>

                <div class="field">
                    <label for="lead_minutes">Notice before a booking <span class="field__hint">minutes</span></label>
                    <input type="number" id="lead_minutes" name="lead_minutes" min="0" max="4320" step="15"
                           value="<?= (int) $rules['lead_minutes'] ?>" required>
                    <p class="field__hint">
                        A time today is only offered if it is at least
                        <?= (int) $rules['lead_minutes'] ?> minutes away — two hours by default, so nobody
                        books a slot while the customer is already on their way.
                    </p>
                </div>
            </div>

            <div class="field">
                <label for="max_advance_days">Days the calendar opens ahead</label>
                <input type="number" id="max_advance_days" name="max_advance_days" min="1" max="365"
                       value="<?= (int) $rules['max_advance_days'] ?>" required>
                <p class="field__hint">
                    Customers can book up to <?= (int) $rules['max_advance_days'] ?> days ahead. Days past
                    that are greyed out on the website rather than offered and then refused.
                </p>
            </div>

            <div class="rule-divider">
                <span>Changing or cancelling</span>
            </div>

            <div class="field">
                <label for="change_window_hours">
                    Customers may change or cancel up to
                    <span class="field__hint">hours before the appointment</span>
                </label>

                <div class="preset-row" role="group" aria-label="Common windows">
                    <?php foreach ([0, 12, 24, 48, 72] as $preset): ?>
                        <?php $isOn = (int) $rules['change_window_hours'] === $preset; ?>
                        <button type="button"
                                class="preset-chip<?= $isOn ? ' is-active' : '' ?>"
                                data-window="<?= $preset ?>"
                                aria-pressed="<?= $isOn ? 'true' : 'false' ?>">
                            <?= $preset === 0 ? 'No cut-off' : $preset . ' h' ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="window-input">
                    <input type="number" id="change_window_hours" name="change_window_hours"
                           min="0" max="<?= (int) $rules['change_window_max'] ?>" step="1"
                           value="<?= (int) $rules['change_window_hours'] ?>" required>
                    <span class="window-input__unit">hours before the appointment</span>
                </div>

                <p class="field__hint">
                    This is printed on the website's booking page, on the customer's manage link, and
                    in the sentence they read when they book: <em>“<?= e((string) $rules['change_window_summary']) ?>”</em>
                    Inside the window the manage page stops offering cancel and reschedule and asks the
                    customer to call instead — a change the server would refuse is never shown as a button.
                    <strong>0</strong> means no cut-off at all.
                </p>

                <p class="field__hint">
                    Think about what the window protects: a 48-hour window is a way to keep a long
                    booking — a bride's morning, a full head of braids — from being given away the
                    night before. A short one is friendlier to customers who plan late.
                </p>
            </div>

            <p class="card__note card__note--left">
                A customer who picks more than one service gets booked for the combined length, so a
                larger gap makes longer appointments easier to place.
            </p>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save booking rules</button>
                <a class="btn btn--ghost" href="/admin/schedule">See the calendar</a>
            </div>
        </form>

        <script>
        (function () {
            var input = document.getElementById('change_window_hours');
            var chips = document.querySelectorAll('.preset-chip');

            function paint() {
                chips.forEach(function (chip) {
                    var on = String(chip.getAttribute('data-window')) === String(input.value);
                    chip.classList.toggle('is-active', on);
                    chip.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    input.value = chip.getAttribute('data-window');
                    paint();
                });
            });

            if (input) input.addEventListener('input', paint);
            paint();
        })();
        </script>

        <form method="post" action="/admin/settings/salon" class="card">
            <?= csrf_field() ?>

            <h2 class="card__title">Salon details</h2>
            <p class="card__sub">
                This is what the website's Contact page prints. Leave a field blank to hide it —
                nothing is invented for you.
            </p>

            <div class="field-row">
                <div class="field">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= e($salon['phone']) ?>"
                           maxlength="32" placeholder="+251 91 234 5678" autocomplete="off">
                </div>

                <div class="field">
                    <label for="whatsapp">WhatsApp</label>
                    <input type="text" id="whatsapp" name="whatsapp" value="<?= e($salon['whatsapp']) ?>"
                           maxlength="32" placeholder="+251 91 234 5678" autocomplete="off">
                    <p class="field__hint">Either form works: 0911 223 344 or +251 91 122 3344.</p>
                </div>
            </div>

            <div class="field">
                <label for="salon_contact_email">Email</label>
                <input type="email" id="salon_contact_email" name="email" value="<?= e($salon['email']) ?>"
                       maxlength="190" placeholder="hello@wiz.arkeonethiopia.com" autocomplete="off">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="instagram">Instagram</label>
                    <input type="text" id="instagram" name="instagram" value="<?= e($salon['instagram']) ?>"
                           maxlength="60" placeholder="wizbeautysalon" autocomplete="off">
                    <p class="field__hint">The handle only. A full profile link works too.</p>
                </div>

                <div class="field">
                    <label for="tiktok">TikTok</label>
                    <input type="text" id="tiktok" name="tiktok" value="<?= e($salon['tiktok']) ?>"
                           maxlength="60" placeholder="wizbeautysalon" autocomplete="off">
                </div>
            </div>

            <div class="field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?= e($salon['address']) ?>"
                       maxlength="190" placeholder="Bole, Addis Ababa" autocomplete="off">
                <p class="field__hint">Shown on the Contact page. The map itself is drawn by the visitor's browser.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Save salon details</button>
                <a class="btn btn--ghost" href="/#contact" target="_blank" rel="noreferrer">See the Contact page</a>
            </div>
        </form>

        <section class="card">
            <h2 class="card__title">Send a test email</h2>
            <p class="card__sub">
                Goes to <strong><?= e($test_recipient) ?></strong>, your own address, at
                <strong><?= (int) $test_left ?></strong> per hour. Receiving it is the only proof that mail works.
            </p>

            <form method="post" action="/admin/settings/test">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary" <?= $canTest ? '' : 'disabled' ?>>
                    Send a test email
                </button>
            </form>

            <p class="card__note">
                <?php if ($state === 'incomplete'): ?>
                    Fill in the missing details above first — a test now would fail.
                <?php elseif ($test_left <= 0): ?>
                    Three tests have been sent in the last hour. Try again a little later.
                <?php else: ?>
                    Each test carries a reference, so you can match the panel, the log and the message.
                <?php endif; ?>
            </p>
        </section>
    </div>

    <aside class="side-stack">
        <section class="card">
            <h2 class="card__title">If email stops arriving</h2>
            <ol class="step-list">
                <li class="step">
                    <span class="step__num">1</span>
                    <span>Send a test above. If it fails, the mail server refused the login and the message says why.</span>
                </li>
                <li class="step">
                    <span class="step__num">2</span>
                    <span>Open the same mailbox in webmail with the same password. If that fails, reset it there, then save the new one here.</span>
                </li>
                <li class="step">
                    <span class="step__num">3</span>
                    <span>If the test arrives but customers get nothing, ask them to check spam, then check the domain's SPF and DKIM records.</span>
                </li>
            </ol>

            <details class="disclosure">
                <summary class="disclosure__summary">What SPF and DKIM mean</summary>
                <p class="card__sub">
                    Two DNS records that tell the world which server may send mail for this domain
                    (SPF) and prove the message was not altered on the way (DKIM). When they are
                    missing, honest mail is more likely to be treated as spam.
                </p>
            </details>
        </section>

        <section class="card">
            <h2 class="card__title">Kept private</h2>
            <p class="card__sub">
                The password is encrypted before it is stored and never shown again. This screen only
                says whether one is set.
            </p>
        </section>
    </aside>
</div>

<script>
    // Choosing an encryption mode offers its usual port, so the two fields cannot
    // disagree. A custom port that matches neither default is left alone.
    (() => {
        const encryption = document.getElementById("encryption");
        const port = document.getElementById("port");
        if (!encryption || !port) return;
        const usual = { tls: "587", ssl: "465" };
        encryption.addEventListener("change", () => {
            const next = usual[encryption.value];
            if (next && ["", "587", "465"].includes(port.value)) port.value = next;
        });
    })();
</script>
