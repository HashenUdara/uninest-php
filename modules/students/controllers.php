<?php

/**
 * Students Module — Controllers
 */

function students_index(): void
{
    $role = user_role();

    if ($role === 'admin') {
        $students = students_admin_all();
        $isAdmin = true;
        $moderatorBatch = null;
    } elseif ($role === 'moderator') {
        $students = students_moderator_batch_all((int) auth_id());
        $isAdmin = false;
        $moderatorBatch = onboarding_find_moderator_batch((int) auth_id());
    } else {
        abort(403, 'You do not have permission to access this page.');
    }

    view('students::index', [
        'students' => $students,
        'is_admin' => $isAdmin,
        'moderator_batch' => $moderatorBatch,
    ], 'dashboard');
}

function students_create_form(): void
{
    middleware_exact_role('admin');

    view('students::create', [
        'universities' => universities_active(),
        'batches' => onboarding_approved_batches(),
    ], 'dashboard');
}

function students_store(): void
{
    csrf_check();
    middleware_exact_role('admin');

    $name = trim(request_input('name', ''));
    $email = trim(request_input('email', ''));
    $password = request_input('password', '');
    $passwordConfirmation = request_input('password_confirmation', '');
    $academicYear = (int) request_input('academic_year', 0);
    $universityId = (int) request_input('university_id', 0);
    $batchId = (int) request_input('batch_id', 0);

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (students_email_exists($email)) $errors[] = 'An account with this email already exists.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $passwordConfirmation) $errors[] = 'Passwords do not match.';
    if ($academicYear < 1 || $academicYear > 8) $errors[] = 'Academic year must be between 1 and 8.';
    if ($universityId <= 0 || !university_is_active($universityId)) $errors[] = 'Select a valid university.';

    $batch = onboarding_batch_by_id($batchId);
    if (!$batch || $batch['status'] !== 'approved') {
        $errors[] = 'Select a valid approved batch.';
    } elseif ((int) $batch['university_id'] !== $universityId) {
        $errors[] = 'Selected university does not match the selected batch.';
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/students/create');
    }

    try {
        students_create_admin([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'academic_year' => $academicYear,
            'university_id' => $universityId,
            'batch_id' => $batchId,
        ], (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to create student right now. Please try again.');
        flash_old_input();
        redirect('/students/create');
    }

    clear_old_input();
    flash('success', 'Student created successfully.');
    redirect('/students');
}

function students_edit_form(string $id): void
{
    middleware_exact_role('admin');

    $studentId = (int) $id;
    $student = students_find_admin($studentId);

    if (!$student) {
        abort(404, 'Student not found.');
    }

    view('students::edit', [
        'student' => $student,
        'universities' => universities_active(),
        'batches' => onboarding_approved_batches(),
    ], 'dashboard');
}

function students_update_action(string $id): void
{
    csrf_check();
    middleware_exact_role('admin');

    $studentId = (int) $id;
    $student = students_find_admin($studentId);

    if (!$student) {
        abort(404, 'Student not found.');
    }

    $name = trim(request_input('name', ''));
    $email = trim(request_input('email', ''));
    $academicYear = (int) request_input('academic_year', 0);
    $universityId = (int) request_input('university_id', 0);
    $batchId = (int) request_input('batch_id', 0);

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (students_email_exists($email, $studentId)) $errors[] = 'An account with this email already exists.';
    if ($academicYear < 1 || $academicYear > 8) $errors[] = 'Academic year must be between 1 and 8.';
    if ($universityId <= 0 || !university_is_active($universityId)) $errors[] = 'Select a valid university.';

    $batch = onboarding_batch_by_id($batchId);
    if (!$batch || $batch['status'] !== 'approved') {
        $errors[] = 'Select a valid approved batch.';
    } elseif ((int) $batch['university_id'] !== $universityId) {
        $errors[] = 'Selected university does not match the selected batch.';
    }

    $lockedBatchId = (int) ($student['first_approved_batch_id'] ?? 0);
    if ($lockedBatchId > 0 && $lockedBatchId !== $batchId) {
        $errors[] = 'Batch cannot be changed after first approved assignment.';
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/students/' . $studentId . '/edit');
    }

    try {
        if (!students_update_admin($studentId, [
            'name' => $name,
            'email' => $email,
            'academic_year' => $academicYear,
            'university_id' => $universityId,
            'batch_id' => $batchId,
        ], (int) auth_id())) {
            flash('error', 'Unable to update this student. Batch assignment is locked.');
            flash_old_input();
            redirect('/students/' . $studentId . '/edit');
        }
    } catch (Throwable) {
        flash('error', 'Unable to update student right now. Please try again.');
        flash_old_input();
        redirect('/students/' . $studentId . '/edit');
    }

    clear_old_input();
    flash('success', 'Student updated successfully.');
    redirect('/students');
}

function students_delete_action(string $id): void
{
    csrf_check();
    middleware_exact_role('admin');

    $studentId = (int) $id;
    $student = students_find_admin($studentId);

    if (!$student) {
        abort(404, 'Student not found.');
    }

    if (students_delete_admin($studentId) < 1) {
        flash('error', 'Unable to delete this student.');
        redirect('/students');
    }

    flash('success', 'Student "' . $student['name'] . '" deleted.');
    redirect('/students');
}

function students_remove_action(string $id): void
{
    csrf_check();
    middleware_exact_role('moderator');

    $studentId = (int) $id;
    $student = students_find_for_moderator_batch($studentId, (int) auth_id());

    if (!$student) {
        abort(404, 'Student not found.');
    }

    if (!students_moderator_remove_from_batch($studentId, (int) auth_id())) {
        flash('error', 'Unable to remove this student from your batch.');
        redirect('/students');
    }

    flash('success', 'Student "' . $student['name'] . '" removed from your batch.');
    redirect('/students');
}
