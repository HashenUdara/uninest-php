<?php

/**
 * Profile Module — Controllers
 */

function profile_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'moderator' => 'Moderator',
        'coordinator' => 'Coordinator',
        default => 'Student',
    };
}

function profile_current_user_or_abort(): array
{
    $userId = (int) auth_id();
    if ($userId <= 0) {
        abort(403, 'You must be logged in to access profile settings.');
    }

    $user = profile_user_with_context($userId);
    if (!$user) {
        abort(404, 'User account not found.');
    }

    return $user;
}

function profile_profile_form_values(array $profile): array
{
    $flashed = get_flash('profile_form_values');
    if (is_array($flashed)) {
        return [
            'name' => (string) ($flashed['name'] ?? ''),
            'email' => (string) ($flashed['email'] ?? ''),
            'academic_year' => (string) ($flashed['academic_year'] ?? ''),
        ];
    }

    return [
        'name' => (string) ($profile['name'] ?? ''),
        'email' => (string) ($profile['email'] ?? ''),
        'academic_year' => isset($profile['academic_year']) ? (string) $profile['academic_year'] : '',
    ];
}

function profile_validate_profile_input(int $userId): array
{
    $name = trim((string) request_input('name', ''));
    $email = trim((string) request_input('email', ''));
    $academicYearRaw = trim((string) request_input('academic_year', ''));

    $errors = [];

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name cannot exceed 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (mb_strlen($email) > 150) {
        $errors[] = 'Email cannot exceed 150 characters.';
    } elseif (profile_email_exists($email, $userId)) {
        $errors[] = 'An account with this email already exists.';
    }

    $academicYear = null;
    if ($academicYearRaw !== '') {
        if (!ctype_digit($academicYearRaw)) {
            $errors[] = 'Academic year must be a whole number between 1 and 8.';
        } else {
            $academicYear = (int) $academicYearRaw;
            if ($academicYear < 1 || $academicYear > 8) {
                $errors[] = 'Academic year must be between 1 and 8.';
            }
        }
    }

    return [
        'errors' => $errors,
        'form_values' => [
            'name' => $name,
            'email' => $email,
            'academic_year' => $academicYearRaw,
        ],
        'data' => [
            'name' => $name,
            'email' => $email,
            'academic_year' => $academicYear,
        ],
    ];
}

function profile_validate_password_input(?string $currentPasswordHash): array
{
    $currentPassword = (string) request_input('current_password', '');
    $newPassword = (string) request_input('new_password', '');
    $newPasswordConfirmation = (string) request_input('new_password_confirmation', '');

    $errors = [];

    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    }
    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    }
    if ($newPasswordConfirmation === '') {
        $errors[] = 'Please confirm your new password.';
    }

    if ($currentPasswordHash === null || $currentPasswordHash === '') {
        $errors[] = 'Unable to validate your current password.';
    }

    if (empty($errors) && !password_verify($currentPassword, (string) $currentPasswordHash)) {
        $errors[] = 'Current password is incorrect.';
    }

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if ($newPassword !== $newPasswordConfirmation) {
        $errors[] = 'New password confirmation does not match.';
    }

    if ($newPassword !== '' && $currentPasswordHash !== null && $currentPasswordHash !== '' && password_verify($newPassword, $currentPasswordHash)) {
        $errors[] = 'New password must be different from your current password.';
    }

    return [
        'errors' => $errors,
        'new_password' => $newPassword,
    ];
}

function profile_index(): void
{
    $user = profile_current_user_or_abort();

    view('profile::settings', [
        'profile_user' => $user,
        'role_label' => profile_role_label((string) ($user['role'] ?? 'student')),
        'form_values' => profile_profile_form_values($user),
    ], 'dashboard');
}

function profile_update_action(): void
{
    csrf_check();

    $user = profile_current_user_or_abort();
    $userId = (int) ($user['id'] ?? 0);

    $validated = profile_validate_profile_input($userId);

    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash('profile_form_values', $validated['form_values']);
        redirect('/dashboard/profile');
    }

    try {
        $updated = profile_update_user($userId, (array) ($validated['data'] ?? []));
    } catch (Throwable) {
        flash('error', 'Unable to update your profile right now. Please try again.');
        flash('profile_form_values', $validated['form_values']);
        redirect('/dashboard/profile');
    }

    auth_set_session_user_by_id($userId);

    if ($updated) {
        flash('success', 'Profile updated successfully.');
    } else {
        flash('success', 'No profile changes were detected.');
    }

    redirect('/dashboard/profile');
}

function profile_password_update_action(): void
{
    csrf_check();

    $user = profile_current_user_or_abort();
    $userId = (int) ($user['id'] ?? 0);

    $currentPasswordHash = profile_user_password_hash($userId);
    $validated = profile_validate_password_input($currentPasswordHash);

    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        redirect('/dashboard/profile');
    }

    $newPassword = (string) ($validated['new_password'] ?? '');
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $updated = profile_update_password_hash($userId, $newPasswordHash);
    } catch (Throwable) {
        flash('error', 'Unable to change your password right now. Please try again.');
        redirect('/dashboard/profile');
    }

    if (!$updated) {
        flash('error', 'Unable to change your password right now. Please try again.');
        redirect('/dashboard/profile');
    }

    auth_set_session_user_by_id($userId);

    flash('success', 'Password changed successfully.');
    redirect('/dashboard/profile');
}
