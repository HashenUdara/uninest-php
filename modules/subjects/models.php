<?php

/**
 * Subjects Module — Models
 */

function subjects_all_admin(): array
{
    return db_fetch_all(
        "SELECT s.*, b.batch_code, b.name AS batch_name, u.name AS creator_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subjects s
         INNER JOIN batches b ON b.id = s.batch_id
         LEFT JOIN users u ON u.id = s.created_by
         ORDER BY s.name ASC"
    );
}

function subjects_all_for_batch(int $batchId): array
{
    return db_fetch_all(
        "SELECT s.*, b.batch_code, b.name AS batch_name, u.name AS creator_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subjects s
         INNER JOIN batches b ON b.id = s.batch_id
         LEFT JOIN users u ON u.id = s.created_by
         WHERE s.batch_id = ?
         ORDER BY s.name ASC",
        [$batchId]
    );
}

function subjects_year_options_for_batch(int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    $rows = db_fetch_all(
        "SELECT DISTINCT academic_year
         FROM subjects
         WHERE batch_id = ?
         ORDER BY academic_year DESC",
        [$batchId]
    );

    return array_map(
        static fn(array $row): int => (int) $row['academic_year'],
        $rows
    );
}

function subjects_semester_options_for_batch(int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    $rows = db_fetch_all(
        "SELECT DISTINCT semester
         FROM subjects
         WHERE batch_id = ?
         ORDER BY semester ASC",
        [$batchId]
    );

    return array_map(
        static fn(array $row): int => (int) $row['semester'],
        $rows
    );
}

function subjects_latest_term_for_batch(int $batchId): ?array
{
    if ($batchId <= 0) {
        return null;
    }

    $row = db_fetch(
        "SELECT academic_year, semester
         FROM subjects
         WHERE batch_id = ?
         ORDER BY academic_year DESC, semester DESC
         LIMIT 1",
        [$batchId]
    );

    if (!$row) {
        return null;
    }

    return [
        'academic_year' => (int) $row['academic_year'],
        'semester' => (int) $row['semester'],
    ];
}

function subjects_terms_for_batch(int $batchId): array
{
    if ($batchId <= 0) {
        return [];
    }

    return db_fetch_all(
        "SELECT DISTINCT academic_year, semester
         FROM subjects
         WHERE batch_id = ?
         ORDER BY academic_year DESC, semester DESC",
        [$batchId]
    );
}

function subjects_student_filters_sql(array $filters, array &$params): string
{
    $conditions = [];

    $year = isset($filters['year']) ? (int) $filters['year'] : 0;
    if ($year > 0) {
        $conditions[] = 's.academic_year = ?';
        $params[] = $year;
    }

    $semester = isset($filters['semester']) ? (int) $filters['semester'] : 0;
    if ($semester > 0) {
        $conditions[] = 's.semester = ?';
        $params[] = $semester;
    }

    $query = trim((string) ($filters['q'] ?? ''));
    if ($query !== '') {
        $conditions[] = '(s.code LIKE ? OR s.name LIKE ?)';
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
    }

    if (empty($conditions)) {
        return '';
    }

    return ' AND ' . implode(' AND ', $conditions);
}

function subjects_student_list_for_batch(int $batchId, array $filters, int $limit, int $offset): array
{
    if ($batchId <= 0) {
        return [];
    }

    $params = [$batchId];
    $filtersSql = subjects_student_filters_sql($filters, $params);
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    return db_fetch_all(
        "SELECT s.*, b.batch_code, b.name AS batch_name, u.name AS creator_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subjects s
         INNER JOIN batches b ON b.id = s.batch_id
         LEFT JOIN users u ON u.id = s.created_by
         WHERE s.batch_id = ?{$filtersSql}
         ORDER BY s.academic_year DESC, s.semester DESC, s.code ASC, s.name ASC
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
}

function subjects_student_count_for_batch(int $batchId, array $filters): int
{
    if ($batchId <= 0) {
        return 0;
    }

    $params = [$batchId];
    $filtersSql = subjects_student_filters_sql($filters, $params);

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM subjects s
         WHERE s.batch_id = ?{$filtersSql}",
        $params
    );

    return (int) ($row['cnt'] ?? 0);
}

