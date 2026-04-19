<?php

/**
 * Community Module — Models
 */

function community_post_types(): array
{
    return ['general', 'discussion', 'question', 'resource_share'];
}

function community_post_type_label(string $postType): string
{
    return match ($postType) {
        'general' => 'General',
        'discussion' => 'Discussion',
        'question' => 'Question',
        'resource_share' => 'Resource Share',
        default => 'General',
    };
}

function community_report_reasons(): array
{
    return ['spam', 'harassment', 'misinformation', 'other'];
}

function community_report_reason_label(string $reason): string
{
    return match ($reason) {
        'spam' => 'Spam',
        'harassment' => 'Harassment',
        'misinformation' => 'Misinformation',
        'other' => 'Other',
        default => 'Other',
    };
}

function community_allowed_image_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'];
}

function community_batch_options_for_admin(): array
{
    return onboarding_approved_batches();
}

function community_find_batch_option_by_id(int $batchId): ?array
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

function community_subject_options_for_batch(int $batchId): array
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

function community_subject_exists_in_batch(int $subjectId, int $batchId): bool
{
    if ($subjectId <= 0 || $batchId <= 0) {
        return false;
    }

    return (bool) db_fetch(
        'SELECT id FROM subjects WHERE id = ? AND batch_id = ? LIMIT 1',
        [$subjectId, $batchId]
    );
}

function community_posts_for_batch(
    int $batchId,
    ?int $subjectId,
    ?string $postType,
    string $sortBy,
    int $viewerUserId,
    string $searchQuery = '',
    int $page = 1,
    int $perPage = 10
): array {
    if ($batchId <= 0) {
        return [
            'posts' => [],
            'has_more' => false,
        ];
    }

    $page = max(1, min(50, $page));
    $perPage = max(1, min(30, $perPage));
    $visibleLimit = $page * $perPage;
    $queryLimit = $visibleLimit + 1;

    $params = [$viewerUserId, $viewerUserId, $batchId];
    $subjectSql = '';
    if ($subjectId !== null && $subjectId > 0) {
        $subjectSql = ' AND p.subject_id = ?';
        $params[] = $subjectId;
    }

    $postTypeSql = '';
    if ($postType !== null && $postType !== '' && in_array($postType, community_post_types(), true)) {
        $postTypeSql = ' AND p.post_type = ?';
        $params[] = $postType;
    }

    $searchSql = '';
    $searchQuery = trim($searchQuery);
    if ($searchQuery !== '') {
        $needle = '%' . $searchQuery . '%';
        $searchSql = ' AND (p.body LIKE ? OR u.name LIKE ? OR s.code LIKE ? OR s.name LIKE ?)';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    $withinPartitionOrderSql = $sortBy === 'top'
        ? 'COALESCE(lc.like_count, 0) DESC, COALESCE(cc.comment_count, 0) DESC, p.created_at DESC, p.id DESC'
        : 'p.created_at DESC, p.id DESC';

    $orderBySql = $withinPartitionOrderSql;

    $rows = db_fetch_all(
        "SELECT p.*,
                u.name AS author_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(lc.like_count, 0) AS like_count,
                COALESCE(cc.comment_count, 0) AS comment_count,
                CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS is_liked_by_viewer,
                CASE WHEN us.id IS NULL THEN 0 ELSE 1 END AS is_saved_by_viewer
         FROM feed_posts p
         INNER JOIN batches b ON b.id = p.batch_id
         LEFT JOIN users u ON u.id = p.author_user_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count
            FROM feed_post_likes
            GROUP BY post_id
         ) lc ON lc.post_id = p.id
         LEFT JOIN (
            SELECT target_id AS post_id, COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'feed_post'
            GROUP BY target_id
         ) cc ON cc.post_id = p.id
         LEFT JOIN feed_post_likes ul
                ON ul.post_id = p.id
               AND ul.user_id = ?
         LEFT JOIN feed_post_saves us
                ON us.post_id = p.id
               AND us.user_id = ?
         WHERE p.batch_id = ?{$subjectSql}
               AND p.post_type <> 'announcement'
               {$postTypeSql}
               {$searchSql}
         ORDER BY {$orderBySql}
         LIMIT {$queryLimit}",
        $params
    );

    $hasMore = count($rows) > $visibleLimit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $visibleLimit);
    }

    return [
        'posts' => $rows,
        'has_more' => $hasMore,
    ];
}

