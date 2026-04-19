<?php

/**
 * Dashboard Module — Models
 */

function dashboard_search_query_max_length(): int
{
    return 120;
}

function dashboard_search_min_query_length(): int
{
    return 2;
}

function dashboard_search_type_options(): array
{
    return [
        'all' => 'All',
        'subject' => 'Subjects',
        'resource' => 'Resources',
        'quiz' => 'Quizzes',
        'kuppi_request' => 'Kuppi Requests',
        'kuppi_scheduled' => 'Scheduled Kuppi',
    ];
}

function dashboard_search_item_types(): array
{
    return ['subject', 'resource', 'quiz', 'kuppi_request', 'kuppi_scheduled'];
}

function dashboard_search_type_label(string $itemType): string
{
    $options = dashboard_search_type_options();
    return $options[$itemType] ?? 'Item';
}

function dashboard_search_batch_options_for_admin(): array
{
    return onboarding_approved_batches();
}

function dashboard_search_find_batch_option_by_id(int $batchId): ?array
{
    if ($batchId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT b.id, b.batch_code, b.name, b.program, b.intake_year,
                u.name AS university_name
         FROM batches b
         LEFT JOIN universities u ON u.id = b.university_id
         WHERE b.id = ?
           AND b.status = 'approved'
         LIMIT 1",
        [$batchId]
    );
}

function dashboard_search_subject_options_for_batch(int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    return db_fetch_all(
        "SELECT id, code, name
         FROM subjects
         WHERE batch_id = ?
         ORDER BY academic_year DESC, semester DESC, code ASC, name ASC",
        [$batchId]
    );
}

function dashboard_search_subject_exists_in_batch(int $subjectId, int $batchId): bool
{
    if ($subjectId <= 0 || $batchId <= 0) {
        return false;
    }

    return (bool) db_fetch(
        'SELECT id FROM subjects WHERE id = ? AND batch_id = ? LIMIT 1',
        [$subjectId, $batchId]
    );
}

function dashboard_search_relative_time_label(string $timestamp): string
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

function dashboard_search_build_union_sql(int $batchId, array &$params): string
{
    $params = [$batchId, $batchId, $batchId, $batchId, $batchId];

    return "
        SELECT
            'subject' AS item_type,
            s.id AS item_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            CONCAT(s.code, ' - ', s.name) AS title,
            COALESCE(s.description, '') AS summary,
            s.updated_at AS event_at,
            s.id AS sort_id,
            NULL AS resource_topic_id,
            NULL AS quiz_mode,
            NULL AS quiz_question_count,
            NULL AS quiz_duration_minutes,
            NULL AS kuppi_session_date,
            NULL AS kuppi_start_time,
            NULL AS kuppi_end_time,
            CONCAT_WS(' ', s.code, s.name, s.description, s.academic_year, s.semester) AS search_blob
        FROM subjects s
        WHERE s.batch_id = ?

        UNION ALL

        SELECT
            'resource' AS item_type,
            r.id AS item_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            r.title AS title,
            COALESCE(r.description, '') AS summary,
            r.created_at AS event_at,
            r.id AS sort_id,
            t.id AS resource_topic_id,
            NULL AS quiz_mode,
            NULL AS quiz_question_count,
            NULL AS quiz_duration_minutes,
            NULL AS kuppi_session_date,
            NULL AS kuppi_start_time,
            NULL AS kuppi_end_time,
            CONCAT_WS(' ', r.title, r.description, r.category, s.code, s.name) AS search_blob
        FROM resources r
        INNER JOIN topics t ON t.id = r.topic_id
        INNER JOIN subjects s ON s.id = t.subject_id
        WHERE r.status = 'published'
          AND s.batch_id = ?

        UNION ALL

        SELECT
            'quiz' AS item_type,
            q.id AS item_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            q.title AS title,
            COALESCE(q.description, '') AS summary,
            q.created_at AS event_at,
            q.id AS sort_id,
            NULL AS resource_topic_id,
            q.mode AS quiz_mode,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS quiz_question_count,
            q.duration_minutes AS quiz_duration_minutes,
            NULL AS kuppi_session_date,
            NULL AS kuppi_start_time,
            NULL AS kuppi_end_time,
            CONCAT_WS(' ', q.title, q.description, q.mode, s.code, s.name) AS search_blob
        FROM quizzes q
        INNER JOIN subjects s ON s.id = q.subject_id
        WHERE q.status = 'approved'
          AND s.batch_id = ?

        UNION ALL

        SELECT
            'kuppi_request' AS item_type,
            kr.id AS item_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            kr.title AS title,
            COALESCE(kr.description, '') AS summary,
            kr.created_at AS event_at,
            kr.id AS sort_id,
            NULL AS resource_topic_id,
            NULL AS quiz_mode,
            NULL AS quiz_question_count,
            NULL AS quiz_duration_minutes,
            NULL AS kuppi_session_date,
            NULL AS kuppi_start_time,
            NULL AS kuppi_end_time,
            CONCAT_WS(' ', kr.title, kr.description, kr.tags_csv, s.code, s.name) AS search_blob
        FROM kuppi_requests kr
        INNER JOIN subjects s ON s.id = kr.subject_id
        WHERE kr.status = 'open'
          AND kr.batch_id = ?

        UNION ALL

        SELECT
            'kuppi_scheduled' AS item_type,
            ks.id AS item_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            ks.title AS title,
            COALESCE(ks.description, '') AS summary,
            ks.created_at AS event_at,
            ks.id AS sort_id,
            NULL AS resource_topic_id,
            NULL AS quiz_mode,
            NULL AS quiz_question_count,
            NULL AS quiz_duration_minutes,
            ks.session_date AS kuppi_session_date,
            ks.start_time AS kuppi_start_time,
            ks.end_time AS kuppi_end_time,
            CONCAT_WS(' ', ks.title, ks.description, ks.location_text, ks.notes, s.code, s.name) AS search_blob
        FROM kuppi_scheduled_sessions ks
        INNER JOIN subjects s ON s.id = ks.subject_id
        WHERE ks.status = 'scheduled'
          AND ks.batch_id = ?
    ";
}

