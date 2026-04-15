<?php

/**
 * Feed Module — Models
 */

function feed_per_page(): int
{
    return 10;
}

function feed_item_types(): array
{
    return ['community', 'resource', 'quiz', 'kuppi_request', 'kuppi_scheduled'];
}

function feed_item_type_options(): array
{
    return [
        'all' => 'All',
        'community' => 'Community',
        'resource' => 'Resources',
        'quiz' => 'Quizzes',
        'kuppi_request' => 'Kuppi Requests',
        'kuppi_scheduled' => 'Scheduled Kuppi',
    ];
}

function feed_item_type_label(string $itemType): string
{
    $options = feed_item_type_options();
    return $options[$itemType] ?? 'Feed Item';
}

function feed_item_type_badge_class(string $itemType): string
{
    return match ($itemType) {
        'community' => 'badge-info',
        'resource' => '',
        'quiz' => 'badge-warning',
        'kuppi_request' => 'badge-info',
        'kuppi_scheduled' => 'badge-warning',
        default => '',
    };
}

function feed_batch_options_for_admin(): array
{
    return onboarding_approved_batches();
}

function feed_find_batch_option_by_id(int $batchId): ?array
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

function feed_subject_options_for_batch(int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    return db_fetch_all(
        "SELECT id, code, name, academic_year, semester
         FROM subjects
         WHERE batch_id = ?
         ORDER BY academic_year DESC, semester DESC, code ASC, name ASC",
        [$batchId]
    );
}

function feed_subject_exists_in_batch(int $subjectId, int $batchId): bool
{
    if ($subjectId <= 0 || $batchId <= 0) {
        return false;
    }

    return (bool) db_fetch(
        'SELECT id FROM subjects WHERE id = ? AND batch_id = ? LIMIT 1',
        [$subjectId, $batchId]
    );
}

