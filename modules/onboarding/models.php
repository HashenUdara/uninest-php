<?php

/**
 * Onboarding Module — Models
 */

function onboarding_complete_for_user(array $user): bool
{
    $role = $user['role'] ?? 'student';

    if ($role === 'admin') {
        return true;
    }

    if ($role !== 'student' && $role !== 'moderator') {
        return true;
    }

    $batchId = (int) ($user['batch_id'] ?? 0);
    if ($batchId <= 0) {
        return false;
    }

    $batch = onboarding_batch_by_id($batchId);
    return (bool) ($batch && $batch['status'] === 'approved');
}

function onboarding_batch_by_id(int $batchId): ?array
{
    return db_fetch('SELECT * FROM batches WHERE id = ?', [$batchId]);
}

function universities_active(): array
{
    return db_fetch_all('SELECT id, name, short_code FROM universities WHERE is_active = 1 ORDER BY name ASC');
}

function university_is_active(int $universityId): bool
{
    return (bool) db_fetch('SELECT id FROM universities WHERE id = ? AND is_active = 1', [$universityId]);
}

function onboarding_find_batch_by_code(string $batchCode): ?array
{
    return db_fetch(
        "SELECT b.*, u.name AS university_name
         FROM batches b
         LEFT JOIN universities u ON u.id = b.university_id
         WHERE b.batch_code = ? AND b.status = 'approved'",
        [$batchCode]
    );
}

function onboarding_find_moderator_batch(int $moderatorUserId): ?array
{
    return db_fetch(
        "SELECT b.*, u.name AS university_name, rv.name AS reviewed_by_name
         FROM batches b
         LEFT JOIN universities u ON u.id = b.university_id
         LEFT JOIN users rv ON rv.id = b.reviewed_by
         WHERE b.moderator_user_id = ?
         LIMIT 1",
        [$moderatorUserId]
    );
}

function onboarding_create_moderator_batch_request(array $data): string
{
    return db_insert('batches', [
        'batch_code'        => null,
        'name'              => $data['name'],
        'program'           => $data['program'],
        'intake_year'       => (int) $data['intake_year'],
        'university_id'     => (int) $data['university_id'],
        'moderator_user_id' => (int) $data['moderator_user_id'],
        'status'            => 'pending',
        'rejection_reason'  => null,
        'reviewed_by'       => null,
        'reviewed_at'       => null,
    ]);
}

function onboarding_resubmit_moderator_batch_request(int $moderatorUserId, array $data): int
{
    return db_update('batches', [
        'name'             => $data['name'],
        'program'          => $data['program'],
        'intake_year'      => (int) $data['intake_year'],
        'university_id'    => (int) $data['university_id'],
        'status'           => 'pending',
        'rejection_reason' => null,
        'reviewed_by'      => null,
        'reviewed_at'      => null,
    ], ['moderator_user_id' => $moderatorUserId]);
}

function onboarding_batch_code_exists(string $code): bool
{
    return (bool) db_fetch('SELECT id FROM batches WHERE batch_code = ?', [$code]);
}

