<?php

/**
 * Community Module — Controllers
 */

function community_max_upload_bytes(): int
{
    return 10 * 1024 * 1024; // 10MB
}

function community_storage_relative_root(): string
{
    return 'storage/community';
}

function community_post_type_badge_class(string $postType): string
{
    return match ($postType) {
        'announcement' => 'badge-warning',
        'question' => 'badge-info',
        default => '',
    };
}

function community_user_can_post(): bool
{
    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator', 'moderator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) > 0;
}

function community_user_can_save_posts(): bool
{
    $role = (string) user_role();
    if ($role === 'admin') {
        return true;
    }

    return in_array($role, ['student', 'coordinator', 'moderator'], true);
}

function community_user_can_moderate_batch(int $batchId): bool
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

function community_feed_per_page(): int
{
    return 10;
}

function community_max_pinned_announcements_per_batch(): int
{
    return 3;
}

function community_report_max_details_length(): int
{
    return 1000;
}

function community_report_reason_options_with_labels(): array
{
    $options = [];
    foreach (community_report_reasons() as $reason) {
        $options[$reason] = community_report_reason_label($reason);
    }

    return $options;
}

function community_feed_url_for_batch(int $batchId): string
{
    if (user_role() === 'admin') {
        return '/dashboard/community?batch_id=' . $batchId;
    }

    return '/dashboard/community';
}

function community_reports_queue_url(?int $batchId = null): string
{
    if (user_role() === 'admin' && $batchId !== null && $batchId > 0) {
        return '/dashboard/community/reports?batch_id=' . $batchId;
    }

    return '/dashboard/community/reports';
}

function community_post_url(array $post): string
{
    $url = '/dashboard/community/' . (int) ($post['id'] ?? 0);
    if (user_role() === 'admin') {
        $batchId = (int) ($post['batch_id'] ?? 0);
        if ($batchId > 0) {
            $url .= '?batch_id=' . $batchId;
        }
    }

    return $url;
}

function community_resolve_valid_return_to(string $returnTo, array $post, string $fallbackAnchor = ''): string
{
    $raw = trim($returnTo);
    if ($raw !== '') {
        $path = (string) parse_url($raw, PHP_URL_PATH);
        if (
            str_starts_with($path, '/dashboard/community')
            || str_starts_with($path, '/dashboard/feed')
            || str_starts_with($path, '/my-posts')
            || str_starts_with($path, '/saved-posts')
        ) {
            return $raw;
        }
    }

    return community_post_url($post) . $fallbackAnchor;
}

function community_store_error_redirect_url(): string
{
    $raw = trim((string) request_input('return_to', ''));
    if ($raw === '') {
        return '/dashboard/community';
    }

    $path = (string) parse_url($raw, PHP_URL_PATH);
    if (str_starts_with($path, '/dashboard/community/create') || str_starts_with($path, '/dashboard/community')) {
        return $raw;
    }

    return '/dashboard/community';
}

function community_request_uploaded_image(string $field = 'image'): ?array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return $file;
}

function community_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded image is too large.',
        UPLOAD_ERR_PARTIAL => 'Image upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload temporary directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded image.',
        UPLOAD_ERR_EXTENSION => 'Image upload blocked by server extension.',
        default => 'Invalid image upload.',
    };
}

function community_validate_uploaded_image(array $file): array
{
    $errors = [];

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = community_upload_error_message($errorCode);
        return ['errors' => $errors];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Uploaded image payload is invalid.';
        return ['errors' => $errors];
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0) {
        $errors[] = 'Uploaded image is empty.';
    }

    if ($fileSize > community_max_upload_bytes()) {
        $errors[] = 'Image size must be 10MB or less.';
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, community_allowed_image_extensions(), true)) {
        $errors[] = 'Allowed image types: ' . implode(', ', community_allowed_image_extensions()) . '.';
    }

    $mime = mime_content_type($tmpName);
    if (!is_string($mime) || !str_starts_with(strtolower($mime), 'image/')) {
        $errors[] = 'Uploaded file must be a valid image.';
    }

    if (!empty($errors)) {
        return ['errors' => $errors];
    }

    return [
        'errors' => [],
        'meta' => [
            'tmp_name' => $tmpName,
            'original_name' => $originalName,
            'extension' => $extension,
            'size' => $fileSize,
            'mime' => $mime,
        ],
    ];
}

