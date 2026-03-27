<?php

/**
 * Resources Module — Controllers
 */

function resources_max_upload_bytes(): int
{
    return 25 * 1024 * 1024; // 25MB
}

function resources_storage_relative_root(): string
{
    return 'storage/resources';
}

function resources_category_display(string $category, ?string $categoryOther = null): string
{
    if ($category === 'Other') {
        $other = trim((string) $categoryOther);
        if ($other !== '') {
            return 'Other: ' . $other;
        }
    }

    return $category;
}

function resources_source_label(string $sourceType): string
{
    return $sourceType === 'file' ? 'File' : 'Link';
}

function resources_file_extension_label(?string $fileName, ?string $filePath = null): string
{
    $base = trim((string) ($fileName ?? ''));
    if ($base === '') {
        $base = trim((string) ($filePath ?? ''));
    }

    $extension = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
    if ($extension === '') {
        return 'FILE';
    }

    return strtoupper(substr($extension, 0, 6));
}

function resources_link_host_label(?string $url): string
{
    $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
    if ($host === '') {
        return 'LINK';
    }

    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    return strtoupper(substr($host, 0, 20));
}

function resources_format_file_size(?int $bytes): string
{
    $size = (int) $bytes;
    if ($size <= 0) {
        return 'Unknown size';
    }

    if ($size < 1024) {
        return $size . ' B';
    }

    if ($size < 1024 * 1024) {
        return number_format($size / 1024, 1) . ' KB';
    }

    return number_format($size / (1024 * 1024), 1) . ' MB';
}

function resources_format_rating_value(float $average): string
{
    return number_format($average, 1);
}

function resources_rating_summary_label(?float $averageRating, int $ratingCount): string
{
    if ($ratingCount <= 0) {
        return 'No ratings yet';
    }

    return 'Rating ' . resources_format_rating_value((float) $averageRating) . '/5 (' . $ratingCount . ')';
}

function resources_comment_count_label(int $commentCount): string
{
    if ($commentCount <= 0) {
        return 'No comments yet';
    }

    return $commentCount . ' comment' . ($commentCount === 1 ? '' : 's');
}

function resources_rating_distribution_peak(array $distribution): int
{
    if (empty($distribution)) {
        return 0;
    }

    return (int) max(array_map(static fn($value): int => (int) $value, $distribution));
}

function resources_file_previewable_extensions(): array
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'txt'];
}

function resources_is_file_previewable(array $resource): bool
{
    if ((string) ($resource['source_type'] ?? '') !== 'file') {
        return false;
    }

    $fileName = trim((string) ($resource['file_name'] ?? ''));
    $filePath = trim((string) ($resource['file_path'] ?? ''));
    $extension = strtolower((string) pathinfo($fileName !== '' ? $fileName : $filePath, PATHINFO_EXTENSION));
    if (in_array($extension, resources_file_previewable_extensions(), true)) {
        return true;
    }

    $mime = strtolower(trim((string) ($resource['file_mime'] ?? '')));
    if ($mime === 'application/pdf') {
        return true;
    }

    if (str_starts_with($mime, 'image/')) {
        return true;
    }

    if (str_starts_with($mime, 'text/')) {
        return true;
    }

    return false;
}

function resources_can_embed_in_iframe(array $resource): bool
{
    if ((string) ($resource['source_type'] ?? '') === 'file') {
        return resources_is_file_previewable($resource);
    }

    return resources_is_valid_http_url(trim((string) ($resource['external_url'] ?? '')));
}

function resources_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'published' => 'Published',
        'rejected' => 'Rejected',
        default => 'Unknown',
    };
}

function resources_status_badge_class(string $status): string
{
    return match ($status) {
        'pending' => 'badge-warning',
        'published' => 'badge-info',
        'rejected' => 'badge-danger',
        default => '',
    };
}

function resources_update_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Update Pending',
        'rejected' => 'Update Rejected',
        default => 'Update Unknown',
    };
}

function resources_update_status_badge_class(string $status): string
{
    return match ($status) {
        'pending' => 'badge-warning',
        'rejected' => 'badge-danger',
        default => '',
    };
}

function resources_is_valid_http_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

function resources_request_uploaded_file(string $field = 'resource_file'): ?array
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

