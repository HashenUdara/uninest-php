<?php

function kuppi_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $viewerId = (int) auth_id();
    $batchOptions = $isAdmin ? kuppi_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }
        $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
    }

    $subjectOptions = $selectedBatchId > 0
        ? kuppi_subject_options_for_batch($selectedBatchId)
        : [];

    $selectedSubjectId = (int) request_input('subject_id', 0);
    if ($selectedSubjectId > 0 && !kuppi_subject_exists_in_batch($selectedSubjectId, $selectedBatchId)) {
        $selectedSubjectId = 0;
    }

    $selectedSort = trim((string) request_input('sort', 'most_votes'));
    if (!in_array($selectedSort, kuppi_sort_options(), true)) {
        $selectedSort = 'most_votes';
    }

    $selectedSearchQuery = trim((string) request_input('q', ''));
    if (strlen($selectedSearchQuery) > 120) {
        $selectedSearchQuery = substr($selectedSearchQuery, 0, 120);
    }

    $selectedPage = max(1, min(50, (int) request_input('page', 1)));
    $selectedSubjectFilter = $selectedSubjectId > 0 ? $selectedSubjectId : null;

    $feedPage = $selectedBatchId > 0
        ? kuppi_requests_for_batch(
            $selectedBatchId,
            $selectedSubjectFilter,
            $selectedSort,
            $viewerId,
            $selectedSearchQuery,
            $selectedPage,
            kuppi_feed_per_page()
        )
        : ['requests' => [], 'has_more' => false];

    $requestCount = $selectedBatchId > 0
        ? kuppi_requests_count_for_batch($selectedBatchId, $selectedSubjectFilter, $selectedSearchQuery)
        : 0;

    view('kuppi::index', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'active_batch' => $activeBatch,
        'selected_batch_id' => $selectedBatchId,
        'subject_options' => $subjectOptions,
        'selected_subject_id' => $selectedSubjectId,
        'selected_sort' => $selectedSort,
        'selected_search_query' => $selectedSearchQuery,
        'selected_page' => $selectedPage,
        'requests' => (array) ($feedPage['requests'] ?? []),
        'has_more_requests' => !empty($feedPage['has_more']),
        'request_count' => $requestCount,
        'can_create' => kuppi_user_can_create(),
    ], 'dashboard');
}

function kuppi_timetable_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $batchOptions = $isAdmin ? kuppi_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
                $activeBatch = null;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }

        if (!kuppi_user_can_view_timetable_for_batch($selectedBatchId)) {
            abort(403, 'You do not have permission to view this timetable.');
        }

        $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
    }

    if ($selectedBatchId > 0 && !kuppi_user_can_view_timetable_for_batch($selectedBatchId)) {
        abort(403, 'You do not have permission to view this timetable.');
    }

    $canManage = $selectedBatchId > 0 && kuppi_user_can_manage_timetable_for_batch($selectedBatchId);
    $slots = $selectedBatchId > 0
        ? kuppi_university_timetable_slots_for_batch($selectedBatchId)
        : [];

    $editSlot = null;
    $editSlotId = (int) request_input('edit', 0);
    if ($canManage && $editSlotId > 0) {
        $editSlot = kuppi_university_timetable_find_for_batch($editSlotId, $selectedBatchId);
        if (!$editSlot) {
            flash('warning', 'Selected slot was not found for this batch.');
            redirect(kuppi_timetable_url($selectedBatchId));
        }
    }

    $weeklyGridData = kuppi_timetable_build_weekly_grid($slots);
    $metrics = kuppi_timetable_metrics($slots, (int) ($weeklyGridData['blocked_cell_count'] ?? 0));

    view('kuppi::timetable', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'selected_batch_id' => $selectedBatchId,
        'active_batch' => $activeBatch,
        'can_manage' => $canManage,
        'is_read_only' => !$canManage,
        'slots' => $slots,
        'edit_slot' => $editSlot,
        'day_labels' => kuppi_timetable_day_labels(),
        'grid_rows' => (array) ($weeklyGridData['rows'] ?? []),
        'metrics' => $metrics,
    ], 'dashboard');
}