function community_post_type_counts_for_batch(int $batchId, ?int $subjectId = null): array
{
    if ($batchId <= 0) {
        return [];
    }

    $params = [$batchId];
    $subjectSql = '';
    if ($subjectId !== null && $subjectId > 0) {
        $subjectSql = ' AND subject_id = ?';
        $params[] = $subjectId;
    }

    $rows = db_fetch_all(
        "SELECT post_type, COUNT(*) AS cnt
         FROM feed_posts
         WHERE batch_id = ?{$subjectSql}
           AND post_type <> 'announcement'
         GROUP BY post_type",
        $params
    );

    $counts = ['_all' => 0];
    foreach (community_post_types() as $type) {
        $counts[$type] = 0;
    }

    foreach ($rows as $row) {
        $type = (string) ($row['post_type'] ?? '');
        $count = (int) ($row['cnt'] ?? 0);
        if (isset($counts[$type])) {
            $counts[$type] = $count;
            $counts['_all'] += $count;
        }
    }

    return $counts;
}

function community_popular_posts_for_batch(
    int $batchId,
    ?int $subjectId,
    int $viewerUserId,
    int $limit = 3
): array {
    if ($batchId <= 0) {
        return [];
    }

    $limit = max(1, min(10, $limit));
    $params = [$viewerUserId, $batchId];
    $subjectSql = '';
    if ($subjectId !== null && $subjectId > 0) {
        $subjectSql = ' AND p.subject_id = ?';
        $params[] = $subjectId;
    }

    return db_fetch_all(
        "SELECT p.*,
                u.name AS author_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(lc.like_count, 0) AS like_count,
                COALESCE(cc.comment_count, 0) AS comment_count,
                CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS is_liked_by_viewer
         FROM feed_posts p
         LEFT JOIN users u ON u.id = p.author_user_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count
            FROM feed_post_likes
            GROUP BY post_id
         ) lc ON lc.post_id = p.id
         LEFT JOIN (
            SELECT target_id AS post_id, COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'feed_post'
            GROUP BY target_id
         ) cc ON cc.post_id = p.id
         LEFT JOIN feed_post_likes ul
                ON ul.post_id = p.id
               AND ul.user_id = ?
         WHERE p.batch_id = ?{$subjectSql}
           AND p.post_type <> 'announcement'
         ORDER BY COALESCE(lc.like_count, 0) DESC, COALESCE(cc.comment_count, 0) DESC, p.created_at DESC, p.id DESC
         LIMIT {$limit}",
        $params
    );
}

function community_find_post_for_batch(int $postId, int $batchId, int $viewerUserId): ?array
{
    if ($postId <= 0 || $batchId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT p.*,
                u.name AS author_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(lc.like_count, 0) AS like_count,
                COALESCE(cc.comment_count, 0) AS comment_count,
                CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS is_liked_by_viewer,
                CASE WHEN us.id IS NULL THEN 0 ELSE 1 END AS is_saved_by_viewer
         FROM feed_posts p
         INNER JOIN batches b ON b.id = p.batch_id
         LEFT JOIN users u ON u.id = p.author_user_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count
            FROM feed_post_likes
            GROUP BY post_id
         ) lc ON lc.post_id = p.id
         LEFT JOIN (
            SELECT target_id AS post_id, COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'feed_post'
            GROUP BY target_id
         ) cc ON cc.post_id = p.id
         LEFT JOIN feed_post_likes ul
                ON ul.post_id = p.id
               AND ul.user_id = ?
         LEFT JOIN feed_post_saves us
                ON us.post_id = p.id
               AND us.user_id = ?
         WHERE p.id = ?
           AND p.batch_id = ?
           AND p.post_type <> 'announcement'
         LIMIT 1",
        [$viewerUserId, $viewerUserId, $postId, $batchId]
    );
}

