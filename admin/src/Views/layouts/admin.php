<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?> · WIZ Beauty Salon</title>
    <link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
    <?= $head ?? '' ?>
</head>
<?php
// Every page renders inside this layout, so the signed-in user is resolved here
// once rather than being passed by each controller.
$signedIn = $user ?? current_user() ?? [];
$isAdmin = ($signedIn['role'] ?? '') === 'admin';

// Two small counts turn the menu into a to-do list. They are read defensively:
// a badge is a convenience and must never stop a page from rendering.
$navPending = 0;
$navUnread = 0;
$container = $GLOBALS['__wiz_app'] ?? null;

if ($container instanceof \Wiz\Core\App) {
    try {
        $navPending = $container->get(\Wiz\Repositories\BookingRepository::class)->countAwaitingApproval();
        $navUnread = $container->get(\Wiz\Repositories\MessageRepository::class)->unreadCount();
    } catch (\Throwable) {
        // Leave the badges off; the pages themselves will report the problem.
    }
}

$activeKey = $active ?? '';

$navItem = static function (string $key, string $href, string $label, int $badge = 0) use ($activeKey): string {
    $current = $activeKey === $key;
    $badgeHtml = $badge > 0
        ? '<span class="nav__badge" aria-label="' . $badge . ' waiting">' . ($badge > 99 ? '99+' : $badge) . '</span>'
        : '';

    return sprintf(
        '<a class="nav__item%s" href="%s"%s><span class="nav__dot" aria-hidden="true"></span>%s%s</a>',
        $current ? ' is-active' : '',
        e($href),
        $current ? ' aria-current="page"' : '',
        e($label),
        $badgeHtml,
    );
};
?>

<body class="app">

<a class="skip-link" href="#main-content">Skip to content</a>

<aside class="sidebar">
    <div class="sidebar__brand">
        <span class="brand-mark">WIZ</span>
        <span class="brand-sub">Beauty Salon</span>
    </div>

    <nav class="nav" aria-label="Main">
        <?= $navItem('dashboard', '/admin', 'Dashboard') ?>

        <p class="nav__heading">Manage</p>
        <?= $navItem('bookings', '/admin/bookings', 'Bookings', $navPending) ?>
        <?= $navItem('services', '/admin/services', 'Services') ?>
        <?= $navItem('gallery', '/admin/gallery', 'Gallery') ?>
        <?= $navItem('reviews', '/admin/reviews', 'Reviews') ?>
        <?= $navItem('schedule', '/admin/schedule', 'Opening hours') ?>
        <?= $navItem('messages', '/admin/messages', 'Messages', $navUnread) ?>

        <?php if ($isAdmin): ?>
            <p class="nav__heading">Administration</p>
            <?= $navItem('team', '/admin/users', 'Team') ?>
            <?= $navItem('activity', '/admin/activity', 'Activity') ?>
            <?= $navItem('email-templates', '/admin/email-templates', 'Email designs') ?>
            <?= $navItem('settings', '/admin/settings', 'Settings') ?>
        <?php endif; ?>
    </nav>

    <a class="sidebar__foot sidebar__foot--link<?= $activeKey === 'account' ? ' is-active' : '' ?>" href="/admin/account">
        <span>Signed in as</span>
        <strong><?= e($signedIn['name'] ?? 'Staff member') ?></strong>
        <span class="role"><?= e(ucfirst((string) ($signedIn['role'] ?? 'staff'))) ?> · My account</span>
    </a>
</aside>

<div class="main">
    <header class="topbar">
        <div>
            <h1 class="topbar__title"><?= e($heading ?? ($title ?? 'Dashboard')) ?></h1>
            <?php if (!empty($subheading)): ?>
                <p class="topbar__sub"><?= e($subheading) ?></p>
            <?php endif; ?>
        </div>

        <form method="post" action="/admin/logout" class="topbar__actions">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost">Sign out</button>
        </form>
    </header>

    <main class="content" id="main-content">
        <?php if ($message = flash('success')): ?>
            <div class="alert alert--success" role="status"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($message = flash('error')): ?>
            <div class="alert alert--error" role="alert"><?= e($message) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="foot">
        WIZ Beauty Salon admin &middot; <?= e(date('Y')) ?>
    </footer>
</div>

    <?= $scripts ?? '' ?>
</body>
</html>
