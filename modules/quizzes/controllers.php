<?php

/**
 * Quizzes Module — Controllers
 */

function quizzes_role_label(): string
{
    return match (user_role()) {
        'admin' => 'Admin',
        'moderator' => 'Moderator',
        'coordinator' => 'Coordinator',
        default => 'Student',
    };
}

function quizzes_current_user_id(): int
{
    return (int) (auth_id() ?? 0);
}

function quizzes_current_batch_id(): int
{
    return (int) (auth_user()['batch_id'] ?? 0);
}

function quizzes_require_creator_role(): void
{
    if (!quizzes_is_creator_role(user_role())) {
        abort(403, 'Only students and coordinators can create quizzes.');
    }
}

function quizzes_require_reviewer_role(): void
{
    if (!quizzes_is_reviewer_role(user_role())) {
        abort(403, 'You do not have permission to review quizzes.');
    }
}

function quizzes_resolve_readable_subject_or_abort(int $subjectId): array
{
    $subject = quizzes_find_readable_subject(
        $subjectId,
        (string) user_role(),
        quizzes_current_user_id(),
        quizzes_current_batch_id()
    );

    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    return $subject;
}

function quizzes_target_status_for_creator(string $intent): string
{
    if (user_role() === 'coordinator') {
        return 'approved';
    }

    return $intent === 'submit' ? 'pending' : 'draft';
}

function quizzes_success_message_for_status(string $status): string
{
    return match ($status) {
        'approved' => 'Quiz published successfully.',
        'pending' => 'Quiz submitted for review.',
        default => 'Quiz saved as draft.',
    };
}

function quizzes_attempt_is_expired(array $attempt): bool
{
    $expiresAtText = trim((string) ($attempt['expires_at'] ?? ''));
    if ($expiresAtText === '') {
        return true;
    }

    try {
        $expiresAt = new DateTimeImmutable($expiresAtText);
    } catch (Throwable) {
        return true;
    }

    return (new DateTimeImmutable('now')) > $expiresAt;
}

function quizzes_attempt_seconds_remaining(array $attempt): int
{
    $expiresAtText = trim((string) ($attempt['expires_at'] ?? ''));
    if ($expiresAtText === '') {
        return 0;
    }

    try {
        $expiresAt = new DateTimeImmutable($expiresAtText);
    } catch (Throwable) {
        return 0;
    }

    $seconds = $expiresAt->getTimestamp() - time();
    return max(0, $seconds);
}

function quizzes_extract_form_payload(array $source): array
{
    $intent = trim((string) ($source['intent'] ?? 'draft'));
    if (!in_array($intent, ['draft', 'submit'], true)) {
        $intent = 'draft';
    }

    return [
        'title' => trim((string) ($source['title'] ?? '')),
        'description' => trim((string) ($source['description'] ?? '')),
        'duration_minutes' => (int) ($source['duration_minutes'] ?? 0),
        'intent' => $intent,
        'questions' => quizzes_normalize_questions_input($source['questions'] ?? []),
    ];
}

function quizzes_normalize_questions_input(mixed $rawQuestions): array
{
    if (!is_array($rawQuestions)) {
        return [];
    }

    $normalized = [];

    foreach ($rawQuestions as $question) {
        if (!is_array($question)) {
            continue;
        }

        $optionsRaw = $question['options'] ?? [];
        if (!is_array($optionsRaw)) {
            $optionsRaw = [];
        }

        $options = array_map(
            static fn($value): string => trim((string) $value),
            array_values($optionsRaw)
        );

        $normalized[] = [
            'text' => trim((string) ($question['text'] ?? '')),
            'options' => $options,
            'correct_index' => (int) ($question['correct_index'] ?? -1),
        ];
    }

    return $normalized;
}

