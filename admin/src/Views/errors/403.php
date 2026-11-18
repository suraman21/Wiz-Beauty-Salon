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
    <p class="error-code"><?= e((string) ($status ?? 403)) ?></p>
    <h1 class="card__title">Not allowed</h1>
    <p class="card__sub"><?= e($message ?? '') ?></p>
    <p class="card__sub">Your account does not have permission to open this page. If you think that is wrong, ask an administrator.</p>

    <div class="error-actions">
        <a class="btn btn--primary" href="/admin">Back to dashboard</a>
        <a class="btn btn--ghost" href="/admin/login">Sign in</a>
    </div>
</section>
