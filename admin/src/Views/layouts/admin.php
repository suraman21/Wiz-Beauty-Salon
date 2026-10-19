<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?> · WIZ Beauty Salon</title>
    <link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<?php
// Every page renders inside this layout, so the signed-in user is resolved here
// once rather than being passed by each controller.
$signedIn = $user ?? current_user() ?? [];
?>

<body class="app">

<aside class="sidebar">
    <div class="sidebar__brand">
        <span class="brand-mark">WIZ</span>
        <span class="brand-sub">Beauty Salon</span>
    </div>

    <nav class="nav" aria-label="Main">
        <a class="nav__item<?= ($active ?? '') === 'dashboard' ? ' is-active' : '' ?>" href="/admin">
            <span class="nav__dot" aria-hidden="true"></span>
            Dashboard
        </a>

        <p class="nav__heading">Manage</p>
        <span class="nav__item is-muted">Bookings <em class="pill">Soon</em></span>
        <a class="nav__item<?= ($active ?? '') === 'services' ? ' is-active' : '' ?>" href="/admin/services">
            <span class="nav__dot" aria-hidden="true"></span>
            Services
        </a>
        <a class="nav__item<?= ($active ?? '') === 'gallery' ? ' is-active' : '' ?>" href="/admin/gallery">
            <span class="nav__dot" aria-hidden="true"></span>
            Gallery
        </a>
        <a class="nav__item<?= ($active ?? '') === 'reviews' ? ' is-active' : '' ?>" href="/admin/reviews">
            <span class="nav__dot" aria-hidden="true"></span>
            Reviews
        </a>
        <span class="nav__item is-muted">Messages <em class="pill">Soon</em></span>

        <p class="nav__heading">Administration</p>
        <span class="nav__item is-muted">Team <em class="pill">Soon</em></span>
        <span class="nav__item is-muted">Settings <em class="pill">Soon</em></span>
    </nav>

    <div class="sidebar__foot">
        <span>Signed in as</span>
        <strong><?= e($signedIn['name'] ?? 'Staff member') ?></strong>
        <span class="role"><?= e(ucfirst((string) ($signedIn['role'] ?? 'staff'))) ?></span>
    </div>
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

    <main class="content">
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

</body>
</html>
