<?php

function kuppi_schedule_select_request_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $role = (string) user_role();
    $currentUser = (array) auth_user();
    $userId = (int) ($currentUser['id'] ?? 0);
    $userBatchId = (int) ($currentUser['batch_id'] ?? 0);
    $adminBatchId = $role === 'admin' ? (int) request_input('batch_id', 0) : 0;
    $selectedSort = trim((string) request_input('sort', 'most_votes'));
    if (!in_array($selectedSort, kuppi_sort_options(), true)) {
        $selectedSort = 'most_votes';
    }
    $searchQuery = trim((string) request_input('q', ''));
    if (strlen($searchQuery) > 120) {
        $searchQuery = substr($searchQuery, 0, 120);
    }

    $directRequestId = (int) request_input('request_id', 0);
    if ($directRequestId > 0) {
        $request = kuppi_resolve_readable_request($directRequestId);
        if ($request && kuppi_user_can_schedule_request($request) && !kuppi_scheduled_session_has_active_for_request($directRequestId)) {
            $draft = kuppi_schedule_default_draft();
            $draft['mode'] = 'request';
            $draft['request_id'] = $directRequestId;
            $draft['batch_id'] = (int) ($request['batch_id'] ?? 0);
            $draft['subject_id'] = (int) ($request['subject_id'] ?? 0);
            $draft['title'] = (string) ($request['title'] ?? '');
            $draft['description'] = (string) ($request['description'] ?? '');
            kuppi_schedule_set_draft($draft);
            redirect('/dashboard/kuppi/schedule/assign');
        }
    }

    $requests = kuppi_schedule_open_requests_for_scheduler(
        $role,
        $userId,
        $userBatchId,
        $searchQuery,
        $selectedSort,
        $adminBatchId
    );

    view('kuppi::schedule_select_request', [
        'requests' => $requests,
        'selected_sort' => $selectedSort,
        'selected_search_query' => $searchQuery,
        'is_admin' => $role === 'admin',
        'admin_batch_id' => $adminBatchId,
        'batch_options' => $role === 'admin' ? kuppi_batch_options_for_admin() : [],
        'active_batch' => $adminBatchId > 0 ? kuppi_find_batch_option_by_id($adminBatchId) : null,
    ], 'dashboard');
}

function kuppi_schedule_manual_start(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $role = (string) user_role();
    $userBatchId = (int) (auth_user()['batch_id'] ?? 0);
    $selectedBatchId = $role === 'admin' ? (int) request_input('batch_id', 0) : $userBatchId;

    if ($role === 'admin' && $selectedBatchId <= 0) {
        flash('warning', 'Select a batch first before starting a manual session.');
        redirect('/dashboard/kuppi/schedule');
    }

    $draft = kuppi_schedule_default_draft();
    $draft['mode'] = 'manual';
    $draft['batch_id'] = $selectedBatchId;
    kuppi_schedule_set_draft($draft);
    redirect('/dashboard/kuppi/schedule/assign');
}

function kuppi_schedule_select_request_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $requestId = (int) request_input('request_id', 0);
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request || !kuppi_user_can_schedule_request($request)) {
        abort(403, 'Selected request is not available for scheduling.');
    }

    if (kuppi_scheduled_session_has_active_for_request($requestId)) {
        flash('error', 'This request already has an active scheduled session.');
        redirect('/dashboard/kuppi/schedule');
    }

    $draft = kuppi_schedule_default_draft();
    $draft['mode'] = 'request';
    $draft['request_id'] = $requestId;
    $draft['batch_id'] = (int) ($request['batch_id'] ?? 0);
    $draft['subject_id'] = (int) ($request['subject_id'] ?? 0);
    $draft['title'] = (string) ($request['title'] ?? '');
    $draft['description'] = (string) ($request['description'] ?? '');
    kuppi_schedule_set_draft($draft);

    redirect('/dashboard/kuppi/schedule/assign');
}

