<?php

/**
 * Feed Module — Controllers
 */

function feed_search_query_max_length(): int
{
    return 120;
}

function feed_type_from_request(): string
{
    $type = trim((string) request_input('type', 'all'));
    return array_key_exists($type, feed_item_type_options()) ? $type : 'all';
}

function feed_search_query_from_request(): string
{
    $query = trim((string) request_input('q', ''));
    if (strlen($query) > feed_search_query_max_length()) {
        $query = substr($query, 0, feed_search_query_max_length());
    }

    return $query;
}

function feed_summary_excerpt(string $summary, int $maxLength = 220): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $summary) ?? '');
    if ($normalized === '') {
        return '';
    }

    if (strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    return rtrim(substr($normalized, 0, max(1, $maxLength - 3))) . '...';
}

function feed_relative_time_label(string $timestamp): string
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

function feed_target_url(array $item, bool $isAdmin, int $selectedBatchId): string
{
    $itemType = (string) ($item['item_type'] ?? '');
    $itemId = (int) ($item['item_id'] ?? 0);
    $subjectId = (int) ($item['subject_id'] ?? 0);
    $topicId = (int) ($item['resource_topic_id'] ?? 0);
    $batchId = (int) ($item['batch_id'] ?? 0);

    $adminBatchId = $selectedBatchId > 0 ? $selectedBatchId : $batchId;

    return match ($itemType) {
        'community' => '/dashboard/community/' . $itemId . ($isAdmin && $adminBatchId > 0 ? '?batch_id=' . $adminBatchId : ''),
        'resource' => '/dashboard/subjects/' . $subjectId . '/topics/' . $topicId . '/resources/' . $itemId,
        'quiz' => '/dashboard/subjects/' . $subjectId . '/quizzes/' . $itemId,
        'kuppi_request' => '/dashboard/kuppi/' . $itemId . ($isAdmin && $adminBatchId > 0 ? '?batch_id=' . $adminBatchId : ''),
        'kuppi_scheduled' => '/dashboard/kuppi/scheduled/' . $itemId . ($isAdmin && $adminBatchId > 0 ? '?batch_id=' . $adminBatchId : ''),
        default => '/dashboard/feed',
    };
}

