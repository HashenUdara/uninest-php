<div class="auth-layout">
    <aside class="auth-visual">
        <a href="/" class="auth-brand" aria-label="<?= e(config('app.name')) ?> Home">
            <img src="<?= asset('img/white-logo.png') ?>" alt="<?= e(config('app.name')) ?>" class="auth-brand-logo">
        </a>

        <div class="auth-quote">
            <p>"Simply all the tools my team and I need."</p>
            <small>Replace this testimonial text later.</small>
        </div>
    </aside>

    <section class="auth-panel" aria-labelledby="reset-title">
        <div class="auth-panel-inner">
            <h1 id="reset-title">Reset password</h1>
            <p class="auth-subtitle">Create a new password for your account.</p>

            <?php if ($error = get_flash('error')): ?>
                <div class="alert alert-error auth-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$is_valid): ?>
                <div class="alert alert-warning auth-alert">This reset link is invalid or expired.</div>
                <p class="auth-link" style="text-align:left; margin-top: 6px;">
                    <a href="/forgot-password">Request a new reset link</a>
                </p>
            <?php else: ?>
                <form method="POST" action="/reset-password" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="auth-field">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6" autofocus>
                    </div>

                    <div class="auth-field">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" required minlength="6">
                    </div>

                    <button type="submit" class="auth-submit">Update password</button>
                </form>
            <?php endif; ?>

            <p class="auth-link">
                Back to <a href="/login">Sign in</a>
            </p>
        </div>
    </section>
</div>