function resources_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
        UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload temporary directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'File upload blocked by server extension.',
        default => 'Invalid file upload.',
    };
}

function resources_validate_uploaded_file(array $file): array
{
    $errors = [];

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = resources_upload_error_message($errorCode);
        return ['errors' => $errors];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = 'Uploaded file payload is invalid.';
        return ['errors' => $errors];
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0) {
        $errors[] = 'Uploaded file is empty.';
    }

    if ($fileSize > resources_max_upload_bytes()) {
        $errors[] = 'File size must be 25MB or less.';
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, resources_allowed_file_extensions(), true)) {
        $errors[] = 'Allowed file types: ' . implode(', ', resources_allowed_file_extensions()) . '.';
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
        ],
    ];
}

function resources_store_uploaded_file(array $file): array
{
    $validated = resources_validate_uploaded_file($file);
    if (!empty($validated['errors'])) {
        throw new RuntimeException((string) ($validated['errors'][0] ?? 'Invalid file upload.'));
    }

    $meta = $validated['meta'];
    $tmpName = (string) $meta['tmp_name'];
    $extension = (string) $meta['extension'];
    $originalName = basename((string) $meta['original_name']);
    $safeOriginalName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: ('resource.' . $extension);

    $subDir = date('Y/m');
    $relativeDir = resources_storage_relative_root() . '/' . $subDir;
    $absoluteDir = BASE_PATH . '/' . $relativeDir;

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('Unable to create storage directory for resources.');
    }

    $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $relativePath = $relativeDir . '/' . $storedName;
    $absolutePath = BASE_PATH . '/' . $relativePath;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('Unable to store uploaded file.');
    }

    $mime = mime_content_type($absolutePath);
    if (!is_string($mime) || trim($mime) === '') {
        $mime = 'application/octet-stream';
    }

    return [
        'file_path' => $relativePath,
        'file_name' => $safeOriginalName,
        'file_mime' => $mime,
        'file_size' => (int) $meta['size'],
    ];
}

