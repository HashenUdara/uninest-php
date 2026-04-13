<?php

/**
 * Quizzes Module — Models
 */

function quizzes_allowed_statuses(): array
{
    return ['draft', 'pending', 'approved', 'rejected'];
}

function quizzes_allowed_attempt_statuses(): array
{
    return ['in_progress', 'submitted', 'auto_submitted'];
}

function quizzes_is_creator_role(?string $role): bool
{
    return in_array((string) $role, ['student', 'coordinator'], true);
}

function quizzes_is_reviewer_role(?string $role): bool
{
    return in_array((string) $role, ['coordinator', 'moderator', 'admin'], true);
}

function quizzes_find_readable_subject(int $subjectId, string $role, int $userId, int $batchId): ?array
{
    if ($subjectId <= 0) {
        return null;
    }

    if ($role === 'admin') {
        return subjects_find_admin($subjectId);
    }

    if ($batchId <= 0) {
        return null;
    }

    return subjects_find_for_batch($subjectId, $batchId);
}

function quizzes_can_review_subject(int $subjectId, string $role, int $reviewerUserId, int $reviewerBatchId): bool
{
    if ($role === 'admin') {
        return subjects_find_admin($subjectId) !== null;
    }

    if ($role === 'moderator') {
        if ($reviewerBatchId <= 0) {
            return false;
        }

        return subjects_find_for_batch($subjectId, $reviewerBatchId) !== null;
    }

    if ($role === 'coordinator') {
        return subjects_find_for_coordinator($subjectId, $reviewerUserId) !== null;
    }

    return false;
}

function quizzes_review_scope_sql(string $role, int $reviewerUserId, int $reviewerBatchId, array &$params, string $quizAlias = 'q', string $subjectAlias = 's'): string
{
    if ($role === 'admin') {
        return '';
    }

    if ($role === 'moderator') {
        if ($reviewerBatchId <= 0) {
            return ' AND 1 = 0';
        }

        $params[] = $reviewerBatchId;
        return " AND {$subjectAlias}.batch_id = ?";
    }

    if ($role === 'coordinator') {
        $params[] = $reviewerUserId;
        return " AND EXISTS (
            SELECT 1
            FROM subject_coordinators sc
            WHERE sc.subject_id = {$quizAlias}.subject_id
              AND sc.student_user_id = ?
        )";
    }

    return ' AND 1 = 0';
}

function quizzes_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'pending' => 'Pending Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Unknown',
    };
}

function quizzes_status_badge_class(string $status): string
{
    return match ($status) {
        'draft' => '',
        'pending' => 'badge-warning',
        'approved' => 'badge-info',
        'rejected' => 'badge-danger',
        default => '',
    };
}

function quizzes_attempt_status_label(string $status): string
{
    return match ($status) {
        'in_progress' => 'In Progress',
        'submitted' => 'Submitted',
        'auto_submitted' => 'Auto Submitted',
        default => 'Unknown',
    };
}

function quizzes_create(array $data): int
{
    return (int) db_insert('quizzes', [
        'subject_id' => (int) $data['subject_id'],
        'created_by_user_id' => $data['created_by_user_id'] ?? null,
        'title' => $data['title'],
        'description' => $data['description'] ?? null,
        'duration_minutes' => (int) $data['duration_minutes'],
        'status' => $data['status'] ?? 'draft',
        'rejection_reason' => $data['rejection_reason'] ?? null,
        'reviewed_by_user_id' => $data['reviewed_by_user_id'] ?? null,
        'reviewed_at' => $data['reviewed_at'] ?? null,
    ]);
}

function quizzes_find_with_subject(int $quizId): ?array
{
    return db_fetch(
        "SELECT q.*,
                s.id AS subject_id,
                s.code AS subject_code,
                s.name AS subject_name,
                s.batch_id AS subject_batch_id,
                u.name AS creator_name,
                u.email AS creator_email
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         LEFT JOIN users u ON u.id = q.created_by_user_id
         WHERE q.id = ?
         LIMIT 1",
        [$quizId]
    );
}