function quizzes_validate_form_payload(array $payload): array
{
    $errors = [];

    $title = trim((string) ($payload['title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $durationMinutes = (int) ($payload['duration_minutes'] ?? 0);
    $questions = (array) ($payload['questions'] ?? []);

    if ($title === '') {
        $errors[] = 'Quiz title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Quiz title must be at most 200 characters.';
    }

    if ($durationMinutes < 5 || $durationMinutes > 180) {
        $errors[] = 'Duration must be between 5 and 180 minutes.';
    }

    if (count($questions) < 1) {
        $errors[] = 'Add at least one question.';
    }

    $validatedQuestions = [];

    foreach ($questions as $index => $question) {
        $questionNumber = $index + 1;
        $questionText = trim((string) ($question['text'] ?? ''));
        $options = array_values((array) ($question['options'] ?? []));
        $correctIndex = (int) ($question['correct_index'] ?? -1);

        if ($questionText === '') {
            $errors[] = "Question {$questionNumber} text is required.";
        }

        if (count($options) < 4 || count($options) > 6) {
            $errors[] = "Question {$questionNumber} must have 4 to 6 options.";
        }

        $hasEmptyOption = false;
        $cleanOptions = [];
        foreach ($options as $optionText) {
            $optionText = trim((string) $optionText);
            if ($optionText === '') {
                $hasEmptyOption = true;
            }
            $cleanOptions[] = $optionText;
        }

        if ($hasEmptyOption) {
            $errors[] = "Question {$questionNumber} has empty option text.";
        }

        if ($correctIndex < 0 || $correctIndex >= count($cleanOptions)) {
            $errors[] = "Question {$questionNumber} must have exactly one valid correct option.";
        }

        $validatedQuestions[] = [
            'question_text' => $questionText,
            'options' => $cleanOptions,
            'correct_index' => $correctIndex,
        ];
    }

    return [
        'errors' => $errors,
        'validated' => [
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'duration_minutes' => $durationMinutes,
            'intent' => (string) ($payload['intent'] ?? 'draft'),
            'questions' => $validatedQuestions,
        ],
    ];
}

function quizzes_default_form_payload(): array
{
    return [
        'title' => '',
        'description' => '',
        'duration_minutes' => 30,
        'intent' => 'draft',
        'questions' => [[
            'text' => '',
            'options' => ['', '', '', ''],
            'correct_index' => -1,
        ]],
    ];
}

function quizzes_form_payload_from_quiz(array $quiz, array $questionRows): array
{
    $questions = [];

    foreach ($questionRows as $question) {
        $options = array_values(array_map(
            static fn(array $option): string => (string) ($option['option_text'] ?? ''),
            (array) ($question['options'] ?? [])
        ));

        $correctIndex = -1;
        foreach ((array) ($question['options'] ?? []) as $index => $option) {
            if ((int) ($option['is_correct'] ?? 0) === 1) {
                $correctIndex = $index;
                break;
            }
        }

        if (count($options) < 4) {
            while (count($options) < 4) {
                $options[] = '';
            }
        }

        $questions[] = [
            'text' => (string) ($question['question_text'] ?? ''),
            'options' => $options,
            'correct_index' => $correctIndex,
        ];
    }

    if (empty($questions)) {
        $questions = quizzes_default_form_payload()['questions'];
    }

    return [
        'title' => (string) ($quiz['title'] ?? ''),
        'description' => (string) ($quiz['description'] ?? ''),
        'duration_minutes' => (int) ($quiz['duration_minutes'] ?? 30),
        'intent' => 'draft',
        'questions' => $questions,
    ];
}

function quizzes_form_payload_from_old_or_default(?array $quiz = null, array $questionRows = []): array
{
    $old = $_SESSION['_old_input'] ?? null;
    if (is_array($old) && (string) ($old['form_scope'] ?? '') === 'quiz_builder') {
        $oldPayload = quizzes_extract_form_payload($old);
        if (empty($oldPayload['questions'])) {
            $oldPayload['questions'] = quizzes_default_form_payload()['questions'];
        }

        return $oldPayload;
    }

    if ($quiz !== null) {
        return quizzes_form_payload_from_quiz($quiz, $questionRows);
    }

    return quizzes_default_form_payload();
}

function quizzes_subject_index(string $id): void
{
    $subjectId = (int) $id;
    $subject = quizzes_resolve_readable_subject_or_abort($subjectId);

    view('quizzes::index', [
        'subject' => $subject,
        'quizzes' => quizzes_subject_published_list_for_viewer($subjectId, quizzes_current_user_id()),
        'role_label' => quizzes_role_label(),
        'can_create' => quizzes_is_creator_role(user_role()),
    ], 'dashboard');
}

function quizzes_hub_index(): void
{
    $role = (string) user_role();
    $viewerId = quizzes_current_user_id();
    $batchId = quizzes_current_batch_id();

    $canCreate = quizzes_is_creator_role($role);
    $canReview = quizzes_is_reviewer_role($role);

    view('quizzes::hub', [
        'role_label' => quizzes_role_label(),
        'subjects' => quizzes_subject_hub_list($role, $viewerId, $batchId),
        'can_create' => $canCreate,
        'can_review' => $canReview,
        'pending_review_count' => $canReview
            ? quizzes_pending_count_for_reviewer($viewerId, $role, $batchId)
            : 0,
        'my_quiz_count' => $canCreate ? quizzes_count_created_by_user($viewerId) : 0,
    ], 'dashboard');
}

function quizzes_subject_create_form(string $id): void
{
    quizzes_require_creator_role();

    $subjectId = (int) $id;
    $subject = quizzes_resolve_readable_subject_or_abort($subjectId);

    view('quizzes::create', [
        'subject' => $subject,
        'role_label' => quizzes_role_label(),
        'form' => quizzes_form_payload_from_old_or_default(),
        'is_coordinator_creator' => user_role() === 'coordinator',
    ], 'dashboard');
}

function quizzes_subject_store(string $id): void
{
    csrf_check();
    quizzes_require_creator_role();

    $subjectId = (int) $id;
    $subject = quizzes_resolve_readable_subject_or_abort($subjectId);

    $payload = quizzes_extract_form_payload($_POST);
    $validated = quizzes_validate_form_payload($payload);

    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/create');
    }

    $validatedPayload = $validated['validated'];
    $targetStatus = quizzes_target_status_for_creator((string) $validatedPayload['intent']);
    $reviewedByUserId = $targetStatus === 'approved' ? quizzes_current_user_id() : null;
    $reviewedAt = $targetStatus === 'approved' ? date('Y-m-d H:i:s') : null;

    try {
        quizzes_create_with_questions([
            'subject_id' => $subjectId,
            'created_by_user_id' => quizzes_current_user_id(),
            'title' => $validatedPayload['title'],
            'description' => $validatedPayload['description'],
            'duration_minutes' => $validatedPayload['duration_minutes'],
            'status' => $targetStatus,
            'rejection_reason' => null,
            'reviewed_by_user_id' => $reviewedByUserId,
            'reviewed_at' => $reviewedAt,
        ], $validatedPayload['questions']);
    } catch (Throwable) {
        flash('error', 'Unable to save quiz right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/create');
    }

    clear_old_input();
    flash('success', quizzes_success_message_for_status($targetStatus));
    redirect('/my-quizzes');
}

function quizzes_subject_show(string $id, string $quizId): void
{
    $subjectId = (int) $id;
    $quizIdInt = (int) $quizId;

    $subject = quizzes_resolve_readable_subject_or_abort($subjectId);
    $quiz = quizzes_find_subject_published($quizIdInt, $subjectId);

    if (!$quiz) {
        abort(404, 'Quiz not found.');
    }

    $viewerId = quizzes_current_user_id();
    $inProgressAttempt = quizzes_find_in_progress_attempt($quizIdInt, $viewerId);

    if ($inProgressAttempt && quizzes_attempt_is_expired($inProgressAttempt)) {
        quizzes_submit_attempt((int) $inProgressAttempt['id'], $quizIdInt, $viewerId, []);
        $inProgressAttempt = null;
    }

    view('quizzes::show', [
        'subject' => $subject,
        'quiz' => $quiz,
        'questions' => quizzes_questions_with_options($quizIdInt, false),
        'attempt_count' => quizzes_attempt_count_for_user($quizIdInt, $viewerId),
        'best_attempt' => quizzes_best_attempt_for_user($quizIdInt, $viewerId),
        'in_progress_attempt' => $inProgressAttempt,
        'role_label' => quizzes_role_label(),
    ], 'dashboard');
}

function quizzes_attempt_start(string $id, string $quizId): void
{
    csrf_check();

    $subjectId = (int) $id;
    $quizIdInt = (int) $quizId;
    $viewerId = quizzes_current_user_id();

    quizzes_resolve_readable_subject_or_abort($subjectId);
    $quiz = quizzes_find_subject_published($quizIdInt, $subjectId);
    if (!$quiz) {
        abort(404, 'Quiz not found.');
    }

    $existing = quizzes_find_in_progress_attempt($quizIdInt, $viewerId);
    if ($existing) {
        $existingAttemptId = (int) ($existing['id'] ?? 0);

        if (quizzes_attempt_is_expired($existing)) {
            quizzes_submit_attempt($existingAttemptId, $quizIdInt, $viewerId, []);
            redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $existingAttemptId . '/result');
        }

        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $existingAttemptId);
    }

    $questionCount = quizzes_question_count($quizIdInt);
    if ($questionCount < 1) {
        flash('error', 'This quiz has no questions yet.');
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt);
    }

    try {
        $attemptId = quizzes_create_attempt(
            $quizIdInt,
            $viewerId,
            (int) ($quiz['duration_minutes'] ?? 30),
            $questionCount
        );
    } catch (Throwable) {
        flash('error', 'Unable to start attempt right now.');
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt);
    }

    redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $attemptId);
}