function community_store_uploaded_image(array $file): array
{
    $validated = community_validate_uploaded_image($file);
    if (!empty($validated['errors'])) {
        throw new RuntimeException((string) ($validated['errors'][0] ?? 'Invalid image upload.'));
    }

    $meta = $validated['meta'];
    $tmpName = (string) $meta['tmp_name'];
    $extension = (string) $meta['extension'];
    $originalName = basename((string) $meta['original_name']);
    $safeOriginalName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: ('post-image.' . $extension);

    $subDir = date('Y/m');
    $relativeDir = community_storage_relative_root() . '/' . $subDir;
    $absoluteDir = BASE_PATH . '/' . $relativeDir;

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('Unable to create storage directory for community images.');
    }

    $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $relativePath = $relativeDir . '/' . $storedName;
    $absolutePath = BASE_PATH . '/' . $relativePath;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('Unable to store uploaded image.');
    }

    $mime = mime_content_type($absolutePath);
    if (!is_string($mime) || trim($mime) === '') {
        $mime = (string) ($meta['mime'] ?? 'application/octet-stream');
    }

    return [
        'image_path' => $relativePath,
        'image_name' => $safeOriginalName,
        'image_mime' => $mime,
        'image_size' => (int) $meta['size'],
    ];
}

function community_delete_file_safe(?string $relativePath): void
{
    $normalizedPath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    if ($normalizedPath === '') {
        return;
    }

    $storagePrefix = community_storage_relative_root() . '/';
    if (!str_starts_with($normalizedPath, $storagePrefix)) {
        return;
    }

    $absolutePath = BASE_PATH . '/' . $normalizedPath;
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function community_cleanup_file_paths(array $paths): void
{
    $uniquePaths = array_values(array_unique(array_filter(array_map(
        static fn($path): string => trim((string) $path),
        $paths
    ))));

    foreach ($uniquePaths as $path) {
        community_delete_file_safe($path);
    }
}

function community_resolve_accessible_image_post(int $postId): ?array
{
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        return null;
    }

    if (trim((string) ($post['image_path'] ?? '')) === '') {
        return null;
    }

    return $post;
}

function community_stream_image(array $post): void
{
    $relativePath = ltrim(str_replace('\\', '/', (string) ($post['image_path'] ?? '')), '/');
    if (!str_starts_with($relativePath, community_storage_relative_root() . '/')) {
        abort(404, 'Image path is invalid.');
    }

    $absolutePath = BASE_PATH . '/' . $relativePath;
    if (!is_file($absolutePath)) {
        abort(404, 'Image not found on server.');
    }

    $mime = trim((string) ($post['image_mime'] ?? ''));
    if ($mime === '') {
        $mime = (string) mime_content_type($absolutePath);
    }
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($absolutePath));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($post['image_name'] ?? 'community-image')) . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    readfile($absolutePath);
    exit;
}

function community_prepare_post_payload(?array $basePost, int $batchId): array
{
    $errors = [];

    $bodyRaw = (string) request_input('body', '');
    $body = trim(str_replace(["\r\n", "\r"], "\n", $bodyRaw));
    $postType = trim((string) request_input('post_type', 'general'));
    $subjectIdRaw = (int) request_input('subject_id', 0);
    $subjectId = $subjectIdRaw > 0 ? $subjectIdRaw : null;
    $removeImage = (string) request_input('remove_image', '0') === '1';
    $uploadedImage = community_request_uploaded_image('image');

    if (!in_array($postType, community_post_types(), true)) {
        $errors[] = 'Select a valid post type.';
    }

    if (strlen($body) > 6000) {
        $errors[] = 'Post content must be at most 6000 characters.';
    }

    if ($subjectId !== null && !community_subject_exists_in_batch($subjectId, $batchId)) {
        $errors[] = 'Selected subject is invalid for your batch.';
    }

    if ($uploadedImage !== null) {
        $imageValidation = community_validate_uploaded_image($uploadedImage);
        $errors = array_merge($errors, $imageValidation['errors'] ?? []);
    }

    $hasExistingImage = $basePost
        && trim((string) ($basePost['image_path'] ?? '')) !== '';
    $hasImageAfterSubmit = $uploadedImage !== null || ($hasExistingImage && !$removeImage);

    if ($body === '' && !$hasImageAfterSubmit) {
        $errors[] = 'Post content is required unless an image is attached.';
    }

    return [
        'errors' => $errors,
        'validated' => [
            'body' => $body === '' ? null : $body,
            'post_type' => $postType,
            'subject_id' => $subjectId,
            'remove_image' => $removeImage,
        ],
        'uploaded_image' => $uploadedImage,
    ];
}

