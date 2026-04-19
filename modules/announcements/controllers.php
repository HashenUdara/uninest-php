<?php

/**
 * Announcements Module — Controllers
 */

function announcements_is_admin(): bool
{
    return (string) user_role() === 'admin';
}

function announcements_user_can_manage(): bool
{
    return in_array((string) user_role(), ['moderator', 'admin'], true);
}

function announcements_index_url_for_batch(int $batchId = 0): string
{
    if (announcements_is_admin() && $batchId > 0) {
        return '/dashboard/announcements?batch_id=' . $batchId;
    }

    return '/dashboard/announcements';
}

function announcements_create_url_for_batch(int $batchId = 0): string
{
    if (announcements_is_admin() && $batchId > 0) {
        return '/dashboard/announcements/create?batch_id=' . $batchId;
    }

    return '/dashboard/announcements/create';
}

function announcements_show_url(array $announcement): string
{
    $url = '/dashboard/announcements/' . (int) ($announcement['id'] ?? 0);
    if (announcements_is_admin()) {
        $batchId = (int) ($announcement['batch_id'] ?? 0);
        if ($batchId > 0) {
            $url .= '?batch_id=' . $batchId;
        }
    }

    return $url;
}

function announcements_normalize_search_query(string $query): string
{
    $query = trim($query);
    if (strlen($query) > announcements_search_query_max_length()) {
        $query = substr($query, 0, announcements_search_query_max_length());
    }

    return $query;
}

function announcements_prepare_payload(int $batchId): array
{
    $errors = [];

    $titleRaw = (string) request_input('title', '');
    $bodyRaw = (string) request_input('body', '');
    $subjectIdRaw = (int) request_input('subject_id', 0);

    $title = trim(str_replace(["\r\n", "\r"], "\n", $titleRaw));
    $body = trim(str_replace(["\r\n", "\r"], "\n", $bodyRaw));
    $subjectId = $subjectIdRaw > 0 ? $subjectIdRaw : null;

    if ($title === '') {
        $errors[] = 'Title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Title must be at most 200 characters.';
    }

    if ($body === '') {
        $errors[] = 'Announcement body is required.';
    } elseif (strlen($body) > 6000) {
        $errors[] = 'Announcement body must be at most 6000 characters.';
    }

    if ($subjectId !== null && !announcements_subject_exists_in_batch($subjectId, $batchId)) {
        $errors[] = 'Selected subject is invalid for this batch.';
    }

    return [
        'errors' => $errors,
        'validated' => [
            'title' => $title,
            'body' => $body,
            'subject_id' => $subjectId,
        ],
    ];
}

function announcements_resolve_valid_return_to(string $returnTo, int $fallbackBatchId): string
{
    $raw = trim($returnTo);
    if ($raw !== '') {
        $path = (string) parse_url($raw, PHP_URL_PATH);
        if (str_starts_with($path, '/dashboard/announcements') || str_starts_with($path, '/dashboard/feed')) {
            return $raw;
        }
    }

    return announcements_index_url_for_batch($fallbackBatchId);
}

function announcements_find_readable_announcement(int $announcementId): ?array
{
    if (announcements_is_admin()) {
        return announcements_find_by_id($announcementId);
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        return null;
    }

    return announcements_find_by_id_for_batch($announcementId, $batchId);
}

function announcements_find_manageable_announcement(int $announcementId): ?array
{
    $announcement = announcements_find_by_id($announcementId);
    if (!$announcement) {
        return null;
    }

    if (announcements_is_admin()) {
        return $announcement;
    }

    if ((string) user_role() !== 'moderator') {
        return null;
    }

    $userBatchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($userBatchId <= 0 || $userBatchId !== (int) ($announcement['batch_id'] ?? 0)) {
        return null;
    }

    return $announcement;
}

