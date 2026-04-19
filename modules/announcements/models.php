<?php

/**
 * Announcements Module — Models
 */

function announcements_per_page(): int
{
    return 10;
}

function announcements_search_query_max_length(): int
{
    return 120;
}

function announcements_batch_options_for_admin(): array
{
    return onboarding_approved_batches();
}

function announcements_find_batch_option_by_id(int $batchId): ?array
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

function announcements_subject_options_for_batch(int $batchId): array
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

function announcements_subject_exists_in_batch(int $subjectId, int $batchId): bool
{
    if ($subjectId <= 0 || $batchId <= 0) {
        return false;
    }

    return (bool) db_fetch(
        'SELECT id FROM subjects WHERE id = ? AND batch_id = ? LIMIT 1',
        [$subjectId, $batchId]
    );
}

function announcements_filters_sql(?int $subjectId, string $searchQuery, array &$params): string
{
    $params = [];
    $conditions = [];

    if ($subjectId !== null && $subjectId > 0) {
        $conditions[] = 'a.subject_id = ?';
        $params[] = $subjectId;
    }

    $search = trim($searchQuery);
    if ($search !== '') {
        $needle = '%' . $search . '%';
        $conditions[] = '(a.title LIKE ? OR a.body LIKE ? OR au.name LIKE ? OR s.code LIKE ? OR s.name LIKE ?)';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    if (empty($conditions)) {
        return '';
    }

    return ' AND ' . implode(' AND ', $conditions);
}

function announcements_fetch_page(
    int $batchId,
    ?int $subjectId,
    string $searchQuery,
    int $page = 1,
    int $perPage = 10
): array {
    if ($batchId <= 0) {
        return ['items' => [], 'has_more' => false];
    }

    $page = max(1, min(50, $page));
    $perPage = max(1, min(30, $perPage));
    $offset = ($page - 1) * $perPage;
    $queryLimit = $perPage + 1;

    $filterParams = [];
    $filtersSql = announcements_filters_sql($subjectId, $searchQuery, $filterParams);

    $rows = db_fetch_all(
        "SELECT a.*,
                au.name AS author_name,
                s.code AS subject_code,
                s.name AS subject_name
         FROM announcements a
         LEFT JOIN users au ON au.id = a.author_user_id
         LEFT JOIN subjects s ON s.id = a.subject_id
         WHERE a.batch_id = ?{$filtersSql}
         ORDER BY a.is_pinned DESC, a.created_at DESC, a.id DESC
         LIMIT {$queryLimit} OFFSET {$offset}",
        array_merge([$batchId], $filterParams)
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

function announcements_filtered_count(int $batchId, ?int $subjectId, string $searchQuery): int
{
    if ($batchId <= 0) {
        return 0;
    }

    $filterParams = [];
    $filtersSql = announcements_filters_sql($subjectId, $searchQuery, $filterParams);

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM announcements a
         LEFT JOIN users au ON au.id = a.author_user_id
         LEFT JOIN subjects s ON s.id = a.subject_id
         WHERE a.batch_id = ?{$filtersSql}",
        array_merge([$batchId], $filterParams)
    );

    return (int) ($row['cnt'] ?? 0);
}

function announcements_today_count(int $batchId, ?int $subjectId, string $searchQuery): int
{
    if ($batchId <= 0) {
        return 0;
    }

    $filterParams = [];
    $filtersSql = announcements_filters_sql($subjectId, $searchQuery, $filterParams);

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM announcements a
         LEFT JOIN users au ON au.id = a.author_user_id
         LEFT JOIN subjects s ON s.id = a.subject_id
         WHERE a.batch_id = ?{$filtersSql}
           AND DATE(a.created_at) = CURDATE()",
        array_merge([$batchId], $filterParams)
    );

    return (int) ($row['cnt'] ?? 0);
}

function announcements_pinned_count_for_batch(int $batchId): int
{
    if ($batchId <= 0) {
        return 0;
    }

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM announcements
         WHERE batch_id = ?
           AND is_pinned = 1",
        [$batchId]
    );

    return (int) ($row['cnt'] ?? 0);
}