function community_compose_post_payload(array $validated, ?array $basePost, ?array $storedImageMeta): array
{
    $payload = [
        'body' => $validated['body'],
        'post_type' => $validated['post_type'],
        'subject_id' => $validated['subject_id'],
        'image_path' => null,
        'image_name' => null,
        'image_mime' => null,
        'image_size' => null,
    ];

    if ($storedImageMeta !== null) {
        $payload['image_path'] = $storedImageMeta['image_path'];
        $payload['image_name'] = $storedImageMeta['image_name'];
        $payload['image_mime'] = $storedImageMeta['image_mime'];
        $payload['image_size'] = $storedImageMeta['image_size'];
        return $payload;
    }

    if (
        $basePost !== null
        && !$validated['remove_image']
        && trim((string) ($basePost['image_path'] ?? '')) !== ''
    ) {
        $payload['image_path'] = $basePost['image_path'] ?? null;
        $payload['image_name'] = $basePost['image_name'] ?? null;
        $payload['image_mime'] = $basePost['image_mime'] ?? null;
        $payload['image_size'] = $basePost['image_size'] ?? null;
    }

    return $payload;
}

function community_resolve_readable_post(int $postId): ?array
{
    if (user_role() === 'admin') {
        return community_find_post_admin($postId, (int) auth_id());
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        return null;
    }

    return community_find_post_for_batch($postId, $batchId, (int) auth_id());
}

function community_post_can_delete(array $post): bool
{
    $postAuthorId = (int) ($post['author_user_id'] ?? 0);
    $currentUserId = (int) auth_id();

    if ($postAuthorId > 0 && $postAuthorId === $currentUserId) {
        return true;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        return true;
    }

    return community_user_can_moderate_batch((int) ($post['batch_id'] ?? 0));
}

function community_comment_can_delete(array $post, array $comment): bool
{
    $commentAuthorId = (int) ($comment['user_id'] ?? 0);
    if ($commentAuthorId > 0 && $commentAuthorId === (int) auth_id()) {
        return true;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        return true;
    }

    return community_user_can_moderate_batch((int) ($post['batch_id'] ?? 0));
}

function community_post_can_pin(array $post): bool
{
    if ((string) ($post['post_type'] ?? '') !== 'announcement') {
        return false;
    }

    return community_user_can_moderate_batch((int) ($post['batch_id'] ?? 0));
}

function community_post_can_resolve_question(array $post): bool
{
    if ((string) ($post['post_type'] ?? '') !== 'question') {
        return false;
    }

    return (int) ($post['author_user_id'] ?? 0) === (int) auth_id();
}

function community_validate_report_reason(string $reason): string
{
    $normalized = trim($reason);
    if (in_array($normalized, community_report_reasons(), true)) {
        return $normalized;
    }

    return 'other';
}

function community_validate_report_details(string $details): ?string
{
    $normalized = trim(str_replace(["\r\n", "\r"], "\n", $details));
    if ($normalized === '') {
        return null;
    }

    if (strlen($normalized) > community_report_max_details_length()) {
        return substr($normalized, 0, community_report_max_details_length());
    }

    return $normalized;
}

function community_resolve_open_reports_for_post_comments(int $postId, int $reviewerUserId, string $actionTaken): void
{
    if ($postId <= 0 || $reviewerUserId <= 0) {
        return;
    }

    $commentRows = comments_rows_for_target('feed_post', $postId);
    foreach ($commentRows as $commentRow) {
        $commentId = (int) ($commentRow['id'] ?? 0);
        if ($commentId <= 0) {
            continue;
        }
        community_resolve_open_reports_for_target('comment', $commentId, $reviewerUserId, $actionTaken);
    }
}

function community_enrich_comment_tree(array $nodes, array $post): array
{
    $currentUserId = (int) auth_id();
    $maxDepth = comments_max_depth_for_target('feed_post');
    $enriched = [];

    foreach ($nodes as $node) {
        $authorId = (int) ($node['user_id'] ?? 0);
        $depth = (int) ($node['depth'] ?? 0);
        $node['can_edit'] = $authorId > 0 && $authorId === $currentUserId;
        $node['can_delete'] = community_comment_can_delete($post, $node);
        $node['can_reply'] = auth_check() && $depth < $maxDepth;
        $node['children'] = community_enrich_comment_tree((array) ($node['children'] ?? []), $post);
        $enriched[] = $node;
    }

    return $enriched;
}

