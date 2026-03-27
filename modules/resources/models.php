<?php

/**
 * Resources Module — Models
 */

function resources_allowed_categories(): array
{
    return [
        'Assignments',
        'Lab Sheets',
        'Lecture Notes',
        'Model Answers',
        'Past Papers',
        'Project Reports',
        'Reference Materials',
        'Short Notes',
        'Tutorials',
        'Video Tutorials',
        'Other',
    ];
}

function resources_allowed_file_extensions(): array
{
    return ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'jpeg', 'png'];
}

function resources_topic_published_list(int $topicId): array
{
    return db_fetch_all(
        "SELECT r.*,
                u.name AS uploader_name,
                COALESCE(rr.average_rating, 0) AS average_rating,
                COALESCE(rr.rating_count, 0) AS rating_count,
                COALESCE(cc.comment_count, 0) AS comment_count
         FROM resources r
         LEFT JOIN users u ON u.id = r.uploaded_by_user_id
         LEFT JOIN (
            SELECT resource_id,
                   ROUND(AVG(rating), 2) AS average_rating,
                   COUNT(*) AS rating_count
            FROM resource_ratings
            GROUP BY resource_id
         ) rr ON rr.resource_id = r.id
         LEFT JOIN (
            SELECT target_id AS resource_id,
                   COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'resource'
            GROUP BY target_id
         ) cc ON cc.resource_id = r.id
         WHERE r.topic_id = ?
           AND r.status = 'published'
         ORDER BY r.updated_at DESC, r.id DESC",
        [$topicId]
    );
}

function resources_find_topic_published(int $resourceId, int $topicId): ?array
{
    return db_fetch(
        "SELECT r.*,
                u.name AS uploader_name,
                COALESCE(rr.average_rating, 0) AS average_rating,
                COALESCE(rr.rating_count, 0) AS rating_count,
                COALESCE(cc.comment_count, 0) AS comment_count
         FROM resources r
         LEFT JOIN users u ON u.id = r.uploaded_by_user_id
         LEFT JOIN (
            SELECT resource_id,
                   ROUND(AVG(rating), 2) AS average_rating,
                   COUNT(*) AS rating_count
            FROM resource_ratings
            GROUP BY resource_id
         ) rr ON rr.resource_id = r.id
         LEFT JOIN (
            SELECT target_id AS resource_id,
                   COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'resource'
            GROUP BY target_id
         ) cc ON cc.resource_id = r.id
         WHERE r.id = ?
           AND r.topic_id = ?
           AND r.status = 'published'
         LIMIT 1",
        [$resourceId, $topicId]
    );
}

function resources_my_all(int $ownerUserId): array
{
    return db_fetch_all(
        "SELECT r.*,
                t.id AS topic_id,
                t.title AS topic_title,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name,
                ur.id AS update_request_id,
                ur.status AS update_request_status,
                ur.rejection_reason AS update_request_rejection_reason,
                ur.updated_at AS update_request_updated_at
         FROM resources r
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         LEFT JOIN resource_update_requests ur ON ur.resource_id = r.id
         WHERE r.uploaded_by_user_id = ?
         ORDER BY r.updated_at DESC, r.id DESC",
        [$ownerUserId]
    );
}

function resources_find_owned(int $resourceId, int $ownerUserId): ?array
{
    return db_fetch(
        "SELECT r.*,
                t.id AS topic_id,
                t.title AS topic_title,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name
         FROM resources r
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         WHERE r.id = ?
           AND r.uploaded_by_user_id = ?
         LIMIT 1",
        [$resourceId, $ownerUserId]
    );
}

function resources_find_with_context(int $resourceId): ?array
{
    return db_fetch(
        "SELECT r.*,
                t.id AS topic_id,
                t.title AS topic_title,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name
         FROM resources r
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         WHERE r.id = ?
         LIMIT 1",
        [$resourceId]
    );
}

