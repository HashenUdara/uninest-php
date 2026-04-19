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

    <section class="auth-panel" aria-labelledby="forgot-title">
        <div class="auth-panel-inner">
            <h1 id="forgot-title">Forgot your password?</h1>
            <p class="auth-subtitle">Enter your account email and we’ll send a secure password reset link.</p>

            <?php if ($success = get_flash('success')): ?>
                <div class="alert alert-success auth-alert"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($error = get_flash('error')): ?>
                <div class="alert alert-error auth-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/forgot-password" class="auth-form">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" placeholder="you@example.com" required autofocus>
                </div>

                <button type="submit" class="auth-submit">Send reset link</button>
            </form>

            <p class="auth-link">
                Remembered your password? <a href="/login">Sign in</a>
            </p>
        </div>
    </section>
</div>