function community_find_post_admin(int $postId, int $viewerUserId): ?array
{
    if ($postId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT p.*,
                u.name AS author_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(lc.like_count, 0) AS like_count,
                COALESCE(cc.comment_count, 0) AS comment_count,
                CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS is_liked_by_viewer,
                CASE WHEN us.id IS NULL THEN 0 ELSE 1 END AS is_saved_by_viewer
         FROM feed_posts p
         INNER JOIN batches b ON b.id = p.batch_id
         LEFT JOIN users u ON u.id = p.author_user_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count
            FROM feed_post_likes
            GROUP BY post_id
         ) lc ON lc.post_id = p.id
         LEFT JOIN (
            SELECT target_id AS post_id, COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'feed_post'
            GROUP BY target_id
         ) cc ON cc.post_id = p.id
         LEFT JOIN feed_post_likes ul
                ON ul.post_id = p.id
               AND ul.user_id = ?
         LEFT JOIN feed_post_saves us
                ON us.post_id = p.id
               AND us.user_id = ?
         WHERE p.id = ?
           AND p.post_type <> 'announcement'
         LIMIT 1",
        [$viewerUserId, $viewerUserId, $postId]
    );
}

function community_find_owned_post(int $postId, int $ownerUserId): ?array
{
    if ($postId <= 0 || $ownerUserId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT p.*,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name
         FROM feed_posts p
         INNER JOIN batches b ON b.id = p.batch_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         WHERE p.id = ?
           AND p.author_user_id = ?
           AND p.post_type <> 'announcement'
         LIMIT 1",
        [$postId, $ownerUserId]
    );
}

function community_create_post(array $data): int
{
    return (int) db_insert('feed_posts', [
        'batch_id' => (int) $data['batch_id'],
        'subject_id' => $data['subject_id'] !== null ? (int) $data['subject_id'] : null,
        'author_user_id' => $data['author_user_id'] !== null ? (int) $data['author_user_id'] : null,
        'post_type' => $data['post_type'],
        'body' => $data['body'] ?? null,
        'image_path' => $data['image_path'] ?? null,
        'image_name' => $data['image_name'] ?? null,
        'image_mime' => $data['image_mime'] ?? null,
        'image_size' => $data['image_size'] ?? null,
        'edited_at' => $data['edited_at'] ?? null,
    ]);
}

function community_update_post_by_owner(int $postId, int $ownerUserId, array $data): bool
{
    db_query(
        "UPDATE feed_posts
         SET subject_id = ?,
             post_type = ?,
             body = ?,
             image_path = ?,
             image_name = ?,
             image_mime = ?,
             image_size = ?,
             edited_at = NOW()
         WHERE id = ?
           AND author_user_id = ?",
        [
            $data['subject_id'] !== null ? (int) $data['subject_id'] : null,
            $data['post_type'],
            $data['body'] ?? null,
            $data['image_path'] ?? null,
            $data['image_name'] ?? null,
            $data['image_mime'] ?? null,
            $data['image_size'] ?? null,
            $postId,
            $ownerUserId,
        ]
    );

    return true;
}

function community_set_question_resolved_state(int $postId, int $authorUserId, bool $resolved): bool
{
    if ($postId <= 0 || $authorUserId <= 0) {
        return false;
    }

    if ($resolved) {
        return db_query(
            "UPDATE feed_posts
             SET is_resolved = 1,
                 resolved_by_user_id = ?,
                 resolved_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?
               AND author_user_id = ?
               AND post_type = 'question'",
            [$authorUserId, $postId, $authorUserId]
        )->rowCount() > 0;
    }

    return db_query(
        "UPDATE feed_posts
         SET is_resolved = 0,
             resolved_by_user_id = NULL,
             resolved_at = NULL,
             updated_at = NOW()
         WHERE id = ?
           AND author_user_id = ?
           AND post_type = 'question'",
        [$postId, $authorUserId]
    )->rowCount() > 0;
}

function community_my_posts(int $ownerUserId): array
{
    if ($ownerUserId <= 0) {
        return [];
    }

    return db_fetch_all(
        "SELECT p.*,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(lc.like_count, 0) AS like_count,
                COALESCE(cc.comment_count, 0) AS comment_count
         FROM feed_posts p
         INNER JOIN batches b ON b.id = p.batch_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count
            FROM feed_post_likes
            GROUP BY post_id
         ) lc ON lc.post_id = p.id
         LEFT JOIN (
            SELECT target_id AS post_id, COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'feed_post'
            GROUP BY target_id
         ) cc ON cc.post_id = p.id
         WHERE p.author_user_id = ?
           AND p.post_type <> 'announcement'
         ORDER BY p.updated_at DESC, p.id DESC",
        [$ownerUserId]
    );
}