function kuppi_schedule_set_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $mode = (string) ($draft['mode'] ?? 'request');
    $role = (string) user_role();
    $currentUser = (array) auth_user();
    $userId = (int) ($currentUser['id'] ?? 0);
    $userBatchId = (int) ($currentUser['batch_id'] ?? 0);
    $linkedRequest = null;

    if ($mode === 'request') {
        $linkedRequest = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$linkedRequest) {
            kuppi_schedule_clear_draft();
            flash('error', 'Selected request is no longer available for scheduling.');
            redirect('/dashboard/kuppi/schedule');
        }

        if (kuppi_scheduled_session_has_active_for_request((int) ($linkedRequest['id'] ?? 0))) {
            kuppi_schedule_clear_draft();
            flash('error', 'This request already has an active scheduled session.');
            redirect('/dashboard/kuppi/schedule');
        }

        $draft['batch_id'] = (int) ($linkedRequest['batch_id'] ?? 0);
        $draft['subject_id'] = (int) ($linkedRequest['subject_id'] ?? 0);
        $draft['title'] = (string) ($linkedRequest['title'] ?? '');
        $draft['description'] = (string) ($linkedRequest['description'] ?? '');
        kuppi_schedule_set_draft($draft);
    }

    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids'])) {
        flash('warning', 'Select one or more hosts first.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (empty($selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = [];
        kuppi_schedule_set_draft($draft);
        flash('warning', 'Selected hosts are no longer available. Please select hosts again.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (count($selectedHostData['selected_host_ids']) !== count($selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = array_values(array_map(
            static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
            $selectedHostData['selected_hosts']
        ));
        kuppi_schedule_set_draft($draft);
    }

    $adminBatchId = $role === 'admin' ? (int) ($draft['batch_id'] ?? 0) : 0;
    $subjectOptions = kuppi_scheduler_subject_options_for_user($role, $userId, $userBatchId, $adminBatchId);
    $availabilityStats = kuppi_schedule_selected_host_availability_stats((array) $selectedHostData['selected_hosts']);
    $selectedSlotKey = kuppi_schedule_slot_key_for_datetime(
        trim((string) ($draft['session_date'] ?? '')),
        trim((string) ($draft['start_time'] ?? ''))
    );
    $slotMatch = kuppi_schedule_selected_host_slot_match((array) $selectedHostData['selected_hosts'], $selectedSlotKey);
    $timetableSlots = [];
    $timetableSelectedDaySlots = [];
    $timetableConflicts = [];
    $timetableBatchId = (int) ($draft['batch_id'] ?? 0);
    $selectedDayOfWeek = kuppi_timetable_day_of_week_from_date(trim((string) ($draft['session_date'] ?? '')));
    if ($timetableBatchId > 0 && kuppi_user_can_view_timetable_for_batch($timetableBatchId)) {
        $timetableSlots = kuppi_university_timetable_slots_for_batch($timetableBatchId);
        if ($selectedDayOfWeek > 0) {
            $timetableSelectedDaySlots = array_values(array_filter($timetableSlots, static function (array $slot) use ($selectedDayOfWeek): bool {
                return (int) ($slot['day_of_week'] ?? 0) === $selectedDayOfWeek;
            }));
        }

        $sessionDateDraft = trim((string) ($draft['session_date'] ?? ''));
        $startTimeDraft = trim((string) ($draft['start_time'] ?? ''));
        $endTimeDraft = trim((string) ($draft['end_time'] ?? ''));
        if ($sessionDateDraft !== '' && $startTimeDraft !== '' && $endTimeDraft !== '') {
            $timetableConflicts = kuppi_university_timetable_conflicts_for_session(
                $timetableBatchId,
                $sessionDateDraft,
                $startTimeDraft,
                $endTimeDraft
            );
        }
    }

    view('kuppi::schedule_set', [
        'draft' => $draft,
        'mode' => $mode,
        'linked_request' => $linkedRequest,
        'selected_hosts' => $selectedHostData['selected_hosts'],
        'availability_stats' => $availabilityStats,
        'selected_slot_key' => $selectedSlotKey,
        'selected_slot_match' => $slotMatch,
        'availability_options' => kuppi_conductor_availability_options(),
        'subject_options' => $subjectOptions,
        'is_admin' => $role === 'admin',
        'batch_options' => $role === 'admin' ? kuppi_batch_options_for_admin() : [],
        'timetable_slots' => $timetableSlots,
        'timetable_selected_day_slots' => $timetableSelectedDaySlots,
        'timetable_conflicts' => $timetableConflicts,
        'timetable_day_labels' => kuppi_timetable_day_labels(),
    ], 'dashboard');
}

