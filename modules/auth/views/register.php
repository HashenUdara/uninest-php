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
            <h1 id="auth-title">Create your <?= e(config('app.name')) ?> account</h1>
            <p class="auth-subtitle">Set up your profile to access courses, subjects, and your student dashboard.</p>

            <?php if ($error = get_flash('error')): ?>
                <div class="alert alert-error auth-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/register" class="auth-form">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= old('name') ?>" placeholder="Your full name" required autofocus>
                </div>

                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>" placeholder="you@example.com" required>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6">
                </div>

                <div class="auth-field">
                    <label for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" required minlength="6">
                </div>

                <button type="submit" class="auth-submit">Create account</button>
            </form>

            <p class="auth-link">
                Already have an account? <a href="/login">Sign in</a>
            </p>
        </div>
    </section>
</div>