function resources_delete_file_safe(?string $relativePath): void
{
    $normalizedPath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    if ($normalizedPath === '') {
        return;
    }

    $storagePrefix = resources_storage_relative_root() . '/';
    if (!str_starts_with($normalizedPath, $storagePrefix)) {
        return;
    }

    $absolutePath = BASE_PATH . '/' . $normalizedPath;
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function resources_cleanup_file_paths(array $paths): void
{
    $uniquePaths = array_values(array_unique(array_filter(array_map(
        static fn($path): string => trim((string) $path),
        $paths
    ))));

    foreach ($uniquePaths as $path) {
        resources_delete_file_safe($path);
    }
}

function resources_resolve_accessible_file_resource(int $resourceId): ?array
{
    $resource = resources_find_with_context($resourceId);
    if (!$resource) {
        return null;
    }

    if (
        (string) ($resource['source_type'] ?? '') !== 'file'
        || trim((string) ($resource['file_path'] ?? '')) === ''
    ) {
        return null;
    }

    $currentUserId = (int) auth_id();
    $ownerId = (int) ($resource['uploaded_by_user_id'] ?? 0);
    $isOwner = $ownerId > 0 && $ownerId === $currentUserId;

    if (!$isOwner) {
        if ((string) ($resource['status'] ?? '') !== 'published') {
            return null;
        }

        $subjectId = (int) ($resource['subject_id'] ?? 0);
        $topicId = (int) ($resource['topic_id'] ?? 0);
        $context = resources_resolve_readable_topic($subjectId, $topicId);
        if (!$context) {
            return null;
        }
    }

    return $resource;
}

function resources_stream_file(array $resource, string $disposition = 'attachment'): void
{
    $relativePath = ltrim(str_replace('\\', '/', (string) $resource['file_path']), '/');
    if (!str_starts_with($relativePath, resources_storage_relative_root() . '/')) {
        abort(404, 'File path is invalid.');
    }

    $absolutePath = BASE_PATH . '/' . $relativePath;
    if (!is_file($absolutePath)) {
        abort(404, 'File not found on server.');
    }

    $filename = trim((string) ($resource['file_name'] ?? 'resource-file'));
    if ($filename === '') {
        $filename = 'resource-file';
    }

    $safeFilename = str_replace(["\r", "\n", '"'], '', $filename);
    $mime = trim((string) ($resource['file_mime'] ?? 'application/octet-stream'));
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    $resolvedDisposition = strtolower($disposition) === 'inline' ? 'inline' : 'attachment';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $resolvedDisposition . '; filename="' . $safeFilename . '"');
    header('Content-Length: ' . (string) filesize($absolutePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    readfile($absolutePath);
    exit;
}

function resources_resolve_readable_topic(int $subjectId, int $topicId): ?array
{
    $subject = topics_resolve_readable_subject($subjectId);
    if (!$subject) {
        return null;
    }

    $topic = topics_find_in_subject($topicId, $subjectId);
    if (!$topic) {
        return null;
    }

    return [
        'subject' => $subject,
        'topic' => $topic,
    ];
}

function resources_resolve_accessible_published_resource(int $resourceId): ?array
{
    $resource = resources_find_published_with_context($resourceId);
    if (!$resource) {
        return null;
    }

    if (user_role() === 'admin') {
        return $resource;
    }

    $subjectId = (int) ($resource['subject_id'] ?? 0);
    $topicId = (int) ($resource['topic_id'] ?? 0);
    $context = resources_resolve_readable_topic($subjectId, $topicId);
    if (!$context) {
        return null;
    }

    return $resource;
}

function resources_detail_path(array $resource): string
{
    return '/dashboard/subjects/' . (int) ($resource['subject_id'] ?? 0)
        . '/topics/' . (int) ($resource['topic_id'] ?? 0)
        . '/resources/' . (int) ($resource['id'] ?? 0);
}

function resources_comment_can_delete(array $resource, array $comment): bool
{
    $currentUserId = (int) auth_id();
    $commentAuthorId = (int) ($comment['user_id'] ?? 0);

    if ($commentAuthorId > 0 && $commentAuthorId === $currentUserId) {
        return true;
    }

    $role = (string) user_role();
    if ($role === 'admin' || $role === 'moderator') {
        return true;
    }

    if ($role === 'coordinator') {
        return subjects_find_for_coordinator((int) ($resource['subject_id'] ?? 0), $currentUserId) !== null;
    }

    return false;
}

function resources_enrich_comment_tree(array $nodes, array $resource): array
{
    $currentUserId = (int) auth_id();
    $maxDepth = comments_max_depth();
    $enriched = [];

    foreach ($nodes as $node) {
        $authorId = (int) ($node['user_id'] ?? 0);
        $depth = (int) ($node['depth'] ?? 0);
        $node['can_edit'] = $authorId > 0 && $authorId === $currentUserId;
        $node['can_delete'] = resources_comment_can_delete($resource, $node);
        $node['can_reply'] = auth_check() && $depth < $maxDepth;
        $node['children'] = resources_enrich_comment_tree((array) ($node['children'] ?? []), $resource);
        $enriched[] = $node;
    }

    return $enriched;
}

function resources_prepare_payload(?array $baseRecord = null): array
{
    $errors = [];

    $title = trim((string) request_input('title', ''));
    $description = trim((string) request_input('description', ''));
    $category = trim((string) request_input('category', ''));
    $categoryOther = trim((string) request_input('category_other', ''));
    $sourceType = trim((string) request_input('source_type', 'file'));
    $externalUrl = trim((string) request_input('external_url', ''));
    $uploadedFile = resources_request_uploaded_file('resource_file');

    if ($title === '') {
        $errors[] = 'Resource title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Resource title must be at most 200 characters.';
    }

    if (!in_array($category, resources_allowed_categories(), true)) {
        $errors[] = 'Select a valid resource category.';
    }

    if ($category === 'Other' && $categoryOther === '') {
        $errors[] = 'Specify the category when selecting Other.';
    }

    if (strlen($categoryOther) > 120) {
        $errors[] = 'Other category must be at most 120 characters.';
    }

    if (!in_array($sourceType, ['file', 'link'], true)) {
        $errors[] = 'Select a valid resource source type.';
    }

    $hasExistingFile = $baseRecord
        && ($baseRecord['source_type'] ?? '') === 'file'
        && trim((string) ($baseRecord['file_path'] ?? '')) !== '';

    if ($sourceType === 'file') {
        if ($uploadedFile === null && !$hasExistingFile) {
            $errors[] = 'Upload a file for file-based resources.';
        }

        if ($uploadedFile !== null) {
            $fileValidation = resources_validate_uploaded_file($uploadedFile);
            $errors = array_merge($errors, $fileValidation['errors'] ?? []);
        }
    }

    if ($sourceType === 'link') {
        if ($uploadedFile !== null) {
            $errors[] = 'Provide either a file or a link, not both.';
        }

        if ($externalUrl === '') {
            $errors[] = 'Resource link is required for link-based resources.';
        } elseif (!resources_is_valid_http_url($externalUrl)) {
            $errors[] = 'Resource link must be a valid http/https URL.';
        }
    }

    return [
        'errors' => $errors,
        'validated' => [
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'category' => $category,
            'category_other' => $category === 'Other' ? ($categoryOther === '' ? null : $categoryOther) : null,
            'source_type' => $sourceType,
            'external_url' => $sourceType === 'link' ? $externalUrl : null,
        ],
        'uploaded_file' => $uploadedFile,
    ];
}

function resources_compose_payload(array $validated, ?array $baseRecord, ?array $storedFileMeta): array
{
    $payload = [
        'title' => $validated['title'],
        'description' => $validated['description'],
        'category' => $validated['category'],
        'category_other' => $validated['category_other'],
        'source_type' => $validated['source_type'],
        'external_url' => $validated['external_url'],
        'file_path' => null,
        'file_name' => null,
        'file_mime' => null,
        'file_size' => null,
    ];

    if ($validated['source_type'] === 'file') {
        if ($storedFileMeta !== null) {
            $payload['file_path'] = $storedFileMeta['file_path'];
            $payload['file_name'] = $storedFileMeta['file_name'];
            $payload['file_mime'] = $storedFileMeta['file_mime'];
            $payload['file_size'] = $storedFileMeta['file_size'];
        } elseif ($baseRecord !== null) {
            $payload['file_path'] = $baseRecord['file_path'] ?? null;
            $payload['file_name'] = $baseRecord['file_name'] ?? null;
            $payload['file_mime'] = $baseRecord['file_mime'] ?? null;
            $payload['file_size'] = $baseRecord['file_size'] ?? null;
        }
    }

    return $payload;
}

function resources_topic_index(string $id, string $topicId): void
{
    $subjectId = (int) $id;
    $topicIdInt = (int) $topicId;
    $context = resources_resolve_readable_topic($subjectId, $topicIdInt);

    if (!$context) {
        abort(404, 'Topic not found.');
    }

    view('resources::topic_index', [
        'subject' => $context['subject'],
        'topic' => $context['topic'],
        'resources' => resources_topic_published_list($topicIdInt),
    ], 'dashboard');
}

function resources_topic_show(string $id, string $topicId, string $resourceId): void
{
    $subjectId = (int) $id;
    $topicIdInt = (int) $topicId;
    $resourceIdInt = (int) $resourceId;
    $context = resources_resolve_readable_topic($subjectId, $topicIdInt);

    if (!$context) {
        abort(404, 'Topic not found.');
    }

    $resource = resources_find_topic_published($resourceIdInt, $topicIdInt);
    if (!$resource) {
        abort(404, 'Published resource not found.');
    }

    $currentUserRating = null;
    $viewerIsStudent = user_role() === 'student';
    $viewerCanRate = $viewerIsStudent && (int) ($resource['uploaded_by_user_id'] ?? 0) !== (int) auth_id();
    if ($viewerCanRate) {
        $currentUserRating = resources_find_student_rating($resourceIdInt, (int) auth_id());
    }

    $ratingDistribution = resources_rating_distribution($resourceIdInt);

    $commentsTree = comments_tree_for_target('resource', $resourceIdInt);
    $commentsTree = resources_enrich_comment_tree($commentsTree, $resource);

    view('resources::show', [
        'subject' => $context['subject'],
        'topic' => $context['topic'],
        'resource' => $resource,
        'current_user_rating' => $currentUserRating,
        'can_rate' => $viewerCanRate,
        'rating_distribution' => $ratingDistribution,
        'rating_distribution_peak' => resources_rating_distribution_peak($ratingDistribution),
        'comments' => $commentsTree,
        'comment_max_level' => comments_max_depth() + 1,
    ], 'dashboard');
}

function resources_topic_create_form(string $id, string $topicId): void
{
    $subjectId = (int) $id;
    $topicIdInt = (int) $topicId;
    $context = resources_resolve_readable_topic($subjectId, $topicIdInt);

    if (!$context) {
        abort(404, 'Topic not found.');
    }

    view('resources::create', [
        'subject' => $context['subject'],
        'topic' => $context['topic'],
        'categories' => resources_allowed_categories(),
    ], 'dashboard');
}

function resources_topic_store(string $id, string $topicId): void
{
    csrf_check();

    $subjectId = (int) $id;
    $topicIdInt = (int) $topicId;
    $context = resources_resolve_readable_topic($subjectId, $topicIdInt);
    if (!$context) {
        abort(404, 'Topic not found.');
    }

    $prepared = resources_prepare_payload(null);
    $errors = $prepared['errors'];
    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/dashboard/subjects/' . $subjectId . '/topics/' . $topicIdInt . '/resources/create');
    }

    $storedFileMeta = null;
    try {
        if ($prepared['uploaded_file'] !== null) {
            $storedFileMeta = resources_store_uploaded_file($prepared['uploaded_file']);
        }
    } catch (Throwable $e) {
        flash('error', 'Unable to upload file: ' . $e->getMessage());
        flash_old_input();
        redirect('/dashboard/subjects/' . $subjectId . '/topics/' . $topicIdInt . '/resources/create');
    }

    $payload = resources_compose_payload($prepared['validated'], null, $storedFileMeta);
    $isStudent = user_role() === 'student';

    try {
        resources_create([
            'topic_id' => $topicIdInt,
            'uploaded_by_user_id' => auth_id(),
            'title' => $payload['title'],
            'description' => $payload['description'],
            'category' => $payload['category'],
            'category_other' => $payload['category_other'],
            'source_type' => $payload['source_type'],
            'file_path' => $payload['file_path'],
            'file_name' => $payload['file_name'],
            'file_mime' => $payload['file_mime'],
            'file_size' => $payload['file_size'],
            'external_url' => $payload['external_url'],
            'status' => $isStudent ? 'pending' : 'published',
        ]);
    } catch (Throwable $e) {
        resources_cleanup_file_paths([$storedFileMeta['file_path'] ?? null]);
        flash('error', 'Unable to create resource right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/subjects/' . $subjectId . '/topics/' . $topicIdInt . '/resources/create');
    }

    clear_old_input();
    flash('success', $isStudent
        ? 'Resource submitted for coordinator approval.'
        : 'Resource published successfully.');
    redirect('/dashboard/subjects/' . $subjectId . '/topics/' . $topicIdInt . '/resources');
}

function resources_my_index(): void
{
    view('resources::my_index', [
        'resources' => resources_my_all((int) auth_id()),
    ], 'dashboard');
}

function resources_my_edit_form(string $id): void
{
    $resourceId = (int) $id;
    $resource = resources_find_owned($resourceId, (int) auth_id());
    if (!$resource) {
        abort(404, 'Resource not found.');
    }

    $updateRequest = resources_find_update_request_by_resource($resourceId);
    $isStudent = user_role() === 'student';

    $formResource = $resource;
    if ($isStudent && (string) $resource['status'] === 'published' && $updateRequest) {
        $formResource = array_merge($formResource, [
            'title' => $updateRequest['title'],
            'description' => $updateRequest['description'],
            'category' => $updateRequest['category'],
            'category_other' => $updateRequest['category_other'],
            'source_type' => $updateRequest['source_type'],
            'file_path' => $updateRequest['file_path'],
            'file_name' => $updateRequest['file_name'],
            'file_mime' => $updateRequest['file_mime'],
            'file_size' => $updateRequest['file_size'],
            'external_url' => $updateRequest['external_url'],
        ]);
    }

    view('resources::edit', [
        'resource' => $resource,
        'form_resource' => $formResource,
        'update_request' => $updateRequest,
        'categories' => resources_allowed_categories(),
        'requires_approval' => $isStudent && (string) $resource['status'] === 'published',
    ], 'dashboard');
}

function resources_my_update_action(string $id): void
{
    csrf_check();

    $resourceId = (int) $id;
    $ownerUserId = (int) auth_id();
    $resource = resources_find_owned($resourceId, $ownerUserId);
    if (!$resource) {
        abort(404, 'Resource not found.');
    }

    $isStudent = user_role() === 'student';
    $existingUpdateRequest = resources_find_update_request_by_resource($resourceId);

    $baseRecord = $resource;
    if ($isStudent && (string) $resource['status'] === 'published' && $existingUpdateRequest) {
        $baseRecord = $existingUpdateRequest;
    }

    $prepared = resources_prepare_payload($baseRecord);
    if (!empty($prepared['errors'])) {
        flash('error', implode(' ', $prepared['errors']));
        flash_old_input();
        redirect('/my-resources/' . $resourceId . '/edit');
    }

    $storedFileMeta = null;
    try {
        if ($prepared['uploaded_file'] !== null) {
            $storedFileMeta = resources_store_uploaded_file($prepared['uploaded_file']);
        }
    } catch (Throwable $e) {
        flash('error', 'Unable to upload file: ' . $e->getMessage());
        flash_old_input();
        redirect('/my-resources/' . $resourceId . '/edit');
    }

    $payload = resources_compose_payload($prepared['validated'], $baseRecord, $storedFileMeta);

    try {
        if ($isStudent && (string) $resource['status'] === 'published') {
            resources_upsert_update_request($resourceId, $ownerUserId, $payload);

            $oldUpdateFilePath = $existingUpdateRequest['file_path'] ?? null;
            if (
                trim((string) $oldUpdateFilePath) !== ''
                && $oldUpdateFilePath !== ($payload['file_path'] ?? null)
                && $oldUpdateFilePath !== ($resource['file_path'] ?? null)
            ) {
                resources_cleanup_file_paths([$oldUpdateFilePath]);
            }

            clear_old_input();
            flash('success', 'Your update is pending coordinator approval. The published version stays visible until approval.');
            redirect('/my-resources');
        }

        $targetStatus = ($isStudent && (string) $resource['status'] !== 'published') ? 'pending' : 'published';

        $updatedRows = resources_update_owned_resource($resourceId, $ownerUserId, $payload, $targetStatus);
        if ($updatedRows < 1) {
            throw new RuntimeException('No rows updated.');
        }

        $deletedUpdateRequest = resources_delete_update_request_for_resource($resourceId);

        $cleanupPaths = [];
        $oldResourceFilePath = $resource['file_path'] ?? null;
        if (
            trim((string) $oldResourceFilePath) !== ''
            && $oldResourceFilePath !== ($payload['file_path'] ?? null)
        ) {
            $cleanupPaths[] = $oldResourceFilePath;
        }

        $deletedUpdateFilePath = $deletedUpdateRequest['file_path'] ?? null;
        if (
            trim((string) $deletedUpdateFilePath) !== ''
            && $deletedUpdateFilePath !== ($payload['file_path'] ?? null)
            && $deletedUpdateFilePath !== ($resource['file_path'] ?? null)
        ) {
            $cleanupPaths[] = $deletedUpdateFilePath;
        }

        resources_cleanup_file_paths($cleanupPaths);
    } catch (Throwable $e) {
        resources_cleanup_file_paths([$storedFileMeta['file_path'] ?? null]);
        flash('error', 'Unable to update this resource right now. Please try again.');
        flash_old_input();
        redirect('/my-resources/' . $resourceId . '/edit');
    }

    clear_old_input();

    if ($isStudent && (string) $resource['status'] !== 'published') {
        flash('success', 'Resource details updated and resubmitted for coordinator approval.');
    } else {
        flash('success', 'Resource updated successfully.');
    }

    redirect('/my-resources');
}

function resources_my_delete_action(string $id): void
{
    csrf_check();

    $resourceId = (int) $id;
    $deleted = resources_delete_owned($resourceId, (int) auth_id());
    if (!$deleted) {
        abort(404, 'Resource not found.');
    }

    resources_cleanup_file_paths([
        $deleted['file_path'] ?? null,
        $deleted['update_file_path'] ?? null,
    ]);

    flash('success', 'Resource deleted successfully.');
    redirect('/my-resources');
}

function resources_download(string $id): void
{
    $resourceId = (int) $id;
    $resource = resources_resolve_accessible_file_resource($resourceId);
    if (!$resource) {
        abort(404, 'Resource file not found or not accessible.');
    }

    resources_stream_file($resource, 'attachment');
}

function resources_inline(string $id): void
{
    $resourceId = (int) $id;
    $resource = resources_resolve_accessible_file_resource($resourceId);
    if (!$resource) {
        abort(404, 'Resource file not found or not accessible.');
    }

    if (!resources_is_file_previewable($resource)) {
        abort(404, 'Inline preview is not available for this file type.');
    }

    resources_stream_file($resource, 'inline');
}

function resources_rating_upsert(string $id): void
{
    csrf_check();

    if (user_role() !== 'student') {
        abort(403, 'Only students can rate resources.');
    }

    $resourceId = (int) $id;
    $resource = resources_resolve_accessible_published_resource($resourceId);
    if (!$resource) {
        abort(404, 'Resource not found.');
    }

    $resourcePath = resources_detail_path($resource);

    if ((int) ($resource['uploaded_by_user_id'] ?? 0) === (int) auth_id()) {
        flash('error', 'You cannot rate your own resource.');
        redirect($resourcePath . '#resource-interactions');
    }

    $rating = (int) request_input('rating', 0);
    if ($rating < 1 || $rating > 5) {
        flash('error', 'Select a rating between 1 and 5.');
        redirect($resourcePath . '#resource-interactions');
    }

    try {
        resources_upsert_student_rating($resourceId, (int) auth_id(), $rating);
    } catch (Throwable) {
        flash('error', 'Unable to save your rating right now. Please try again.');
        redirect($resourcePath . '#resource-interactions');
    }

    flash('success', 'Your rating has been saved.');
    redirect($resourcePath . '#resource-interactions');
}

function resources_comment_store(string $id): void
{
    csrf_check();

    $resourceId = (int) $id;
    $resource = resources_resolve_accessible_published_resource($resourceId);
    if (!$resource) {
        abort(404, 'Resource not found.');
    }

    $resourcePath = resources_detail_path($resource);
    $targetType = 'resource';
    $targetId = $resourceId;

    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($resourcePath . '#resource-comments');
    }

    $parentCommentId = (int) request_input('parent_comment_id', 0);
    $parentId = null;
    $depth = 0;

    if ($parentCommentId > 0) {
        $parent = comments_find_target_comment($parentCommentId, $targetType, $targetId);
        if (!$parent) {
            flash('error', 'Reply target not found.');
            redirect($resourcePath . '#resource-comments');
        }

        $depth = (int) ($parent['depth'] ?? 0) + 1;
        if ($depth > comments_max_depth()) {
            flash('error', 'Reply depth limit reached.');
            redirect($resourcePath . '#resource-comments');
        }

        $parentId = $parentCommentId;
    }

    try {
        comments_insert(
            $targetType,
            $targetId,
            (int) auth_id(),
            $validation['body'],
            $parentId,
            $depth
        );
    } catch (Throwable) {
        flash('error', 'Unable to post comment right now. Please try again.');
        redirect($resourcePath . '#resource-comments');
    }

    flash('success', 'Comment posted.');
    redirect($resourcePath . '#resource-comments');
}

