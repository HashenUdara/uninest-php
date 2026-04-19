<?php

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
        flash('warning', 'You have already applied. You can edit your application.');
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/' . (int) ($existingApplication['id'] ?? 0) . '/edit');
    }

    view('kuppi::conductor_apply', [
        'request' => $request,
        'availability_options' => kuppi_conductor_availability_options(),
        'back_request_url' => kuppi_request_url($request),
        'is_edit' => false,
        'form_action' => '/dashboard/kuppi/' . $requestId . '/conductors/apply',
        'submit_label' => 'Submit Application',
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

function kuppi_conductor_edit_form(string $id, string $applicationId): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $application = kuppi_find_conductor_application_for_request((int) $applicationId, $requestId);
    if (!$application) {
        abort(404, 'Conductor application not found.');
    }

    if (!kuppi_can_manage_own_conductor_application($request, $application)) {
        abort(403, 'Only the applicant can edit this conductor application while the request is open.');
    }

    view('kuppi::conductor_apply', [
        'request' => $request,
        'application' => $application,
        'availability_options' => kuppi_conductor_availability_options(),
        'back_request_url' => kuppi_request_url($request),
        'is_edit' => true,
        'form_action' => '/dashboard/kuppi/' . $requestId . '/conductors/' . (int) $application['id'],
        'delete_action' => '/dashboard/kuppi/' . $requestId . '/conductors/' . (int) $application['id'] . '/delete',
        'submit_label' => 'Update Application',
    ], 'dashboard');
}

function kuppi_conductor_update_action(string $id, string $applicationId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $application = kuppi_find_conductor_application_for_request((int) $applicationId, $requestId);
    if (!$application) {
        abort(404, 'Conductor application not found.');
    }

    if (!kuppi_can_manage_own_conductor_application($request, $application)) {
        abort(403, 'Only the applicant can update this conductor application while the request is open.');
    }

    $validated = kuppi_validate_conductor_application_input();
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/' . (int) $application['id'] . '/edit');
    }

    try {
        $updated = kuppi_update_conductor_application_by_owner(
            (int) $application['id'],
            $requestId,
            (int) auth_id(),
            [
                'motivation' => $validated['data']['motivation'],
                'availability_csv' => $validated['data']['availability_csv'],
            ]
        );
    } catch (Throwable) {
        flash('error', 'Unable to update conductor application right now.');
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/' . (int) $application['id'] . '/edit');
    }

    if (!$updated) {
        flash('error', 'Unable to update this conductor application.');
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/' . (int) $application['id'] . '/edit');
    }

    clear_old_input();
    flash('success', 'Conductor application updated.');
    redirect(kuppi_request_url($request));
}

function kuppi_conductor_delete_action(string $id, string $applicationId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $application = kuppi_find_conductor_application_for_request((int) $applicationId, $requestId);
    if (!$application) {
        abort(404, 'Conductor application not found.');
    }

    if (!kuppi_can_manage_own_conductor_application($request, $application)) {
        abort(403, 'Only the applicant can delete this conductor application while the request is open.');
    }

    $returnTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);

    try {
        $deleted = kuppi_delete_conductor_application_by_owner(
            (int) $application['id'],
            $requestId,
            (int) auth_id()
        );
    } catch (Throwable) {
        flash('error', 'Unable to delete conductor application right now.');
        redirect($returnTo);
    }

    if (!$deleted) {
        flash('error', 'Unable to delete this conductor application.');
        redirect($returnTo);
    }

    clear_old_input();
    flash('success', 'Conductor application deleted.');
    redirect($returnTo);
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

function kuppi_conductor_vote_delete_action(string $id, string $applicationId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $returnTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);
    if (!kuppi_user_can_vote_conductor($request)) {
        abort(403, 'Only students in this batch can remove conductor votes.');
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
        $removed = kuppi_remove_conductor_vote((int) $applicationId, (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to remove conductor vote right now.');
        redirect($returnTo);
    }

    flash('success', $removed ? 'Conductor vote removed.' : 'No active conductor vote found.');
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

function kuppi_vote_delete_action(string $id): void
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

    try {
        $removed = kuppi_remove_vote($requestId, (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to remove your vote right now.');
        redirect($returnTo);
    }

    flash('success', $removed ? 'Vote removed.' : 'No active vote found.');
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

