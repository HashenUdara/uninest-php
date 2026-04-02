<?php

/**
 * Kuppi Module — Controllers
 */

function kuppi_feed_per_page(): int
{
    return 10;
}

function kuppi_relative_time_label(string $timestamp): string
{
    $ts = strtotime($timestamp);
    if ($ts === false) {
        return 'just now';
    }

    $delta = time() - $ts;
    if ($delta <= 0) {
        return 'just now';
    }

    if ($delta < 60) {
        return 'just now';
    }

    $units = [
        ['seconds' => 604800, 'label' => 'week'],
        ['seconds' => 86400, 'label' => 'day'],
        ['seconds' => 3600, 'label' => 'hour'],
        ['seconds' => 60, 'label' => 'minute'],
    ];

    foreach ($units as $unit) {
        $seconds = (int) ($unit['seconds'] ?? 0);
        if ($seconds <= 0 || $delta < $seconds) {
            continue;
        }

        $value = (int) floor($delta / $seconds);
        $label = (string) ($unit['label'] ?? 'minute');
        return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
    }

    return 'just now';
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

function kuppi_user_can_apply_as_conductor(array $request): bool
{
    if (!kuppi_request_is_open($request)) {
        return false;
    }

    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === (int) ($request['batch_id'] ?? 0);
}

function kuppi_user_can_vote_conductor(array $request): bool
{
    if (!kuppi_request_is_open($request)) {
        return false;
    }

    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === (int) ($request['batch_id'] ?? 0);
}

function kuppi_request_is_open(array $request): bool
{
    return (string) ($request['status'] ?? '') === 'open';
}

function kuppi_conductor_availability_options(): array
{
    return [
        'weekday_mornings' => 'Weekday Mornings',
        'weekday_afternoons' => 'Weekday Afternoons',
        'weekday_evenings' => 'Weekday Evenings',
        'weekend_mornings' => 'Weekend Mornings',
        'weekend_afternoons' => 'Weekend Afternoons',
        'weekend_evenings' => 'Weekend Evenings',
    ];
}

function kuppi_conductor_availability_to_csv(array $selected): string
{
    $allowed = array_keys(kuppi_conductor_availability_options());
    $normalized = [];
    foreach ($selected as $value) {
        $value = trim((string) $value);
        if ($value === '' || !in_array($value, $allowed, true)) {
            continue;
        }
        $normalized[] = $value;
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);
    return implode(',', $normalized);
}

function kuppi_conductor_availability_from_csv(string $csv): array
{
    $csv = trim($csv);
    if ($csv === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn(string $value): string => trim($value),
        explode(',', $csv)
    ), static fn(string $value): bool => $value !== ''));
}

function kuppi_validate_conductor_application_input(): array
{
    $motivationRaw = (string) request_input('motivation', '');
    $motivation = trim(str_replace(["\r\n", "\r"], "\n", $motivationRaw));
    $availabilityInput = $_POST['availability'] ?? [];
    $availabilityList = is_array($availabilityInput) ? $availabilityInput : [];
    $availabilityCsv = kuppi_conductor_availability_to_csv($availabilityList);
    $availability = kuppi_conductor_availability_from_csv($availabilityCsv);
    $errors = [];

    if ($motivation === '') {
        $errors[] = 'Motivation is required.';
    } elseif (strlen($motivation) > 300) {
        $errors[] = 'Motivation must be at most 300 characters.';
    }

    if (empty($availability)) {
        $errors[] = 'Select at least one availability option.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'motivation' => $motivation,
            'availability_csv' => $availabilityCsv,
            'availability' => $availability,
        ],
    ];
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

function kuppi_comment_target_type(): string
{
    return 'kuppi_request';
}

