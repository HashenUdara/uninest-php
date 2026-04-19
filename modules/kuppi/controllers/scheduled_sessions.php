<?php

function kuppi_schedule_success(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to view this page.');
    }

    $sessionId = (int) request_input('id', 0);
    $session = null;
    if ($sessionId > 0) {
        $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    }

    view('kuppi::schedule_success', [
        'session' => $session,
    ], 'dashboard');
}

function kuppi_scheduled_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $userBatchId = (int) (auth_user()['batch_id'] ?? 0);
    $adminBatchId = $isAdmin ? (int) request_input('batch_id', 0) : 0;
    $searchQuery = trim((string) request_input('q', ''));
    $subjectId = (int) request_input('subject_id', 0);
    $statusFilter = trim((string) request_input('status', ''));

    $sessions = kuppi_scheduled_sessions_for_scope(
        $role,
        $userBatchId,
        $searchQuery,
        $subjectId,
        $statusFilter,
        $adminBatchId
    );

    $subjectOptions = $isAdmin
        ? ($adminBatchId > 0 ? kuppi_subject_options_for_batch($adminBatchId) : [])
        : kuppi_subject_options_for_batch($userBatchId);

    view('kuppi::scheduled_index', [
        'sessions' => $sessions,
        'selected_search_query' => $searchQuery,
        'selected_subject_id' => $subjectId,
        'selected_status' => $statusFilter,
        'subject_options' => $subjectOptions,
        'status_options' => kuppi_scheduled_statuses(),
        'is_admin' => $isAdmin,
        'admin_batch_id' => $adminBatchId,
        'batch_options' => $isAdmin ? kuppi_batch_options_for_admin() : [],
    ], 'dashboard');
}

function kuppi_scheduled_show(string $id): void
{
    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    $hosts = kuppi_scheduled_hosts_for_session($sessionId);

    view('kuppi::scheduled_show', [
        'session' => $session,
        'hosts' => $hosts,
        'can_manage' => kuppi_user_can_manage_scheduled_session($session),
        'availability_options' => kuppi_conductor_availability_options(),
    ], 'dashboard');
}

function kuppi_scheduled_edit_form(string $id): void
{
    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to edit this scheduled session.');
    }

    if ((string) ($session['status'] ?? '') === 'cancelled') {
        flash('warning', 'Cancelled sessions cannot be edited.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    $draftLike = [
        'mode' => (int) ($session['request_id'] ?? 0) > 0 ? 'request' : 'manual',
        'request_id' => (int) ($session['request_id'] ?? 0),
        'batch_id' => (int) ($session['batch_id'] ?? 0),
    ];
    $candidates = kuppi_schedule_host_candidates($draftLike);
    $hosts = kuppi_scheduled_hosts_for_session($sessionId);
    $selectedHostIds = array_values(array_filter(array_map(
        static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
        $hosts
    ), static fn(int $id): bool => $id > 0));

    view('kuppi::scheduled_edit', [
        'session' => $session,
        'hosts' => $hosts,
        'candidates' => $candidates,
        'selected_host_ids' => $selectedHostIds,
        'status_options' => ['scheduled', 'completed'],
        'availability_options' => kuppi_conductor_availability_options(),
    ], 'dashboard');
}

function kuppi_scheduled_update_action(string $id): void
{
    csrf_check();

    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to update this scheduled session.');
    }

    if ((string) ($session['status'] ?? '') === 'cancelled') {
        flash('error', 'Cancelled sessions cannot be edited.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    $draft = [
        'mode' => (int) ($session['request_id'] ?? 0) > 0 ? 'request' : 'manual',
        'batch_id' => (int) ($session['batch_id'] ?? 0),
        'subject_id' => (int) ($session['subject_id'] ?? 0),
        'request_id' => (int) ($session['request_id'] ?? 0),
        'title' => (string) ($session['title'] ?? ''),
        'description' => (string) ($session['description'] ?? ''),
    ];

    $_POST['batch_id'] = (string) ($session['batch_id'] ?? 0);
    $_POST['subject_id'] = (string) ($session['subject_id'] ?? 0);
    $validation = kuppi_schedule_validate_set_input($draft);
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    $candidateMap = kuppi_schedule_candidate_map(kuppi_schedule_host_candidates($draft));
    $selection = kuppi_schedule_selected_hosts_from_input($candidateMap);
    if (!empty($selection['errors'])) {
        flash('error', implode(' ', $selection['errors']));
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    $status = trim((string) request_input('status', 'scheduled'));
    if (!in_array($status, ['scheduled', 'completed'], true)) {
        $status = 'scheduled';
    }

    try {
        $updated = kuppi_scheduled_update_with_hosts($sessionId, [
            'title' => (string) $validation['data']['title'],
            'description' => (string) $validation['data']['description'],
            'session_date' => (string) $validation['data']['session_date'],
            'start_time' => (string) $validation['data']['start_time'],
            'end_time' => (string) $validation['data']['end_time'],
            'duration_minutes' => (int) $validation['data']['duration_minutes'],
            'max_attendees' => (int) $validation['data']['max_attendees'],
            'location_type' => (string) $validation['data']['location_type'],
            'location_text' => (string) $validation['data']['location_text'],
            'meeting_link' => (string) $validation['data']['meeting_link'],
            'notes' => (string) $validation['data']['notes'],
            'status' => $status,
        ], $selection['hosts']);
    } catch (Throwable) {
        flash('error', 'Unable to update scheduled session right now.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    if (!$updated) {
        flash('error', 'Unable to update this scheduled session. It may conflict with another active request session.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    $updatedSession = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if ($updatedSession) {
        kuppi_schedule_notify($updatedSession, kuppi_scheduled_hosts_for_session($sessionId), 'updated');
    }

    flash('success', 'Scheduled session updated.');
    redirect('/dashboard/kuppi/scheduled/' . $sessionId);
}

function kuppi_scheduled_cancel_action(string $id): void
{
    csrf_check();

    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to cancel this scheduled session.');
    }

    if ((string) ($session['status'] ?? '') === 'cancelled') {
        flash('warning', 'Session is already cancelled.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    try {
        $ok = kuppi_scheduled_cancel($sessionId, (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to cancel this scheduled session right now.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    if (!$ok) {
        flash('error', 'Unable to cancel this scheduled session.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    $updatedSession = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if ($updatedSession) {
        kuppi_schedule_notify($updatedSession, kuppi_scheduled_hosts_for_session($sessionId), 'cancelled');
    }

    flash('success', 'Scheduled session cancelled.');
    redirect('/dashboard/kuppi/scheduled/' . $sessionId);
}

function kuppi_scheduled_delete_action(string $id): void
{
    csrf_check();

    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to delete this scheduled session.');
    }

    $hosts = kuppi_scheduled_hosts_for_session($sessionId);

    try {
        $deleted = kuppi_scheduled_delete($sessionId);
    } catch (Throwable) {
        flash('error', 'Unable to delete this scheduled session right now.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    if (!$deleted) {
        flash('error', 'Unable to delete this scheduled session.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    kuppi_schedule_notify($session, $hosts, 'deleted');
    flash('success', 'Scheduled session deleted.');
    redirect('/dashboard/kuppi/scheduled');
}
