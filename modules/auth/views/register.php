<?php
$inviteBatchCode = strtoupper(trim((string) request_input('batch_code', '')));
$inviteRole = trim((string) request_input('role', ''));
$defaultRole = in_array($inviteRole, ['student', 'moderator'], true) ? $inviteRole : 'student';
?>

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

    <section class="auth-panel" aria-labelledby="auth-title">
        <div class="auth-panel-inner auth-register-panel">
            <h1 id="auth-title">Create your <?= e(config('app.name')) ?> account</h1>
            <p class="auth-subtitle">Join your university batch and start peer learning sessions.</p>

            <?php if ($error = get_flash('error')): ?>
                <div class="alert alert-error auth-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/register" class="auth-form" id="register-form">
                <?= csrf_field() ?>

                <div class="auth-grid auth-grid-2">
                    <div class="auth-field">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= old('name') ?>" placeholder="Your full name" required autofocus>
                    </div>

                    <div class="auth-field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= old('email') ?>" placeholder="you@example.com" required>
                    </div>
                </div>

                <div class="auth-grid auth-grid-2">
                    <div class="auth-field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6">
                    </div>

                    <div class="auth-field">
                        <label for="password_confirmation">Confirm Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" required minlength="6">
                    </div>
                </div>

                <div class="auth-grid auth-grid-2">
                    <div class="auth-field">
                        <label for="academic_year">Academic Year</label>
                        <?php $selectedAcademicYear = old('academic_year', '1'); ?>
                        <select id="academic_year" name="academic_year" required>
                            <?php for ($year = 1; $year <= 8; $year++): ?>
                                <option value="<?= $year ?>" <?= $selectedAcademicYear === (string) $year ? 'selected' : '' ?>>
                                    Year <?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="auth-field">
                        <label for="university_id">University (Sri Lanka)</label>
                        <?php $selectedUniversity = old('university_id'); ?>
                        <select id="university_id" name="university_id" required>
                            <option value="">Select university</option>
                            <?php foreach ($universities as $university): ?>
                                <option value="<?= (int) $university['id'] ?>" <?= $selectedUniversity === (string) $university['id'] ? 'selected' : '' ?>>
                                    <?= e($university['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php $selectedRole = old('role', $defaultRole); ?>
                <div class="auth-role-group">
                    <p class="auth-role-title">Select Your Role</p>
                    <div class="auth-role-grid">
                        <label class="auth-role-option <?= $selectedRole === 'student' ? 'active' : '' ?>" data-role-option="student">
                            <input type="radio" name="role" value="student" <?= $selectedRole === 'student' ? 'checked' : '' ?>>
                            <span class="auth-role-icon">🎓</span>
                            <strong>Student</strong>
                            <small>Join a batch and access peer-learning content.</small>
                        </label>

                        <label class="auth-role-option <?= $selectedRole === 'moderator' ? 'active' : '' ?>" data-role-option="moderator">
                            <input type="radio" name="role" value="moderator" <?= $selectedRole === 'moderator' ? 'checked' : '' ?>>
                            <span class="auth-role-icon">🛡️</span>
                            <strong>Moderator (Batch Rep)</strong>
                            <small>Create your batch and approve student join requests.</small>
                        </label>
                    </div>
                </div>

                <div id="moderator-fields" class="auth-role-fields <?= $selectedRole === 'moderator' ? '' : 'hidden' ?>">
                    <div class="auth-grid auth-grid-2">
                        <div class="auth-field">
                            <label for="batch_name">Batch Name</label>
                            <input type="text" id="batch_name" name="batch_name" value="<?= old('batch_name') ?>" placeholder="e.g. CS 24/25" maxlength="150">
                        </div>

                        <div class="auth-field">
                            <label for="program">Program</label>
                            <input type="text" id="program" name="program" value="<?= old('program') ?>" placeholder="e.g. BSc Computer Science" maxlength="150">
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="intake_year">Intake Year</label>
                        <input type="number" id="intake_year" name="intake_year" value="<?= old('intake_year', (string) date('Y')) ?>" min="2000" max="2100">
                    </div>
                </div>

                <div id="student-fields" class="auth-role-fields <?= $selectedRole === 'student' ? '' : 'hidden' ?>">
                    <div class="auth-field">
                        <label for="batch_code">Active Batch ID</label>
                        <input type="text" id="batch_code" name="batch_code" value="<?= old('batch_code', $inviteBatchCode) ?>" placeholder="e.g. BATCH-AB12CD" maxlength="20">
                    </div>
                </div>

                <button type="submit" class="auth-submit" id="register-submit">Sign up</button>
            </form>

            <p class="auth-link">
                Already have an account? <a href="/login">Sign in</a>
            </p>
        </div>
    </section>
</div>

<script>
    (function () {
        const roleInputs = document.querySelectorAll('input[name="role"]');
        const studentFields = document.getElementById('student-fields');
        const moderatorFields = document.getElementById('moderator-fields');
        const submitBtn = document.getElementById('register-submit');

        if (!roleInputs.length || !studentFields || !moderatorFields || !submitBtn) return;

        function updateRoleUI(selectedRole) {
            document.querySelectorAll('[data-role-option]').forEach((el) => {
                el.classList.toggle('active', el.getAttribute('data-role-option') === selectedRole);
            });

            const isModerator = selectedRole === 'moderator';
            moderatorFields.classList.toggle('hidden', !isModerator);
            studentFields.classList.toggle('hidden', isModerator);
            submitBtn.textContent = isModerator ? 'Create moderator account' : 'Create student account';
        }

        roleInputs.forEach((input) => {
            input.addEventListener('change', () => updateRoleUI(input.value));
            if (input.checked) updateRoleUI(input.value);
        });
    })();
</script>