function kuppi_schedule_set_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids']) || empty($selectedHostData['selected_hosts'])) {
        flash('warning', 'Select one or more hosts first.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (count((array) $selectedHostData['selected_host_ids']) !== count((array) $selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = array_values(array_map(
            static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
            (array) $selectedHostData['selected_hosts']
        ));
        kuppi_schedule_set_draft($draft);
        flash('warning', 'Some selected hosts are no longer available. Please review host selection.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $validation = kuppi_schedule_validate_set_input($draft);
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect('/dashboard/kuppi/schedule/set');
    }

    $nextDraft = array_merge($draft, $validation['data']);
    kuppi_schedule_set_draft($nextDraft);

    redirect('/dashboard/kuppi/schedule/review');
}

function kuppi_schedule_assign_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $mode = (string) ($draft['mode'] ?? 'request');
    $linkedRequest = null;
    if ($mode === 'request') {
        $linkedRequest = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$linkedRequest) {
            kuppi_schedule_clear_draft();
            flash('error', 'Selected request is no longer available for scheduling.');
            redirect('/dashboard/kuppi/schedule');
        }

        if (kuppi_scheduled_session_has_active_for_request((int) ($linkedRequest['id'] ?? 0))) {
            kuppi_schedule_clear_draft();
            flash('error', 'This request already has an active scheduled session.');
            redirect('/dashboard/kuppi/schedule');
        }

        $draft['batch_id'] = (int) ($linkedRequest['batch_id'] ?? 0);
        $draft['subject_id'] = (int) ($linkedRequest['subject_id'] ?? 0);
        $draft['title'] = (string) ($linkedRequest['title'] ?? '');
        $draft['description'] = (string) ($linkedRequest['description'] ?? '');
        kuppi_schedule_set_draft($draft);
    }

    $candidates = kuppi_schedule_host_candidates($draft);
    $selectedHostIds = kuppi_schedule_default_host_ids($draft, $candidates);

    view('kuppi::schedule_assign', [
        'draft' => $draft,
        'mode' => $mode,
        'linked_request' => $linkedRequest,
        'candidates' => $candidates,
        'selected_host_ids' => $selectedHostIds,
        'availability_options' => kuppi_conductor_availability_options(),
    ], 'dashboard');
}

function kuppi_schedule_assign_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $candidateMap = kuppi_schedule_candidate_map(kuppi_schedule_host_candidates($draft));
    $selection = kuppi_schedule_selected_hosts_from_input($candidateMap);

    if (!empty($selection['errors'])) {
        flash('error', implode(' ', $selection['errors']));
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $draft['host_user_ids'] = $selection['selected_ids'];
    kuppi_schedule_set_draft($draft);
    redirect('/dashboard/kuppi/schedule/set');
}

function kuppi_schedule_review_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids']) || empty($selectedHostData['selected_hosts'])) {
        flash('warning', 'Select at least one host.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (count((array) $selectedHostData['selected_host_ids']) !== count((array) $selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = array_values(array_map(
            static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
            (array) $selectedHostData['selected_hosts']
        ));
        kuppi_schedule_set_draft($draft);
        flash('warning', 'Some selected hosts are no longer available. Please review host selection.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (trim((string) ($draft['session_date'] ?? '')) === '') {
        flash('warning', 'Set schedule details before review.');
        redirect('/dashboard/kuppi/schedule/set');
    }

    view('kuppi::schedule_review', [
        'draft' => $draft,
        'selected_hosts' => $selectedHostData['selected_hosts'],
        'linked_request' => kuppi_schedule_resolve_request_for_draft($draft),
    ], 'dashboard');
}

