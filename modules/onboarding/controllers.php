<?php

/**
 * Onboarding Module — Controllers
 */

function onboarding_status(): void
{
    $user = auth_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        redirect('/login');
    }

    if (onboarding_complete_for_user($user)) {
        redirect('/dashboard');
    }

    $data = [
        'user'         => $user,
        'role'         => user_role(),
        'batch'        => null,
        'request'      => null,
        'locked_batch' => null,
        'universities' => universities_active(),
    ];

    if (user_role() === 'moderator') {
        $data['batch'] = onboarding_find_moderator_batch((int) auth_id());
    } elseif (user_role() === 'student') {
        $data['request'] = onboarding_find_student_request((int) auth_id());
        $lockedBatchId = (int) ($user['first_approved_batch_id'] ?? 0);
        if ($lockedBatchId > 0) {
            $data['locked_batch'] = onboarding_batch_by_id($lockedBatchId);
        }
    } else {
        redirect('/dashboard');
    }

    view('onboarding::status', $data, 'dashboard');
}

function onboarding_moderator_resubmit(): void
{
    csrf_check();

    middleware_exact_role('moderator');

    $moderatorId = (int) auth_id();
    $batch       = onboarding_find_moderator_batch($moderatorId);
    if (!$batch) {
        flash('error', 'Batch request not found.');
        redirect('/onboarding');
    }

    if ($batch['status'] !== 'rejected') {
        flash('error', 'Only rejected requests can be resubmitted.');
        redirect('/onboarding');
    }

    $name         = trim(request_input('batch_name', ''));
    $program      = trim(request_input('program', ''));
    $intakeYear   = (int) request_input('intake_year', 0);
    $universityId = (int) request_input('university_id', 0);

    $errors = [];
    if ($name === '') $errors[] = 'Batch name is required.';
    if ($program === '') $errors[] = 'Program is required.';
    if ($intakeYear < 2000 || $intakeYear > 2100) $errors[] = 'Intake year is invalid.';
    if ($universityId <= 0 || !university_is_active($universityId)) $errors[] = 'Select a valid university.';

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/onboarding');
    }

    onboarding_resubmit_moderator_batch_request($moderatorId, [
        'name'          => $name,
        'program'       => $program,
        'intake_year'   => $intakeYear,
        'university_id' => $universityId,
    ]);

    db_query('UPDATE users SET batch_id = NULL WHERE id = ?', [$moderatorId]);
    auth_set_session_user_by_id($moderatorId);
    clear_old_input();

    flash('success', 'Batch request resubmitted for admin approval.');
    redirect('/onboarding');
}

function onboarding_student_resubmit(): void
{
    csrf_check();

    middleware_exact_role('student');

    $studentId = (int) auth_id();
    $request   = onboarding_find_student_request($studentId);
    if (!$request) {
        flash('error', 'Join request not found.');
        redirect('/onboarding');
    }

    if ($request['status'] !== 'rejected') {
        flash('error', 'Only rejected requests can be resubmitted.');
        redirect('/onboarding');
    }

    if (!empty(auth_user()['batch_id'])) {
        flash('error', 'You are already assigned to a batch.');
        redirect('/dashboard');
    }

    $batchCode = strtoupper(trim(request_input('batch_code', '')));
    if ($batchCode === '') {
        flash('error', 'Batch ID is required.');
        flash_old_input();
        redirect('/onboarding');
    }

    $batch = onboarding_find_batch_by_code($batchCode);
    if (!$batch) {
        flash('error', 'Invalid or inactive batch ID.');
        flash_old_input();
        redirect('/onboarding');
    }

    $lockedBatchId = (int) (auth_user()['first_approved_batch_id'] ?? 0);
    if ($lockedBatchId > 0 && (int) $batch['id'] !== $lockedBatchId) {
        $lockedBatch = onboarding_batch_by_id($lockedBatchId);
        $lockedBatchCode = trim((string) ($lockedBatch['batch_code'] ?? ''));
        flash('error', $lockedBatchCode !== ''
            ? 'You can only reapply to your original batch: ' . $lockedBatchCode . '.'
            : 'You can only reapply to your original batch.');
        flash_old_input();
        redirect('/onboarding');
    }

    onboarding_resubmit_student_request($studentId, (int) $batch['id']);
    clear_old_input();

    flash('success', 'Join request resubmitted to the moderator.');
    redirect('/onboarding');
}

function admin_batch_requests_index(): void
{
    middleware_exact_role('admin');

    $requests = onboarding_admin_batch_requests();
    view('onboarding::admin_batch_requests', ['requests' => $requests], 'dashboard');
}

