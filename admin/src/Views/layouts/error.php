<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Error') ?> · WIZ Beauty Salon</title>
    <link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<?php
// Public error layout.
//
// Deliberately session-free: an error can happen before anyone signs in, and a
// customer following a booking link must never be shown the staff area.
?>
<body class="auth-body">

<main class="auth-shell">
    <div class="auth-brand">
        <span class="brand-mark brand-mark--lg">WIZ</span>
        <span class="brand-sub">Beauty Salon</span>
    </div>

    <?= $content ?>

    <p class="auth-foot">
        <a href="/">wiz.arkeonethiopia.com</a> &middot; Addis Ababa
    </p>
</main>

</body>
</html>