function resources_comment_update(string $id, string $commentId): void
{
    csrf_check();

    $resourceId = (int) $id;
    $resource = resources_resolve_accessible_published_resource($resourceId);
    if (!$resource) {
        abort(404, 'Resource not found.');
    }

    $resourcePath = resources_detail_path($resource);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, 'resource', $resourceId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if ((int) ($comment['user_id'] ?? 0) !== (int) auth_id()) {
        abort(403, 'You can only edit your own comments.');
    }

    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($resourcePath . '#resource-comments');
    }

    if (!comments_update_body_by_author($commentIdInt, (int) auth_id(), $validation['body'])) {
        flash('error', 'Unable to update this comment.');
        redirect($resourcePath . '#resource-comments');
    }

    flash('success', 'Comment updated.');
    redirect($resourcePath . '#resource-comments');
}

function resources_comment_delete(string $id, string $commentId): void
{
    csrf_check();

    $resourceId = (int) $id;
    $resource = resources_resolve_accessible_published_resource($resourceId);
    if (!$resource) {
        abort(404, 'Resource not found.');
    }

    $resourcePath = resources_detail_path($resource);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, 'resource', $resourceId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if (!resources_comment_can_delete($resource, $comment)) {
        abort(403, 'You do not have permission to delete this comment.');
    }

    if (!comments_delete_by_id($commentIdInt)) {
        flash('error', 'Unable to delete this comment.');
        redirect($resourcePath . '#resource-comments');
    }

    flash('success', 'Comment deleted.');
    redirect($resourcePath . '#resource-comments');
}