function onboarding_generate_batch_code(int $maxAttempts = 20): string
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $candidate = 'BATCH-' . strtoupper(bin2hex(random_bytes(3))); // 6 chars
        if (!onboarding_batch_code_exists($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Failed to generate unique batch code.');
}

function onboarding_admin_batch_requests(): array
{
    return db_fetch_all(
        "SELECT b.*, m.name AS moderator_name, m.email AS moderator_email,
                u.name AS university_name,
                rv.name AS reviewed_by_name
         FROM batches b
         INNER JOIN users m ON m.id = b.moderator_user_id
         LEFT JOIN universities u ON u.id = b.university_id
         LEFT JOIN users rv ON rv.id = b.reviewed_by
         ORDER BY CASE b.status
             WHEN 'pending' THEN 1
             WHEN 'rejected' THEN 2
             WHEN 'approved' THEN 3
             ELSE 4
         END, b.created_at DESC"
    );
}

function onboarding_admin_find_batch_request(int $batchId): ?array
{
    return db_fetch('SELECT * FROM batches WHERE id = ?', [$batchId]);
}

function onboarding_admin_approve_batch_request(int $batchId, int $adminUserId): bool
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $batch = db_fetch('SELECT * FROM batches WHERE id = ? FOR UPDATE', [$batchId]);
        if (!$batch) {
            $pdo->rollBack();
            return false;
        }

        $code = $batch['batch_code'] ?: onboarding_generate_batch_code();

        db_query(
            "UPDATE batches
             SET status = 'approved',
                 batch_code = ?,
                 rejection_reason = NULL,
                 reviewed_by = ?,
                 reviewed_at = NOW()
             WHERE id = ?",
            [$code, $adminUserId, $batchId]
        );

        db_query(
            'UPDATE users SET batch_id = ? WHERE id = ? AND role = ?',
            [$batchId, (int) $batch['moderator_user_id'], 'moderator']
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

function onboarding_admin_reject_batch_request(int $batchId, int $adminUserId, ?string $reason = null): bool
{
    $batch = onboarding_admin_find_batch_request($batchId);
    if (!$batch) {
        return false;
    }

    db_query(
        "UPDATE batches
         SET status = 'rejected',
             rejection_reason = ?,
             reviewed_by = ?,
             reviewed_at = NOW()
         WHERE id = ?",
        [$reason ?: null, $adminUserId, $batchId]
    );

    db_query('UPDATE users SET batch_id = NULL WHERE id = ? AND role = ?', [(int) $batch['moderator_user_id'], 'moderator']);

    return true;
}

function onboarding_find_student_request(int $studentUserId): ?array
{
    return db_fetch(
        "SELECT r.*, b.batch_code, b.name AS batch_name, b.program, b.intake_year,
                u.name AS university_name, rv.name AS reviewed_by_name
         FROM student_batch_requests r
         INNER JOIN batches b ON b.id = r.requested_batch_id
         LEFT JOIN universities u ON u.id = b.university_id
         LEFT JOIN users rv ON rv.id = r.reviewed_by
         WHERE r.student_user_id = ?
         LIMIT 1",
        [$studentUserId]
    );
}

function onboarding_create_student_request(int $studentUserId, int $batchId): string
{
    return db_insert('student_batch_requests', [
        'student_user_id'    => $studentUserId,
        'requested_batch_id' => $batchId,
        'status'             => 'pending',
        'rejection_reason'   => null,
        'reviewed_by'        => null,
        'reviewed_role'      => null,
        'reviewed_at'        => null,
    ]);
}

function onboarding_resubmit_student_request(int $studentUserId, int $batchId): int
{
    return db_update('student_batch_requests', [
        'requested_batch_id' => $batchId,
        'status'             => 'pending',
        'rejection_reason'   => null,
        'reviewed_by'        => null,
        'reviewed_role'      => null,
        'reviewed_at'        => null,
    ], ['student_user_id' => $studentUserId]);
}

function onboarding_moderator_student_requests(int $moderatorUserId): array
{
    return db_fetch_all(
        "SELECT r.*, s.name AS student_name, s.email AS student_email,
                b.name AS batch_name, b.batch_code, b.program, b.intake_year,
                rv.name AS reviewed_by_name
         FROM student_batch_requests r
         INNER JOIN users s ON s.id = r.student_user_id
         INNER JOIN batches b ON b.id = r.requested_batch_id
         LEFT JOIN users rv ON rv.id = r.reviewed_by
         WHERE b.moderator_user_id = ?
         ORDER BY CASE r.status
             WHEN 'pending' THEN 1
             WHEN 'rejected' THEN 2
             WHEN 'approved' THEN 3
             ELSE 4
         END, r.created_at DESC",
        [$moderatorUserId]
    );
}

function onboarding_admin_student_requests(): array
{
    return db_fetch_all(
        "SELECT r.*, s.name AS student_name, s.email AS student_email,
                b.name AS batch_name, b.batch_code, b.program, b.intake_year,
                m.name AS moderator_name, rv.name AS reviewed_by_name
         FROM student_batch_requests r
         INNER JOIN users s ON s.id = r.student_user_id
         INNER JOIN batches b ON b.id = r.requested_batch_id
         INNER JOIN users m ON m.id = b.moderator_user_id
         LEFT JOIN users rv ON rv.id = r.reviewed_by
         ORDER BY CASE r.status
             WHEN 'pending' THEN 1
             WHEN 'rejected' THEN 2
             WHEN 'approved' THEN 3
             ELSE 4
         END, r.created_at DESC"
    );
}

function onboarding_find_student_request_for_moderator(int $requestId, int $moderatorUserId): ?array
{
    return db_fetch(
        "SELECT r.*, b.moderator_user_id
         FROM student_batch_requests r
         INNER JOIN batches b ON b.id = r.requested_batch_id
         WHERE r.id = ? AND b.moderator_user_id = ?",
        [$requestId, $moderatorUserId]
    );
}

function onboarding_find_student_request_by_id(int $requestId): ?array
{
    return db_fetch('SELECT * FROM student_batch_requests WHERE id = ?', [$requestId]);
}

function onboarding_approve_student_request(int $requestId, int $reviewerId, string $reviewerRole): bool
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $request = db_fetch('SELECT * FROM student_batch_requests WHERE id = ? FOR UPDATE', [$requestId]);
        if (!$request) {
            $pdo->rollBack();
            return false;
        }

        $batch = db_fetch('SELECT id, status FROM batches WHERE id = ? FOR UPDATE', [(int) $request['requested_batch_id']]);
        if (!$batch || $batch['status'] !== 'approved') {
            $pdo->rollBack();
            return false;
        }

        $student = db_fetch('SELECT id, batch_id, first_approved_batch_id FROM users WHERE id = ? FOR UPDATE', [(int) $request['student_user_id']]);
        if (!$student) {
            $pdo->rollBack();
            return false;
        }

        $existingBatchId  = (int) ($student['batch_id'] ?? 0);
        $lockedBatchId    = (int) ($student['first_approved_batch_id'] ?? 0);
        $requestedBatchId = (int) $request['requested_batch_id'];

        // Batch reassignment is not allowed once a student has a locked approved batch.
        if ($lockedBatchId > 0 && $lockedBatchId !== $requestedBatchId) {
            $pdo->rollBack();
            return false;
        }

        // Backward compatibility for pre-lock records.
        if ($lockedBatchId <= 0 && $existingBatchId > 0 && $existingBatchId !== $requestedBatchId) {
            $pdo->rollBack();
            return false;
        }

        db_query(
            "UPDATE student_batch_requests
             SET status = 'approved',
                 rejection_reason = NULL,
                 reviewed_by = ?,
                 reviewed_role = ?,
                 reviewed_at = NOW()
             WHERE id = ?",
            [$reviewerId, $reviewerRole, $requestId]
        );

        db_query(
            'UPDATE users
             SET batch_id = ?,
                 first_approved_batch_id = COALESCE(first_approved_batch_id, ?)
             WHERE id = ?',
            [$requestedBatchId, $requestedBatchId, (int) $student['id']]
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

function onboarding_reject_student_request(int $requestId, int $reviewerId, string $reviewerRole, ?string $reason = null): bool
{
    $request = onboarding_find_student_request_by_id($requestId);
    if (!$request) {
        return false;
    }

    db_query(
        "UPDATE student_batch_requests
         SET status = 'rejected',
             rejection_reason = ?,
             reviewed_by = ?,
             reviewed_role = ?,
             reviewed_at = NOW()
         WHERE id = ?",
        [$reason ?: null, $reviewerId, $reviewerRole, $requestId]
    );

    return true;
}

function onboarding_approved_batches(): array
{
    return db_fetch_all(
        "SELECT b.id, b.batch_code, b.name, b.program, b.intake_year,
                u.name AS university_name
         FROM batches b
         LEFT JOIN universities u ON u.id = b.university_id
         WHERE b.status = 'approved'
         ORDER BY b.name ASC"
    );
}

function onboarding_admin_counts(): array
{
    return [
        'pending_batch_requests'   => (int) db_fetch("SELECT COUNT(*) AS cnt FROM batches WHERE status = 'pending'")['cnt'],
        'pending_student_requests' => (int) db_fetch("SELECT COUNT(*) AS cnt FROM student_batch_requests WHERE status = 'pending'")['cnt'],
    ];
}

function onboarding_moderator_pending_student_request_count(int $moderatorUserId): int
{
    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM student_batch_requests r
         INNER JOIN batches b ON b.id = r.requested_batch_id
         WHERE b.moderator_user_id = ? AND r.status = 'pending'",
        [$moderatorUserId]
    );

    return (int) ($row['cnt'] ?? 0);
}