function quizzes_find_subject_published(int $quizId, int $subjectId): ?array
{
    return db_fetch(
        "SELECT q.*,
                s.code AS subject_code,
                s.name AS subject_name,
                u.name AS creator_name,
                COALESCE(qq.question_count, 0) AS question_count
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         LEFT JOIN users u ON u.id = q.created_by_user_id
         LEFT JOIN (
            SELECT quiz_id, COUNT(*) AS question_count
            FROM quiz_questions
            GROUP BY quiz_id
         ) qq ON qq.quiz_id = q.id
         WHERE q.id = ?
           AND q.subject_id = ?
           AND q.status = 'approved'
         LIMIT 1",
        [$quizId, $subjectId]
    );
}

function quizzes_subject_published_list_for_viewer(int $subjectId, int $viewerUserId): array
{
    return db_fetch_all(
        "SELECT q.*,
                u.name AS creator_name,
                COALESCE(qq.question_count, 0) AS question_count,
                COALESCE(va.attempt_count, 0) AS viewer_attempt_count,
                va.best_score AS viewer_best_score
         FROM quizzes q
         LEFT JOIN users u ON u.id = q.created_by_user_id
         LEFT JOIN (
            SELECT quiz_id, COUNT(*) AS question_count
            FROM quiz_questions
            GROUP BY quiz_id
         ) qq ON qq.quiz_id = q.id
         LEFT JOIN (
            SELECT qa.quiz_id,
                   COUNT(*) AS attempt_count,
                   MAX(qa.score_percent) AS best_score
            FROM quiz_attempts qa
            WHERE qa.user_id = ?
              AND qa.status IN ('submitted', 'auto_submitted')
            GROUP BY qa.quiz_id
         ) va ON va.quiz_id = q.id
         WHERE q.subject_id = ?
           AND q.status = 'approved'
         ORDER BY q.updated_at DESC, q.id DESC",
        [$viewerUserId, $subjectId]
    );
}

function quizzes_my_list(int $ownerUserId): array
{
    return db_fetch_all(
        "SELECT q.*,
                s.code AS subject_code,
                s.name AS subject_name,
                COALESCE(qq.question_count, 0) AS question_count
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         LEFT JOIN (
            SELECT quiz_id, COUNT(*) AS question_count
            FROM quiz_questions
            GROUP BY quiz_id
         ) qq ON qq.quiz_id = q.id
         WHERE q.created_by_user_id = ?
         ORDER BY q.updated_at DESC, q.id DESC",
        [$ownerUserId]
    );
}

function quizzes_count_created_by_user(int $ownerUserId): int
{
    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM quizzes
         WHERE created_by_user_id = ?",
        [$ownerUserId]
    );

    return (int) ($row['cnt'] ?? 0);
}

function quizzes_subject_hub_list(string $role, int $viewerUserId, int $viewerBatchId): array
{
    $params = [$viewerUserId];
    $scopeSql = '';

    if ($role !== 'admin') {
        if ($viewerBatchId <= 0) {
            return [];
        }

        $scopeSql = 'WHERE s.batch_id = ?';
        $params[] = $viewerBatchId;
    }

    return db_fetch_all(
        "SELECT s.id,
                s.code,
                s.name,
                s.batch_id,
                b.batch_code,
                b.name AS batch_name,
                COALESCE(qs.total_quizzes, 0) AS total_quizzes,
                COALESCE(qs.approved_quizzes, 0) AS approved_quizzes,
                COALESCE(qs.pending_quizzes, 0) AS pending_quizzes,
                COALESCE(mq.my_quizzes, 0) AS my_quizzes
         FROM subjects s
         INNER JOIN batches b ON b.id = s.batch_id
         LEFT JOIN (
            SELECT q.subject_id,
                   COUNT(*) AS total_quizzes,
                   SUM(CASE WHEN q.status = 'approved' THEN 1 ELSE 0 END) AS approved_quizzes,
                   SUM(CASE WHEN q.status = 'pending' THEN 1 ELSE 0 END) AS pending_quizzes
            FROM quizzes q
            GROUP BY q.subject_id
         ) qs ON qs.subject_id = s.id
         LEFT JOIN (
            SELECT q.subject_id, COUNT(*) AS my_quizzes
            FROM quizzes q
            WHERE q.created_by_user_id = ?
            GROUP BY q.subject_id
         ) mq ON mq.subject_id = s.id
         {$scopeSql}
         ORDER BY s.code ASC, s.name ASC",
        $params
    );
}