function resources_find_published_with_context(int $resourceId): ?array
{
    return db_fetch(
        "SELECT r.*,
                t.id AS topic_id,
                t.title AS topic_title,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name,
                u.name AS uploader_name,
                COALESCE(rr.average_rating, 0) AS average_rating,
                COALESCE(rr.rating_count, 0) AS rating_count,
                COALESCE(cc.comment_count, 0) AS comment_count
         FROM resources r
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         LEFT JOIN users u ON u.id = r.uploaded_by_user_id
         LEFT JOIN (
            SELECT resource_id,
                   ROUND(AVG(rating), 2) AS average_rating,
                   COUNT(*) AS rating_count
            FROM resource_ratings
            GROUP BY resource_id
         ) rr ON rr.resource_id = r.id
         LEFT JOIN (
            SELECT target_id AS resource_id,
                   COUNT(*) AS comment_count
            FROM comments
            WHERE target_type = 'resource'
            GROUP BY target_id
         ) cc ON cc.resource_id = r.id
         WHERE r.id = ?
           AND r.status = 'published'
         LIMIT 1",
        [$resourceId]
    );
}

function resources_find_student_rating(int $resourceId, int $studentUserId): ?int
{
    $row = db_fetch(
        'SELECT rating FROM resource_ratings WHERE resource_id = ? AND student_user_id = ? LIMIT 1',
        [$resourceId, $studentUserId]
    );

    if (!$row) {
        return null;
    }

    return (int) ($row['rating'] ?? 0);
}

function resources_upsert_student_rating(int $resourceId, int $studentUserId, int $rating): void
{
    db_query(
        "INSERT INTO resource_ratings (resource_id, student_user_id, rating)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
             rating = VALUES(rating),
             updated_at = CURRENT_TIMESTAMP",
        [$resourceId, $studentUserId, $rating]
    );
}

function resources_rating_distribution(int $resourceId): array
{
    $rows = db_fetch_all(
        "SELECT rating, COUNT(*) AS cnt
         FROM resource_ratings
         WHERE resource_id = ?
         GROUP BY rating",
        [$resourceId]
    );

    $distribution = [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0,
    ];

    foreach ($rows as $row) {
        $rating = (int) ($row['rating'] ?? 0);
        if ($rating >= 1 && $rating <= 5) {
            $distribution[$rating] = (int) ($row['cnt'] ?? 0);
        }
    }

    return $distribution;
}

function resources_all_ids_in_topic(int $topicId): array
{
    $rows = db_fetch_all(
        'SELECT id FROM resources WHERE topic_id = ?',
        [$topicId]
    );

    return array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
}

function resources_all_ids_in_subject(int $subjectId): array
{
    $rows = db_fetch_all(
        "SELECT r.id
         FROM resources r
         INNER JOIN topics t ON t.id = r.topic_id
         WHERE t.subject_id = ?",
        [$subjectId]
    );

    return array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
}

function resources_all_ids_in_batch(int $batchId): array
{
    $rows = db_fetch_all(
        "SELECT r.id
         FROM resources r
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         WHERE s.batch_id = ?",
        [$batchId]
    );

    return array_values(array_map(static fn(array $row): int => (int) $row['id'], $rows));
}

function resources_delete_comments_for_resource_ids(array $resourceIds): int
{
    return comments_delete_for_target_ids('resource', $resourceIds);
}

function resources_delete_comments_for_topic(int $topicId): int
{
    return resources_delete_comments_for_resource_ids(resources_all_ids_in_topic($topicId));
}

function resources_delete_comments_for_subject(int $subjectId): int
{
    return resources_delete_comments_for_resource_ids(resources_all_ids_in_subject($subjectId));
}

function resources_delete_comments_for_batch(int $batchId): int
{
    return resources_delete_comments_for_resource_ids(resources_all_ids_in_batch($batchId));
}

function resources_create(array $data): string
{
    return db_insert('resources', [
        'topic_id' => (int) $data['topic_id'],
        'uploaded_by_user_id' => $data['uploaded_by_user_id'] ?? auth_id(),
        'title' => $data['title'],
        'description' => $data['description'] ?? null,
        'category' => $data['category'],
        'category_other' => $data['category_other'] ?? null,
        'source_type' => $data['source_type'],
        'file_path' => $data['file_path'] ?? null,
        'file_name' => $data['file_name'] ?? null,
        'file_mime' => $data['file_mime'] ?? null,
        'file_size' => $data['file_size'] ?? null,
        'external_url' => $data['external_url'] ?? null,
        'status' => $data['status'] ?? 'pending',
        'rejection_reason' => $data['rejection_reason'] ?? null,
        'reviewed_by_user_id' => $data['reviewed_by_user_id'] ?? null,
        'reviewed_at' => $data['reviewed_at'] ?? null,
    ]);
}

