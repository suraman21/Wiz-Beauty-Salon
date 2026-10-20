<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?> · WIZ Beauty Salon</title>
    <link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
</head>
<body class="auth-body">

<main class="auth-shell">
    <div class="auth-brand">
        <img class="brand-logo brand-logo--lg" src="<?= e(asset('wiz-logo.png')) ?>" alt="WIZ Beauty Salon" width="76" height="76">
    </div>

    <?= $content ?>

    <p class="auth-foot">Staff area &middot; Authorised access only</p>
</main>

</body>
</html>
