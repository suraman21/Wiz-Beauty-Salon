<?php
/**
 * Customer-facing error page.
 *
 * Rendered inside layouts/error, outside the staff session. The wording tells
 * a customer what they can do next — call the salon, book again, go back —
 * rather than talking about dashboards or sign-in.
 *
 * @var string $message
 * @var int $status
 * @var string|null $reference
 */

$status = (int) ($status ?? 500);

[$heading, $explanation] = match ($status) {
    403 => ['That page is not available', 'This link is not allowed to open that page.'],
    404 => ['We could not find that page', 'The link may be old, or the address may have been typed differently.'],
    419 => ['That form has expired', 'For your security the page timed out. Go back, reload it and send it again.'],
    429 => ['One moment, please', 'There have been a lot of requests just now. Please wait a minute and try again.'],
    default => ['Something went wrong', 'The page could not load. Please try again in a moment.'],
};
?>

<section class="card card--auth card--error">
    <p class="error-code"><?= e((string) $status) ?></p>
    <h1 class="card__title"><?= e($heading) ?></h1>

    <?php if (($message ?? '') !== '' && $status !== 500): ?>
        <p class="card__sub"><?= e($message) ?></p>
    <?php endif; ?>

    <p class="card__sub"><?= e($explanation) ?></p>

    <?php if ($status === 404 && str_contains((string) ($path ?? ''), '/booking/manage/')): ?>
        <p class="card__sub">
            If you are trying to change or cancel an appointment, use the link from your
            confirmation email, or contact the salon and we will do it for you.
        </p>
    <?php endif; ?>

    <div class="error-actions">
        <a class="btn btn--primary" href="/booking">Book an appointment</a>
        <a class="btn btn--ghost" href="/contact">Contact the salon</a>
    </div>

    <p class="card__sub error-home"><a href="/">Back to the WIZ website</a></p>

    <?php if (!empty($reference)): ?>
        <p class="error-reference">Reference: <strong><?= e($reference) ?></strong></p>
    <?php endif; ?>
</section>