function quizzes_find_owned_with_subject(int $quizId, int $ownerUserId): ?array
{
    return db_fetch(
        "SELECT q.*,
                s.code AS subject_code,
                s.name AS subject_name,
                s.batch_id AS subject_batch_id,
                COALESCE(qq.question_count, 0) AS question_count
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         LEFT JOIN (
            SELECT quiz_id, COUNT(*) AS question_count
            FROM quiz_questions
            GROUP BY quiz_id
         ) qq ON qq.quiz_id = q.id
         WHERE q.id = ?
           AND q.created_by_user_id = ?
         LIMIT 1",
        [$quizId, $ownerUserId]
    );
}

function quizzes_find_owned_editable_with_subject(int $quizId, int $ownerUserId): ?array
{
    return db_fetch(
        "SELECT q.*,
                s.code AS subject_code,
                s.name AS subject_name,
                s.batch_id AS subject_batch_id,
                COALESCE(qq.question_count, 0) AS question_count
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         LEFT JOIN (
            SELECT quiz_id, COUNT(*) AS question_count
            FROM quiz_questions
            GROUP BY quiz_id
         ) qq ON qq.quiz_id = q.id
         WHERE q.id = ?
           AND q.created_by_user_id = ?
           AND q.status IN ('draft', 'rejected')
         LIMIT 1",
        [$quizId, $ownerUserId]
    );
}

function quizzes_question_count(int $quizId): int
{
    $row = db_fetch('SELECT COUNT(*) AS cnt FROM quiz_questions WHERE quiz_id = ?', [$quizId]);
    return (int) ($row['cnt'] ?? 0);
}

function quizzes_has_at_least_one_question(int $quizId): bool
{
    return quizzes_question_count($quizId) > 0;
}

function quizzes_replace_questions(int $quizId, array $questions): void
{
    db_query('DELETE FROM quiz_questions WHERE quiz_id = ?', [$quizId]);

    foreach ($questions as $questionIndex => $question) {
        $questionId = (int) db_insert('quiz_questions', [
            'quiz_id' => $quizId,
            'question_text' => $question['question_text'],
            'sort_order' => $questionIndex + 1,
        ]);

        $correctIndex = (int) $question['correct_index'];
        foreach ($question['options'] as $optionIndex => $optionText) {
            db_insert('quiz_options', [
                'question_id' => $questionId,
                'option_text' => $optionText,
                'is_correct' => $optionIndex === $correctIndex ? 1 : 0,
                'sort_order' => $optionIndex + 1,
            ]);
        }
    }
}

function quizzes_create_with_questions(array $quizData, array $questions): int
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $quizId = quizzes_create($quizData);
        quizzes_replace_questions($quizId, $questions);

        $pdo->commit();
        return $quizId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function quizzes_update_owned_with_questions(int $quizId, int $ownerUserId, array $quizData, array $questions): bool
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $existing = db_fetch(
            "SELECT id
             FROM quizzes
             WHERE id = ?
               AND created_by_user_id = ?
               AND status IN ('draft', 'rejected')
             FOR UPDATE",
            [$quizId, $ownerUserId]
        );

        if (!$existing) {
            $pdo->rollBack();
            return false;
        }

        db_query(
            "UPDATE quizzes
             SET title = ?,
                 description = ?,
                 duration_minutes = ?,
                 status = ?,
                 rejection_reason = ?,
                 reviewed_by_user_id = ?,
                 reviewed_at = ?
             WHERE id = ?
               AND created_by_user_id = ?
               AND status IN ('draft', 'rejected')",
            [
                $quizData['title'],
                $quizData['description'] ?? null,
                (int) $quizData['duration_minutes'],
                $quizData['status'],
                $quizData['rejection_reason'] ?? null,
                $quizData['reviewed_by_user_id'] ?? null,
                $quizData['reviewed_at'] ?? null,
                $quizId,
                $ownerUserId,
            ]
        );

        quizzes_replace_questions($quizId, $questions);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function quizzes_submit_owned(int $quizId, int $ownerUserId, string $targetStatus, ?int $reviewedByUserId = null): bool
{
    if (!in_array($targetStatus, ['pending', 'approved'], true)) {
        return false;
    }

    $reviewedAt = null;
    if ($targetStatus === 'approved') {
        $reviewedAt = date('Y-m-d H:i:s');
    }

    $result = db_query(
        "UPDATE quizzes
         SET status = ?,
             rejection_reason = NULL,
             reviewed_by_user_id = ?,
             reviewed_at = ?
         WHERE id = ?
           AND created_by_user_id = ?
           AND status IN ('draft', 'rejected')",
        [$targetStatus, $reviewedByUserId, $reviewedAt, $quizId, $ownerUserId]
    )->rowCount();

    return $result > 0;
}