function community_index(): void
{
    $role = (string) user_role();
    $viewerId = (int) auth_id();
    $isAdmin = $role === 'admin';
    $batchOptions = $isAdmin ? community_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = community_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }
        $activeBatch = community_find_batch_option_by_id($selectedBatchId);
    }

    $subjectOptions = $selectedBatchId > 0
        ? community_subject_options_for_batch($selectedBatchId)
        : [];

    $selectedSubjectId = (int) request_input('subject_id', 0);
    if ($selectedSubjectId > 0 && !community_subject_exists_in_batch($selectedSubjectId, $selectedBatchId)) {
        $selectedSubjectId = 0;
    }

    $selectedPostType = trim((string) request_input('post_type', ''));
    if ($selectedPostType !== '' && !in_array($selectedPostType, community_post_types(), true)) {
        $selectedPostType = '';
    }

    $selectedSort = trim((string) request_input('sort', 'recent'));
    if (!in_array($selectedSort, ['recent', 'top'], true)) {
        $selectedSort = 'recent';
    }

    $selectedSearchQuery = trim((string) request_input('q', ''));
    if (strlen($selectedSearchQuery) > 120) {
        $selectedSearchQuery = substr($selectedSearchQuery, 0, 120);
    }

    $selectedPage = max(1, min(50, (int) request_input('page', 1)));
    $selectedSubjectFilter = $selectedSubjectId > 0 ? $selectedSubjectId : null;

    $feedPage = $selectedBatchId > 0
        ? community_posts_for_batch(
            $selectedBatchId,
            $selectedSubjectFilter,
            $selectedPostType !== '' ? $selectedPostType : null,
            $selectedSort,
            $viewerId,
            $selectedSearchQuery,
            $selectedPage,
            community_feed_per_page()
        )
        : ['posts' => [], 'has_more' => false];

    $postTypeCounts = $selectedBatchId > 0
        ? community_post_type_counts_for_batch($selectedBatchId, $selectedSubjectFilter)
        : [];

    $popularPosts = $selectedBatchId > 0
        ? community_popular_posts_for_batch($selectedBatchId, $selectedSubjectFilter, $viewerId, 3)
        : [];

    view('community::index', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'active_batch' => $activeBatch,
        'selected_batch_id' => $selectedBatchId,
        'subject_options' => $subjectOptions,
        'selected_subject_id' => $selectedSubjectId,
        'selected_post_type' => $selectedPostType,
        'selected_sort' => $selectedSort,
        'selected_search_query' => $selectedSearchQuery,
        'selected_page' => $selectedPage,
        'post_types' => community_post_types(),
        'post_type_counts' => $postTypeCounts,
        'popular_posts' => $popularPosts,
        'posts' => (array) ($feedPage['posts'] ?? []),
        'has_more_posts' => !empty($feedPage['has_more']),
        'can_post' => community_user_can_post(),
        'can_save_posts' => community_user_can_save_posts(),
        'report_reason_options' => community_report_reason_options_with_labels(),
    ], 'dashboard');
}

function community_create_form(): void
{
    if (!community_user_can_post()) {
        abort(403, 'You do not have permission to create community posts.');
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        abort(403, 'You are not assigned to a batch.');
    }

    view('community::create', [
        'active_batch' => community_find_batch_option_by_id($batchId),
        'post_types' => community_post_types(),
        'subject_options' => community_subject_options_for_batch($batchId),
        'back_feed_url' => '/dashboard/community',
    ], 'dashboard');
}

function community_store(): void
{
    csrf_check();
    $errorRedirect = community_store_error_redirect_url();

    if (!community_user_can_post()) {
        abort(403, 'You do not have permission to create community posts.');
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        abort(403, 'You are not assigned to a batch.');
    }

    $prepared = community_prepare_post_payload(null, $batchId);
    if (!empty($prepared['errors'])) {
        flash('error', implode(' ', $prepared['errors']));
        flash_old_input();
        redirect($errorRedirect);
    }

    $storedImageMeta = null;
    try {
        if ($prepared['uploaded_image'] !== null) {
            $storedImageMeta = community_store_uploaded_image($prepared['uploaded_image']);
        }
    } catch (Throwable $e) {
        flash('error', 'Unable to upload image: ' . $e->getMessage());
        flash_old_input();
        redirect($errorRedirect);
    }

    $payload = community_compose_post_payload($prepared['validated'], null, $storedImageMeta);

    try {
        $postId = community_create_post([
            'batch_id' => $batchId,
            'subject_id' => $payload['subject_id'],
            'author_user_id' => (int) auth_id(),
            'post_type' => $payload['post_type'],
            'body' => $payload['body'],
            'image_path' => $payload['image_path'],
            'image_name' => $payload['image_name'],
            'image_mime' => $payload['image_mime'],
            'image_size' => $payload['image_size'],
        ]);
    } catch (Throwable) {
        community_cleanup_file_paths([$storedImageMeta['image_path'] ?? null]);
        flash('error', 'Unable to create post right now. Please try again.');
        flash_old_input();
        redirect($errorRedirect);
    }

    clear_old_input();
    flash('success', 'Post published to your batch community feed.');
    redirect('/dashboard/community/' . $postId);
}