function subjects_all_for_coordinator(int $coordinatorUserId): array
{
    return db_fetch_all(
        "SELECT s.*, b.batch_code, b.name AS batch_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subject_coordinators sc_self
         INNER JOIN subjects s ON s.id = sc_self.subject_id
         INNER JOIN batches b ON b.id = s.batch_id
         WHERE sc_self.student_user_id = ?
         ORDER BY s.name ASC",
        [$coordinatorUserId]
    );
}

function subjects_find_admin(int $id): ?array
{
    return db_fetch(
        "SELECT s.*, b.batch_code, b.name AS batch_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subjects s
         INNER JOIN batches b ON b.id = s.batch_id
         WHERE s.id = ?",
        [$id]
    );
}

function subjects_find_for_batch(int $id, int $batchId): ?array
{
    return db_fetch(
        "SELECT s.*, b.batch_code, b.name AS batch_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subjects s
         INNER JOIN batches b ON b.id = s.batch_id
         WHERE s.id = ? AND s.batch_id = ?",
        [$id, $batchId]
    );
}

function subjects_find_for_coordinator(int $id, int $coordinatorUserId): ?array
{
    return db_fetch(
        "SELECT s.*, b.batch_code, b.name AS batch_name,
                (SELECT COUNT(*) FROM subject_coordinators sc WHERE sc.subject_id = s.id) AS coordinators_count
         FROM subject_coordinators sc_self
         INNER JOIN subjects s ON s.id = sc_self.subject_id
         INNER JOIN batches b ON b.id = s.batch_id
         WHERE s.id = ? AND sc_self.student_user_id = ?",
        [$id, $coordinatorUserId]
    );
}

function subjects_code_exists_in_batch(string $code, int $batchId, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        return (bool) db_fetch(
            'SELECT id FROM subjects WHERE code = ? AND batch_id = ? AND id != ?',
            [$code, $batchId, $excludeId]
        );
    }

    return (bool) db_fetch('SELECT id FROM subjects WHERE code = ? AND batch_id = ?', [$code, $batchId]);
}

function subjects_create(array $data): string
{
    return db_insert('subjects', [
        'batch_id' => (int) $data['batch_id'],
        'code' => $data['code'],
        'name' => $data['name'],
        'description' => $data['description'] ?? '',
        'credits' => (int) ($data['credits'] ?? 3),
        'academic_year' => (int) ($data['academic_year'] ?? 1),
        'semester' => (int) ($data['semester'] ?? 1),
        'created_by' => $data['created_by'] ?? auth_id(),
    ]);
}

function subjects_update_data(int $id, array $data): int
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $subject = db_fetch('SELECT id, batch_id FROM subjects WHERE id = ? FOR UPDATE', [$id]);
        if (!$subject) {
            $pdo->rollBack();
            return 0;
        }

        $currentBatchId = (int) $subject['batch_id'];
        $targetBatchId = (int) $data['batch_id'];

        $removedCoordinatorIds = [];
        if ($currentBatchId !== $targetBatchId) {
            $removedCoordinatorIds = array_map(
                static fn(array $row): int => (int) $row['student_user_id'],
                db_fetch_all('SELECT student_user_id FROM subject_coordinators WHERE subject_id = ?', [$id])
            );

            db_query('DELETE FROM subject_coordinators WHERE subject_id = ?', [$id]);
        }

        $updated = db_query(
            "UPDATE subjects
             SET code = ?,
                 name = ?,
                 description = ?,
                 credits = ?,
                 academic_year = ?,
                 semester = ?,
                 batch_id = ?
             WHERE id = ?",
            [
                $data['code'],
                $data['name'],
                $data['description'] ?? '',
                (int) ($data['credits'] ?? 3),
                (int) ($data['academic_year'] ?? 1),
                (int) ($data['semester'] ?? 1),
                $targetBatchId,
                $id,
            ]
        )->rowCount();

        foreach (array_values(array_unique($removedCoordinatorIds)) as $userId) {
            subjects_sync_user_role_for_assignments((int) $userId);
        }

        $pdo->commit();
        return $updated;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function subjects_delete_by_id(int $id): int
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        resources_delete_comments_for_subject($id);

        $coordinatorIds = array_map(
            static fn(array $row): int => (int) $row['student_user_id'],
            db_fetch_all('SELECT student_user_id FROM subject_coordinators WHERE subject_id = ?', [$id])
        );

        $deleted = db_query('DELETE FROM subjects WHERE id = ?', [$id])->rowCount();

        foreach (array_values(array_unique($coordinatorIds)) as $userId) {
            subjects_sync_user_role_for_assignments((int) $userId);
        }

        $pdo->commit();
        return $deleted;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function subjects_coordinators_for_subject(int $subjectId): array
{
    return db_fetch_all(
        "SELECT u.id, u.name, u.email, u.academic_year, u.role, sc.created_at
         FROM subject_coordinators sc
         INNER JOIN users u ON u.id = sc.student_user_id
         WHERE sc.subject_id = ?
           AND u.role IN ('student', 'coordinator')
         ORDER BY u.name ASC",
        [$subjectId]
    );
}

