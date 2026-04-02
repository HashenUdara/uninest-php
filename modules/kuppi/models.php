<?php

/**
 * Kuppi Module — Models
 */

function kuppi_allowed_statuses(): array
{
    return ['open', 'scheduled', 'completed', 'cancelled'];
}

function kuppi_sort_options(): array
{
    return ['most_votes', 'recent'];
}

function kuppi_batch_options_for_admin(): array
{
    return onboarding_approved_batches();
}

function kuppi_find_batch_option_by_id(int $batchId): ?array
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

function kuppi_subject_options_for_batch(int $batchId): array
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

function kuppi_subject_exists_in_batch(int $subjectId, int $batchId): bool
{
    if ($subjectId <= 0 || $batchId <= 0) {
        return false;
    }

    return (bool) db_fetch(
        'SELECT id FROM subjects WHERE id = ? AND batch_id = ? LIMIT 1',
        [$subjectId, $batchId]
    );
}

function kuppi_requests_for_batch(
    int $batchId,
    ?int $subjectId,
    string $sortBy,
    int $viewerUserId,
    string $searchQuery = '',
    int $page = 1,
    int $perPage = 10
): array {
    if ($batchId <= 0) {
        return [
            'requests' => [],
            'has_more' => false,
        ];
    }

    $page = max(1, min(50, $page));
    $perPage = max(1, min(30, $perPage));
    $visibleLimit = $page * $perPage;
    $queryLimit = $visibleLimit + 1;

    $params = [$viewerUserId, $batchId];
    $subjectSql = '';
    if ($subjectId !== null && $subjectId > 0) {
        $subjectSql = ' AND kr.subject_id = ?';
        $params[] = $subjectId;
    }

    $searchSql = '';
    $searchQuery = trim($searchQuery);
    if ($searchQuery !== '') {
        $needle = '%' . $searchQuery . '%';
        $searchSql = ' AND (kr.title LIKE ? OR kr.description LIKE ? OR kr.tags_csv LIKE ? OR s.code LIKE ? OR s.name LIKE ?)';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    $orderBySql = $sortBy === 'recent'
        ? 'kr.created_at DESC, kr.id DESC'
        : 'COALESCE(vv.score, 0) DESC, COALESCE(vv.upvotes, 0) DESC, kr.created_at DESC, kr.id DESC';

    $rows = db_fetch_all(
        "SELECT kr.*,
                u.name AS requester_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(vv.upvotes, 0) AS upvote_count,
                COALESCE(vv.downvotes, 0) AS downvote_count,
                COALESCE(vv.score, 0) AS vote_score,
                uv.vote_type AS viewer_vote
         FROM kuppi_requests kr
         INNER JOIN batches b ON b.id = kr.batch_id
         INNER JOIN subjects s ON s.id = kr.subject_id
         LEFT JOIN users u ON u.id = kr.requested_by_user_id
         LEFT JOIN (
            SELECT request_id,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) AS upvotes,
                   SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) AS downvotes,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) AS score
            FROM kuppi_request_votes
            GROUP BY request_id
         ) vv ON vv.request_id = kr.id
         LEFT JOIN kuppi_request_votes uv
                ON uv.request_id = kr.id
               AND uv.user_id = ?
         WHERE kr.batch_id = ?
           AND kr.status = 'open'
           {$subjectSql}
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
        'requests' => $rows,
        'has_more' => $hasMore,
    ];
}

function kuppi_requests_count_for_batch(
    int $batchId,
    ?int $subjectId,
    string $searchQuery = ''
): int {
    if ($batchId <= 0) {
        return 0;
    }

    $params = [$batchId];
    $subjectSql = '';
    if ($subjectId !== null && $subjectId > 0) {
        $subjectSql = ' AND kr.subject_id = ?';
        $params[] = $subjectId;
    }

    $searchSql = '';
    $searchQuery = trim($searchQuery);
    if ($searchQuery !== '') {
        $needle = '%' . $searchQuery . '%';
        $searchSql = ' AND (kr.title LIKE ? OR kr.description LIKE ? OR kr.tags_csv LIKE ? OR s.code LIKE ? OR s.name LIKE ?)';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM kuppi_requests kr
         INNER JOIN subjects s ON s.id = kr.subject_id
         WHERE kr.batch_id = ?
           AND kr.status = 'open'
           {$subjectSql}
           {$searchSql}",
        $params
    );

    return (int) ($row['cnt'] ?? 0);
}