function admin_batch_request_approve(string $id): void
{
    csrf_check();
    middleware_exact_role('admin');

    $batchId = (int) $id;
    $batch   = onboarding_admin_find_batch_request($batchId);
    if (!$batch) {
        abort(404, 'Batch request not found.');
    }
    if ($batch['status'] !== 'pending') {
        flash('error', 'Only pending batch requests can be approved.');
        redirect('/admin/batch-requests');
    }

    onboarding_admin_approve_batch_request($batchId, (int) auth_id());
    flash('success', 'Batch request approved successfully.');
    redirect('/admin/batch-requests');
}

function admin_batch_request_reject(string $id): void
{
    csrf_check();
    middleware_exact_role('admin');

    $batchId = (int) $id;
    $batch   = onboarding_admin_find_batch_request($batchId);
    if (!$batch) {
        abort(404, 'Batch request not found.');
    }
    if ($batch['status'] !== 'pending') {
        flash('error', 'Only pending batch requests can be rejected.');
        redirect('/admin/batch-requests');
    }

    $reason = trim(request_input('rejection_reason', ''));
    onboarding_admin_reject_batch_request($batchId, (int) auth_id(), $reason !== '' ? $reason : null);

    flash('success', 'Batch request rejected.');
    redirect('/admin/batch-requests');
}

function moderator_join_requests_index(): void
{
    middleware_exact_role('moderator');

    $requests = onboarding_moderator_student_requests((int) auth_id());
    view('onboarding::moderator_join_requests', ['requests' => $requests], 'dashboard');
}

function moderator_join_request_approve(string $id): void
{
    csrf_check();
    middleware_exact_role('moderator');

    $requestId = (int) $id;
    $request   = onboarding_find_student_request_for_moderator($requestId, (int) auth_id());
    if (!$request) {
        abort(404, 'Join request not found.');
    }
    if ($request['status'] !== 'pending') {
        flash('error', 'Only pending requests can be approved.');
        redirect('/moderator/join-requests');
    }

    if (!onboarding_approve_student_request($requestId, (int) auth_id(), 'moderator')) {
        flash('error', 'Unable to approve this request. Student may already belong to another batch.');
        redirect('/moderator/join-requests');
    }

    flash('success', 'Student request approved.');
    redirect('/moderator/join-requests');
}

function moderator_join_request_reject(string $id): void
{
    csrf_check();
    middleware_exact_role('moderator');

    $requestId = (int) $id;
    $request   = onboarding_find_student_request_for_moderator($requestId, (int) auth_id());
    if (!$request) {
        abort(404, 'Join request not found.');
    }
    if ($request['status'] !== 'pending') {
        flash('error', 'Only pending requests can be rejected.');
        redirect('/moderator/join-requests');
    }

    $reason = trim(request_input('rejection_reason', ''));
    onboarding_reject_student_request($requestId, (int) auth_id(), 'moderator', $reason !== '' ? $reason : null);

    flash('success', 'Student request rejected.');
    redirect('/moderator/join-requests');
}

function admin_student_requests_index(): void
{
    middleware_exact_role('admin');

    $requests = onboarding_admin_student_requests();
    view('onboarding::admin_student_requests', ['requests' => $requests], 'dashboard');
}

function admin_student_request_approve(string $id): void
{
    csrf_check();
    middleware_exact_role('admin');

    $requestId = (int) $id;
    $request   = onboarding_find_student_request_by_id($requestId);
    if (!$request) {
        abort(404, 'Join request not found.');
    }
    if ($request['status'] !== 'pending') {
        flash('error', 'Only pending requests can be approved.');
        redirect('/admin/student-requests');
    }

    if (!onboarding_approve_student_request($requestId, (int) auth_id(), 'admin')) {
        flash('error', 'Unable to approve this request. Student may already belong to another batch.');
        redirect('/admin/student-requests');
    }

    flash('success', 'Student request approved by admin.');
    redirect('/admin/student-requests');
}

function admin_student_request_reject(string $id): void
{
    csrf_check();
    middleware_exact_role('admin');

    $requestId = (int) $id;
    $request   = onboarding_find_student_request_by_id($requestId);
    if (!$request) {
        abort(404, 'Join request not found.');
    }
    if ($request['status'] !== 'pending') {
        flash('error', 'Only pending requests can be rejected.');
        redirect('/admin/student-requests');
    }

    $reason = trim(request_input('rejection_reason', ''));
    onboarding_reject_student_request($requestId, (int) auth_id(), 'admin', $reason !== '' ? $reason : null);

    flash('success', 'Student request rejected by admin.');
    redirect('/admin/student-requests');
}