function quizzes_attempt_take(string $id, string $quizId, string $attemptId): void
{
    $subjectId = (int) $id;
    $quizIdInt = (int) $quizId;
    $attemptIdInt = (int) $attemptId;
    $viewerId = quizzes_current_user_id();

    $subject = quizzes_resolve_readable_subject_or_abort($subjectId);
    $quiz = quizzes_find_subject_published($quizIdInt, $subjectId);
    if (!$quiz) {
        abort(404, 'Quiz not found.');
    }

    $attempt = quizzes_find_attempt_for_user($attemptIdInt, $quizIdInt, $viewerId);
    if (!$attempt) {
        abort(404, 'Attempt not found.');
    }

    if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $attemptIdInt . '/result');
    }

    if (quizzes_attempt_is_expired($attempt)) {
        quizzes_submit_attempt($attemptIdInt, $quizIdInt, $viewerId, []);
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $attemptIdInt . '/result');
    }

    view('quizzes::attempt', [
        'subject' => $subject,
        'quiz' => $quiz,
        'attempt' => $attempt,
        'questions' => quizzes_questions_with_options($quizIdInt, false),
        'selected_answers' => quizzes_attempt_answers_map($attemptIdInt),
        'seconds_remaining' => quizzes_attempt_seconds_remaining($attempt),
        'role_label' => quizzes_role_label(),
    ], 'dashboard');
}