function feed_build_union_sql(int $batchId, int $viewerUserId, array &$params): string
{
    $params = [
        $viewerUserId,
        $viewerUserId,
        $batchId,
        $batchId,
        $batchId,
        $viewerUserId,
        $batchId,
        $batchId,
    ];

    return "
        SELECT
            'community' AS item_type,
            p.id AS item_id,
            p.batch_id,
            p.subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            COALESCE(u.name, 'Unknown User') AS actor_name,
            p.author_user_id AS actor_user_id,
            CASE p.post_type
                WHEN 'announcement' THEN 'Announcement'
                WHEN 'question' THEN 'Question'
                WHEN 'discussion' THEN 'Discussion'
                WHEN 'resource_share' THEN 'Resource Share'
                ELSE 'Community Post'
            END AS title,
            TRIM(COALESCE(p.body, '')) AS summary,
            p.created_at AS event_at,
            p.id AS sort_id,
            p.post_type AS community_post_type,
            (SELECT COUNT(*) FROM feed_post_likes fpl WHERE fpl.post_id = p.id) AS community_like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.target_type = 'feed_post' AND c.target_id = p.id) AS community_comment_count,
            CASE WHEN EXISTS(SELECT 1 FROM feed_post_likes vfl WHERE vfl.post_id = p.id AND vfl.user_id = ?) THEN 1 ELSE 0 END AS community_is_liked_by_viewer,
            CASE WHEN EXISTS(SELECT 1 FROM feed_post_saves vfs WHERE vfs.post_id = p.id AND vfs.user_id = ?) THEN 1 ELSE 0 END AS community_is_saved_by_viewer,
            NULL AS resource_topic_id,
            NULL AS resource_source_type,
            NULL AS resource_category,
            NULL AS resource_rating_avg,
            NULL AS resource_rating_count,
            NULL AS resource_comment_count,
            NULL AS quiz_mode,
            NULL AS quiz_duration_minutes,
            NULL AS quiz_question_count,
            NULL AS kuppi_vote_score,
            NULL AS kuppi_upvote_count,
            NULL AS kuppi_downvote_count,
            NULL AS kuppi_conductor_count,
            NULL AS kuppi_comment_count,
            NULL AS kuppi_viewer_vote,
            NULL AS scheduled_session_date,
            NULL AS scheduled_start_time,
            NULL AS scheduled_end_time,
            NULL AS scheduled_host_count,
            CONCAT_WS(' ', p.post_type, p.body, s.code, s.name, u.name) AS search_blob
        FROM feed_posts p
        LEFT JOIN subjects s ON s.id = p.subject_id
        LEFT JOIN users u ON u.id = p.author_user_id
        WHERE p.batch_id = ?

        UNION ALL

        SELECT
            'resource' AS item_type,
            r.id AS item_id,
            s.batch_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            COALESCE(u.name, 'Unknown User') AS actor_name,
            r.uploaded_by_user_id AS actor_user_id,
            r.title,
            COALESCE(r.description, '') AS summary,
            r.created_at AS event_at,
            r.id AS sort_id,
            NULL AS community_post_type,
            NULL AS community_like_count,
            NULL AS community_comment_count,
            NULL AS community_is_liked_by_viewer,
            NULL AS community_is_saved_by_viewer,
            t.id AS resource_topic_id,
            r.source_type AS resource_source_type,
            r.category AS resource_category,
            ROUND(COALESCE((SELECT AVG(rr.rating) FROM resource_ratings rr WHERE rr.resource_id = r.id), 0), 2) AS resource_rating_avg,
            (SELECT COUNT(*) FROM resource_ratings rr2 WHERE rr2.resource_id = r.id) AS resource_rating_count,
            (SELECT COUNT(*) FROM comments rc WHERE rc.target_type = 'resource' AND rc.target_id = r.id) AS resource_comment_count,
            NULL AS quiz_mode,
            NULL AS quiz_duration_minutes,
            NULL AS quiz_question_count,
            NULL AS kuppi_vote_score,
            NULL AS kuppi_upvote_count,
            NULL AS kuppi_downvote_count,
            NULL AS kuppi_conductor_count,
            NULL AS kuppi_comment_count,
            NULL AS kuppi_viewer_vote,
            NULL AS scheduled_session_date,
            NULL AS scheduled_start_time,
            NULL AS scheduled_end_time,
            NULL AS scheduled_host_count,
            CONCAT_WS(' ', r.title, r.description, r.category, s.code, s.name, u.name) AS search_blob
        FROM resources r
        INNER JOIN topics t ON t.id = r.topic_id
        INNER JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN users u ON u.id = r.uploaded_by_user_id
        WHERE r.status = 'published'
          AND s.batch_id = ?

        UNION ALL

        SELECT
            'quiz' AS item_type,
            q.id AS item_id,
            s.batch_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            COALESCE(u.name, 'Unknown User') AS actor_name,
            q.created_by_user_id AS actor_user_id,
            q.title,
            COALESCE(q.description, '') AS summary,
            q.created_at AS event_at,
            q.id AS sort_id,
            NULL AS community_post_type,
            NULL AS community_like_count,
            NULL AS community_comment_count,
            NULL AS community_is_liked_by_viewer,
            NULL AS community_is_saved_by_viewer,
            NULL AS resource_topic_id,
            NULL AS resource_source_type,
            NULL AS resource_category,
            NULL AS resource_rating_avg,
            NULL AS resource_rating_count,
            NULL AS resource_comment_count,
            q.mode AS quiz_mode,
            q.duration_minutes AS quiz_duration_minutes,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS quiz_question_count,
            NULL AS kuppi_vote_score,
            NULL AS kuppi_upvote_count,
            NULL AS kuppi_downvote_count,
            NULL AS kuppi_conductor_count,
            NULL AS kuppi_comment_count,
            NULL AS kuppi_viewer_vote,
            NULL AS scheduled_session_date,
            NULL AS scheduled_start_time,
            NULL AS scheduled_end_time,
            NULL AS scheduled_host_count,
            CONCAT_WS(' ', q.title, q.description, q.mode, s.code, s.name, u.name) AS search_blob
        FROM quizzes q
        INNER JOIN subjects s ON s.id = q.subject_id
        LEFT JOIN users u ON u.id = q.created_by_user_id
        WHERE q.status = 'approved'
          AND s.batch_id = ?

        UNION ALL

        SELECT
            'kuppi_request' AS item_type,
            kr.id AS item_id,
            kr.batch_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            COALESCE(u.name, 'Unknown User') AS actor_name,
            kr.requested_by_user_id AS actor_user_id,
            kr.title,
            COALESCE(kr.description, '') AS summary,
            kr.created_at AS event_at,
            kr.id AS sort_id,
            NULL AS community_post_type,
            NULL AS community_like_count,
            NULL AS community_comment_count,
            NULL AS community_is_liked_by_viewer,
            NULL AS community_is_saved_by_viewer,
            NULL AS resource_topic_id,
            NULL AS resource_source_type,
            NULL AS resource_category,
            NULL AS resource_rating_avg,
            NULL AS resource_rating_count,
            NULL AS resource_comment_count,
            NULL AS quiz_mode,
            NULL AS quiz_duration_minutes,
            NULL AS quiz_question_count,
            COALESCE((
                SELECT SUM(CASE WHEN krv.vote_type = 'up' THEN 1 ELSE -1 END)
                FROM kuppi_request_votes krv
                WHERE krv.request_id = kr.id
            ), 0) AS kuppi_vote_score,
            COALESCE((
                SELECT SUM(CASE WHEN krv.vote_type = 'up' THEN 1 ELSE 0 END)
                FROM kuppi_request_votes krv
                WHERE krv.request_id = kr.id
            ), 0) AS kuppi_upvote_count,
            COALESCE((
                SELECT SUM(CASE WHEN krv.vote_type = 'down' THEN 1 ELSE 0 END)
                FROM kuppi_request_votes krv
                WHERE krv.request_id = kr.id
            ), 0) AS kuppi_downvote_count,
            (SELECT COUNT(*) FROM kuppi_conductor_applications kca WHERE kca.request_id = kr.id) AS kuppi_conductor_count,
            (SELECT COUNT(*) FROM comments kc WHERE kc.target_type = 'kuppi_request' AND kc.target_id = kr.id) AS kuppi_comment_count,
            (SELECT kv.vote_type FROM kuppi_request_votes kv WHERE kv.request_id = kr.id AND kv.user_id = ? LIMIT 1) AS kuppi_viewer_vote,
            NULL AS scheduled_session_date,
            NULL AS scheduled_start_time,
            NULL AS scheduled_end_time,
            NULL AS scheduled_host_count,
            CONCAT_WS(' ', kr.title, kr.description, kr.tags_csv, s.code, s.name, u.name) AS search_blob
        FROM kuppi_requests kr
        INNER JOIN subjects s ON s.id = kr.subject_id
        LEFT JOIN users u ON u.id = kr.requested_by_user_id
        WHERE kr.status = 'open'
          AND kr.batch_id = ?

        UNION ALL

        SELECT
            'kuppi_scheduled' AS item_type,
            ks.id AS item_id,
            ks.batch_id,
            s.id AS subject_id,
            s.code AS subject_code,
            s.name AS subject_name,
            COALESCE(u.name, 'Unknown User') AS actor_name,
            ks.created_by_user_id AS actor_user_id,
            ks.title,
            COALESCE(ks.description, '') AS summary,
            ks.created_at AS event_at,
            ks.id AS sort_id,
            NULL AS community_post_type,
            NULL AS community_like_count,
            NULL AS community_comment_count,
            NULL AS community_is_liked_by_viewer,
            NULL AS community_is_saved_by_viewer,
            NULL AS resource_topic_id,
            NULL AS resource_source_type,
            NULL AS resource_category,
            NULL AS resource_rating_avg,
            NULL AS resource_rating_count,
            NULL AS resource_comment_count,
            NULL AS quiz_mode,
            NULL AS quiz_duration_minutes,
            NULL AS quiz_question_count,
            NULL AS kuppi_vote_score,
            NULL AS kuppi_upvote_count,
            NULL AS kuppi_downvote_count,
            NULL AS kuppi_conductor_count,
            NULL AS kuppi_comment_count,
            NULL AS kuppi_viewer_vote,
            ks.session_date AS scheduled_session_date,
            ks.start_time AS scheduled_start_time,
            ks.end_time AS scheduled_end_time,
            (SELECT COUNT(*) FROM kuppi_scheduled_session_hosts ksh WHERE ksh.session_id = ks.id) AS scheduled_host_count,
            CONCAT_WS(' ', ks.title, ks.description, ks.location_text, ks.notes, s.code, s.name, u.name) AS search_blob
        FROM kuppi_scheduled_sessions ks
        INNER JOIN subjects s ON s.id = ks.subject_id
        LEFT JOIN users u ON u.id = ks.created_by_user_id
        WHERE ks.status = 'scheduled'
          AND ks.batch_id = ?
    ";
}