function community_show(string $id): void
{
    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Community post not found.');
    }

    $commentsTree = comments_tree_for_target('feed_post', $postId);
    $commentsTree = community_enrich_comment_tree($commentsTree, $post);

    view('community::show', [
        'post' => $post,
        'comments' => $commentsTree,
        'comment_max_level' => comments_max_depth_for_target('feed_post') + 1,
        'can_edit_post' => (int) ($post['author_user_id'] ?? 0) === (int) auth_id(),
        'can_delete_post' => community_post_can_delete($post),
        'can_save_posts' => community_user_can_save_posts(),
        'can_resolve_question' => community_post_can_resolve_question($post),
        'can_pin_post' => community_post_can_pin($post),
        'report_reason_options' => community_report_reason_options_with_labels(),
        'back_feed_url' => community_feed_url_for_batch((int) ($post['batch_id'] ?? 0)),
    ], 'dashboard');
}

function community_image(string $id): void
{
    $postId = (int) $id;
    $post = community_resolve_accessible_image_post($postId);
    if (!$post) {
        abort(404, 'Post image not found.');
    }

    community_stream_image($post);
}

function community_update_action(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_find_owned_post($postId, (int) auth_id());
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $errorRedirect = community_post_url($post);
    $prepared = community_prepare_post_payload($post, (int) ($post['batch_id'] ?? 0));
    if (!empty($prepared['errors'])) {
        flash('error', implode(' ', $prepared['errors']));
        flash_old_input();
        redirect($errorRedirect);
    }

    $storedImageMeta = null;
    try {
        if ($prepared['uploaded_image'] !== null) {
            $storedImageMeta = community_store_uploaded_image($prepared['uploaded_image']);
        }
    } catch (Throwable $e) {
        flash('error', 'Unable to upload image: ' . $e->getMessage());
        flash_old_input();
        redirect($errorRedirect);
    }

    $payload = community_compose_post_payload($prepared['validated'], $post, $storedImageMeta);

    try {
        community_update_post_by_owner($postId, (int) auth_id(), $payload);
    } catch (Throwable) {
        community_cleanup_file_paths([$storedImageMeta['image_path'] ?? null]);
        flash('error', 'Unable to update this post right now. Please try again.');
        flash_old_input();
        redirect($errorRedirect);
    }

    $oldImagePath = trim((string) ($post['image_path'] ?? ''));
    $newImagePath = trim((string) ($payload['image_path'] ?? ''));
    if ($oldImagePath !== '' && $oldImagePath !== $newImagePath) {
        community_cleanup_file_paths([$oldImagePath]);
    }

    clear_old_input();
    flash('success', 'Post updated.');
    redirect('/dashboard/community/' . $postId);
}

function community_delete_action(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    if (!community_post_can_delete($post)) {
        abort(403, 'You do not have permission to delete this post.');
    }

    $imagePath = $post['image_path'] ?? null;
    if (!community_delete_post_by_id($postId)) {
        flash('error', 'Unable to delete this post.');
        redirect(community_feed_url_for_batch((int) ($post['batch_id'] ?? 0)));
    }

    community_cleanup_file_paths([$imagePath]);
    flash('success', 'Post deleted.');
    redirect(community_feed_url_for_batch((int) ($post['batch_id'] ?? 0)));
}

function community_like_toggle(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    community_toggle_like($postId, (int) auth_id());

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );
    redirect($returnTo);
}

function community_save_toggle(string $id): void
{
    csrf_check();

    if (!community_user_can_save_posts()) {
        abort(403, 'You do not have permission to save posts.');
    }

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $isSaved = community_toggle_save($postId, (int) auth_id());
    flash('success', $isSaved ? 'Post saved.' : 'Post removed from saved posts.');

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );
    redirect($returnTo);
}