function quizzes_attempt_submit(string $id, string $quizId, string $attemptId): void
{
    csrf_check();

    $subjectId = (int) $id;
    $quizIdInt = (int) $quizId;
    $attemptIdInt = (int) $attemptId;
    $viewerId = quizzes_current_user_id();

    quizzes_resolve_readable_subject_or_abort($subjectId);
    $quiz = quizzes_find_subject_published($quizIdInt, $subjectId);
    if (!$quiz) {
        abort(404, 'Quiz not found.');
    }

    $attempt = quizzes_find_attempt_for_user($attemptIdInt, $quizIdInt, $viewerId);
    if (!$attempt) {
        abort(404, 'Attempt not found.');
    }

    if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $attemptIdInt . '/result');
    }

    $rawAnswers = $_POST['answers'] ?? [];
    if (!is_array($rawAnswers)) {
        $rawAnswers = [];
    }

    $selected = [];
    foreach ($rawAnswers as $questionId => $optionId) {
        $questionIdInt = (int) $questionId;
        $optionIdInt = (int) $optionId;
        if ($questionIdInt > 0) {
            $selected[$questionIdInt] = $optionIdInt;
        }
    }

    try {
        $result = quizzes_submit_attempt($attemptIdInt, $quizIdInt, $viewerId, $selected);
        if (!$result) {
            flash('error', 'Unable to submit this attempt.');
            redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt);
        }
    } catch (Throwable) {
        flash('error', 'Unable to submit this attempt right now.');
        redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt);
    }

    redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $attemptIdInt . '/result');
}

