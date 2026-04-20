<?php

/**
 * Subjects Module — Controllers
 */

function subjects_resolve_manageable_subject(int $subjectId): ?array
{
    if (user_role() === 'admin') {
        return subjects_find_admin($subjectId);
    }

    if (user_role() === 'moderator') {
        $batchId = (int) (auth_user()['batch_id'] ?? 0);
        return subjects_find_for_batch($subjectId, $batchId);
    }

    return null;
}

function subjects_index(): void
{
    $isAdmin = user_role() === 'admin';

    if ($isAdmin) {
        $subjects = subjects_all_admin();
    } else {
        $batchId = (int) (auth_user()['batch_id'] ?? 0);
        $subjects = subjects_all_for_batch($batchId);
    }

    view('subjects::index', [
        'subjects' => $subjects,
        'is_admin' => $isAdmin,
    ], 'dashboard');
}

function subjects_create_form(): void
{
    $isAdmin = user_role() === 'admin';
    $batches = $isAdmin ? onboarding_approved_batches() : [];

    view('subjects::create', [
        'is_admin' => $isAdmin,
        'batches' => $batches,
    ], 'dashboard');
}

function subjects_store(): void
{
    csrf_check();

    $isAdmin = user_role() === 'admin';
    $code = strtoupper(trim(request_input('code', '')));
    $name = trim(request_input('name', ''));
    $description = trim(request_input('description', ''));
    $credits = (int) request_input('credits', 3);
    $academicYear = (int) request_input('academic_year', 1);
    $semester = (int) request_input('semester', 1);
    $batchId = $isAdmin ? (int) request_input('batch_id', 0) : (int) (auth_user()['batch_id'] ?? 0);

    $errors = [];
    if ($code === '') $errors[] = 'Subject code is required.';
    if ($name === '') $errors[] = 'Subject name is required.';
    if ($credits < 1 || $credits > 10) $errors[] = 'Credits must be between 1 and 10.';
    if ($academicYear < 1 || $academicYear > 4) $errors[] = 'Academic year must be between 1 and 4.';
    if ($semester < 1 || $semester > 2) $errors[] = 'Semester must be between 1 and 2.';

    if ($batchId <= 0) {
        $errors[] = 'A valid batch is required.';
    } else {
        $batch = onboarding_batch_by_id($batchId);
        if (!$batch || $batch['status'] !== 'approved') {
            $errors[] = 'Selected batch is invalid or inactive.';
        }
    }

    if ($code !== '' && $batchId > 0 && subjects_code_exists_in_batch($code, $batchId)) {
        $errors[] = 'A subject with this code already exists in the selected batch.';
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/subjects/create');
    }

    subjects_create([
        'batch_id' => $batchId,
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'credits' => $credits,
        'academic_year' => $academicYear,
        'semester' => $semester,
    ]);

    clear_old_input();
    flash('success', 'Subject created successfully.');
    redirect('/subjects');
}

function subjects_edit_form(string $id): void
{
    $subjectId = (int) $id;
    $isAdmin = user_role() === 'admin';
    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    $subject = $isAdmin ? subjects_find_admin($subjectId) : subjects_find_for_batch($subjectId, $batchId);

    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    view('subjects::edit', [
        'subject' => $subject,
        'is_admin' => $isAdmin,
        'batches' => $isAdmin ? onboarding_approved_batches() : [],
    ], 'dashboard');
}