function kuppi_schedule_confirm_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids']) || empty($selectedHostData['selected_hosts'])) {
        flash('error', 'At least one host is required.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (trim((string) ($draft['session_date'] ?? '')) === '') {
        flash('warning', 'Set schedule details first.');
        redirect('/dashboard/kuppi/schedule/set');
    }

    if (count((array) $selectedHostData['selected_host_ids']) !== count((array) $selectedHostData['selected_hosts'])) {
        flash('error', 'Selected hosts are no longer available.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $hosts = [];
    foreach ((array) $selectedHostData['selected_hosts'] as $candidate) {
        $hostUserId = (int) ($candidate['host_user_id'] ?? 0);
        if ($hostUserId <= 0) {
            continue;
        }

        $hosts[] = [
            'host_user_id' => $hostUserId,
            'source_type' => (string) ($candidate['source_type'] ?? 'manual'),
            'source_application_id' => !empty($candidate['source_application_id']) ? (int) $candidate['source_application_id'] : null,
            'assigned_by_user_id' => (int) auth_id(),
        ];
    }

    if (empty($hosts)) {
        flash('error', 'At least one host is required.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $request = null;
    $requestId = (int) ($draft['request_id'] ?? 0);
    if ((string) ($draft['mode'] ?? '') === 'request') {
        $request = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$request) {
            kuppi_schedule_clear_draft();
            flash('error', 'Selected request is no longer available.');
            redirect('/dashboard/kuppi/schedule');
        }

        if (kuppi_scheduled_session_has_active_for_request($requestId)) {
            kuppi_schedule_clear_draft();
            flash('error', 'This request already has an active scheduled session.');
            redirect('/dashboard/kuppi/schedule');
        }
    } else {
        $requestId = 0;
    }

    try {
        $sessionId = kuppi_scheduled_create_with_hosts([
            'batch_id' => (int) ($draft['batch_id'] ?? 0),
            'subject_id' => (int) ($draft['subject_id'] ?? 0),
            'request_id' => $requestId,
            'title' => (string) ($draft['title'] ?? ''),
            'description' => (string) ($draft['description'] ?? ''),
            'session_date' => (string) ($draft['session_date'] ?? ''),
            'start_time' => (string) ($draft['start_time'] ?? ''),
            'end_time' => (string) ($draft['end_time'] ?? ''),
            'duration_minutes' => (int) ($draft['duration_minutes'] ?? 0),
            'max_attendees' => (int) ($draft['max_attendees'] ?? 0),
            'location_type' => (string) ($draft['location_type'] ?? 'physical'),
            'location_text' => (string) ($draft['location_text'] ?? ''),
            'meeting_link' => (string) ($draft['meeting_link'] ?? ''),
            'notes' => (string) ($draft['notes'] ?? ''),
            'status' => 'scheduled',
            'created_by_user_id' => (int) auth_id(),
        ], $hosts);
    } catch (Throwable) {
        flash('error', 'Unable to schedule this session right now.');
        redirect('/dashboard/kuppi/schedule/review');
    }

    if ($sessionId <= 0) {
        flash('error', 'This request already has an active scheduled session.');
        redirect('/dashboard/kuppi/schedule');
    }

    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    $sessionHosts = kuppi_scheduled_hosts_for_session($sessionId);
    if ($session) {
        kuppi_schedule_notify($session, $sessionHosts, 'created');
    }

    kuppi_schedule_clear_draft();
    flash('success', 'Kuppi session scheduled successfully.');
    redirect('/dashboard/kuppi/schedule/success?id=' . $sessionId);
}

