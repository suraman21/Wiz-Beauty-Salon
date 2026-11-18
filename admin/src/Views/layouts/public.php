<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Your booking') ?> · WIZ Beauty Salon</title>
    <link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body class="auth-body pub-body">

<?php
/**
 * The shell around the customer's own pages.
 *
 * This is a page a customer reaches from an email, on a phone, at night. It used
 * to be a bare card with the salon's initials on top: no way back to the site,
 * and nothing that said where they were. Now it carries the same navigation as
 * the website — collapsed into one menu button under 860px, with no JavaScript
 * doing the work, so it still opens if a script fails — and the footer repeats
 * it for anyone who scrolls to the end of a long reschedule card.
 *
 * @var string $content
 */
$siteNav = [
    ['label' => 'Home', 'href' => site_url('/')],
    ['label' => 'Services', 'href' => site_url('/services')],
    ['label' => 'About', 'href' => site_url('/about')],
    ['label' => 'Gallery', 'href' => site_url('/gallery')],
    ['label' => 'Contact', 'href' => site_url('/contact')],
];
?>

<header class="pub-head">
    <div class="pub-head__bar">
        <a class="pub-brand" href="<?= e(site_url('/')) ?>" aria-label="WIZ Beauty Salon — home">
            <span class="pub-brand__mark">WIZ</span>
            <span class="pub-brand__sub">Beauty Salon</span>
        </a>

        <nav class="pub-nav" aria-label="Site">
            <?php foreach ($siteNav as $item): ?>
                <a class="pub-nav__link" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <a class="pub-nav__link pub-nav__link--soft" href="<?= e(site_url('/booking/recover')) ?>">Manage booking</a>
        </nav>

        <div class="pub-head__actions">
            <a class="btn btn--primary btn--sm pub-head__book" href="<?= e(site_url('/booking')) ?>">Book now</a>

            <!-- No script, no problem: <details> is the menu on a phone. -->
            <details class="pub-menu">
                <summary class="pub-menu__button" aria-label="Open menu">
                    <span class="pub-menu__bars" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="pub-menu__text">Menu</span>
                </summary>
                <div class="pub-menu__panel">
                    <?php foreach ($siteNav as $item): ?>
                        <a class="pub-menu__link" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                    <?php endforeach; ?>
                    <a class="pub-menu__link" href="<?= e(site_url('/booking/recover')) ?>">Manage booking</a>
                    <a class="pub-menu__link pub-menu__link--cta" href="<?= e(site_url('/booking')) ?>">Book an appointment</a>
                </div>
            </details>
        </div>
    </div>
</header>

<main class="auth-shell auth-shell--wide">
    <?= $content ?>
</main>

<footer class="pub-foot">
    <div class="pub-foot__inner">
        <p class="pub-foot__note">
            This page belongs to you — do not share the link. Anyone who has it can change this booking.
        </p>
        <nav class="pub-foot__nav" aria-label="More pages">
            <?php foreach ($siteNav as $item): ?>
                <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(site_url('/booking/information')) ?>">Booking terms</a>
        </nav>
    </div>
</footer>

</body>
</html>