function subjects_update_action(string $id): void
{
    csrf_check();

    $subjectId = (int) $id;
    $isAdmin = user_role() === 'admin';
    $sessionBatch = (int) (auth_user()['batch_id'] ?? 0);
    $subject = $isAdmin ? subjects_find_admin($subjectId) : subjects_find_for_batch($subjectId, $sessionBatch);

    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    $code = strtoupper(trim(request_input('code', '')));
    $name = trim(request_input('name', ''));
    $description = trim(request_input('description', ''));
    $credits = (int) request_input('credits', 3);
    $academicYear = (int) request_input('academic_year', 1);
    $semester = (int) request_input('semester', 1);
    $batchId = $isAdmin ? (int) request_input('batch_id', 0) : (int) $subject['batch_id'];

    $errors = [];
    if ($code === '') $errors[] = 'Subject code is required.';
    if ($name === '') $errors[] = 'Subject name is required.';
    if ($credits < 1 || $credits > 10) $errors[] = 'Credits must be between 1 and 10.';
    if ($academicYear < 1 || $academicYear > 4) $errors[] = 'Academic year must be between 1 and 4.';
    if ($semester < 1 || $semester > 2) $errors[] = 'Semester must be between 1 and 2.';

    if ($batchId <= 0) {
        $errors[] = 'A valid batch is required.';
    } else {
        $batch = onboarding_batch_by_id($batchId);
        if (!$batch || $batch['status'] !== 'approved') {
            $errors[] = 'Selected batch is invalid or inactive.';
        }
    }

    if ($code !== '' && $batchId > 0 && subjects_code_exists_in_batch($code, $batchId, $subjectId)) {
        $errors[] = 'A subject with this code already exists in the selected batch.';
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/subjects/' . $subjectId . '/edit');
    }

    subjects_update_data($subjectId, [
        'batch_id' => $batchId,
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'credits' => $credits,
        'academic_year' => $academicYear,
        'semester' => $semester,
    ]);

    clear_old_input();
    flash('success', 'Subject updated successfully.');
    redirect('/subjects');
}

function subjects_delete_action(string $id): void
{
    csrf_check();

    $subjectId = (int) $id;
    $subject = subjects_resolve_manageable_subject($subjectId);
    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    subjects_delete_by_id($subjectId);
    flash('success', 'Subject "' . $subject['name'] . '" deleted.');
    redirect('/subjects');
}

function subjects_coordinators_page(string $id): void
{
    $subjectId = (int) $id;
    $subject = subjects_resolve_manageable_subject($subjectId);
    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    view('subjects::coordinators', [
        'subject' => $subject,
        'is_admin' => user_role() === 'admin',
        'coordinators' => subjects_coordinators_for_subject($subjectId),
        'candidates' => subjects_coordinator_candidates_for_subject($subjectId),
    ], 'dashboard');
}

function subjects_coordinator_assign_action(string $id): void
{
    csrf_check();

    $subjectId = (int) $id;
    $subject = subjects_resolve_manageable_subject($subjectId);
    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    $studentId = (int) request_input('student_user_id', 0);
    if ($studentId <= 0) {
        flash('error', 'Select a valid student to assign.');
        redirect('/subjects/' . $subjectId . '/coordinators');
    }

    $result = subjects_assign_coordinator($subjectId, $studentId, (int) auth_id());
    if ($result === 'invalid_student') {
        flash('error', 'Only students in this subject batch can be assigned as coordinators.');
    } elseif ($result === 'subject_not_found') {
        flash('error', 'Subject not found.');
    } elseif ($result === 'exists') {
        flash('success', 'Coordinator assignment refreshed.');
    } else {
        flash('success', 'Coordinator assigned successfully.');
    }

    redirect('/subjects/' . $subjectId . '/coordinators');
}

function subjects_coordinator_unassign_action(string $id, string $studentId): void
{
    csrf_check();

    $subjectId = (int) $id;
    $subject = subjects_resolve_manageable_subject($subjectId);
    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    $targetStudentId = (int) $studentId;
    if ($targetStudentId <= 0) {
        flash('error', 'Invalid coordinator selected.');
        redirect('/subjects/' . $subjectId . '/coordinators');
    }

    $result = subjects_unassign_coordinator($subjectId, $targetStudentId);
    if ($result === 'removed') {
        flash('success', 'Coordinator removed from this subject.');
    } else {
        flash('error', 'Coordinator assignment not found.');
    }

    redirect('/subjects/' . $subjectId . '/coordinators');
}

function subjects_coordinator_index(): void
{
    middleware_exact_role('coordinator');

    $subjects = subjects_all_for_coordinator((int) auth_id());

    view('subjects::coordinator_index', [
        'subjects' => $subjects,
    ], 'dashboard');
}

function subjects_coordinator_edit_form(string $id): void
{
    middleware_exact_role('coordinator');

    $subjectId = (int) $id;
    $subject = subjects_find_for_coordinator($subjectId, (int) auth_id());
    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    view('subjects::coordinator_edit', [
        'subject' => $subject,
    ], 'dashboard');
}