function community_report_post(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );

    $viewerId = (int) auth_id();
    if ((int) ($post['author_user_id'] ?? 0) === $viewerId) {
        flash('error', 'You cannot report your own post.');
        redirect($returnTo);
    }

    $reason = community_validate_report_reason((string) request_input('reason', 'other'));
    $details = community_validate_report_details((string) request_input('details', ''));
    $alreadyOpen = community_find_open_report_for_target($viewerId, 'post', $postId);

    if ($alreadyOpen) {
        flash('warning', 'You already have an open report for this post.');
        redirect($returnTo);
    }

    try {
        community_create_report([
            'batch_id' => (int) ($post['batch_id'] ?? 0),
            'target_type' => 'post',
            'target_id' => $postId,
            'reporter_user_id' => $viewerId,
            'reason' => $reason,
            'details' => $details,
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to submit report right now.');
        redirect($returnTo);
    }

    flash('success', 'Post reported. Moderators will review it.');
    redirect($returnTo);
}

function community_comment_report(string $id, string $commentId): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, 'feed_post', $postId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    $postPath = community_post_url($post) . '#post-comments';
    $viewerId = (int) auth_id();
    if ((int) ($comment['user_id'] ?? 0) === $viewerId) {
        flash('error', 'You cannot report your own comment.');
        redirect($postPath);
    }

    $reason = community_validate_report_reason((string) request_input('reason', 'other'));
    $details = community_validate_report_details((string) request_input('details', ''));
    $alreadyOpen = community_find_open_report_for_target($viewerId, 'comment', $commentIdInt);

    if ($alreadyOpen) {
        flash('warning', 'You already have an open report for this comment.');
        redirect($postPath);
    }

    try {
        community_create_report([
            'batch_id' => (int) ($post['batch_id'] ?? 0),
            'target_type' => 'comment',
            'target_id' => $commentIdInt,
            'reporter_user_id' => $viewerId,
            'reason' => $reason,
            'details' => $details,
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to submit report right now.');
        redirect($postPath);
    }

    flash('success', 'Comment reported. Moderators will review it.');
    redirect($postPath);
}

function community_question_resolve(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    if (!community_post_can_resolve_question($post)) {
        abort(403, 'Only the question author can mark this as solved.');
    }

    if (!community_set_question_resolved_state($postId, (int) auth_id(), true)) {
        flash('error', 'Unable to mark this question as solved.');
    } else {
        flash('success', 'Question marked as solved.');
    }

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );
    redirect($returnTo);
}

function community_question_reopen(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    if (!community_post_can_resolve_question($post)) {
        abort(403, 'Only the question author can reopen this question.');
    }

    if (!community_set_question_resolved_state($postId, (int) auth_id(), false)) {
        flash('error', 'Unable to reopen this question.');
    } else {
        flash('success', 'Question reopened.');
    }

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );
    redirect($returnTo);
}

function community_pin_post(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    if (!community_post_can_pin($post)) {
        abort(403, 'You do not have permission to pin this post.');
    }

    $batchId = (int) ($post['batch_id'] ?? 0);
    if ((int) ($post['is_pinned'] ?? 0) !== 1) {
        $pinnedCount = community_pinned_announcement_count_for_batch($batchId);
        if ($pinnedCount >= community_max_pinned_announcements_per_batch()) {
            flash('error', 'Only ' . community_max_pinned_announcements_per_batch() . ' announcements can be pinned at once.');
            redirect(community_resolve_valid_return_to((string) request_input('return_to', ''), $post));
        }
    }

    if (!community_set_post_pin_state($postId, $batchId, (int) auth_id(), true)) {
        flash('error', 'Unable to pin this announcement.');
    } else {
        flash('success', 'Announcement pinned.');
    }

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );
    redirect($returnTo);
}

function community_unpin_post(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    if (!community_post_can_pin($post)) {
        abort(403, 'You do not have permission to unpin this post.');
    }

    if (!community_set_post_pin_state($postId, (int) ($post['batch_id'] ?? 0), (int) auth_id(), false)) {
        flash('error', 'Unable to unpin this announcement.');
    } else {
        flash('success', 'Announcement unpinned.');
    }

    $returnTo = community_resolve_valid_return_to(
        (string) request_input('return_to', ''),
        $post
    );
    redirect($returnTo);
}

function community_reports_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    if (!$isAdmin && $role !== 'moderator') {
        abort(403, 'You do not have permission to access moderation reports.');
    }

    $batchOptions = $isAdmin ? community_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0 && !community_find_batch_option_by_id($selectedBatchId)) {
            $selectedBatchId = 0;
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }
    }

    $reports = community_reports_queue($selectedBatchId > 0 ? $selectedBatchId : null, $isAdmin);
    $openCount = 0;
    foreach ($reports as $report) {
        if ((string) ($report['status'] ?? '') === 'open') {
            $openCount++;
        }
    }

    view('community::reports', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'selected_batch_id' => $selectedBatchId,
        'active_batch' => $selectedBatchId > 0 ? community_find_batch_option_by_id($selectedBatchId) : null,
        'reports' => $reports,
        'open_count' => $openCount,
    ], 'dashboard');
}

function community_report_dismiss(string $id): void
{
    csrf_check();

    $reportId = (int) $id;
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    if (!$isAdmin && $role !== 'moderator') {
        abort(403, 'You do not have permission to moderate reports.');
    }

    $batchId = $isAdmin ? null : (int) (auth_user()['batch_id'] ?? 0);
    $report = community_find_report_queue_item($reportId, $batchId, $isAdmin);
    if (!$report) {
        abort(404, 'Report not found.');
    }

    if ((string) ($report['status'] ?? '') !== 'open') {
        flash('warning', 'This report is already closed.');
        redirect(community_reports_queue_url($isAdmin ? (int) ($report['batch_id'] ?? 0) : null));
    }

    if (!community_dismiss_report($reportId, (int) auth_id())) {
        flash('error', 'Unable to dismiss this report.');
    } else {
        flash('success', 'Report dismissed.');
    }

    redirect(community_reports_queue_url($isAdmin ? (int) ($report['batch_id'] ?? 0) : null));
}