function kuppi_find_request_for_batch(int $requestId, int $batchId, int $viewerUserId): ?array
{
    if ($requestId <= 0 || $batchId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT kr.*,
                u.name AS requester_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(vv.upvotes, 0) AS upvote_count,
                COALESCE(vv.downvotes, 0) AS downvote_count,
                COALESCE(vv.score, 0) AS vote_score,
                uv.vote_type AS viewer_vote
         FROM kuppi_requests kr
         INNER JOIN batches b ON b.id = kr.batch_id
         INNER JOIN subjects s ON s.id = kr.subject_id
         LEFT JOIN users u ON u.id = kr.requested_by_user_id
         LEFT JOIN (
            SELECT request_id,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) AS upvotes,
                   SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) AS downvotes,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) AS score
            FROM kuppi_request_votes
            GROUP BY request_id
         ) vv ON vv.request_id = kr.id
         LEFT JOIN kuppi_request_votes uv
                ON uv.request_id = kr.id
               AND uv.user_id = ?
         WHERE kr.id = ?
           AND kr.batch_id = ?
         LIMIT 1",
        [$viewerUserId, $requestId, $batchId]
    );
}

function kuppi_find_request_admin(int $requestId, int $viewerUserId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT kr.*,
                u.name AS requester_name,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(vv.upvotes, 0) AS upvote_count,
                COALESCE(vv.downvotes, 0) AS downvote_count,
                COALESCE(vv.score, 0) AS vote_score,
                uv.vote_type AS viewer_vote
         FROM kuppi_requests kr
         INNER JOIN batches b ON b.id = kr.batch_id
         INNER JOIN subjects s ON s.id = kr.subject_id
         LEFT JOIN users u ON u.id = kr.requested_by_user_id
         LEFT JOIN (
            SELECT request_id,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) AS upvotes,
                   SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) AS downvotes,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) AS score
            FROM kuppi_request_votes
            GROUP BY request_id
         ) vv ON vv.request_id = kr.id
         LEFT JOIN kuppi_request_votes uv
                ON uv.request_id = kr.id
               AND uv.user_id = ?
         WHERE kr.id = ?
         LIMIT 1",
        [$viewerUserId, $requestId]
    );
}

function kuppi_find_owned_request(int $requestId, int $ownerUserId): ?array
{
    if ($requestId <= 0 || $ownerUserId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT kr.*,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name
         FROM kuppi_requests kr
         INNER JOIN batches b ON b.id = kr.batch_id
         INNER JOIN subjects s ON s.id = kr.subject_id
         WHERE kr.id = ?
           AND kr.requested_by_user_id = ?
         LIMIT 1",
        [$requestId, $ownerUserId]
    );
}

function kuppi_my_requests(int $ownerUserId): array
{
    if ($ownerUserId <= 0) {
        return [];
    }

    return db_fetch_all(
        "SELECT kr.*,
                b.batch_code,
                b.name AS batch_name,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(vv.upvotes, 0) AS upvote_count,
                COALESCE(vv.downvotes, 0) AS downvote_count,
                COALESCE(vv.score, 0) AS vote_score
         FROM kuppi_requests kr
         INNER JOIN batches b ON b.id = kr.batch_id
         INNER JOIN subjects s ON s.id = kr.subject_id
         LEFT JOIN (
            SELECT request_id,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) AS upvotes,
                   SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) AS downvotes,
                   SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END) AS score
            FROM kuppi_request_votes
            GROUP BY request_id
         ) vv ON vv.request_id = kr.id
         WHERE kr.requested_by_user_id = ?
         ORDER BY kr.updated_at DESC, kr.id DESC",
        [$ownerUserId]
    );
}

