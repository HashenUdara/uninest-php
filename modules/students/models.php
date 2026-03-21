<?php

/**
 * Students Module — Models
 */

function students_admin_all(): array
{
    return db_fetch_all(
        "SELECT u.id, u.name, u.email, u.academic_year, u.university_id, u.batch_id, u.first_approved_batch_id, u.created_at,
                uni.name AS university_name,
                b.name AS batch_name, b.batch_code,
                lb.name AS locked_batch_name, lb.batch_code AS locked_batch_code
         FROM users u
         LEFT JOIN universities uni ON uni.id = u.university_id
         LEFT JOIN batches b ON b.id = u.batch_id
         LEFT JOIN batches lb ON lb.id = u.first_approved_batch_id
         WHERE u.role = 'student'
         ORDER BY u.created_at DESC"
    );
}

function students_moderator_batch_all(int $moderatorUserId): array
{
    return db_fetch_all(
        "SELECT u.id, u.name, u.email, u.academic_year, u.batch_id, u.first_approved_batch_id, u.created_at,
                uni.name AS university_name,
                b.name AS batch_name, b.batch_code,
                lb.name AS locked_batch_name, lb.batch_code AS locked_batch_code
         FROM users u
         INNER JOIN batches b ON b.id = u.batch_id
         LEFT JOIN universities uni ON uni.id = u.university_id
         LEFT JOIN batches lb ON lb.id = u.first_approved_batch_id
         WHERE u.role = 'student' AND b.moderator_user_id = ?
         ORDER BY u.created_at DESC",
        [$moderatorUserId]
    );
}

function students_find_admin(int $studentId): ?array
{
    return db_fetch(
        "SELECT u.id, u.name, u.email, u.academic_year, u.university_id, u.batch_id, u.first_approved_batch_id,
                uni.name AS university_name,
                b.name AS batch_name, b.batch_code,
                lb.name AS locked_batch_name, lb.batch_code AS locked_batch_code
         FROM users u
         LEFT JOIN universities uni ON uni.id = u.university_id
         LEFT JOIN batches b ON b.id = u.batch_id
         LEFT JOIN batches lb ON lb.id = u.first_approved_batch_id
         WHERE u.id = ? AND u.role = 'student'",
        [$studentId]
    );
}

function students_find_for_moderator_batch(int $studentId, int $moderatorUserId): ?array
{
    return db_fetch(
        "SELECT u.id, u.name, u.email, u.academic_year, u.university_id, u.batch_id, u.first_approved_batch_id,
                uni.name AS university_name,
                b.name AS batch_name, b.batch_code
         FROM users u
         INNER JOIN batches b ON b.id = u.batch_id
         LEFT JOIN universities uni ON uni.id = u.university_id
         WHERE u.id = ? AND u.role = 'student' AND b.moderator_user_id = ?",
        [$studentId, $moderatorUserId]
    );
}

function students_email_exists(string $email, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        return (bool) db_fetch('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $excludeId]);
    }

    return (bool) db_fetch('SELECT id FROM users WHERE email = ?', [$email]);
}

function students_find_request_by_student_id(int $studentId): ?array
{
    return db_fetch(
        'SELECT id, student_user_id FROM student_batch_requests WHERE student_user_id = ? LIMIT 1',
        [$studentId]
    );
}

function students_sync_request_status(
    int $studentId,
    int $batchId,
    string $status,
    int $reviewerId,
    string $reviewerRole,
    ?string $reason = null
): void {
    $existing = students_find_request_by_student_id($studentId);

    if ($existing) {
        db_query(
            "UPDATE student_batch_requests
             SET requested_batch_id = ?,
                 status = ?,
                 rejection_reason = ?,
                 reviewed_by = ?,
                 reviewed_role = ?,
                 reviewed_at = NOW()
             WHERE student_user_id = ?",
            [$batchId, $status, $reason, $reviewerId, $reviewerRole, $studentId]
        );

        return;
    }

    db_insert('student_batch_requests', [
        'student_user_id'    => $studentId,
        'requested_batch_id' => $batchId,
        'status'             => $status,
        'rejection_reason'   => $reason,
        'reviewed_by'        => $reviewerId,
        'reviewed_role'      => $reviewerRole,
        'reviewed_at'        => date('Y-m-d H:i:s'),
    ]);
}

function students_create_admin(array $data, int $adminUserId): int
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $batchId = (int) $data['batch_id'];

        $studentId = (int) db_insert('users', [
            'name'                    => $data['name'],
            'email'                   => $data['email'],
            'password'                => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'                    => 'student',
            'academic_year'           => (int) $data['academic_year'],
            'university_id'           => (int) $data['university_id'],
            'batch_id'                => $batchId,
            'first_approved_batch_id' => $batchId,
        ]);

        students_sync_request_status($studentId, $batchId, 'approved', $adminUserId, 'admin');

        $pdo->commit();
        return $studentId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function students_update_admin(int $studentId, array $data, int $adminUserId): bool
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $student = db_fetch(
            'SELECT id, batch_id, first_approved_batch_id FROM users WHERE id = ? AND role = ? FOR UPDATE',
            [$studentId, 'student']
        );

        if (!$student) {
            $pdo->rollBack();
            return false;
        }

        $newBatchId = (int) $data['batch_id'];
        $lockedBatchId = (int) ($student['first_approved_batch_id'] ?? 0);
        if ($lockedBatchId > 0 && $lockedBatchId !== $newBatchId) {
            $pdo->rollBack();
            return false;
        }

        db_query(
            'UPDATE users
             SET name = ?,
                 email = ?,
                 academic_year = ?,
                 university_id = ?,
                 batch_id = ?,
                 first_approved_batch_id = COALESCE(first_approved_batch_id, ?)
             WHERE id = ? AND role = ?',
            [
                $data['name'],
                $data['email'],
                (int) $data['academic_year'],
                (int) $data['university_id'],
                $newBatchId,
                $newBatchId,
                $studentId,
                'student',
            ]
        );

        students_sync_request_status($studentId, $newBatchId, 'approved', $adminUserId, 'admin');

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function students_delete_admin(int $studentId): int
{
    return db_query('DELETE FROM users WHERE id = ? AND role = ?', [$studentId, 'student'])->rowCount();
}

function students_moderator_remove_from_batch(int $studentId, int $moderatorUserId): bool
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $student = db_fetch(
            "SELECT u.id, u.batch_id, u.first_approved_batch_id
             FROM users u
             INNER JOIN batches b ON b.id = u.batch_id
             WHERE u.id = ? AND u.role = 'student' AND b.moderator_user_id = ?
             FOR UPDATE",
            [$studentId, $moderatorUserId]
        );

        if (!$student) {
            $pdo->rollBack();
            return false;
        }

        $currentBatchId = (int) ($student['batch_id'] ?? 0);
        $lockedBatchId = (int) ($student['first_approved_batch_id'] ?? 0);
        $targetBatchId = $lockedBatchId > 0 ? $lockedBatchId : $currentBatchId;

        if ($targetBatchId <= 0) {
            $pdo->rollBack();
            return false;
        }

        db_query(
            'UPDATE users
             SET batch_id = NULL,
                 first_approved_batch_id = COALESCE(first_approved_batch_id, ?)
             WHERE id = ? AND role = ?',
            [$targetBatchId, $studentId, 'student']
        );

        students_sync_request_status(
            $studentId,
            $targetBatchId,
            'rejected',
            $moderatorUserId,
            'moderator',
            'Removed from your batch by moderator. Reapply using your original batch ID.'
        );

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