function quizzes_attempt_result(string $id, string $quizId, string $attemptId): void
{
    $subjectId = (int) $id;
    $quizIdInt = (int) $quizId;
    $attemptIdInt = (int) $attemptId;
    $viewerId = quizzes_current_user_id();

    $subject = quizzes_resolve_readable_subject_or_abort($subjectId);
    $quiz = quizzes_find_subject_published($quizIdInt, $subjectId);
    if (!$quiz) {
        abort(404, 'Quiz not found.');
    }

    $attempt = quizzes_attempt_result_for_user($attemptIdInt, $quizIdInt, $viewerId);
    if (!$attempt) {
        $inProgress = quizzes_find_attempt_for_user($attemptIdInt, $quizIdInt, $viewerId);
        if ($inProgress && (string) ($inProgress['status'] ?? '') === 'in_progress') {
            redirect('/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizIdInt . '/attempts/' . $attemptIdInt);
        }
        abort(404, 'Quiz result not found.');
    }

    view('quizzes::result', [
        'subject' => $subject,
        'quiz' => $quiz,
        'attempt' => $attempt,
        'result_rows' => quizzes_attempt_result_rows($attemptIdInt),
        'best_attempt' => quizzes_best_attempt_for_user($quizIdInt, $viewerId),
        'attempt_count' => quizzes_attempt_count_for_user($quizIdInt, $viewerId),
        'role_label' => quizzes_role_label(),
    ], 'dashboard');
}

function quizzes_my_index(): void
{
    quizzes_require_creator_role();

    view('quizzes::my_index', [
        'quizzes' => quizzes_my_list(quizzes_current_user_id()),
    ], 'dashboard');
}

function quizzes_my_edit_form(string $id): void
{
    quizzes_require_creator_role();

    $quizId = (int) $id;
    $ownerId = quizzes_current_user_id();

    $owned = quizzes_find_owned_with_subject($quizId, $ownerId);
    if (!$owned) {
        abort(404, 'Quiz not found.');
    }

    if (!in_array((string) ($owned['status'] ?? ''), ['draft', 'rejected'], true)) {
        abort(403, 'Only draft or rejected quizzes can be edited.');
    }

    $questions = quizzes_questions_with_options($quizId, true);

    view('quizzes::edit', [
        'quiz' => $owned,
        'subject' => [
            'id' => (int) ($owned['subject_id'] ?? 0),
            'code' => (string) ($owned['subject_code'] ?? ''),
            'name' => (string) ($owned['subject_name'] ?? ''),
        ],
        'form' => quizzes_form_payload_from_old_or_default($owned, $questions),
        'is_coordinator_creator' => user_role() === 'coordinator',
        'role_label' => quizzes_role_label(),
    ], 'dashboard');
}

function quizzes_my_update_action(string $id): void
{
    csrf_check();
    quizzes_require_creator_role();

    $quizId = (int) $id;
    $ownerId = quizzes_current_user_id();

    $owned = quizzes_find_owned_with_subject($quizId, $ownerId);
    if (!$owned) {
        abort(404, 'Quiz not found.');
    }

    if (!in_array((string) ($owned['status'] ?? ''), ['draft', 'rejected'], true)) {
        abort(403, 'Only draft or rejected quizzes can be edited.');
    }

    $payload = quizzes_extract_form_payload($_POST);
    $validated = quizzes_validate_form_payload($payload);

    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/my-quizzes/' . $quizId . '/edit');
    }

    $validatedPayload = $validated['validated'];
    $targetStatus = quizzes_target_status_for_creator((string) $validatedPayload['intent']);

    try {
        $updated = quizzes_update_owned_with_questions($quizId, $ownerId, [
            'title' => $validatedPayload['title'],
            'description' => $validatedPayload['description'],
            'duration_minutes' => $validatedPayload['duration_minutes'],
            'status' => $targetStatus,
            'rejection_reason' => null,
            'reviewed_by_user_id' => $targetStatus === 'approved' ? $ownerId : null,
            'reviewed_at' => $targetStatus === 'approved' ? date('Y-m-d H:i:s') : null,
        ], $validatedPayload['questions']);

        if (!$updated) {
            flash('error', 'Unable to update this quiz.');
            redirect('/my-quizzes/' . $quizId . '/edit');
        }
    } catch (Throwable) {
        flash('error', 'Unable to update this quiz right now.');
        flash_old_input();
        redirect('/my-quizzes/' . $quizId . '/edit');
    }

    clear_old_input();
    flash('success', quizzes_success_message_for_status($targetStatus));
    redirect('/my-quizzes');
}

