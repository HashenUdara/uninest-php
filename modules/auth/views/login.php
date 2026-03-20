<div class="auth-layout">
    <aside class="auth-visual">
        <a href="/" class="auth-brand" aria-label="<?= e(config('app.name')) ?> Home">
            <span class="auth-brand-mark" aria-hidden="true"></span>
            <span class="auth-brand-text">Logo</span>
        </a>

        <div class="auth-quote">
            <p>"Simply all the tools my team and I need."</p>
            <small>Replace this testimonial text later.</small>
        </div>
    </aside>

    <section class="auth-panel" aria-labelledby="auth-title">
        <div class="auth-panel-inner">
            <h1 id="auth-title">Welcome back to <?= e(config('app.name')) ?></h1>
            <p class="auth-subtitle">Sign in to continue to your dashboard and learning workspace.</p>

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

                <p class="auth-meta-link">Forgot password? Contact your administrator.</p>

                <label class="auth-toggle-row" for="remember">
                    <span>Remember sign in details</span>
                    <span class="auth-toggle">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <span class="auth-toggle-ui" aria-hidden="true"></span>
                    </span>
                </label>

                <button type="submit" class="auth-submit">Log in</button>
            </form>

            <div class="auth-divider"><span>OR</span></div>

            <button type="button" class="auth-social-btn" disabled aria-disabled="true">
                <span class="auth-google-icon" aria-hidden="true">G</span>
                <span>Continue with Google (Coming soon)</span>
            </button>

            <p class="auth-link">
                Don't have an account? <a href="/register">Sign up</a>
            </p>
        </div>
    </section>
</div>