function resources_update_owned_resource(int $resourceId, int $ownerUserId, array $data, string $status): int
{
    return db_query(
        "UPDATE resources
         SET title = ?,
             description = ?,
             category = ?,
             category_other = ?,
             source_type = ?,
             file_path = ?,
             file_name = ?,
             file_mime = ?,
             file_size = ?,
             external_url = ?,
             status = ?,
             rejection_reason = NULL,
             reviewed_by_user_id = NULL,
             reviewed_at = NULL
         WHERE id = ?
           AND uploaded_by_user_id = ?",
        [
            $data['title'],
            $data['description'] ?? null,
            $data['category'],
            $data['category_other'] ?? null,
            $data['source_type'],
            $data['file_path'] ?? null,
            $data['file_name'] ?? null,
            $data['file_mime'] ?? null,
            $data['file_size'] ?? null,
            $data['external_url'] ?? null,
            $status,
            $resourceId,
            $ownerUserId,
        ]
    )->rowCount();
}

function resources_find_update_request_by_resource(int $resourceId): ?array
{
    return db_fetch(
        'SELECT * FROM resource_update_requests WHERE resource_id = ? LIMIT 1',
        [$resourceId]
    );
}

function resources_upsert_update_request(int $resourceId, int $requestedByUserId, array $data): void
{
    db_query(
        "INSERT INTO resource_update_requests (
            resource_id,
            requested_by_user_id,
            title,
            description,
            category,
            category_other,
            source_type,
            file_path,
            file_name,
            file_mime,
            file_size,
            external_url,
            status,
            rejection_reason,
            reviewed_by_user_id,
            reviewed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL, NULL, NULL)
        ON DUPLICATE KEY UPDATE
            requested_by_user_id = VALUES(requested_by_user_id),
            title = VALUES(title),
            description = VALUES(description),
            category = VALUES(category),
            category_other = VALUES(category_other),
            source_type = VALUES(source_type),
            file_path = VALUES(file_path),
            file_name = VALUES(file_name),
            file_mime = VALUES(file_mime),
            file_size = VALUES(file_size),
            external_url = VALUES(external_url),
            status = 'pending',
            rejection_reason = NULL,
            reviewed_by_user_id = NULL,
            reviewed_at = NULL",
        [
            $resourceId,
            $requestedByUserId,
            $data['title'],
            $data['description'] ?? null,
            $data['category'],
            $data['category_other'] ?? null,
            $data['source_type'],
            $data['file_path'] ?? null,
            $data['file_name'] ?? null,
            $data['file_mime'] ?? null,
            $data['file_size'] ?? null,
            $data['external_url'] ?? null,
        ]
    );
}

function resources_delete_update_request_for_resource(int $resourceId): ?array
{
    $existing = resources_find_update_request_by_resource($resourceId);
    if (!$existing) {
        return null;
    }

    db_query('DELETE FROM resource_update_requests WHERE resource_id = ?', [$resourceId]);
    return $existing;
}