function announcements_index(): void
{
    $isAdmin = announcements_is_admin();
    $batchOptions = $isAdmin ? announcements_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = announcements_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }

        $activeBatch = announcements_find_batch_option_by_id($selectedBatchId);
    }

    $subjectOptions = $selectedBatchId > 0
        ? announcements_subject_options_for_batch($selectedBatchId)
        : [];

    $selectedSubjectId = (int) request_input('subject_id', 0);
    if ($selectedSubjectId > 0 && !announcements_subject_exists_in_batch($selectedSubjectId, $selectedBatchId)) {
        $selectedSubjectId = 0;
    }

    $searchQuery = announcements_normalize_search_query((string) request_input('q', ''));
    $selectedPage = max(1, min(50, (int) request_input('page', 1)));

    $subjectFilter = $selectedSubjectId > 0 ? $selectedSubjectId : null;

    $pageResult = $selectedBatchId > 0
        ? announcements_fetch_page(
            $selectedBatchId,
            $subjectFilter,
            $searchQuery,
            $selectedPage,
            announcements_per_page()
        )
        : ['items' => [], 'has_more' => false];

    $filteredCount = $selectedBatchId > 0
        ? announcements_filtered_count($selectedBatchId, $subjectFilter, $searchQuery)
        : 0;

    $todayCount = $selectedBatchId > 0
        ? announcements_today_count($selectedBatchId, $subjectFilter, $searchQuery)
        : 0;

    $pinnedCount = $selectedBatchId > 0
        ? announcements_pinned_count_for_batch($selectedBatchId)
        : 0;

    view('announcements::index', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'selected_batch_id' => $selectedBatchId,
        'active_batch' => $activeBatch,
        'subject_options' => $subjectOptions,
        'selected_subject_id' => $selectedSubjectId,
        'selected_search_query' => $searchQuery,
        'selected_page' => $selectedPage,
        'items' => (array) ($pageResult['items'] ?? []),
        'has_more_items' => !empty($pageResult['has_more']),
        'filtered_count' => $filteredCount,
        'today_count' => $todayCount,
        'pinned_count' => $pinnedCount,
        'can_manage' => announcements_user_can_manage(),
    ], 'dashboard');
}

function announcements_create_form(): void
{
    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to create announcements.');
    }

    $isAdmin = announcements_is_admin();
    $batchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $batchId = (int) request_input('batch_id', 0);
        if ($batchId <= 0) {
            flash('warning', 'Select a batch first.');
            redirect('/dashboard/announcements');
        }

        $activeBatch = announcements_find_batch_option_by_id($batchId);
        if (!$activeBatch) {
            flash('error', 'Selected batch is invalid.');
            redirect('/dashboard/announcements');
        }
    } else {
        $batchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($batchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }

        $activeBatch = announcements_find_batch_option_by_id($batchId);
    }

    view('announcements::create', [
        'is_admin' => $isAdmin,
        'batch_id' => $batchId,
        'active_batch' => $activeBatch,
        'subject_options' => announcements_subject_options_for_batch($batchId),
        'back_url' => announcements_index_url_for_batch($batchId),
    ], 'dashboard');
}

function announcements_store(): void
{
    csrf_check();

    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to create announcements.');
    }

    $isAdmin = announcements_is_admin();
    $batchId = 0;

    if ($isAdmin) {
        $batchId = (int) request_input('batch_id', 0);
        if ($batchId <= 0 || !announcements_find_batch_option_by_id($batchId)) {
            flash('error', 'A valid batch context is required.');
            flash_old_input();
            redirect('/dashboard/announcements');
        }
    } else {
        $batchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($batchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }
    }

    $prepared = announcements_prepare_payload($batchId);
    if (!empty($prepared['errors'])) {
        flash('error', implode(' ', $prepared['errors']));
        flash_old_input();
        redirect(announcements_create_url_for_batch($batchId));
    }

    try {
        $announcementId = announcements_create([
            'batch_id' => $batchId,
            'subject_id' => $prepared['validated']['subject_id'],
            'author_user_id' => (int) auth_id(),
            'title' => $prepared['validated']['title'],
            'body' => $prepared['validated']['body'],
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to publish announcement right now. Please try again.');
        flash_old_input();
        redirect(announcements_create_url_for_batch($batchId));
    }

    clear_old_input();
    flash('success', 'Announcement published.');

    $announcement = announcements_find_by_id($announcementId);
    if (!$announcement) {
        redirect(announcements_index_url_for_batch($batchId));
    }

    redirect(announcements_show_url($announcement));
}

function announcements_show(string $id): void
{
    $announcementId = (int) $id;
    $announcement = announcements_find_readable_announcement($announcementId);
    if (!$announcement) {
        abort(404, 'Announcement not found.');
    }

    $canManage = announcements_user_can_manage() && announcements_find_manageable_announcement($announcementId) !== null;

    view('announcements::show', [
        'announcement' => $announcement,
        'can_manage' => $canManage,
        'is_admin' => announcements_is_admin(),
        'back_url' => announcements_index_url_for_batch((int) ($announcement['batch_id'] ?? 0)),
    ], 'dashboard');
}

function announcements_edit_form(string $id): void
{
    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to edit announcements.');
    }

    $announcementId = (int) $id;
    $announcement = announcements_find_manageable_announcement($announcementId);
    if (!$announcement) {
        abort(404, 'Announcement not found.');
    }

    $batchId = (int) ($announcement['batch_id'] ?? 0);

    view('announcements::edit', [
        'announcement' => $announcement,
        'is_admin' => announcements_is_admin(),
        'batch_id' => $batchId,
        'active_batch' => announcements_find_batch_option_by_id($batchId),
        'subject_options' => announcements_subject_options_for_batch($batchId),
        'back_url' => announcements_show_url($announcement),
    ], 'dashboard');
}