function quizzes_my_submit_action(string $id): void
{
    csrf_check();
    quizzes_require_creator_role();

    $quizId = (int) $id;
    $ownerId = quizzes_current_user_id();

    $owned = quizzes_find_owned_with_subject($quizId, $ownerId);
    if (!$owned) {
        abort(404, 'Quiz not found.');
    }

    if (!in_array((string) ($owned['status'] ?? ''), ['draft', 'rejected'], true)) {
        abort(403, 'Only draft or rejected quizzes can be submitted.');
    }

    if (!quizzes_has_at_least_one_question($quizId)) {
        flash('error', 'Add at least one question before submitting.');
        redirect('/my-quizzes/' . $quizId . '/edit');
    }

    $targetStatus = user_role() === 'coordinator' ? 'approved' : 'pending';
    $reviewedBy = $targetStatus === 'approved' ? $ownerId : null;

    if (!quizzes_submit_owned($quizId, $ownerId, $targetStatus, $reviewedBy)) {
        flash('error', 'Unable to submit this quiz right now.');
        redirect('/my-quizzes');
    }

    flash('success', $targetStatus === 'approved'
        ? 'Quiz published successfully.'
        : 'Quiz submitted for review.');
    redirect('/my-quizzes');
}

function quizzes_my_delete_action(string $id): void
{
    csrf_check();
    quizzes_require_creator_role();

    $quizId = (int) $id;
    $ownerId = quizzes_current_user_id();

    $owned = quizzes_find_owned_with_subject($quizId, $ownerId);
    if (!$owned) {
        abort(404, 'Quiz not found.');
    }

    if (!in_array((string) ($owned['status'] ?? ''), ['draft', 'rejected'], true)) {
        abort(403, 'Only draft or rejected quizzes can be deleted.');
    }

    if (!quizzes_delete_editable_owned($quizId, $ownerId)) {
        flash('error', 'Unable to delete this quiz.');
        redirect('/my-quizzes');
    }

    flash('success', 'Quiz deleted successfully.');
    redirect('/my-quizzes');
}

function quizzes_review_queue_index(): void
{
    quizzes_require_reviewer_role();

    $status = trim((string) request_input('status', 'pending'));
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }

    $role = (string) user_role();
    $reviewerId = quizzes_current_user_id();
    $reviewerBatchId = quizzes_current_batch_id();

    $items = quizzes_review_list($status, $role, $reviewerId, $reviewerBatchId);
    $quizIds = array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $items);

    view('quizzes::review_queue', [
        'status_filter' => $status,
        'metrics' => quizzes_review_metrics($role, $reviewerId, $reviewerBatchId),
        'items' => $items,
        'samples_by_quiz' => quizzes_review_samples_for_quizzes($quizIds, 3),
    ], 'dashboard');
}

function quizzes_review_approve(string $id): void
{
    csrf_check();
    quizzes_require_reviewer_role();

    $quizId = (int) $id;
    $reviewerId = quizzes_current_user_id();
    $role = (string) user_role();
    $batchId = quizzes_current_batch_id();

    $pending = quizzes_find_pending_for_reviewer($quizId, $role, $reviewerId, $batchId);
    if (!$pending) {
        abort(404, 'Pending quiz not found.');
    }

    if (!quizzes_mark_approved($quizId, $reviewerId)) {
        flash('error', 'Unable to approve this quiz.');
        redirect('/dashboard/quiz-requests');
    }

    flash('success', 'Quiz approved and published.');
    redirect('/dashboard/quiz-requests?status=pending');
}

function quizzes_review_reject(string $id): void
{
    csrf_check();
    quizzes_require_reviewer_role();

    $quizId = (int) $id;
    $reviewerId = quizzes_current_user_id();
    $role = (string) user_role();
    $batchId = quizzes_current_batch_id();

    $pending = quizzes_find_pending_for_reviewer($quizId, $role, $reviewerId, $batchId);
    if (!$pending) {
        abort(404, 'Pending quiz not found.');
    }

    $reason = trim((string) request_input('rejection_reason', ''));
    if ($reason === '') {
        flash('error', 'Rejection reason is required.');
        redirect('/dashboard/quiz-requests?status=pending');
    }

    if (!quizzes_mark_rejected($quizId, $reviewerId, $reason)) {
        flash('error', 'Unable to reject this quiz.');
        redirect('/dashboard/quiz-requests?status=pending');
    }

    flash('success', 'Quiz rejected.');
    redirect('/dashboard/quiz-requests?status=pending');
}