function resources_delete_owned(int $resourceId, int $ownerUserId): ?array
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $resource = db_fetch(
            "SELECT r.id,
                    r.file_path,
                    ur.file_path AS update_file_path
             FROM resources r
             LEFT JOIN resource_update_requests ur ON ur.resource_id = r.id
             WHERE r.id = ?
               AND r.uploaded_by_user_id = ?
             LIMIT 1
             FOR UPDATE",
            [$resourceId, $ownerUserId]
        );

        if (!$resource) {
            $pdo->rollBack();
            return null;
        }

        resources_delete_comments_for_resource_ids([$resourceId]);

        $deleted = db_query(
            'DELETE FROM resources WHERE id = ? AND uploaded_by_user_id = ?',
            [$resourceId, $ownerUserId]
        )->rowCount();

        if ($deleted < 1) {
            $pdo->rollBack();
            return null;
        }

        $pdo->commit();

        return [
            'file_path' => $resource['file_path'] ?? null,
            'update_file_path' => $resource['update_file_path'] ?? null,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function resources_coordinator_pending_create_requests(int $coordinatorUserId): array
{
    return db_fetch_all(
        "SELECT r.id AS resource_id,
                r.title,
                r.description,
                r.category,
                r.category_other,
                r.source_type,
                r.file_name,
                r.external_url,
                r.created_at,
                u.id AS uploader_id,
                u.name AS uploader_name,
                u.email AS uploader_email,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name,
                t.id AS topic_id,
                t.title AS topic_title
         FROM resources r
         INNER JOIN users u ON u.id = r.uploaded_by_user_id
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         INNER JOIN subject_coordinators sc ON sc.subject_id = s.id
         WHERE sc.student_user_id = ?
           AND r.status = 'pending'
           AND u.role = 'student'
         ORDER BY r.created_at ASC, r.id ASC",
        [$coordinatorUserId]
    );
}

function resources_coordinator_pending_update_requests(int $coordinatorUserId): array
{
    return db_fetch_all(
        "SELECT ur.id AS update_request_id,
                ur.resource_id,
                ur.title,
                ur.description,
                ur.category,
                ur.category_other,
                ur.source_type,
                ur.file_name,
                ur.external_url,
                ur.created_at,
                ur.updated_at,
                r.title AS current_title,
                r.description AS current_description,
                r.category AS current_category,
                r.category_other AS current_category_other,
                r.source_type AS current_source_type,
                r.file_name AS current_file_name,
                r.external_url AS current_external_url,
                u.id AS requester_id,
                u.name AS requester_name,
                u.email AS requester_email,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name,
                t.id AS topic_id,
                t.title AS topic_title
         FROM resource_update_requests ur
         INNER JOIN resources r ON r.id = ur.resource_id
         INNER JOIN users u ON u.id = ur.requested_by_user_id
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         INNER JOIN subject_coordinators sc ON sc.subject_id = s.id
         WHERE sc.student_user_id = ?
           AND ur.status = 'pending'
           AND u.role = 'student'
           AND r.status = 'published'
         ORDER BY ur.created_at ASC, ur.id ASC",
        [$coordinatorUserId]
    );
}

function resources_coordinator_pending_count(int $coordinatorUserId): int
{
    $row = db_fetch(
        "SELECT
            (
                SELECT COUNT(*)
                FROM resources r
                INNER JOIN users u ON u.id = r.uploaded_by_user_id
                INNER JOIN topics t ON t.id = r.topic_id
                INNER JOIN subjects s ON s.id = t.subject_id
                INNER JOIN subject_coordinators sc ON sc.subject_id = s.id
                WHERE sc.student_user_id = ?
                  AND r.status = 'pending'
                  AND u.role = 'student'
            ) AS pending_create_count,
            (
                SELECT COUNT(*)
                FROM resource_update_requests ur
                INNER JOIN resources r ON r.id = ur.resource_id
                INNER JOIN users u ON u.id = ur.requested_by_user_id
                INNER JOIN topics t ON t.id = r.topic_id
                INNER JOIN subjects s ON s.id = t.subject_id
                INNER JOIN subject_coordinators sc ON sc.subject_id = s.id
                WHERE sc.student_user_id = ?
                  AND ur.status = 'pending'
                  AND u.role = 'student'
                  AND r.status = 'published'
            ) AS pending_update_count",
        [$coordinatorUserId, $coordinatorUserId]
    );

    if (!$row) {
        return 0;
    }

    return (int) ($row['pending_create_count'] ?? 0) + (int) ($row['pending_update_count'] ?? 0);
}

function resources_find_pending_create_for_coordinator(int $resourceId, int $coordinatorUserId): ?array
{
    return db_fetch(
        "SELECT r.id AS resource_id,
                r.file_path,
                t.id AS topic_id,
                s.id AS subject_id
         FROM resources r
         INNER JOIN users u ON u.id = r.uploaded_by_user_id
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         INNER JOIN subject_coordinators sc ON sc.subject_id = s.id
         WHERE sc.student_user_id = ?
           AND r.id = ?
           AND r.status = 'pending'
           AND u.role = 'student'
         LIMIT 1",
        [$coordinatorUserId, $resourceId]
    );
}

function resources_find_pending_update_for_coordinator(int $updateRequestId, int $coordinatorUserId): ?array
{
    return db_fetch(
        "SELECT ur.id AS update_request_id,
                ur.resource_id,
                ur.file_path,
                r.file_path AS resource_file_path,
                t.id AS topic_id,
                s.id AS subject_id
         FROM resource_update_requests ur
         INNER JOIN resources r ON r.id = ur.resource_id
         INNER JOIN users u ON u.id = ur.requested_by_user_id
         INNER JOIN topics t ON t.id = r.topic_id
         INNER JOIN subjects s ON s.id = t.subject_id
         INNER JOIN subject_coordinators sc ON sc.subject_id = s.id
         WHERE sc.student_user_id = ?
           AND ur.id = ?
           AND ur.status = 'pending'
           AND u.role = 'student'
           AND r.status = 'published'
         LIMIT 1",
        [$coordinatorUserId, $updateRequestId]
    );
}

function resources_mark_create_approved(int $resourceId, int $reviewerUserId): bool
{
    $updated = db_query(
        "UPDATE resources
         SET status = 'published',
             rejection_reason = NULL,
             reviewed_by_user_id = ?,
             reviewed_at = NOW()
         WHERE id = ?
           AND status = 'pending'",
        [$reviewerUserId, $resourceId]
    )->rowCount();

    return $updated > 0;
}

function resources_mark_create_rejected(int $resourceId, int $reviewerUserId, string $reason): bool
{
    $updated = db_query(
        "UPDATE resources
         SET status = 'rejected',
             rejection_reason = ?,
             reviewed_by_user_id = ?,
             reviewed_at = NOW()
         WHERE id = ?
           AND status = 'pending'",
        [$reason, $reviewerUserId, $resourceId]
    )->rowCount();

    return $updated > 0;
}

function resources_apply_pending_update_approval(int $updateRequestId, int $reviewerUserId): ?array
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $request = db_fetch(
            "SELECT ur.*,
                    r.file_path AS current_file_path
             FROM resource_update_requests ur
             INNER JOIN resources r ON r.id = ur.resource_id
             WHERE ur.id = ?
               AND ur.status = 'pending'
             FOR UPDATE",
            [$updateRequestId]
        );

        if (!$request) {
            $pdo->rollBack();
            return null;
        }

        db_query(
            "UPDATE resources
             SET title = ?,
                 description = ?,
                 category = ?,
                 category_other = ?,
                 source_type = ?,
                 file_path = ?,
                 file_name = ?,
                 file_mime = ?,
                 file_size = ?,
                 external_url = ?,
                 status = 'published',
                 rejection_reason = NULL,
                 reviewed_by_user_id = ?,
                 reviewed_at = NOW()
             WHERE id = ?",
            [
                $request['title'],
                $request['description'] ?? null,
                $request['category'],
                $request['category_other'] ?? null,
                $request['source_type'],
                $request['file_path'] ?? null,
                $request['file_name'] ?? null,
                $request['file_mime'] ?? null,
                $request['file_size'] ?? null,
                $request['external_url'] ?? null,
                $reviewerUserId,
                (int) $request['resource_id'],
            ]
        );

        db_query('DELETE FROM resource_update_requests WHERE id = ?', [$updateRequestId]);

        $pdo->commit();

        return [
            'old_file_path' => $request['current_file_path'] ?? null,
            'new_file_path' => $request['file_path'] ?? null,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function resources_mark_update_rejected(int $updateRequestId, int $reviewerUserId, string $reason): bool
{
    $updated = db_query(
        "UPDATE resource_update_requests
         SET status = 'rejected',
             rejection_reason = ?,
             reviewed_by_user_id = ?,
             reviewed_at = NOW()
         WHERE id = ?
           AND status = 'pending'",
        [$reason, $reviewerUserId, $updateRequestId]
    )->rowCount();

    return $updated > 0;
}