function community_toggle_like(int $postId, int $userId): bool
{
    if ($postId <= 0 || $userId <= 0) {
        return false;
    }

    $existing = db_fetch(
        'SELECT id FROM feed_post_likes WHERE post_id = ? AND user_id = ? LIMIT 1',
        [$postId, $userId]
    );

    if ($existing) {
        db_query(
            'DELETE FROM feed_post_likes WHERE post_id = ? AND user_id = ?',
            [$postId, $userId]
        );
        return false;
    }

    try {
        db_insert('feed_post_likes', [
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
    } catch (Throwable) {
        return true;
    }

    return true;
}

function community_toggle_save(int $postId, int $userId): bool
{
    if ($postId <= 0 || $userId <= 0) {
        return false;
    }

    $existing = db_fetch(
        'SELECT id FROM feed_post_saves WHERE post_id = ? AND user_id = ? LIMIT 1',
        [$postId, $userId]
    );

    if ($existing) {
        db_query(
            'DELETE FROM feed_post_saves WHERE post_id = ? AND user_id = ?',
            [$postId, $userId]
        );
        return false;
    }

    try {
        db_insert('feed_post_saves', [
            'post_id' => $postId,
            'user_id' => $userId,
        ]);
    } catch (Throwable) {
        return true;
    }

    return true;
}

function community_saved_posts_for_user(int $userId, ?int $batchId, bool $isAdmin): array
{
    if ($userId <= 0) {
        return [];
    }

    $params = [$userId, $userId, $userId];
    $scopeSql = '';
    if (!$isAdmin) {
        if ($batchId === null || $batchId <= 0) {
            return [];
        }
        $scopeSql = ' AND p.batch_id = ?';
        $params[] = $batchId;
    }

    return db_fetch_all(
        "SELECT p.*,
                fps.created_at AS saved_at,
                u.name AS author_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(lc.like_count, 0) AS like_count,
                COALESCE(cc.comment_count, 0) AS comment_count,
                CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS is_liked_by_viewer,
                1 AS is_saved_by_viewer
         FROM feed_post_saves fps
         INNER JOIN feed_posts p ON p.id = fps.post_id
         INNER JOIN batches b ON b.id = p.batch_id
         LEFT JOIN users u ON u.id = p.author_user_id
         LEFT JOIN subjects s ON s.id = p.subject_id
         LEFT JOIN (
            SELECT post_id, COUNT(*) AS like_count
            FROM feed_post_likes
            GROUP BY post_id
         ) lc ON lc.post_id = p.id
         LEFT JOIN (
            SELECT target_id AS post_id, COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'feed_post'
            GROUP BY target_id
         ) cc ON cc.post_id = p.id
         LEFT JOIN feed_post_likes ul
                ON ul.post_id = p.id
               AND ul.user_id = ?
         LEFT JOIN feed_post_saves us
                ON us.post_id = p.id
               AND us.user_id = ?
         WHERE fps.user_id = ?{$scopeSql}
         ORDER BY fps.created_at DESC, fps.id DESC",
        $params
    );
}

function community_find_open_report_for_target(int $reporterUserId, string $targetType, int $targetId): ?array
{
    if ($reporterUserId <= 0 || $targetId <= 0 || !in_array($targetType, ['post', 'comment'], true)) {
        return null;
    }

    return db_fetch(
        "SELECT id
         FROM feed_reports
         WHERE reporter_user_id = ?
           AND target_type = ?
           AND target_id = ?
           AND status = 'open'
         LIMIT 1",
        [$reporterUserId, $targetType, $targetId]
    );
}

function community_create_report(array $data): int
{
    return (int) db_insert('feed_reports', [
        'batch_id' => (int) $data['batch_id'],
        'target_type' => $data['target_type'],
        'target_id' => (int) $data['target_id'],
        'reporter_user_id' => (int) $data['reporter_user_id'],
        'reason' => $data['reason'],
        'details' => $data['details'] ?? null,
        'status' => 'open',
        'reviewed_by_user_id' => null,
        'reviewed_at' => null,
        'action_taken' => null,
    ]);
}

function community_reports_queue(?int $batchId, bool $isAdmin): array
{
    $params = [];
    $whereSql = '';
    if (!$isAdmin) {
        if ($batchId === null || $batchId <= 0) {
            return [];
        }
        $whereSql = 'WHERE r.batch_id = ?';
        $params[] = $batchId;
    }

    return db_fetch_all(
        "SELECT r.*,
                b.batch_code AS report_batch_code,
                b.name AS report_batch_name,
                reporter.name AS reporter_name,
                reviewer.name AS reviewer_name,
                CASE
                    WHEN r.target_type = 'post' THEN p.id
                    WHEN r.target_type = 'comment' THEN cp.id
                    ELSE NULL
                END AS thread_post_id,
                CASE
                    WHEN r.target_type = 'post' THEN p.post_type
                    WHEN r.target_type = 'comment' THEN cp.post_type
                    ELSE NULL
                END AS thread_post_type,
                CASE
                    WHEN r.target_type = 'post' THEN p.body
                    WHEN r.target_type = 'comment' THEN cp.body
                    ELSE NULL
                END AS thread_post_body,
                CASE
                    WHEN r.target_type = 'post' THEN post_author.name
                    WHEN r.target_type = 'comment' THEN cp_author.name
                    ELSE NULL
                END AS thread_post_author_name,
                CASE
                    WHEN r.target_type = 'post' THEN sp.code
                    WHEN r.target_type = 'comment' THEN sc.code
                    ELSE NULL
                END AS thread_subject_code,
                CASE
                    WHEN r.target_type = 'post' THEN sp.name
                    WHEN r.target_type = 'comment' THEN sc.name
                    ELSE NULL
                END AS thread_subject_name,
                c.id AS target_comment_id,
                c.body AS target_comment_body,
                comment_author.name AS target_comment_author_name,
                CASE
                    WHEN r.target_type = 'post' THEN CASE WHEN p.id IS NULL THEN 0 ELSE 1 END
                    WHEN r.target_type = 'comment' THEN CASE WHEN c.id IS NULL THEN 0 ELSE 1 END
                    ELSE 0
                END AS target_exists
         FROM feed_reports r
         LEFT JOIN batches b ON b.id = r.batch_id
         LEFT JOIN users reporter ON reporter.id = r.reporter_user_id
         LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
         LEFT JOIN feed_posts p
                ON r.target_type = 'post'
               AND p.id = r.target_id
         LEFT JOIN users post_author ON post_author.id = p.author_user_id
         LEFT JOIN subjects sp ON sp.id = p.subject_id
         LEFT JOIN comments c
                ON r.target_type = 'comment'
               AND c.id = r.target_id
         LEFT JOIN users comment_author ON comment_author.id = c.user_id
         LEFT JOIN feed_posts cp
                ON c.target_type = 'feed_post'
               AND cp.id = c.target_id
         LEFT JOIN users cp_author ON cp_author.id = cp.author_user_id
         LEFT JOIN subjects sc ON sc.id = cp.subject_id
         {$whereSql}
         ORDER BY CASE r.status
                    WHEN 'open' THEN 0
                    WHEN 'dismissed' THEN 1
                    ELSE 2
                  END ASC,
                  r.created_at DESC,
                  r.id DESC",
        $params
    );
}

function community_find_report_queue_item(int $reportId, ?int $batchId, bool $isAdmin): ?array
{
    if ($reportId <= 0) {
        return null;
    }

    $params = [$reportId];
    $scopeSql = '';
    if (!$isAdmin) {
        if ($batchId === null || $batchId <= 0) {
            return null;
        }
        $scopeSql = ' AND r.batch_id = ?';
        $params[] = $batchId;
    }

    return db_fetch(
        "SELECT r.*,
                b.batch_code AS report_batch_code,
                b.name AS report_batch_name,
                reporter.name AS reporter_name,
                reviewer.name AS reviewer_name,
                CASE
                    WHEN r.target_type = 'post' THEN p.id
                    WHEN r.target_type = 'comment' THEN cp.id
                    ELSE NULL
                END AS thread_post_id,
                CASE
                    WHEN r.target_type = 'post' THEN p.post_type
                    WHEN r.target_type = 'comment' THEN cp.post_type
                    ELSE NULL
                END AS thread_post_type,
                CASE
                    WHEN r.target_type = 'post' THEN p.body
                    WHEN r.target_type = 'comment' THEN cp.body
                    ELSE NULL
                END AS thread_post_body,
                CASE
                    WHEN r.target_type = 'post' THEN post_author.name
                    WHEN r.target_type = 'comment' THEN cp_author.name
                    ELSE NULL
                END AS thread_post_author_name,
                CASE
                    WHEN r.target_type = 'post' THEN sp.code
                    WHEN r.target_type = 'comment' THEN sc.code
                    ELSE NULL
                END AS thread_subject_code,
                CASE
                    WHEN r.target_type = 'post' THEN sp.name
                    WHEN r.target_type = 'comment' THEN sc.name
                    ELSE NULL
                END AS thread_subject_name,
                c.id AS target_comment_id,
                c.body AS target_comment_body,
                c.user_id AS target_comment_author_id,
                comment_author.name AS target_comment_author_name,
                CASE
                    WHEN r.target_type = 'post' THEN CASE WHEN p.id IS NULL THEN 0 ELSE 1 END
                    WHEN r.target_type = 'comment' THEN CASE WHEN c.id IS NULL THEN 0 ELSE 1 END
                    ELSE 0
                END AS target_exists
         FROM feed_reports r
         LEFT JOIN batches b ON b.id = r.batch_id
         LEFT JOIN users reporter ON reporter.id = r.reporter_user_id
         LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
         LEFT JOIN feed_posts p
                ON r.target_type = 'post'
               AND p.id = r.target_id
         LEFT JOIN users post_author ON post_author.id = p.author_user_id
         LEFT JOIN subjects sp ON sp.id = p.subject_id
         LEFT JOIN comments c
                ON r.target_type = 'comment'
               AND c.id = r.target_id
         LEFT JOIN users comment_author ON comment_author.id = c.user_id
         LEFT JOIN feed_posts cp
                ON c.target_type = 'feed_post'
               AND cp.id = c.target_id
         LEFT JOIN users cp_author ON cp_author.id = cp.author_user_id
         LEFT JOIN subjects sc ON sc.id = cp.subject_id
         WHERE r.id = ?{$scopeSql}
         LIMIT 1",
        $params
    );
}

function community_dismiss_report(int $reportId, int $reviewerUserId): bool
{
    if ($reportId <= 0 || $reviewerUserId <= 0) {
        return false;
    }

    return db_query(
        "UPDATE feed_reports
         SET status = 'dismissed',
             reviewed_by_user_id = ?,
             reviewed_at = NOW(),
             action_taken = 'dismissed',
             updated_at = NOW()
         WHERE id = ?
           AND status = 'open'",
        [$reviewerUserId, $reportId]
    )->rowCount() > 0;
}

function community_resolve_report(int $reportId, int $reviewerUserId, string $actionTaken): bool
{
    if ($reportId <= 0 || $reviewerUserId <= 0) {
        return false;
    }

    return db_query(
        "UPDATE feed_reports
         SET status = 'resolved',
             reviewed_by_user_id = ?,
             reviewed_at = NOW(),
             action_taken = ?,
             updated_at = NOW()
         WHERE id = ?
           AND status = 'open'",
        [$reviewerUserId, $actionTaken, $reportId]
    )->rowCount() > 0;
}

function community_resolve_open_reports_for_target(string $targetType, int $targetId, int $reviewerUserId, string $actionTaken): int
{
    if ($targetId <= 0 || $reviewerUserId <= 0 || !in_array($targetType, ['post', 'comment'], true)) {
        return 0;
    }

    return db_query(
        "UPDATE feed_reports
         SET status = 'resolved',
             reviewed_by_user_id = ?,
             reviewed_at = NOW(),
             action_taken = ?,
             updated_at = NOW()
         WHERE target_type = ?
           AND target_id = ?
           AND status = 'open'",
        [$reviewerUserId, $actionTaken, $targetType, $targetId]
    )->rowCount();
}

function community_post_ids_for_batch(int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    $rows = db_fetch_all(
        'SELECT id FROM feed_posts WHERE batch_id = ?',
        [$batchId]
    );

    return array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
}

function community_delete_comments_for_post_ids(array $postIds): int
{
    return comments_delete_for_target_ids('feed_post', $postIds);
}

function community_delete_comments_for_post(int $postId): int
{
    return comments_delete_for_target('feed_post', $postId);
}

function community_delete_comments_for_batch(int $batchId): int
{
    return community_delete_comments_for_post_ids(community_post_ids_for_batch($batchId));
}

function community_delete_post_by_id(int $postId): bool
{
    if ($postId <= 0) {
        return false;
    }

    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        community_delete_comments_for_post($postId);
        $deleted = db_query('DELETE FROM feed_posts WHERE id = ?', [$postId])->rowCount() > 0;
        if (!$deleted) {
            $pdo->rollBack();
            return false;
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