function feed_filters_sql(?string $itemType, ?int $subjectId, string $searchQuery, array &$params): string
{
    $params = [];
    $conditions = [];

    $type = trim((string) $itemType);
    if ($type !== '' && $type !== 'all' && in_array($type, feed_item_types(), true)) {
        $conditions[] = 'cf.item_type = ?';
        $params[] = $type;
    }

    if ($subjectId !== null && $subjectId > 0) {
        $conditions[] = 'cf.subject_id = ?';
        $params[] = $subjectId;
    }

    $search = trim($searchQuery);
    if ($search !== '') {
        $conditions[] = 'cf.search_blob LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if (empty($conditions)) {
        return '';
    }

    return ' AND ' . implode(' AND ', $conditions);
}

function feed_fetch_page(
    int $batchId,
    int $viewerUserId,
    string $itemType,
    ?int $subjectId,
    string $searchQuery,
    int $page = 1,
    int $perPage = 10
): array {
    if ($batchId <= 0) {
        return ['items' => [], 'has_more' => false];
    }

    $page = max(1, min(50, $page));
    $perPage = max(1, min(20, $perPage));
    $offset = ($page - 1) * $perPage;
    $queryLimit = $perPage + 1;

    $baseParams = [];
    $unionSql = feed_build_union_sql($batchId, $viewerUserId, $baseParams);

    $filterParams = [];
    $filtersSql = feed_filters_sql($itemType, $subjectId, $searchQuery, $filterParams);

    $rows = db_fetch_all(
        "SELECT *
         FROM ({$unionSql}) cf
         WHERE 1 = 1 {$filtersSql}
         ORDER BY cf.event_at DESC, cf.sort_id DESC
         LIMIT {$queryLimit} OFFSET {$offset}",
        array_merge($baseParams, $filterParams)
    );

    $hasMore = count($rows) > $perPage;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $perPage);
    }

    return [
        'items' => $rows,
        'has_more' => $hasMore,
    ];
}