function quizzes_delete_editable_owned(int $quizId, int $ownerUserId): bool
{
    $deleted = db_query(
        "DELETE FROM quizzes
         WHERE id = ?
           AND created_by_user_id = ?
           AND status IN ('draft', 'rejected')",
        [$quizId, $ownerUserId]
    )->rowCount();

    return $deleted > 0;
}

function quizzes_questions_with_options(int $quizId, bool $includeCorrectFlag = false): array
{
    $questions = db_fetch_all(
        "SELECT id, quiz_id, question_text, sort_order
         FROM quiz_questions
         WHERE quiz_id = ?
         ORDER BY sort_order ASC, id ASC",
        [$quizId]
    );

    if (empty($questions)) {
        return [];
    }

    $questionIds = array_values(array_map(static fn(array $row): int => (int) $row['id'], $questions));
    $placeholders = implode(', ', array_fill(0, count($questionIds), '?'));

    $options = db_fetch_all(
        "SELECT id, question_id, option_text, is_correct, sort_order
         FROM quiz_options
         WHERE question_id IN ({$placeholders})
         ORDER BY question_id ASC, sort_order ASC, id ASC",
        $questionIds
    );

    $optionMap = [];
    foreach ($options as $option) {
        $questionId = (int) ($option['question_id'] ?? 0);
        if (!isset($optionMap[$questionId])) {
            $optionMap[$questionId] = [];
        }

        $entry = [
            'id' => (int) ($option['id'] ?? 0),
            'question_id' => $questionId,
            'option_text' => (string) ($option['option_text'] ?? ''),
            'sort_order' => (int) ($option['sort_order'] ?? 0),
        ];

        if ($includeCorrectFlag) {
            $entry['is_correct'] = (int) ($option['is_correct'] ?? 0);
        }

        $optionMap[$questionId][] = $entry;
    }

    $result = [];
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        $result[] = [
            'id' => $questionId,
            'quiz_id' => (int) ($question['quiz_id'] ?? 0),
            'question_text' => (string) ($question['question_text'] ?? ''),
            'sort_order' => (int) ($question['sort_order'] ?? 0),
            'options' => $optionMap[$questionId] ?? [],
        ];
    }

    return $result;
}

function quizzes_review_metrics(string $role, int $reviewerUserId, int $reviewerBatchId): array
{
    $params = [];
    $scopeSql = quizzes_review_scope_sql($role, $reviewerUserId, $reviewerBatchId, $params);

    $row = db_fetch(
        "SELECT
            SUM(CASE WHEN q.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN q.status = 'approved' AND DATE(q.reviewed_at) = CURDATE() THEN 1 ELSE 0 END) AS approved_today_count,
            SUM(CASE WHEN q.status = 'rejected' AND DATE(q.reviewed_at) = CURDATE() THEN 1 ELSE 0 END) AS rejected_today_count
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         WHERE 1 = 1{$scopeSql}",
        $params
    );

    return [
        'pending_count' => (int) ($row['pending_count'] ?? 0),
        'approved_today_count' => (int) ($row['approved_today_count'] ?? 0),
        'rejected_today_count' => (int) ($row['rejected_today_count'] ?? 0),
    ];
}

