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
    <p class="error-code"><?= e((string) ($status ?? 429)) ?></p>
    <h1 class="card__title">Too many attempts</h1>
    <p class="card__sub"><?= e($message ?? '') ?></p>
    <p class="card__sub">We paused sign-ins from this device for a short while. Please wait a few minutes before trying again.</p>

    <div class="error-actions">
        <a class="btn btn--primary" href="/admin">Back to dashboard</a>
        <a class="btn btn--ghost" href="/admin/login">Sign in</a>
    </div>
</section>