function kuppi_timetable_store(): void
{
    csrf_check();

    $role = (string) user_role();
    if (!in_array($role, ['moderator', 'admin'], true)) {
        abort(403, 'Only moderators and admins can manage official timetable slots.');
    }

    $batchId = $role === 'admin'
        ? (int) request_input('batch_id', 0)
        : (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        flash('error', 'Select a batch before adding timetable slots.');
        redirect(kuppi_timetable_url());
    }

    if (!kuppi_user_can_manage_timetable_for_batch($batchId)) {
        abort(403, 'You do not have permission to manage this timetable.');
    }

    if ($role === 'admin' && !kuppi_find_batch_option_by_id($batchId)) {
        flash('error', 'Selected batch is not available.');
        redirect(kuppi_timetable_url());
    }

    $validated = kuppi_timetable_validate_slot_input($batchId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect(kuppi_timetable_url($batchId));
    }

    try {
        kuppi_university_timetable_create([
            'batch_id' => $batchId,
            'day_of_week' => (int) $validated['data']['day_of_week'],
            'start_time' => (string) $validated['data']['start_time'],
            'end_time' => (string) $validated['data']['end_time'],
            'reason' => (string) $validated['data']['reason'],
            'created_by_user_id' => (int) auth_id(),
            'updated_by_user_id' => (int) auth_id(),
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to add the timetable slot right now.');
        redirect(kuppi_timetable_url($batchId));
    }

    clear_old_input();
    flash('success', 'Official timetable slot added.');
    redirect(kuppi_timetable_url($batchId));
}

function kuppi_timetable_update(string $id): void
{
    csrf_check();

    $role = (string) user_role();
    if (!in_array($role, ['moderator', 'admin'], true)) {
        abort(403, 'Only moderators and admins can manage official timetable slots.');
    }

    $slotId = (int) $id;
    $slot = kuppi_university_timetable_find_by_id($slotId);
    if (!$slot) {
        abort(404, 'Timetable slot not found.');
    }

    $batchId = (int) ($slot['batch_id'] ?? 0);
    if (!kuppi_user_can_manage_timetable_for_batch($batchId)) {
        abort(403, 'You do not have permission to update this timetable slot.');
    }
    if ($role === 'admin') {
        $contextBatchId = (int) request_input('batch_id', 0);
        if ($contextBatchId <= 0 || $contextBatchId !== $batchId) {
            abort(403, 'Select the correct batch context before updating this slot.');
        }
    }

    $validated = kuppi_timetable_validate_slot_input($batchId, $slotId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect(kuppi_timetable_url($batchId, ['edit' => $slotId]));
    }

    try {
        $updated = kuppi_university_timetable_update($slotId, $batchId, [
            'day_of_week' => (int) $validated['data']['day_of_week'],
            'start_time' => (string) $validated['data']['start_time'],
            'end_time' => (string) $validated['data']['end_time'],
            'reason' => (string) $validated['data']['reason'],
            'updated_by_user_id' => (int) auth_id(),
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to update the timetable slot right now.');
        redirect(kuppi_timetable_url($batchId, ['edit' => $slotId]));
    }

    if (!$updated) {
        flash('error', 'Unable to update this timetable slot.');
        redirect(kuppi_timetable_url($batchId, ['edit' => $slotId]));
    }

    clear_old_input();
    flash('success', 'Official timetable slot updated.');
    redirect(kuppi_timetable_url($batchId));
}

function kuppi_timetable_delete(string $id): void
{
    csrf_check();

    $role = (string) user_role();
    if (!in_array($role, ['moderator', 'admin'], true)) {
        abort(403, 'Only moderators and admins can manage official timetable slots.');
    }

    $slotId = (int) $id;
    $slot = kuppi_university_timetable_find_by_id($slotId);
    if (!$slot) {
        abort(404, 'Timetable slot not found.');
    }

    $batchId = (int) ($slot['batch_id'] ?? 0);
    if (!kuppi_user_can_manage_timetable_for_batch($batchId)) {
        abort(403, 'You do not have permission to delete this timetable slot.');
    }
    if ($role === 'admin') {
        $contextBatchId = (int) request_input('batch_id', 0);
        if ($contextBatchId <= 0 || $contextBatchId !== $batchId) {
            abort(403, 'Select the correct batch context before deleting this slot.');
        }
    }

    try {
        $deleted = kuppi_university_timetable_delete($slotId, $batchId);
    } catch (Throwable) {
        flash('error', 'Unable to delete the timetable slot right now.');
        redirect(kuppi_timetable_url($batchId));
    }

    if (!$deleted) {
        flash('error', 'Unable to delete this timetable slot.');
        redirect(kuppi_timetable_url($batchId));
    }

    flash('success', 'Official timetable slot removed.');
    redirect(kuppi_timetable_url($batchId));
}

function kuppi_create_form(): void
{
    if (!kuppi_user_can_create()) {
        abort(403, 'Only students and coordinators can request Kuppi sessions.');
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        abort(403, 'You are not assigned to a batch.');
    }

    view('kuppi::create', [
        'active_batch' => kuppi_find_batch_option_by_id($batchId),
        'subject_options' => kuppi_subject_options_for_batch($batchId),
    ], 'dashboard');
}

function kuppi_store(): void
{
    csrf_check();

    if (!kuppi_user_can_create()) {
        abort(403, 'Only students and coordinators can request Kuppi sessions.');
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        abort(403, 'You are not assigned to a batch.');
    }

    $validated = kuppi_validate_request_input($batchId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/create');
    }

    try {
        $requestId = kuppi_create_request([
            'batch_id' => $batchId,
            'subject_id' => (int) $validated['data']['subject_id'],
            'requested_by_user_id' => (int) auth_id(),
            'title' => $validated['data']['title'],
            'description' => $validated['data']['description'],
            'tags_csv' => $validated['data']['tags_csv'],
            'status' => 'open',
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to create request right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/kuppi/create');
    }

    clear_old_input();
    flash('success', 'Kuppi request created.');
    redirect('/dashboard/kuppi/' . $requestId);
}
