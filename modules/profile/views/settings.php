<?php
$profileUser = (array) ($profile_user ?? []);
$formValues = (array) ($form_values ?? []);
$roleLabel = (string) ($role_label ?? 'Student');

$nameValue = (string) ($formValues['name'] ?? ($profileUser['name'] ?? ''));
$emailValue = (string) ($formValues['email'] ?? ($profileUser['email'] ?? ''));
$academicYearValue = (string) ($formValues['academic_year'] ?? (isset($profileUser['academic_year']) ? (string) $profileUser['academic_year'] : ''));

$displayUniversity = trim((string) ($profileUser['university_name'] ?? ''));
$displayBatchName = trim((string) ($profileUser['batch_name'] ?? ''));
$displayBatchCode = trim((string) ($profileUser['batch_code'] ?? ''));

$joinedDateRaw = (string) ($profileUser['created_at'] ?? '');
$joinedDateLabel = 'Not available';
if ($joinedDateRaw !== '') {
    $timestamp = strtotime($joinedDateRaw);
    if ($timestamp !== false) {
        $joinedDateLabel = date('M d, Y', $timestamp);
    }
}

$updatedDateRaw = (string) ($profileUser['updated_at'] ?? '');
$updatedDateLabel = 'Not available';
if ($updatedDateRaw !== '') {
    $timestamp = strtotime($updatedDateRaw);
    if ($timestamp !== false) {
        $updatedDateLabel = date('M d, Y, h:i A', $timestamp);
    }
}
?>

<div class="page-header profile-page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e($roleLabel) ?> / Account</p>
        <h1>Profile Settings</h1>
        <p class="page-subtitle">Manage your account details and keep your sign-in credentials secure.</p>
    </div>
</div>

<section class="profile-settings-layout">
    <div class="profile-settings-main">
        <article class="card profile-card">
            <div class="card-body">
                <div class="profile-card-head">
                    <h2><?= ui_lucide_icon('user-round') ?> Profile Information</h2>
                    <p>Update your public account details used across the platform.</p>
                </div>

                <form method="POST" action="/dashboard/profile" class="profile-form-grid" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group form-group-span-2">
                        <label for="profile_name">Full Name *</label>
                        <input
                            type="text"
                            id="profile_name"
                            name="name"
                            maxlength="100"
                            required
                            value="<?= e($nameValue) ?>"
                            placeholder="e.g., Nimal Perera"
                        >
                    </div>

                    <div class="form-group form-group-span-2">
                        <label for="profile_email">Email Address *</label>
                        <input
                            type="email"
                            id="profile_email"
                            name="email"
                            maxlength="150"
                            required
                            value="<?= e($emailValue) ?>"
                            placeholder="name@uninest.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="profile_academic_year">Academic Year</label>
                        <select id="profile_academic_year" name="academic_year">
                            <option value="">Not set</option>
                            <?php for ($year = 1; $year <= 8; $year++): ?>
                                <option value="<?= $year ?>" <?= $academicYearValue === (string) $year ? 'selected' : '' ?>>Year <?= $year ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="profile-form-actions form-group-span-2">
                        <button type="submit" class="btn btn-primary">
                            <?= ui_lucide_icon('save') ?> Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>
        </article>

        <article class="card profile-card">
            <div class="card-body">
                <div class="profile-card-head">
                    <h2><?= ui_lucide_icon('shield-check') ?> Security</h2>
                    <p>Use a strong password and update it regularly to keep your account secure.</p>
                </div>

                <form method="POST" action="/dashboard/profile/password" class="profile-form-grid" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group form-group-span-2">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                        <small class="text-muted">Minimum 8 characters.</small>
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirmation">Confirm New Password *</label>
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation" minlength="8" autocomplete="new-password" required>
                    </div>

                    <div class="profile-form-actions form-group-span-2">
                        <button type="submit" class="btn btn-primary">
                            <?= ui_lucide_icon('key-round') ?> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </article>
    </div>

    <aside class="profile-settings-side">
        <article class="card profile-side-card">
            <div class="card-body">
                <div class="profile-side-user">
                    <span class="profile-side-avatar <?= e(ui_avatar_tone_class((string) ($profileUser['email'] ?? $profileUser['name'] ?? ''))) ?>">
                        <?= e(ui_initials((string) ($profileUser['name'] ?? 'User'))) ?>
                    </span>
                    <div>
                        <h3><?= e((string) ($profileUser['name'] ?? 'User')) ?></h3>
                        <p><?= e((string) ($profileUser['email'] ?? '')) ?></p>
                    </div>
                </div>

                <ul class="profile-side-metrics">
                    <li>
                        <span>Role</span>
                        <strong><?= e($roleLabel) ?></strong>
                    </li>
                    <li>
                        <span>University</span>
                        <strong><?= e($displayUniversity !== '' ? $displayUniversity : 'Not assigned') ?></strong>
                    </li>
                    <li>
                        <span>Batch</span>
                        <strong>
                            <?php if ($displayBatchCode !== ''): ?>
                                <?= e($displayBatchCode) ?>
                            <?php elseif ($displayBatchName !== ''): ?>
                                <?= e($displayBatchName) ?>
                            <?php else: ?>
                                Not assigned
                            <?php endif; ?>
                        </strong>
                    </li>
                    <li>
                        <span>Member Since</span>
                        <strong><?= e($joinedDateLabel) ?></strong>
                    </li>
                    <li>
                        <span>Last Updated</span>
                        <strong><?= e($updatedDateLabel) ?></strong>
                    </li>
                </ul>

                <article class="profile-side-note" role="note">
                    <h4><?= ui_lucide_icon('info') ?> Account Notes</h4>
                    <p>Batch and university assignments are managed by onboarding and admin workflows.</p>
                </article>
            </div>
        </article>
    </aside>
</section>