function community_report_remove(string $id): void
{
    csrf_check();

    $reportId = (int) $id;
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    if (!$isAdmin && $role !== 'moderator') {
        abort(403, 'You do not have permission to moderate reports.');
    }

    $batchId = $isAdmin ? null : (int) (auth_user()['batch_id'] ?? 0);
    $report = community_find_report_queue_item($reportId, $batchId, $isAdmin);
    if (!$report) {
        abort(404, 'Report not found.');
    }

    if ((string) ($report['status'] ?? '') !== 'open') {
        flash('warning', 'This report is already closed.');
        redirect(community_reports_queue_url($isAdmin ? (int) ($report['batch_id'] ?? 0) : null));
    }

    $reviewerId = (int) auth_id();
    $targetType = (string) ($report['target_type'] ?? '');
    $targetId = (int) ($report['target_id'] ?? 0);
    $actionTaken = 'target_missing';

    if ($targetType === 'post') {
        $post = community_find_post_admin($targetId, $reviewerId);
        if ($post) {
            if (!community_user_can_moderate_batch((int) ($post['batch_id'] ?? 0))) {
                abort(403, 'You do not have permission to remove this post.');
            }

            $actionTaken = 'removed_post';
            community_resolve_open_reports_for_post_comments($targetId, $reviewerId, $actionTaken);

            $imagePath = $post['image_path'] ?? null;
            if (!community_delete_post_by_id($targetId)) {
                flash('error', 'Unable to remove reported post.');
                redirect(community_reports_queue_url($isAdmin ? (int) ($report['batch_id'] ?? 0) : null));
            }

            community_cleanup_file_paths([$imagePath]);
        }
    } elseif ($targetType === 'comment') {
        $comment = comments_find_by_id($targetId);
        if ($comment && (string) ($comment['target_type'] ?? '') === 'feed_post') {
            $threadPostId = (int) ($comment['target_id'] ?? 0);
            if ($threadPostId > 0) {
                $threadPost = community_find_post_admin($threadPostId, $reviewerId);
                if ($threadPost) {
                    if (!community_user_can_moderate_batch((int) ($threadPost['batch_id'] ?? 0))) {
                        abort(403, 'You do not have permission to remove this comment.');
                    }

                    $actionTaken = 'removed_comment';
                    comments_delete_by_id($targetId);
                }
            }
        }
    } else {
        flash('error', 'Invalid report target.');
        redirect(community_reports_queue_url($isAdmin ? (int) ($report['batch_id'] ?? 0) : null));
    }

    $resolvedCount = community_resolve_open_reports_for_target($targetType, $targetId, $reviewerId, $actionTaken);
    if ($resolvedCount === 0) {
        community_resolve_report($reportId, $reviewerId, $actionTaken);
    }

    flash('success', $actionTaken === 'target_missing' ? 'Report closed. Target was already removed.' : 'Reported content removed and report resolved.');
    redirect(community_reports_queue_url($isAdmin ? (int) ($report['batch_id'] ?? 0) : null));
}

function community_comment_store(string $id): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $postPath = community_post_url($post);
    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($postPath . '#post-comments');
    }

    $parentCommentId = (int) request_input('parent_comment_id', 0);
    $parentId = null;
    $depth = 0;
    $maxDepth = comments_max_depth_for_target('feed_post');

    if ($parentCommentId > 0) {
        $parent = comments_find_target_comment($parentCommentId, 'feed_post', $postId);
        if (!$parent) {
            flash('error', 'Reply target not found.');
            redirect($postPath . '#post-comments');
        }

        $depth = (int) ($parent['depth'] ?? 0) + 1;
        if ($depth > $maxDepth) {
            flash('error', 'Reply depth limit reached.');
            redirect($postPath . '#post-comments');
        }

        $parentId = $parentCommentId;
    }

    try {
        comments_insert('feed_post', $postId, (int) auth_id(), $validation['body'], $parentId, $depth);
    } catch (Throwable) {
        flash('error', 'Unable to post comment right now. Please try again.');
        redirect($postPath . '#post-comments');
    }

    flash('success', 'Comment posted.');
    redirect($postPath . '#post-comments');
}