function feed_type_counts(
    int $batchId,
    int $viewerUserId,
    ?int $subjectId,
    string $searchQuery
): array {
    $counts = ['_all' => 0];
    foreach (feed_item_types() as $type) {
        $counts[$type] = 0;
    }

    if ($batchId <= 0) {
        return $counts;
    }

    $baseParams = [];
    $unionSql = feed_build_union_sql($batchId, $viewerUserId, $baseParams);

    $filterParams = [];
    $filtersSql = feed_filters_sql('all', $subjectId, $searchQuery, $filterParams);

    $rows = db_fetch_all(
        "SELECT cf.item_type, COUNT(*) AS cnt
         FROM ({$unionSql}) cf
         WHERE 1 = 1 {$filtersSql}
         GROUP BY cf.item_type",
        array_merge($baseParams, $filterParams)
    );

    foreach ($rows as $row) {
        $type = (string) ($row['item_type'] ?? '');
        $cnt = (int) ($row['cnt'] ?? 0);
        if (isset($counts[$type])) {
            $counts[$type] = $cnt;
            $counts['_all'] += $cnt;
        }
    }

    return $counts;
}

function feed_today_count(
    int $batchId,
    int $viewerUserId,
    string $itemType,
    ?int $subjectId,
    string $searchQuery
): int {
    if ($batchId <= 0) {
        return 0;
    }

    $baseParams = [];
    $unionSql = feed_build_union_sql($batchId, $viewerUserId, $baseParams);

    $filterParams = [];
    $filtersSql = feed_filters_sql($itemType, $subjectId, $searchQuery, $filterParams);

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM ({$unionSql}) cf
         WHERE 1 = 1
           {$filtersSql}
           AND DATE(cf.event_at) = CURDATE()",
        array_merge($baseParams, $filterParams)
    );

    return (int) ($row['cnt'] ?? 0);
}