function kuppi_comment_can_delete(array $request, array $comment): bool
{
    $currentUserId = (int) auth_id();
    $commentAuthorId = (int) ($comment['user_id'] ?? 0);
    if ($commentAuthorId > 0 && $commentAuthorId === $currentUserId) {
        return true;
    }

    if (kuppi_user_can_moderate_batch((int) ($request['batch_id'] ?? 0))) {
        return true;
    }

    if ((string) user_role() === 'coordinator') {
        return subjects_find_for_coordinator((int) ($request['subject_id'] ?? 0), $currentUserId) !== null;
    }

    return false;
}

function kuppi_enrich_comment_tree(array $nodes, array $request): array
{
    $currentUserId = (int) auth_id();
    $maxDepth = comments_max_depth_for_target(kuppi_comment_target_type());
    $enriched = [];

    foreach ($nodes as $node) {
        $authorId = (int) ($node['user_id'] ?? 0);
        $depth = (int) ($node['depth'] ?? 0);
        $node['can_edit'] = $authorId > 0 && $authorId === $currentUserId;
        $node['can_delete'] = kuppi_comment_can_delete($request, $node);
        $node['can_reply'] = auth_check() && $depth < $maxDepth;
        $node['children'] = kuppi_enrich_comment_tree((array) ($node['children'] ?? []), $request);
        $enriched[] = $node;
    }

    return $enriched;
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

    $viewerId = (int) auth_id();
    $conductorApplications = kuppi_conductor_applications_for_request($requestId, $viewerId);
    $viewerApplication = kuppi_find_user_conductor_application($requestId, $viewerId);

    $topVoteApplicationId = 0;
    $topVoteCount = -1;
    foreach ($conductorApplications as &$application) {
        $application['availability'] = kuppi_conductor_availability_from_csv((string) ($application['availability_csv'] ?? ''));
        $voteCount = (int) ($application['vote_count'] ?? 0);
        if ($voteCount > $topVoteCount) {
            $topVoteCount = $voteCount;
            $topVoteApplicationId = (int) ($application['id'] ?? 0);
        }
    }
    unset($application);

    $commentsTree = comments_tree_for_target(kuppi_comment_target_type(), $requestId);
    $commentsTree = kuppi_enrich_comment_tree($commentsTree, $request);
    $commentCount = comments_count_for_target(kuppi_comment_target_type(), $requestId);

    view('kuppi::show', [
        'request' => $request,
        'tags' => kuppi_tags_to_array((string) ($request['tags_csv'] ?? '')),
        'can_edit_request' => kuppi_can_edit_request($request),
        'can_delete_request' => kuppi_can_delete_request($request),
        'can_vote_request' => kuppi_user_can_vote_request($request),
        'can_apply_as_conductor' => kuppi_user_can_apply_as_conductor($request),
        'can_vote_conductor' => kuppi_user_can_vote_conductor($request),
        'conductor_applications' => $conductorApplications,
        'conductor_count' => count($conductorApplications),
        'viewer_conductor_application' => $viewerApplication,
        'top_vote_application_id' => $topVoteApplicationId,
        'availability_options' => kuppi_conductor_availability_options(),
        'comments' => $commentsTree,
        'comment_count' => $commentCount,
        'comment_max_level' => comments_max_depth_for_target(kuppi_comment_target_type()) + 1,
        'back_list_url' => kuppi_index_url_for_request($request),
    ], 'dashboard');
}

function kuppi_conductor_apply_form(string $id): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_user_can_apply_as_conductor($request)) {
        abort(403, 'Only students can apply as conductors for open requests in their batch.');
    }

    $existingApplication = kuppi_find_user_conductor_application($requestId, (int) auth_id());
    if ($existingApplication) {
        flash('warning', 'You have already applied as a conductor for this request.');
        redirect(kuppi_request_url($request));
    }

    view('kuppi::conductor_apply', [
        'request' => $request,
        'availability_options' => kuppi_conductor_availability_options(),
        'back_request_url' => kuppi_request_url($request),
    ], 'dashboard');
}