function announcements_find_by_id(int $announcementId): ?array
{
    if ($announcementId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT a.*,
                au.name AS author_name,
                s.code AS subject_code,
                s.name AS subject_name,
                b.batch_code,
                b.name AS batch_name,
                u.name AS university_name
         FROM announcements a
         INNER JOIN batches b ON b.id = a.batch_id
         LEFT JOIN universities u ON u.id = b.university_id
         LEFT JOIN users au ON au.id = a.author_user_id
         LEFT JOIN subjects s ON s.id = a.subject_id
         WHERE a.id = ?
         LIMIT 1",
        [$announcementId]
    );
}

function announcements_find_by_id_for_batch(int $announcementId, int $batchId): ?array
{
    if ($announcementId <= 0 || $batchId <= 0) {
        return null;
    }

    return db_fetch(
        "SELECT a.*,
                au.name AS author_name,
                s.code AS subject_code,
                s.name AS subject_name,
                b.batch_code,
                b.name AS batch_name,
                u.name AS university_name
         FROM announcements a
         INNER JOIN batches b ON b.id = a.batch_id
         LEFT JOIN universities u ON u.id = b.university_id
         LEFT JOIN users au ON au.id = a.author_user_id
         LEFT JOIN subjects s ON s.id = a.subject_id
         WHERE a.id = ?
           AND a.batch_id = ?
         LIMIT 1",
        [$announcementId, $batchId]
    );
}

function announcements_create(array $data): int
{
    return (int) db_insert('announcements', [
        'batch_id' => (int) $data['batch_id'],
        'subject_id' => $data['subject_id'] !== null ? (int) $data['subject_id'] : null,
        'author_user_id' => $data['author_user_id'] !== null ? (int) $data['author_user_id'] : null,
        'title' => $data['title'],
        'body' => $data['body'],
    ]);
}

function announcements_update(int $announcementId, array $data): bool
{
    if ($announcementId <= 0) {
        return false;
    }

    db_query(
        "UPDATE announcements
         SET subject_id = ?,
             title = ?,
             body = ?,
             updated_at = NOW()
         WHERE id = ?",
        [
            $data['subject_id'] !== null ? (int) $data['subject_id'] : null,
            $data['title'],
            $data['body'],
            $announcementId,
        ]
    );

    return true;
}

function announcements_delete(int $announcementId): bool
{
    if ($announcementId <= 0) {
        return false;
    }

    db_query('DELETE FROM announcements WHERE id = ?', [$announcementId]);
    return true;
}

function announcements_pin_single(int $announcementId, int $batchId, int $actorUserId): bool
{
    if ($announcementId <= 0 || $batchId <= 0 || $actorUserId <= 0) {
        return false;
    }

    $pdo = db_connect();
    try {
        $pdo->beginTransaction();

        db_query(
            "UPDATE announcements
             SET is_pinned = 0,
                 pinned_by_user_id = NULL,
                 pinned_at = NULL,
                 updated_at = NOW()
             WHERE batch_id = ?
               AND is_pinned = 1",
            [$batchId]
        );

        $updated = db_query(
            "UPDATE announcements
             SET is_pinned = 1,
                 pinned_by_user_id = ?,
                 pinned_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?
               AND batch_id = ?",
            [$actorUserId, $announcementId, $batchId]
        )->rowCount() > 0;

        $pdo->commit();
        return $updated;
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function announcements_unpin(int $announcementId, int $batchId): bool
{
    if ($announcementId <= 0 || $batchId <= 0) {
        return false;
    }

    return db_query(
        "UPDATE announcements
         SET is_pinned = 0,
             pinned_by_user_id = NULL,
             pinned_at = NULL,
             updated_at = NOW()
         WHERE id = ?
           AND batch_id = ?",
        [$announcementId, $batchId]
    )->rowCount() > 0;
}