function resources_coordinator_requests_index(): void
{
    middleware_exact_role('coordinator');

    $coordinatorId = (int) auth_id();

    view('resources::coordinator_requests', [
        'create_requests' => resources_coordinator_pending_create_requests($coordinatorId),
        'update_requests' => resources_coordinator_pending_update_requests($coordinatorId),
        'pending_count' => resources_coordinator_pending_count($coordinatorId),
    ], 'dashboard');
}

function resources_coordinator_create_approve(string $id): void
{
    csrf_check();
    middleware_exact_role('coordinator');

    $resourceId = (int) $id;
    $coordinatorId = (int) auth_id();
    $pending = resources_find_pending_create_for_coordinator($resourceId, $coordinatorId);
    if (!$pending) {
        abort(404, 'Pending resource request not found.');
    }

    if (!resources_mark_create_approved($resourceId, $coordinatorId)) {
        flash('error', 'Unable to approve this request.');
        redirect('/coordinator/resource-requests');
    }

    flash('success', 'Resource request approved and published.');
    redirect('/coordinator/resource-requests');
}

function resources_coordinator_create_reject(string $id): void
{
    csrf_check();
    middleware_exact_role('coordinator');

    $resourceId = (int) $id;
    $coordinatorId = (int) auth_id();
    $pending = resources_find_pending_create_for_coordinator($resourceId, $coordinatorId);
    if (!$pending) {
        abort(404, 'Pending resource request not found.');
    }

    $reason = trim((string) request_input('rejection_reason', ''));
    if ($reason === '') {
        flash('error', 'Rejection reason is required.');
        redirect('/coordinator/resource-requests');
    }

    if (!resources_mark_create_rejected($resourceId, $coordinatorId, $reason)) {
        flash('error', 'Unable to reject this request.');
        redirect('/coordinator/resource-requests');
    }

    flash('success', 'Resource request rejected.');
    redirect('/coordinator/resource-requests');
}