function kuppi_create_request(array $data): int
{
    return (int) db_insert('kuppi_requests', [
        'batch_id' => (int) $data['batch_id'],
        'subject_id' => (int) $data['subject_id'],
        'requested_by_user_id' => $data['requested_by_user_id'] !== null ? (int) $data['requested_by_user_id'] : null,
        'title' => $data['title'],
        'description' => $data['description'],
        'tags_csv' => $data['tags_csv'] !== '' ? $data['tags_csv'] : null,
        'status' => $data['status'] ?? 'open',
    ]);
}

function kuppi_update_request_by_owner(int $requestId, int $ownerUserId, array $data): bool
{
    $stmt = db_query(
        "UPDATE kuppi_requests
         SET subject_id = ?,
             title = ?,
             description = ?,
             tags_csv = ?,
             updated_at = NOW()
         WHERE id = ?
           AND requested_by_user_id = ?
           AND status = 'open'",
        [
            (int) $data['subject_id'],
            $data['title'],
            $data['description'],
            $data['tags_csv'] !== '' ? $data['tags_csv'] : null,
            $requestId,
            $ownerUserId,
        ]
    );

    if ($stmt->rowCount() > 0) {
        return true;
    }

    return (bool) db_fetch(
        "SELECT id
         FROM kuppi_requests
         WHERE id = ?
           AND requested_by_user_id = ?
           AND status = 'open'
         LIMIT 1",
        [$requestId, $ownerUserId]
    );
}

function kuppi_delete_request_by_id(int $requestId): bool
{
    if ($requestId <= 0) {
        return false;
    }

    return db_query(
        'DELETE FROM kuppi_requests WHERE id = ?',
        [$requestId]
    )->rowCount() > 0;
}

function kuppi_apply_vote(int $requestId, int $userId, string $direction): ?string
{
    if ($requestId <= 0 || $userId <= 0 || !in_array($direction, ['up', 'down'], true)) {
        return null;
    }

    $existing = db_fetch(
        "SELECT id, vote_type
         FROM kuppi_request_votes
         WHERE request_id = ?
           AND user_id = ?
         LIMIT 1",
        [$requestId, $userId]
    );

    if ($existing) {
        $existingDirection = (string) ($existing['vote_type'] ?? '');
        if ($existingDirection === $direction) {
            db_query(
                'DELETE FROM kuppi_request_votes WHERE request_id = ? AND user_id = ?',
                [$requestId, $userId]
            );
            return null;
        }

        db_query(
            "UPDATE kuppi_request_votes
             SET vote_type = ?,
                 updated_at = NOW()
             WHERE request_id = ?
               AND user_id = ?",
            [$direction, $requestId, $userId]
        );
        return $direction;
    }

    db_insert('kuppi_request_votes', [
        'request_id' => $requestId,
        'user_id' => $userId,
        'vote_type' => $direction,
    ]);

    return $direction;
}

function kuppi_vote_totals_for_request(int $requestId): array
{
    if ($requestId <= 0) {
        return ['upvote_count' => 0, 'downvote_count' => 0, 'vote_score' => 0];
    }

    $row = db_fetch(
        "SELECT
            COALESCE(SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END), 0) AS upvote_count,
            COALESCE(SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END), 0) AS downvote_count,
            COALESCE(SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE -1 END), 0) AS vote_score
         FROM kuppi_request_votes
         WHERE request_id = ?",
        [$requestId]
    ) ?? [];

    return [
        'upvote_count' => (int) ($row['upvote_count'] ?? 0),
        'downvote_count' => (int) ($row['downvote_count'] ?? 0),
        'vote_score' => (int) ($row['vote_score'] ?? 0),
    ];
}