function dashboard_search_filters_sql(
    string $searchQuery,
    string $itemType,
    ?int $subjectId,
    array &$params
): string {
    $params = [];
    $conditions = [];

    $search = trim($searchQuery);
    if ($search !== '') {
        $conditions[] = 'sx.search_blob LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if ($itemType !== 'all' && in_array($itemType, dashboard_search_item_types(), true)) {
        $conditions[] = 'sx.item_type = ?';
        $params[] = $itemType;
    }

    if ($subjectId !== null && $subjectId > 0) {
        $conditions[] = 'sx.subject_id = ?';
        $params[] = $subjectId;
    }

    if (empty($conditions)) {
        return '';
    }

    return ' AND ' . implode(' AND ', $conditions);
}

function dashboard_search_fetch_items(
    int $batchId,
    string $searchQuery,
    string $itemType = 'all',
    ?int $subjectId = null,
    int $limit = 24
): array {
    if ($batchId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $baseParams = [];
    $unionSql = dashboard_search_build_union_sql($batchId, $baseParams);

    $filterParams = [];
    $filtersSql = dashboard_search_filters_sql($searchQuery, $itemType, $subjectId, $filterParams);

    return db_fetch_all(
        "SELECT *
         FROM ({$unionSql}) sx
         WHERE 1 = 1 {$filtersSql}
         ORDER BY sx.event_at DESC, sx.sort_id DESC
         LIMIT {$limit}",
        array_merge($baseParams, $filterParams)
    );
}

function dashboard_search_counts_by_type(array $items): array
{
    $counts = ['all' => count($items)];
    foreach (dashboard_search_item_types() as $type) {
        $counts[$type] = 0;
    }

    foreach ($items as $item) {
        $type = (string) ($item['item_type'] ?? '');
        if (array_key_exists($type, $counts)) {
            $counts[$type]++;
        }
    }

    return $counts;
}

function dashboard_search_target_url(array $item, bool $isAdmin, int $selectedBatchId): string
{
    $itemType = (string) ($item['item_type'] ?? '');
    $itemId = (int) ($item['item_id'] ?? 0);
    $subjectId = (int) ($item['subject_id'] ?? 0);
    $topicId = (int) ($item['resource_topic_id'] ?? 0);

    $adminBatchQuery = $isAdmin && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '';

    return match ($itemType) {
        'subject' => '/dashboard/subjects/' . $subjectId . '/topics',
        'resource' => '/dashboard/subjects/' . $subjectId . '/topics/' . $topicId . '/resources/' . $itemId,
        'quiz' => '/dashboard/subjects/' . $subjectId . '/quizzes/' . $itemId,
        'kuppi_request' => '/dashboard/kuppi/' . $itemId . $adminBatchQuery,
        'kuppi_scheduled' => '/dashboard/kuppi/scheduled/' . $itemId . $adminBatchQuery,
        default => '/dashboard/search',
    };
}

function dashboard_search_item_icon(string $itemType): string
{
    return match ($itemType) {
        'subject' => 'book-open',
        'resource' => 'file-text',
        'quiz' => 'clipboard-check',
        'kuppi_request' => 'message-square-heart',
        'kuppi_scheduled' => 'calendar-check-2',
        default => 'search',
    };
}

function dashboard_search_present_item(array $item, bool $isAdmin, int $selectedBatchId): array
{
    $eventAt = (string) ($item['event_at'] ?? '');
    $itemType = (string) ($item['item_type'] ?? '');

    $item['item_type_label'] = dashboard_search_type_label($itemType);
    $item['item_type_icon'] = dashboard_search_item_icon($itemType);
    $item['target_url'] = dashboard_search_target_url($item, $isAdmin, $selectedBatchId);
    $item['event_label'] = dashboard_search_relative_time_label($eventAt);
    $item['event_at_display'] = $eventAt !== '' && strtotime($eventAt) !== false
        ? date('M d, Y · H:i', strtotime($eventAt))
        : '';

    return $item;
}