function quizzes_pending_count_for_reviewer(int $reviewerUserId, string $role, int $reviewerBatchId): int
{
    $params = ['pending'];
    $scopeSql = quizzes_review_scope_sql($role, $reviewerUserId, $reviewerBatchId, $params);

    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         WHERE q.status = ?{$scopeSql}",
        $params
    );

    return (int) ($row['cnt'] ?? 0);
}

function quizzes_review_list(string $status, string $role, int $reviewerUserId, int $reviewerBatchId): array
{
    $params = [$status];
    $scopeSql = quizzes_review_scope_sql($role, $reviewerUserId, $reviewerBatchId, $params);

    $orderBy = $status === 'pending'
        ? 'q.created_at ASC, q.id ASC'
        : 'q.reviewed_at DESC, q.id DESC';

    return db_fetch_all(
        "SELECT q.*,
                s.code AS subject_code,
                s.name AS subject_name,
                s.batch_id AS subject_batch_id,
                cu.name AS creator_name,
                ru.name AS reviewer_name,
                COALESCE(qq.question_count, 0) AS question_count
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         LEFT JOIN users cu ON cu.id = q.created_by_user_id
         LEFT JOIN users ru ON ru.id = q.reviewed_by_user_id
         LEFT JOIN (
            SELECT quiz_id, COUNT(*) AS question_count
            FROM quiz_questions
            GROUP BY quiz_id
         ) qq ON qq.quiz_id = q.id
         WHERE q.status = ?{$scopeSql}
         ORDER BY {$orderBy}",
        $params
    );
}

function quizzes_review_samples_for_quizzes(array $quizIds, int $limitPerQuiz = 3): array
{
    $quizIds = array_values(array_filter(array_map('intval', $quizIds), static fn(int $id): bool => $id > 0));
    if (empty($quizIds)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($quizIds), '?'));
    $rows = db_fetch_all(
        "SELECT quiz_id, question_text
         FROM quiz_questions
         WHERE quiz_id IN ({$placeholders})
         ORDER BY quiz_id ASC, sort_order ASC, id ASC",
        $quizIds
    );

    $samples = [];
    foreach ($rows as $row) {
        $quizId = (int) ($row['quiz_id'] ?? 0);
        if ($quizId <= 0) {
            continue;
        }

        if (!isset($samples[$quizId])) {
            $samples[$quizId] = [];
        }

        if (count($samples[$quizId]) >= $limitPerQuiz) {
            continue;
        }

        $samples[$quizId][] = (string) ($row['question_text'] ?? '');
    }

    return $samples;
}

function quizzes_find_pending_for_reviewer(int $quizId, string $role, int $reviewerUserId, int $reviewerBatchId): ?array
{
    $params = [$quizId, 'pending'];
    $scopeSql = quizzes_review_scope_sql($role, $reviewerUserId, $reviewerBatchId, $params);

    return db_fetch(
        "SELECT q.*, s.code AS subject_code, s.name AS subject_name
         FROM quizzes q
         INNER JOIN subjects s ON s.id = q.subject_id
         WHERE q.id = ?
           AND q.status = ?{$scopeSql}
         LIMIT 1",
        $params
    );
}

function quizzes_mark_approved(int $quizId, int $reviewerUserId): bool
{
    $updated = db_query(
        "UPDATE quizzes
         SET status = 'approved',
             rejection_reason = NULL,
             reviewed_by_user_id = ?,
             reviewed_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND status = 'pending'",
        [$reviewerUserId, $quizId]
    )->rowCount();

    return $updated > 0;
}

function quizzes_mark_rejected(int $quizId, int $reviewerUserId, string $reason): bool
{
    $updated = db_query(
        "UPDATE quizzes
         SET status = 'rejected',
             rejection_reason = ?,
             reviewed_by_user_id = ?,
             reviewed_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND status = 'pending'",
        [$reason, $reviewerUserId, $quizId]
    )->rowCount();

    return $updated > 0;
}