function feed_item_title(array $item): string
{
    $title = trim((string) ($item['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    return feed_item_type_label((string) ($item['item_type'] ?? 'item'));
}

function feed_item_summary(array $item): string
{
    $itemType = (string) ($item['item_type'] ?? '');
    $rawSummary = trim((string) ($item['summary'] ?? ''));
    if ($rawSummary !== '') {
        return feed_summary_excerpt($rawSummary);
    }

    return match ($itemType) {
        'community' => 'New community post shared.',
        'resource' => 'New published resource is now available.',
        'quiz' => 'New approved quiz is ready to attempt.',
        'kuppi_request' => 'New Kuppi request opened for votes and conductors.',
        'kuppi_scheduled' => 'New Kuppi session added to the schedule.',
        default => 'New activity item.',
    };
}

function feed_present_item(array $item, bool $isAdmin, int $selectedBatchId, int $viewerUserId): array
{
    $itemType = (string) ($item['item_type'] ?? '');
    $actorUserId = (int) ($item['actor_user_id'] ?? 0);
    $batchId = (int) ($item['batch_id'] ?? 0);
    $role = (string) user_role();

    $item['item_type_label'] = feed_item_type_label($itemType);
    $item['item_type_badge_class'] = feed_item_type_badge_class($itemType);
    $item['title'] = feed_item_title($item);
    $item['summary'] = feed_item_summary($item);
    $item['event_label'] = feed_relative_time_label((string) ($item['event_at'] ?? ''));
    $item['event_at_display'] = date('M d, Y · H:i', strtotime((string) ($item['event_at'] ?? 'now')));
    $item['target_url'] = feed_target_url($item, $isAdmin, $selectedBatchId);
    $item['is_owner'] = $actorUserId > 0 && $actorUserId === $viewerUserId;

    $canVote = false;
    if ($itemType === 'kuppi_request') {
        if ($isAdmin) {
            $canVote = !$item['is_owner'] && $selectedBatchId > 0 && $selectedBatchId === $batchId;
        } else {
            $canVote = in_array($role, ['student', 'coordinator', 'moderator'], true) && !$item['is_owner'];
        }
    }

    $item['can_vote_request'] = $canVote;

    return $item;
}

function feed_present_items(array $items, bool $isAdmin, int $selectedBatchId, int $viewerUserId): array
{
    $normalized = [];
    foreach ($items as $item) {
        $normalized[] = feed_present_item($item, $isAdmin, $selectedBatchId, $viewerUserId);
    }

    return $normalized;
}

function feed_subject_label(array $subjectOptions, int $subjectId): string
{
    if ($subjectId <= 0) {
        return 'All Subjects';
    }

    foreach ($subjectOptions as $subject) {
        if ((int) ($subject['id'] ?? 0) !== $subjectId) {
            continue;
        }

        $code = trim((string) ($subject['code'] ?? ''));
        $name = trim((string) ($subject['name'] ?? ''));
        if ($code !== '' && $name !== '') {
            return $code . ' — ' . $name;
        }

        if ($code !== '') {
            return $code;
        }

        if ($name !== '') {
            return $name;
        }

        break;
    }

    return 'All Subjects';
}

function feed_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $viewerUserId = (int) auth_id();

    $batchOptions = $isAdmin ? feed_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = feed_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }

        $activeBatch = feed_find_batch_option_by_id($selectedBatchId);
    }

    $subjectOptions = $selectedBatchId > 0
        ? feed_subject_options_for_batch($selectedBatchId)
        : [];

    $selectedSubjectId = (int) request_input('subject_id', 0);
    if ($selectedSubjectId > 0 && !feed_subject_exists_in_batch($selectedSubjectId, $selectedBatchId)) {
        $selectedSubjectId = 0;
    }

    $selectedType = feed_type_from_request();
    $searchQuery = feed_search_query_from_request();
    $selectedPage = max(1, min(50, (int) request_input('page', 1)));
    $subjectFilter = $selectedSubjectId > 0 ? $selectedSubjectId : null;

    $feedPage = $selectedBatchId > 0
        ? feed_fetch_page(
            $selectedBatchId,
            $viewerUserId,
            $selectedType,
            $subjectFilter,
            $searchQuery,
            $selectedPage,
            feed_per_page()
        )
        : ['items' => [], 'has_more' => false];

    $typeCounts = $selectedBatchId > 0
        ? feed_type_counts($selectedBatchId, $viewerUserId, $subjectFilter, $searchQuery)
        : array_merge(['_all' => 0], array_fill_keys(feed_item_types(), 0));

    $todayCount = $selectedBatchId > 0
        ? feed_today_count($selectedBatchId, $viewerUserId, 'all', $subjectFilter, $searchQuery)
        : 0;

    $items = feed_present_items(
        (array) ($feedPage['items'] ?? []),
        $isAdmin,
        $selectedBatchId,
        $viewerUserId
    );

    $selectedTypeCount = $selectedType === 'all'
        ? (int) ($typeCounts['_all'] ?? 0)
        : (int) ($typeCounts[$selectedType] ?? 0);

    view('feed::index', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'selected_batch_id' => $selectedBatchId,
        'active_batch' => $activeBatch,
        'subject_options' => $subjectOptions,
        'selected_subject_id' => $selectedSubjectId,
        'selected_subject_label' => feed_subject_label($subjectOptions, $selectedSubjectId),
        'selected_type' => $selectedType,
        'selected_search_query' => $searchQuery,
        'selected_page' => $selectedPage,
        'items' => $items,
        'has_more_items' => !empty($feedPage['has_more']),
        'type_options' => feed_item_type_options(),
        'type_counts' => $typeCounts,
        'selected_type_count' => $selectedTypeCount,
        'today_count' => $todayCount,
        'can_save_posts' => community_user_can_save_posts(),
    ], 'dashboard');
}
