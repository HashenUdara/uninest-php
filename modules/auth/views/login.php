<div class="auth-layout">
    <aside class="auth-visual">
        <a href="/" class="auth-brand" aria-label="<?= e(config('app.name')) ?> Home">
            <img src="<?= asset('img/white-logo.png') ?>" alt="<?= e(config('app.name')) ?>" class="auth-brand-logo">
        </a>

        <div class="auth-quote">
            <p>"One platform for subjects, resources, quizzes, and Kuppi sessions."</p>
            <small>Built for Sri Lankan university peer learning communities.</small>
        </div>
    </aside>

    <section class="auth-panel" aria-labelledby="auth-title">
        <div class="auth-panel-inner">
            <h1 id="auth-title">Welcome back to <?= e(config('app.name')) ?></h1>
            <p class="auth-subtitle">Sign in to continue learning with your batch and campus community.</p>

            <?php if ($success = get_flash('success')): ?>
                <div class="alert alert-success auth-alert"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($error = get_flash('error')): ?>
                <div class="alert alert-error auth-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/login" class="auth-form">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" placeholder="you@example.com" required autofocus>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <p class="auth-meta-link"><a href="/forgot-password">Forgot password?</a></p>

                <button type="submit" class="auth-submit">Log in</button>
            </form>

            <p class="auth-link">
                Don't have an account? <a href="/register">Sign up</a>
            </p>
        </div>
    </section>
</div>