function quizzes_find_in_progress_attempt(int $quizId, int $userId): ?array
{
    return db_fetch(
        "SELECT *
         FROM quiz_attempts
         WHERE quiz_id = ?
           AND user_id = ?
           AND status = 'in_progress'
         ORDER BY id DESC
         LIMIT 1",
        [$quizId, $userId]
    );
}

function quizzes_create_attempt(int $quizId, int $userId, int $durationMinutes, int $totalQuestions): int
{
    $startedAt = new DateTimeImmutable('now');
    $expiresAt = $startedAt->modify('+' . max(1, $durationMinutes) . ' minutes');

    return (int) db_insert('quiz_attempts', [
        'quiz_id' => $quizId,
        'user_id' => $userId,
        'status' => 'in_progress',
        'started_at' => $startedAt->format('Y-m-d H:i:s'),
        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        'submitted_at' => null,
        'correct_count' => 0,
        'total_questions' => max(0, $totalQuestions),
        'score_percent' => 0,
    ]);
}

function quizzes_find_attempt_for_user(int $attemptId, int $quizId, int $userId): ?array
{
    return db_fetch(
        "SELECT qa.*, q.subject_id, q.title AS quiz_title, q.duration_minutes
         FROM quiz_attempts qa
         INNER JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.id = ?
           AND qa.quiz_id = ?
           AND qa.user_id = ?
         LIMIT 1",
        [$attemptId, $quizId, $userId]
    );
}

function quizzes_attempt_answers_map(int $attemptId): array
{
    $rows = db_fetch_all(
        'SELECT question_id, selected_option_id FROM quiz_attempt_answers WHERE attempt_id = ?',
        [$attemptId]
    );

    $map = [];
    foreach ($rows as $row) {
        $questionId = (int) ($row['question_id'] ?? 0);
        if ($questionId <= 0) {
            continue;
        }

        $map[$questionId] = isset($row['selected_option_id']) ? (int) $row['selected_option_id'] : null;
    }

    return $map;
}

