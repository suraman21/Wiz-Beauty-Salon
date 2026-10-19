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
    <p class="error-code"><?= e((string) ($status ?? 500)) ?></p>
    <h1 class="card__title">Something went wrong</h1>
    <p class="card__sub"><?= e($message ?? '') ?></p>
    <p class="card__sub">An unexpected error stopped this page from loading. The problem has been logged for the team.</p>

    <div class="error-actions">
        <a class="btn btn--primary" href="/admin">Back to dashboard</a>
        <a class="btn btn--ghost" href="/admin/login">Sign in</a>
    </div>

    <?php if (!empty($reference)): ?>
        <p class="error-reference">Reference: <strong><?= e($reference) ?></strong></p>
    <?php endif; ?>
</section>
