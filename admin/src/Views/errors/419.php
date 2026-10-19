<?php
/**
 * Error page.
 *
 * Rendered inside the auth layout, which is deliberately session-free: an error
 * can happen before anyone is signed in.
 *
 * @var string $message
 * @var int $status
 */
?>
<section class="card card--auth card--error">
    <p class="error-code"><?= e((string) ($status ?? 419)) ?></p>
    <h1 class="card__title">Session expired</h1>
    <p class="card__sub"><?= e($message ?? '') ?></p>
    <p class="card__sub">For your security the form timed out. Sign in again and repeat what you were doing.</p>

    <div class="error-actions">
        <a class="btn btn--primary" href="/admin">Back to dashboard</a>
        <a class="btn btn--ghost" href="/admin/login">Sign in</a>
    </div>
</section>