function quizzes_submit_attempt(int $attemptId, int $quizId, int $userId, array $selectedOptionIdsByQuestion): ?array
{
    $pdo = db_connect();
    $pdo->beginTransaction();

    try {
        $attempt = db_fetch(
            "SELECT *
             FROM quiz_attempts
             WHERE id = ?
               AND quiz_id = ?
               AND user_id = ?
             FOR UPDATE",
            [$attemptId, $quizId, $userId]
        );

        if (!$attempt) {
            $pdo->rollBack();
            return null;
        }

        $status = (string) ($attempt['status'] ?? '');
        if ($status !== 'in_progress') {
            $pdo->commit();
            return [
                'attempt_id' => (int) ($attempt['id'] ?? 0),
                'status' => $status,
                'already_submitted' => true,
                'correct_count' => (int) ($attempt['correct_count'] ?? 0),
                'total_questions' => (int) ($attempt['total_questions'] ?? 0),
                'score_percent' => (float) ($attempt['score_percent'] ?? 0),
            ];
        }

        $questionRows = db_fetch_all(
            "SELECT qq.id AS question_id,
                    qo.id AS option_id,
                    qo.is_correct
             FROM quiz_questions qq
             INNER JOIN quiz_options qo ON qo.question_id = qq.id
             WHERE qq.quiz_id = ?
             ORDER BY qq.sort_order ASC, qq.id ASC, qo.sort_order ASC, qo.id ASC",
            [$quizId]
        );

        $questionOptionIds = [];
        $correctOptionByQuestion = [];

        foreach ($questionRows as $row) {
            $questionId = (int) ($row['question_id'] ?? 0);
            $optionId = (int) ($row['option_id'] ?? 0);
            $isCorrect = (int) ($row['is_correct'] ?? 0) === 1;

            if ($questionId <= 0 || $optionId <= 0) {
                continue;
            }

            if (!isset($questionOptionIds[$questionId])) {
                $questionOptionIds[$questionId] = [];
            }

            $questionOptionIds[$questionId][$optionId] = true;

            if ($isCorrect) {
                $correctOptionByQuestion[$questionId] = $optionId;
            }
        }

        $questionIds = array_keys($questionOptionIds);
        $totalQuestions = count($questionIds);
        $correctCount = 0;

        db_query('DELETE FROM quiz_attempt_answers WHERE attempt_id = ?', [$attemptId]);

        foreach ($questionIds as $questionId) {
            $selected = null;
            if (array_key_exists($questionId, $selectedOptionIdsByQuestion)) {
                $candidate = (int) $selectedOptionIdsByQuestion[$questionId];
                if ($candidate > 0 && isset($questionOptionIds[$questionId][$candidate])) {
                    $selected = $candidate;
                }
            }

            $correctOptionId = (int) ($correctOptionByQuestion[$questionId] ?? 0);
            $isCorrect = $selected !== null && $selected === $correctOptionId;
            if ($isCorrect) {
                $correctCount++;
            }

            db_insert('quiz_attempt_answers', [
                'attempt_id' => $attemptId,
                'question_id' => $questionId,
                'selected_option_id' => $selected,
                'is_correct' => $isCorrect ? 1 : 0,
            ]);
        }

        $scorePercent = $totalQuestions > 0
            ? round(($correctCount / $totalQuestions) * 100, 2)
            : 0.0;

        $now = new DateTimeImmutable('now');
        $expiresAt = new DateTimeImmutable((string) $attempt['expires_at']);
        $finalStatus = $now > $expiresAt ? 'auto_submitted' : 'submitted';

        db_query(
            "UPDATE quiz_attempts
             SET status = ?,
                 submitted_at = ?,
                 correct_count = ?,
                 total_questions = ?,
                 score_percent = ?
             WHERE id = ?",
            [
                $finalStatus,
                $now->format('Y-m-d H:i:s'),
                $correctCount,
                $totalQuestions,
                $scorePercent,
                $attemptId,
            ]
        );

        $pdo->commit();

        return [
            'attempt_id' => $attemptId,
            'status' => $finalStatus,
            'already_submitted' => false,
            'correct_count' => $correctCount,
            'total_questions' => $totalQuestions,
            'score_percent' => $scorePercent,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function quizzes_attempt_result_for_user(int $attemptId, int $quizId, int $userId): ?array
{
    return db_fetch(
        "SELECT qa.*, q.title AS quiz_title, q.subject_id, q.duration_minutes
         FROM quiz_attempts qa
         INNER JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.id = ?
           AND qa.quiz_id = ?
           AND qa.user_id = ?
           AND qa.status IN ('submitted', 'auto_submitted')
         LIMIT 1",
        [$attemptId, $quizId, $userId]
    );
}

function quizzes_attempt_result_rows(int $attemptId): array
{
    return db_fetch_all(
        "SELECT qq.id AS question_id,
                qq.question_text,
                qq.sort_order,
                aa.selected_option_id,
                so.option_text AS selected_option_text,
                co.id AS correct_option_id,
                co.option_text AS correct_option_text,
                aa.is_correct
         FROM quiz_attempt_answers aa
         INNER JOIN quiz_questions qq ON qq.id = aa.question_id
         LEFT JOIN quiz_options so ON so.id = aa.selected_option_id
         LEFT JOIN quiz_options co ON co.question_id = qq.id AND co.is_correct = 1
         WHERE aa.attempt_id = ?
         ORDER BY qq.sort_order ASC, qq.id ASC",
        [$attemptId]
    );
}

function quizzes_best_attempt_for_user(int $quizId, int $userId): ?array
{
    return db_fetch(
        "SELECT *
         FROM quiz_attempts
         WHERE quiz_id = ?
           AND user_id = ?
           AND status IN ('submitted', 'auto_submitted')
         ORDER BY score_percent DESC, submitted_at DESC, id DESC
         LIMIT 1",
        [$quizId, $userId]
    );
}

function quizzes_attempt_count_for_user(int $quizId, int $userId): int
{
    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM quiz_attempts
         WHERE quiz_id = ?
           AND user_id = ?
           AND status IN ('submitted', 'auto_submitted')",
        [$quizId, $userId]
    );

    return (int) ($row['cnt'] ?? 0);
}