function subjects_coordinator_update_action(string $id): void
{
    csrf_check();
    middleware_exact_role('coordinator');

    $subjectId = (int) $id;
    $subject = subjects_find_for_coordinator($subjectId, (int) auth_id());
    if (!$subject) {
        abort(404, 'Subject not found.');
    }

    $code = strtoupper(trim(request_input('code', '')));
    $name = trim(request_input('name', ''));
    $description = trim(request_input('description', ''));
    $credits = (int) request_input('credits', 3);
    $academicYear = (int) request_input('academic_year', 1);
    $semester = (int) request_input('semester', 1);
    $batchId = (int) $subject['batch_id'];

    $errors = [];
    if ($code === '') $errors[] = 'Subject code is required.';
    if ($name === '') $errors[] = 'Subject name is required.';
    if ($credits < 1 || $credits > 10) $errors[] = 'Credits must be between 1 and 10.';
    if ($academicYear < 1 || $academicYear > 4) $errors[] = 'Academic year must be between 1 and 4.';
    if ($semester < 1 || $semester > 2) $errors[] = 'Semester must be between 1 and 2.';
    if ($code !== '' && subjects_code_exists_in_batch($code, $batchId, $subjectId)) {
        $errors[] = 'A subject with this code already exists in your batch.';
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        flash_old_input();
        redirect('/coordinator/subjects/' . $subjectId . '/edit');
    }

    subjects_update_data($subjectId, [
        'batch_id' => $batchId,
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'credits' => $credits,
        'academic_year' => $academicYear,
        'semester' => $semester,
    ]);

    clear_old_input();
    flash('success', 'Subject updated successfully.');
    redirect('/coordinator/subjects');
}

function subjects_student_list(): void
{
    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    $termTabs = subjects_terms_for_batch($batchId);
    $latestTerm = !empty($termTabs)
        ? [
            'academic_year' => (int) $termTabs[0]['academic_year'],
            'semester' => (int) $termTabs[0]['semester'],
        ]
        : null;

    $yearRaw = trim((string) request_input('year', ''));
    $semesterRaw = trim((string) request_input('semester', ''));
    $queryRaw = trim((string) request_input('q', ''));

    $requestedYear = ctype_digit($yearRaw) ? (int) $yearRaw : null;
    $requestedSemester = ctype_digit($semesterRaw) ? (int) $semesterRaw : null;
    $activeTerm = null;

    if ($requestedYear !== null && $requestedSemester !== null) {
        foreach ($termTabs as $term) {
            if ((int) $term['academic_year'] === $requestedYear && (int) $term['semester'] === $requestedSemester) {
                $activeTerm = [
                    'academic_year' => $requestedYear,
                    'semester' => $requestedSemester,
                ];
                break;
            }
        }
    }

    if ($activeTerm === null && $latestTerm !== null) {
        $activeTerm = $latestTerm;
    }

    $yearFilter = $activeTerm['academic_year'] ?? null;
    $semesterFilter = $activeTerm['semester'] ?? null;
    $queryFilter = substr($queryRaw, 0, 80);

    $filters = [
        'year' => $yearFilter,
        'semester' => $semesterFilter,
        'q' => $queryFilter,
    ];

    $perPage = 12;
    $page = max(1, (int) request_input('page', 1));
    $totalSubjects = subjects_student_count_for_batch($batchId, $filters);
    $totalPages = max(1, (int) ceil($totalSubjects / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $subjects = subjects_student_list_for_batch($batchId, $filters, $perPage, ($page - 1) * $perPage);

    view('subjects::student_list', [
        'subjects' => $subjects,
        'term_tabs' => $termTabs,
        'active_term' => $activeTerm,
        'filters' => [
            'year' => $yearFilter !== null ? (string) $yearFilter : '',
            'semester' => $semesterFilter !== null ? (string) $semesterFilter : '',
            'q' => $queryFilter,
        ],
        'total_subjects' => $totalSubjects,
        'page' => $page,
        'total_pages' => $totalPages,
        'per_page' => $perPage,
    ], 'dashboard');
}