function subjects_coordinator_candidates_for_subject(int $subjectId): array
{
    return db_fetch_all(
        "SELECT u.id, u.name, u.email, u.academic_year, u.role,
                CASE WHEN sc.student_user_id IS NULL THEN 0 ELSE 1 END AS is_assigned
         FROM subjects s
         INNER JOIN users u ON u.batch_id = s.batch_id
         LEFT JOIN subject_coordinators sc
                ON sc.subject_id = s.id
               AND sc.student_user_id = u.id
         WHERE s.id = ?
           AND u.role IN ('student', 'coordinator')
         ORDER BY u.name ASC",
        [$subjectId]
    );
}

function subjects_assign_coordinator(int $subjectId, int $studentUserId, int $assignedBy): string
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $subject = db_fetch('SELECT id, batch_id FROM subjects WHERE id = ? FOR UPDATE', [$subjectId]);
        if (!$subject) {
            $pdo->rollBack();
            return 'subject_not_found';
        }

        $student = db_fetch(
            'SELECT id, role, batch_id FROM users WHERE id = ? FOR UPDATE',
            [$studentUserId]
        );

        if (
            !$student
            || !in_array((string) $student['role'], ['student', 'coordinator'], true)
            || (int) ($student['batch_id'] ?? 0) !== (int) $subject['batch_id']
        ) {
            $pdo->rollBack();
            return 'invalid_student';
        }

        $inserted = db_query(
            "INSERT INTO subject_coordinators (subject_id, student_user_id, assigned_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by)",
            [$subjectId, $studentUserId, $assignedBy]
        )->rowCount();

        subjects_sync_user_role_for_assignments($studentUserId);

        $pdo->commit();
        return $inserted > 0 ? 'assigned' : 'exists';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function subjects_unassign_coordinator(int $subjectId, int $studentUserId): string
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $deleted = db_query(
            'DELETE FROM subject_coordinators WHERE subject_id = ? AND student_user_id = ?',
            [$subjectId, $studentUserId]
        )->rowCount();

        subjects_sync_user_role_for_assignments($studentUserId);

        $pdo->commit();
        return $deleted > 0 ? 'removed' : 'not_found';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function subjects_user_has_coordinator_assignments(int $userId): bool
{
    return (bool) db_fetch(
        'SELECT id FROM subject_coordinators WHERE student_user_id = ? LIMIT 1',
        [$userId]
    );
}

function subjects_sync_user_role_for_assignments(int $userId): void
{
    $user = db_fetch('SELECT id, role FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$user) {
        return;
    }

    $currentRole = (string) ($user['role'] ?? '');
    if ($currentRole === 'admin' || $currentRole === 'moderator') {
        return;
    }

    $targetRole = subjects_user_has_coordinator_assignments($userId) ? 'coordinator' : 'student';
    if ($currentRole === $targetRole) {
        return;
    }

    db_query('UPDATE users SET role = ? WHERE id = ?', [$targetRole, $userId]);
}

function subjects_remove_student_coordinator_assignments(int $studentUserId): int
{
    $deleted = db_query(
        'DELETE FROM subject_coordinators WHERE student_user_id = ?',
        [$studentUserId]
    )->rowCount();

    subjects_sync_user_role_for_assignments($studentUserId);
    return $deleted;
}

function subjects_remove_student_coordinator_assignments_for_batch(int $studentUserId, int $batchId): int
{
    $deleted = db_query(
        "DELETE sc
         FROM subject_coordinators sc
         INNER JOIN subjects s ON s.id = sc.subject_id
         WHERE sc.student_user_id = ?
           AND s.batch_id = ?",
        [$studentUserId, $batchId]
    )->rowCount();

    subjects_sync_user_role_for_assignments($studentUserId);
    return $deleted;
}