function kuppi_conductor_apply_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_user_can_apply_as_conductor($request)) {
        abort(403, 'Only students can apply as conductors for open requests in their batch.');
    }

    $existingApplication = kuppi_find_user_conductor_application($requestId, (int) auth_id());
    if ($existingApplication) {
        flash('warning', 'You have already applied as a conductor for this request.');
        redirect(kuppi_request_url($request));
    }

    $validated = kuppi_validate_conductor_application_input();
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/apply');
    }

    try {
        kuppi_create_conductor_application([
            'request_id' => $requestId,
            'applicant_user_id' => (int) auth_id(),
            'motivation' => $validated['data']['motivation'],
            'availability_csv' => $validated['data']['availability_csv'],
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to submit conductor application right now.');
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/apply');
    }

    clear_old_input();
    flash('success', 'Conductor application submitted.');
    redirect(kuppi_request_url($request));
}

function kuppi_conductor_vote_action(string $id, string $applicationId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $returnTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);
    if (!kuppi_user_can_vote_conductor($request)) {
        abort(403, 'Only students in this batch can vote for conductors.');
    }

    $application = kuppi_find_conductor_application_for_request((int) $applicationId, $requestId);
    if (!$application) {
        abort(404, 'Conductor application not found.');
    }

    if ((int) ($application['applicant_user_id'] ?? 0) === (int) auth_id()) {
        flash('error', 'You cannot vote for your own conductor application.');
        redirect($returnTo);
    }

    try {
        $isVoted = kuppi_toggle_conductor_vote((int) $applicationId, (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to save conductor vote right now.');
        redirect($returnTo);
    }

    flash('success', $isVoted ? 'Conductor vote added.' : 'Conductor vote removed.');
    redirect($returnTo);
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

function kuppi_comment_store(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $requestPath = kuppi_request_url($request);
    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($requestPath . '#kuppi-comments');
    }

    $targetType = kuppi_comment_target_type();
    $parentCommentId = (int) request_input('parent_comment_id', 0);
    $parentId = null;
    $depth = 0;
    $maxDepth = comments_max_depth_for_target($targetType);

    if ($parentCommentId > 0) {
        $parent = comments_find_target_comment($parentCommentId, $targetType, $requestId);
        if (!$parent) {
            flash('error', 'Reply target not found.');
            redirect($requestPath . '#kuppi-comments');
        }

        $depth = (int) ($parent['depth'] ?? 0) + 1;
        if ($depth > $maxDepth) {
            flash('error', 'Reply depth limit reached.');
            redirect($requestPath . '#kuppi-comments');
        }

        $parentId = $parentCommentId;
    }

    try {
        comments_insert($targetType, $requestId, (int) auth_id(), $validation['body'], $parentId, $depth);
    } catch (Throwable) {
        flash('error', 'Unable to post comment right now. Please try again.');
        redirect($requestPath . '#kuppi-comments');
    }

    flash('success', 'Comment posted.');
    redirect($requestPath . '#kuppi-comments');
}

function kuppi_comment_update(string $id, string $commentId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $requestPath = kuppi_request_url($request);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, kuppi_comment_target_type(), $requestId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if ((int) ($comment['user_id'] ?? 0) !== (int) auth_id()) {
        abort(403, 'You can only edit your own comments.');
    }

    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($requestPath . '#kuppi-comments');
    }

    if (!comments_update_body_by_author($commentIdInt, (int) auth_id(), $validation['body'])) {
        flash('error', 'Unable to update this comment.');
        redirect($requestPath . '#kuppi-comments');
    }

    flash('success', 'Comment updated.');
    redirect($requestPath . '#kuppi-comments');
}

function kuppi_comment_delete(string $id, string $commentId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $requestPath = kuppi_request_url($request);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, kuppi_comment_target_type(), $requestId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if (!kuppi_comment_can_delete($request, $comment)) {
        abort(403, 'You do not have permission to delete this comment.');
    }

    if (!comments_delete_by_id($commentIdInt)) {
        flash('error', 'Unable to delete this comment.');
        redirect($requestPath . '#kuppi-comments');
    }

    flash('success', 'Comment deleted.');
    redirect($requestPath . '#kuppi-comments');
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
