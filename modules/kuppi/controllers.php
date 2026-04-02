<?php

/**
 * Kuppi Module — Controllers
 */

function kuppi_feed_per_page(): int
{
    return 10;
}

function kuppi_user_can_create(): bool
{
    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) > 0;
}

function kuppi_user_can_moderate_batch(int $batchId): bool
{
    if ($batchId <= 0) {
        return false;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        return true;
    }

    if ($role !== 'moderator') {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === $batchId;
}

function kuppi_user_can_vote_request(array $request): bool
{
    $requestBatchId = (int) ($request['batch_id'] ?? 0);
    if ($requestBatchId <= 0) {
        return false;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        $selectedBatchId = (int) request_input('batch_id', 0);
        return $selectedBatchId > 0 && $selectedBatchId === $requestBatchId;
    }

    if (!in_array($role, ['student', 'coordinator', 'moderator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === $requestBatchId;
}

function kuppi_request_is_open(array $request): bool
{
    return (string) ($request['status'] ?? '') === 'open';
}

function kuppi_tags_to_array(string $tagsCsv): array
{
    $tagsCsv = trim($tagsCsv);
    if ($tagsCsv === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn(string $tag): string => trim($tag),
        explode(',', $tagsCsv)
    ), static fn(string $tag): bool => $tag !== ''));
}

function kuppi_normalize_tags_csv(string $raw): string
{
    $parts = explode(',', strtolower($raw));
    $normalized = [];
    foreach ($parts as $part) {
        $tag = trim($part);
        if ($tag === '') {
            continue;
        }

        $tag = preg_replace('/[\s_]+/', '-', $tag) ?? '';
        $tag = preg_replace('/[^a-z0-9-]/', '', $tag) ?? '';
        $tag = trim($tag, '-');
        if ($tag === '') {
            continue;
        }

        $normalized[] = $tag;
    }

    $normalized = array_values(array_unique($normalized));
    return implode(',', $normalized);
}

function kuppi_validate_request_input(int $batchId): array
{
    $title = trim((string) request_input('title', ''));
    $descriptionRaw = (string) request_input('description', '');
    $description = trim(str_replace(["\r\n", "\r"], "\n", $descriptionRaw));
    $subjectId = (int) request_input('subject_id', 0);
    $tagsCsv = kuppi_normalize_tags_csv((string) request_input('tags_csv', ''));
    $tags = kuppi_tags_to_array($tagsCsv);
    $errors = [];

    if ($subjectId <= 0) {
        $errors[] = 'Subject is required.';
    } elseif (!kuppi_subject_exists_in_batch($subjectId, $batchId)) {
        $errors[] = 'Selected subject is invalid for your batch.';
    }

    if ($title === '') {
        $errors[] = 'Title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Title must be at most 200 characters.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    } elseif (strlen($description) > 2000) {
        $errors[] = 'Description must be at most 2000 characters.';
    }

    if (count($tags) > 8) {
        $errors[] = 'You can add at most 8 tags.';
    }

    foreach ($tags as $tag) {
        if (strlen($tag) > 24) {
            $errors[] = 'Each tag must be at most 24 characters.';
            break;
        }
    }

    return [
        'errors' => $errors,
        'data' => [
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'tags_csv' => $tagsCsv,
        ],
    ];
}

function kuppi_index_url_for_batch(int $batchId): string
{
    if (user_role() === 'admin' && $batchId > 0) {
        return '/dashboard/kuppi?batch_id=' . $batchId;
    }

    return '/dashboard/kuppi';
}

function kuppi_index_url_for_request(array $request): string
{
    return kuppi_index_url_for_batch((int) ($request['batch_id'] ?? 0));
}

function kuppi_request_url(array $request): string
{
    $requestId = (int) ($request['id'] ?? 0);
    $url = '/dashboard/kuppi/' . $requestId;

    if (user_role() === 'admin') {
        $batchId = (int) ($request['batch_id'] ?? 0);
        if ($batchId > 0) {
            $url .= '?batch_id=' . $batchId;
        }
    }

    return $url;
}

function kuppi_resolve_valid_return_to(string $returnTo, array $request): string
{
    $raw = trim($returnTo);
    if ($raw !== '') {
        $path = (string) parse_url($raw, PHP_URL_PATH);
        if (
            str_starts_with($path, '/dashboard/kuppi')
            || str_starts_with($path, '/my-kuppi-requests')
        ) {
            return $raw;
        }
    }

    return kuppi_index_url_for_request($request);
}

function kuppi_resolve_readable_request(int $requestId): ?array
{
    if (user_role() === 'admin') {
        return kuppi_find_request_admin($requestId, (int) auth_id());
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        return null;
    }

    return kuppi_find_request_for_batch($requestId, $batchId, (int) auth_id());
}

function kuppi_can_edit_request(array $request): bool
{
    return (int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id()
        && kuppi_request_is_open($request);
}

function kuppi_can_delete_request(array $request): bool
{
    if ((int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id()) {
        return true;
    }

    return kuppi_user_can_moderate_batch((int) ($request['batch_id'] ?? 0));
}

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

function kuppi_show(string $id): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    view('kuppi::show', [
        'request' => $request,
        'tags' => kuppi_tags_to_array((string) ($request['tags_csv'] ?? '')),
        'can_edit_request' => kuppi_can_edit_request($request),
        'can_delete_request' => kuppi_can_delete_request($request),
        'can_vote_request' => kuppi_user_can_vote_request($request),
        'back_list_url' => kuppi_index_url_for_request($request),
    ], 'dashboard');
}

function kuppi_edit_form(string $id): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_can_edit_request($request)) {
        abort(403, 'Only the request owner can edit open requests.');
    }

    view('kuppi::edit', [
        'request' => $request,
        'subject_options' => kuppi_subject_options_for_batch((int) ($request['batch_id'] ?? 0)),
        'back_list_url' => kuppi_index_url_for_request($request),
    ], 'dashboard');
}

function kuppi_update_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_can_edit_request($request)) {
        abort(403, 'Only the request owner can edit open requests.');
    }

    $batchId = (int) ($request['batch_id'] ?? 0);
    $validated = kuppi_validate_request_input($batchId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/edit');
    }

    try {
        kuppi_update_request_by_owner($requestId, (int) auth_id(), [
            'subject_id' => (int) $validated['data']['subject_id'],
            'title' => $validated['data']['title'],
            'description' => $validated['data']['description'],
            'tags_csv' => $validated['data']['tags_csv'],
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to update request right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/edit');
    }

    clear_old_input();
    flash('success', 'Kuppi request updated.');
    redirect('/dashboard/kuppi/' . $requestId);
}

function kuppi_delete_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_can_delete_request($request)) {
        abort(403, 'You do not have permission to delete this request.');
    }

    $redirectTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);

    if (!kuppi_delete_request_by_id($requestId)) {
        flash('error', 'Unable to delete this request.');
        redirect($redirectTo);
    }

    flash('success', 'Kuppi request deleted.');
    redirect($redirectTo);
}

function kuppi_vote_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $returnTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);
    if (!kuppi_user_can_vote_request($request)) {
        abort(403, 'You do not have permission to vote on this request.');
    }

    if ((int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id()) {
        flash('error', 'You cannot vote on your own request.');
        redirect($returnTo);
    }

    $direction = trim((string) request_input('vote', ''));
    if (!in_array($direction, ['up', 'down'], true)) {
        flash('error', 'Invalid vote action.');
        redirect($returnTo);
    }

    try {
        $appliedVote = kuppi_apply_vote($requestId, (int) auth_id(), $direction);
    } catch (Throwable) {
        flash('error', 'Unable to save your vote right now.');
        redirect($returnTo);
    }

    if ($appliedVote === null) {
        flash('success', 'Vote removed.');
    } elseif ($appliedVote === 'up') {
        flash('success', 'Upvoted.');
    } else {
        flash('success', 'Downvoted.');
    }

    redirect($returnTo);
}

function kuppi_my_index(): void
{
    if (!kuppi_user_can_create()) {
        abort(403, 'Only students and coordinators can access this page.');
    }

    view('kuppi::my_index', [
        'requests' => kuppi_my_requests((int) auth_id()),
    ], 'dashboard');
}
