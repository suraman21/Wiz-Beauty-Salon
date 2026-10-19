<section class="card card--auth">
    <h1 class="card__title">Sign in</h1>
    <p class="card__sub">Use the email address and password for your staff account.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/login" novalidate>
        <?= csrf_field() ?>

        <?php if (($next ?? '') !== ''): ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
        <?php endif; ?>

        <div class="field">
            <label for="email">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= e(old('email')) ?>"
                autocomplete="username"
                inputmode="email"
                spellcheck="false"
                required
                autofocus
            >
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn--primary btn--block">Sign in</button>
    </form>

    <p class="card__note">Forgotten your password? Ask an administrator to reset it for you.</p>
</section>