function announcements_update_action(string $id): void
{
    csrf_check();

    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to update announcements.');
    }

    $announcementId = (int) $id;
    $announcement = announcements_find_manageable_announcement($announcementId);
    if (!$announcement) {
        abort(404, 'Announcement not found.');
    }

    $batchId = (int) ($announcement['batch_id'] ?? 0);
    $prepared = announcements_prepare_payload($batchId);

    if (!empty($prepared['errors'])) {
        flash('error', implode(' ', $prepared['errors']));
        flash_old_input();
        redirect('/dashboard/announcements/' . $announcementId . '/edit' . (announcements_is_admin() ? '?batch_id=' . $batchId : ''));
    }

    try {
        announcements_update($announcementId, $prepared['validated']);
    } catch (Throwable) {
        flash('error', 'Unable to update announcement right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/announcements/' . $announcementId . '/edit' . (announcements_is_admin() ? '?batch_id=' . $batchId : ''));
    }

    clear_old_input();
    flash('success', 'Announcement updated.');

    $fresh = announcements_find_by_id($announcementId);
    if (!$fresh) {
        redirect(announcements_index_url_for_batch($batchId));
    }

    redirect(announcements_show_url($fresh));
}

function announcements_delete_action(string $id): void
{
    csrf_check();

    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to delete announcements.');
    }

    $announcementId = (int) $id;
    $announcement = announcements_find_manageable_announcement($announcementId);
    if (!$announcement) {
        abort(404, 'Announcement not found.');
    }

    $batchId = (int) ($announcement['batch_id'] ?? 0);
    $returnTo = announcements_resolve_valid_return_to((string) request_input('return_to', ''), $batchId);

    if (!announcements_delete($announcementId)) {
        flash('error', 'Unable to delete announcement.');
        redirect($returnTo);
    }

    flash('success', 'Announcement deleted.');
    redirect($returnTo);
}

function announcements_pin_action(string $id): void
{
    csrf_check();

    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to pin announcements.');
    }

    $announcementId = (int) $id;
    $announcement = announcements_find_manageable_announcement($announcementId);
    if (!$announcement) {
        abort(404, 'Announcement not found.');
    }

    $batchId = (int) ($announcement['batch_id'] ?? 0);
    $returnTo = announcements_resolve_valid_return_to((string) request_input('return_to', ''), $batchId);

    if (!announcements_pin_single($announcementId, $batchId, (int) auth_id())) {
        flash('error', 'Unable to pin announcement.');
        redirect($returnTo);
    }

    flash('success', 'Announcement pinned.');
    redirect($returnTo);
}

function announcements_unpin_action(string $id): void
{
    csrf_check();

    if (!announcements_user_can_manage()) {
        abort(403, 'You do not have permission to unpin announcements.');
    }

    $announcementId = (int) $id;
    $announcement = announcements_find_manageable_announcement($announcementId);
    if (!$announcement) {
        abort(404, 'Announcement not found.');
    }

    $batchId = (int) ($announcement['batch_id'] ?? 0);
    $returnTo = announcements_resolve_valid_return_to((string) request_input('return_to', ''), $batchId);

    if (!announcements_unpin($announcementId, $batchId)) {
        flash('error', 'Unable to unpin announcement.');
        redirect($returnTo);
    }

    flash('success', 'Announcement unpinned.');
    redirect($returnTo);
}