function resources_coordinator_update_approve(string $id): void
{
    csrf_check();
    middleware_exact_role('coordinator');

    $updateRequestId = (int) $id;
    $coordinatorId = (int) auth_id();
    $pending = resources_find_pending_update_for_coordinator($updateRequestId, $coordinatorId);
    if (!$pending) {
        abort(404, 'Pending update request not found.');
    }

    $result = resources_apply_pending_update_approval($updateRequestId, $coordinatorId);
    if (!$result) {
        flash('error', 'Unable to approve this update request.');
        redirect('/coordinator/resource-requests');
    }

    $oldFilePath = $result['old_file_path'] ?? null;
    $newFilePath = $result['new_file_path'] ?? null;
    if (trim((string) $oldFilePath) !== '' && $oldFilePath !== $newFilePath) {
        resources_cleanup_file_paths([$oldFilePath]);
    }

    flash('success', 'Resource update approved and published.');
    redirect('/coordinator/resource-requests');
}

function resources_coordinator_update_reject(string $id): void
{
    csrf_check();
    middleware_exact_role('coordinator');

    $updateRequestId = (int) $id;
    $coordinatorId = (int) auth_id();
    $pending = resources_find_pending_update_for_coordinator($updateRequestId, $coordinatorId);
    if (!$pending) {
        abort(404, 'Pending update request not found.');
    }

    $reason = trim((string) request_input('rejection_reason', ''));
    if ($reason === '') {
        flash('error', 'Rejection reason is required.');
        redirect('/coordinator/resource-requests');
    }

    if (!resources_mark_update_rejected($updateRequestId, $coordinatorId, $reason)) {
        flash('error', 'Unable to reject this update request.');
        redirect('/coordinator/resource-requests');
    }

    flash('success', 'Resource update request rejected.');
    redirect('/coordinator/resource-requests');
}