function community_comment_update(string $id, string $commentId): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $postPath = community_post_url($post);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, 'feed_post', $postId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if ((int) ($comment['user_id'] ?? 0) !== (int) auth_id()) {
        abort(403, 'You can only edit your own comments.');
    }

    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($postPath . '#post-comments');
    }

    if (!comments_update_body_by_author($commentIdInt, (int) auth_id(), $validation['body'])) {
        flash('error', 'Unable to update this comment.');
        redirect($postPath . '#post-comments');
    }

    flash('success', 'Comment updated.');
    redirect($postPath . '#post-comments');
}

function community_comment_delete(string $id, string $commentId): void
{
    csrf_check();

    $postId = (int) $id;
    $post = community_resolve_readable_post($postId);
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $postPath = community_post_url($post);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, 'feed_post', $postId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if (!community_comment_can_delete($post, $comment)) {
        abort(403, 'You do not have permission to delete this comment.');
    }

    if (!comments_delete_by_id($commentIdInt)) {
        flash('error', 'Unable to delete this comment.');
        redirect($postPath . '#post-comments');
    }

    flash('success', 'Comment deleted.');
    redirect($postPath . '#post-comments');
}

function community_saved_posts_index(): void
{
    if (!community_user_can_save_posts()) {
        abort(403, 'You do not have permission to access saved posts.');
    }

    $isAdmin = user_role() === 'admin';
    $batchId = $isAdmin ? null : (int) (auth_user()['batch_id'] ?? 0);
    if (!$isAdmin && ($batchId === null || $batchId <= 0)) {
        abort(403, 'You are not assigned to a batch.');
    }

    view('community::saved', [
        'posts' => community_saved_posts_for_user((int) auth_id(), $batchId, $isAdmin),
    ], 'dashboard');
}

function community_my_index(): void
{
    if (!community_user_can_post()) {
        abort(403, 'You do not have permission to manage posts.');
    }

    view('community::my_index', [
        'posts' => community_my_posts((int) auth_id()),
    ], 'dashboard');
}

function community_my_edit_form(string $id): void
{
    if (!community_user_can_post()) {
        abort(403, 'You do not have permission to edit posts.');
    }

    $postId = (int) $id;
    $post = community_find_owned_post($postId, (int) auth_id());
    if (!$post) {
        abort(404, 'Post not found.');
    }

    view('community::edit', [
        'post' => $post,
        'post_types' => community_post_types(),
        'subject_options' => community_subject_options_for_batch((int) ($post['batch_id'] ?? 0)),
    ], 'dashboard');
}

function community_my_update_action(string $id): void
{
    csrf_check();

    if (!community_user_can_post()) {
        abort(403, 'You do not have permission to edit posts.');
    }

    $postId = (int) $id;
    $post = community_find_owned_post($postId, (int) auth_id());
    if (!$post) {
        abort(404, 'Post not found.');
    }

    $errorRedirect = '/my-posts/' . $postId . '/edit';
    $prepared = community_prepare_post_payload($post, (int) ($post['batch_id'] ?? 0));
    if (!empty($prepared['errors'])) {
        flash('error', implode(' ', $prepared['errors']));
        flash_old_input();
        redirect($errorRedirect);
    }

    $storedImageMeta = null;
    try {
        if ($prepared['uploaded_image'] !== null) {
            $storedImageMeta = community_store_uploaded_image($prepared['uploaded_image']);
        }
    } catch (Throwable $e) {
        flash('error', 'Unable to upload image: ' . $e->getMessage());
        flash_old_input();
        redirect($errorRedirect);
    }

    $payload = community_compose_post_payload($prepared['validated'], $post, $storedImageMeta);

    try {
        community_update_post_by_owner($postId, (int) auth_id(), $payload);
    } catch (Throwable) {
        community_cleanup_file_paths([$storedImageMeta['image_path'] ?? null]);
        flash('error', 'Unable to update this post right now. Please try again.');
        flash_old_input();
        redirect($errorRedirect);
    }

    $oldImagePath = trim((string) ($post['image_path'] ?? ''));
    $newImagePath = trim((string) ($payload['image_path'] ?? ''));
    if ($oldImagePath !== '' && $oldImagePath !== $newImagePath) {
        community_cleanup_file_paths([$oldImagePath]);
    }

    clear_old_input();
    flash('success', 'Post updated.');
    redirect('/my-posts');
}

function community_my_delete_action(string $id): void
{
    csrf_check();

    if (!community_user_can_post()) {
        abort(403, 'You do not have permission to delete posts.');
    }

    $postId = (int) $id;
    $post = community_find_owned_post($postId, (int) auth_id());
    if (!$post) {
        abort(404, 'Post not found.');
    }

    if (!community_delete_post_by_id($postId)) {
        flash('error', 'Unable to delete this post.');
        redirect('/my-posts');
    }

    community_cleanup_file_paths([$post['image_path'] ?? null]);
    flash('success', 'Post deleted.');
    redirect('/my-posts');
}
