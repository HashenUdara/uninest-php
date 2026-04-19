<?php

function kuppi_user_is_scheduler(): bool
{
    return in_array((string) user_role(), ['coordinator', 'moderator', 'admin'], true);
}

function kuppi_user_can_schedule_subject(int $subjectId, int $batchId): bool
{
    if ($subjectId <= 0 || $batchId <= 0) {
        return false;
    }

    $role = (string) user_role();
    $currentUser = (array) auth_user();
    $currentUserId = (int) ($currentUser['id'] ?? 0);
    $currentBatchId = (int) ($currentUser['batch_id'] ?? 0);

    if ($role === 'admin') {
        return kuppi_subject_exists_in_batch($subjectId, $batchId);
    }

    if ($currentBatchId !== $batchId) {
        return false;
    }

    if ($role === 'moderator') {
        return kuppi_subject_exists_in_batch($subjectId, $batchId);
    }

    if ($role === 'coordinator') {
        return subjects_find_for_coordinator($subjectId, $currentUserId) !== null;
    }

    return false;
}

function kuppi_user_can_schedule_request(array $request): bool
{
    if (!kuppi_user_is_scheduler()) {
        return false;
    }

    return kuppi_user_can_schedule_subject(
        (int) ($request['subject_id'] ?? 0),
        (int) ($request['batch_id'] ?? 0)
    );
}

function kuppi_user_can_manage_scheduled_session(array $session): bool
{
    if (!kuppi_user_is_scheduler()) {
        return false;
    }

    return kuppi_user_can_schedule_subject(
        (int) ($session['subject_id'] ?? 0),
        (int) ($session['batch_id'] ?? 0)
    );
}

function kuppi_schedule_draft_key(): string
{
    return 'kuppi_schedule_draft';
}

function kuppi_schedule_get_draft(): array
{
    $draft = $_SESSION[kuppi_schedule_draft_key()] ?? [];
    return is_array($draft) ? $draft : [];
}

function kuppi_schedule_set_draft(array $draft): void
{
    $_SESSION[kuppi_schedule_draft_key()] = $draft;
}

function kuppi_schedule_clear_draft(): void
{
    unset($_SESSION[kuppi_schedule_draft_key()]);
}

function kuppi_schedule_require_draft(): array
{
    $draft = kuppi_schedule_get_draft();
    if (empty($draft)) {
        flash('warning', 'Start scheduling by selecting a request or manual mode.');
        redirect('/dashboard/kuppi/schedule');
    }
    return $draft;
}

function kuppi_schedule_default_draft(): array
{
    return [
        'mode' => 'request',
        'batch_id' => 0,
        'subject_id' => 0,
        'request_id' => 0,
        'title' => '',
        'description' => '',
        'session_date' => '',
        'start_time' => '',
        'end_time' => '',
        'duration_minutes' => 0,
        'max_attendees' => 25,
        'location_type' => 'physical',
        'location_text' => '',
        'meeting_link' => '',
        'notes' => '',
        'host_user_ids' => [],
    ];
}

function kuppi_schedule_resolve_request_for_draft(array $draft): ?array
{
    $requestId = (int) ($draft['request_id'] ?? 0);
    if ($requestId <= 0) {
        return null;
    }

    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        return null;
    }

    if (!kuppi_user_can_schedule_request($request)) {
        return null;
    }

    return $request;
}

function kuppi_schedule_validate_date_time(
    string $sessionDate,
    string $startTime,
    string $endTime
): array {
    $errors = [];
    $durationMinutes = 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate) || strtotime($sessionDate) === false) {
        $errors[] = 'Valid session date is required.';
    } elseif ($sessionDate < date('Y-m-d')) {
        $errors[] = 'Session date cannot be in the past.';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        $errors[] = 'Valid start time is required.';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        $errors[] = 'Valid end time is required.';
    }

    if (empty($errors)) {
        $startTs = strtotime($sessionDate . ' ' . $startTime . ':00');
        $endTs = strtotime($sessionDate . ' ' . $endTime . ':00');
        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            $errors[] = 'End time must be after start time.';
        } else {
            $durationMinutes = (int) floor(($endTs - $startTs) / 60);
        }
    }

    return [
        'errors' => $errors,
        'duration_minutes' => $durationMinutes,
    ];
}

function kuppi_schedule_validate_set_input(array $draft): array
{
    $role = (string) user_role();
    $currentUser = (array) auth_user();
    $currentUserId = (int) ($currentUser['id'] ?? 0);
    $currentBatchId = (int) ($currentUser['batch_id'] ?? 0);
    $mode = (string) ($draft['mode'] ?? 'request');
    $errors = [];

    $batchId = $mode === 'manual'
        ? ($role === 'admin' ? (int) request_input('batch_id', 0) : $currentBatchId)
        : (int) ($draft['batch_id'] ?? 0);

    $request = null;
    if ($mode === 'request') {
        $request = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$request) {
            $errors[] = 'Selected request is no longer available.';
        } else {
            $requestId = (int) ($request['id'] ?? 0);
            if ($requestId > 0 && kuppi_scheduled_session_has_active_for_request($requestId)) {
                $errors[] = 'This request already has an active scheduled session.';
            }

            if ((string) ($request['status'] ?? '') !== 'open') {
                $errors[] = 'Only open requests can be scheduled.';
            }
        }
    }

    if ($batchId <= 0) {
        $errors[] = 'Batch is required.';
    }

    $subjectId = $mode === 'request'
        ? (int) (($request['subject_id'] ?? $draft['subject_id']) ?? 0)
        : (int) request_input('subject_id', 0);
    if ($subjectId <= 0) {
        $errors[] = 'Subject is required.';
    } elseif (!kuppi_user_can_schedule_subject($subjectId, $batchId)) {
        $errors[] = 'You do not have permission to schedule this subject.';
    }

    $title = $mode === 'request'
        ? (string) (($request['title'] ?? $draft['title']) ?? '')
        : trim((string) request_input('title', ''));
    $description = $mode === 'request'
        ? (string) (($request['description'] ?? $draft['description']) ?? '')
        : trim((string) request_input('description', ''));

    if ($title === '') {
        $errors[] = 'Session title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Session title must be at most 200 characters.';
    }

    if ($description === '') {
        $errors[] = 'Session description is required.';
    } elseif (strlen($description) > 2000) {
        $errors[] = 'Session description must be at most 2000 characters.';
    }

    $sessionDate = trim((string) request_input('session_date', ''));
    $startTime = trim((string) request_input('start_time', ''));
    $endTime = trim((string) request_input('end_time', ''));
    $timeValidation = kuppi_schedule_validate_date_time($sessionDate, $startTime, $endTime);
    $errors = array_merge($errors, $timeValidation['errors']);
    $durationMinutes = (int) ($timeValidation['duration_minutes'] ?? 0);
    $timetableConflicts = [];

    if ($batchId > 0 && empty($timeValidation['errors'])) {
        $timetableConflicts = kuppi_university_timetable_conflicts_for_session($batchId, $sessionDate, $startTime, $endTime);
        if (!empty($timetableConflicts)) {
            $preview = array_slice(array_map('kuppi_timetable_slot_summary', $timetableConflicts), 0, 2);
            $suffix = count($timetableConflicts) > 2 ? ' and additional blocked slots.' : '.';
            $errors[] = 'Selected session time conflicts with official university lecture slots: '
                . implode('; ', $preview)
                . $suffix;
        }
    }

    $maxAttendees = (int) request_input('max_attendees', 0);
    if ($maxAttendees <= 0 || $maxAttendees > 2000) {
        $errors[] = 'Maximum attendees must be between 1 and 2000.';
    }

    $locationType = trim((string) request_input('location_type', 'physical'));
    if (!in_array($locationType, kuppi_scheduled_location_types(), true)) {
        $errors[] = 'Valid location type is required.';
    }

    $locationText = trim((string) request_input('location_text', ''));
    $meetingLink = trim((string) request_input('meeting_link', ''));
    if ($locationType === 'physical') {
        if ($locationText === '') {
            $errors[] = 'Physical location is required.';
        } elseif (strlen($locationText) > 255) {
            $errors[] = 'Physical location must be at most 255 characters.';
        }
        $meetingLink = '';
    } else {
        if ($meetingLink === '') {
            $errors[] = 'Meeting link is required for online sessions.';
        } elseif (!filter_var($meetingLink, FILTER_VALIDATE_URL)) {
            $errors[] = 'Meeting link must be a valid URL.';
        } elseif (strlen($meetingLink) > 255) {
            $errors[] = 'Meeting link must be at most 255 characters.';
        }
        $locationText = '';
    }

    $notes = trim((string) request_input('notes', ''));
    if (strlen($notes) > 3000) {
        $errors[] = 'Notes must be at most 3000 characters.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'mode' => $mode,
            'batch_id' => $batchId,
            'subject_id' => $subjectId,
            'request_id' => $mode === 'request' ? (int) (($request['id'] ?? $draft['request_id']) ?? 0) : 0,
            'title' => $title,
            'description' => $description,
            'session_date' => $sessionDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'max_attendees' => $maxAttendees,
            'location_type' => $locationType,
            'location_text' => $locationText,
            'meeting_link' => $meetingLink,
            'notes' => $notes,
            'updated_by_user_id' => $currentUserId,
        ],
        'timetable_conflicts' => $timetableConflicts,
    ];
}

function kuppi_schedule_host_candidates(array $draft): array
{
    $mode = (string) ($draft['mode'] ?? 'request');
    $candidates = [];

    if ($mode === 'request') {
        $requestId = (int) ($draft['request_id'] ?? 0);
        $requestCandidates = kuppi_schedule_conductor_candidates_for_request($requestId);
        if (!empty($requestCandidates)) {
            foreach ($requestCandidates as $row) {
                $candidates[] = [
                    'host_user_id' => (int) ($row['host_user_id'] ?? 0),
                    'host_name' => (string) ($row['host_name'] ?? 'Unknown User'),
                    'host_email' => (string) ($row['host_email'] ?? ''),
                    'host_role' => (string) ($row['host_role'] ?? 'student'),
                    'host_academic_year' => (int) ($row['host_academic_year'] ?? 0),
                    'source_type' => 'request_conductor',
                    'source_application_id' => (int) ($row['application_id'] ?? 0),
                    'vote_count' => (int) ($row['vote_count'] ?? 0),
                    'availability' => kuppi_conductor_availability_from_csv((string) ($row['availability_csv'] ?? '')),
                ];
            }

            return $candidates;
        }

        $batchId = (int) ($draft['batch_id'] ?? 0);
        if ($batchId <= 0) {
            $resolvedRequest = kuppi_schedule_resolve_request_for_draft($draft);
            $batchId = (int) ($resolvedRequest['batch_id'] ?? 0);
        }

        foreach (kuppi_schedule_manual_host_candidates_for_batch($batchId) as $row) {
            $candidates[] = [
                'host_user_id' => (int) ($row['host_user_id'] ?? 0),
                'host_name' => (string) ($row['host_name'] ?? 'Unknown User'),
                'host_email' => (string) ($row['host_email'] ?? ''),
                'host_role' => (string) ($row['host_role'] ?? 'student'),
                'host_academic_year' => (int) ($row['host_academic_year'] ?? 0),
                'source_type' => 'manual',
                'source_application_id' => null,
                'vote_count' => 0,
                'availability' => [],
            ];
        }

        return $candidates;
    }

    $batchId = (int) ($draft['batch_id'] ?? 0);
    foreach (kuppi_schedule_manual_host_candidates_for_batch($batchId) as $row) {
        $candidates[] = [
            'host_user_id' => (int) ($row['host_user_id'] ?? 0),
            'host_name' => (string) ($row['host_name'] ?? 'Unknown User'),
            'host_email' => (string) ($row['host_email'] ?? ''),
            'host_role' => (string) ($row['host_role'] ?? 'student'),
            'host_academic_year' => (int) ($row['host_academic_year'] ?? 0),
            'source_type' => 'manual',
            'source_application_id' => null,
            'vote_count' => 0,
            'availability' => [],
        ];
    }

    return $candidates;
}

function kuppi_schedule_candidate_map(array $candidates): array
{
    $map = [];
    foreach ($candidates as $candidate) {
        $hostUserId = (int) ($candidate['host_user_id'] ?? 0);
        if ($hostUserId <= 0) {
            continue;
        }
        $map[$hostUserId] = $candidate;
    }
    return $map;
}

function kuppi_schedule_default_host_ids(array $draft, array $candidates): array
{
    $existing = (array) ($draft['host_user_ids'] ?? []);
    if (!empty($existing)) {
        return array_values(array_unique(array_map('intval', $existing)));
    }

    if ((string) ($draft['mode'] ?? 'request') !== 'request') {
        return [];
    }

    $maxVotes = 0;
    foreach ($candidates as $candidate) {
        $votes = (int) ($candidate['vote_count'] ?? 0);
        if ($votes > $maxVotes) {
            $maxVotes = $votes;
        }
    }

    if ($maxVotes <= 0) {
        return [];
    }

    $selected = [];
    foreach ($candidates as $candidate) {
        if ((int) ($candidate['vote_count'] ?? 0) === $maxVotes) {
            $selected[] = (int) ($candidate['host_user_id'] ?? 0);
        }
    }

    return array_values(array_filter(array_unique($selected), static fn(int $id): bool => $id > 0));
}

function kuppi_schedule_selected_hosts_from_input(array $candidateMap): array
{
    $selectedRaw = $_POST['host_user_ids'] ?? [];
    $selectedList = is_array($selectedRaw) ? $selectedRaw : [];
    $selectedIds = kuppi_schedule_normalize_host_ids($selectedList);

    $errors = [];
    if (empty($selectedIds)) {
        $errors[] = 'Select at least one host.';
    }

    $hosts = [];
    foreach ($selectedIds as $hostUserId) {
        if (!isset($candidateMap[$hostUserId])) {
            $errors[] = 'One or more selected hosts are invalid.';
            continue;
        }

        $candidate = $candidateMap[$hostUserId];
        $hosts[] = [
            'host_user_id' => $hostUserId,
            'source_type' => (string) ($candidate['source_type'] ?? 'manual'),
            'source_application_id' => !empty($candidate['source_application_id']) ? (int) $candidate['source_application_id'] : null,
            'assigned_by_user_id' => (int) auth_id(),
        ];
    }

    return [
        'errors' => array_values(array_unique($errors)),
        'selected_ids' => $selectedIds,
        'hosts' => $hosts,
    ];
}

function kuppi_schedule_notify(array $session, array $hosts, string $event): void
{
    if (!smtp_is_configured()) {
        return;
    }

    $batchId = (int) ($session['batch_id'] ?? 0);
    $requestId = (int) ($session['request_id'] ?? 0);
    $title = (string) ($session['title'] ?? 'Kuppi Session');
    $subjectCode = (string) ($session['subject_code'] ?? '');
    $sessionDate = (string) ($session['session_date'] ?? '');
    $startTime = (string) ($session['start_time'] ?? '');
    $endTime = (string) ($session['end_time'] ?? '');
    $locationType = (string) ($session['location_type'] ?? 'physical');
    $location = $locationType === 'online'
        ? (string) ($session['meeting_link'] ?? '')
        : (string) ($session['location_text'] ?? '');

    $recipientMap = [];
    foreach (kuppi_scheduled_notification_batch_recipients($batchId) as $row) {
        $email = strtolower(trim((string) ($row['user_email'] ?? '')));
        if ($email === '') {
            continue;
        }
        $recipientMap[$email] = (string) ($row['user_name'] ?? 'Student');
    }

    if ($requestId > 0) {
        $owner = kuppi_scheduled_notification_request_owner($requestId);
        if ($owner) {
            $ownerEmail = strtolower(trim((string) ($owner['user_email'] ?? '')));
            if ($ownerEmail !== '') {
                $recipientMap[$ownerEmail] = (string) ($owner['user_name'] ?? 'Student');
            }
        }
    }

    foreach ($hosts as $host) {
        $hostEmail = strtolower(trim((string) ($host['host_email'] ?? '')));
        if ($hostEmail !== '') {
            $recipientMap[$hostEmail] = (string) ($host['host_name'] ?? 'Host');
        }
    }

    if (empty($recipientMap)) {
        return;
    }

    $subjectPrefix = match ($event) {
        'created' => 'New Kuppi Session Scheduled',
        'updated' => 'Kuppi Session Updated',
        'cancelled' => 'Kuppi Session Cancelled',
        'deleted' => 'Kuppi Session Removed',
        default => 'Kuppi Session Notification',
    };

    $subjectLine = $subjectPrefix . ': ' . $title;
    $dateLabel = $sessionDate !== '' ? date('F j, Y', strtotime($sessionDate)) : 'TBD';
    $startedAt = microtime(true);
    $timeBudgetSeconds = 8.0;

    foreach ($recipientMap as $email => $name) {
        if ((microtime(true) - $startedAt) >= $timeBudgetSeconds) {
            error_log('Kuppi schedule email budget exceeded; remaining recipients skipped.');
            break;
        }

        $bodyLines = [
            'Hello ' . $name . ',',
            '',
            $subjectPrefix . '.',
            '',
            'Title: ' . $title,
            'Subject: ' . ($subjectCode !== '' ? $subjectCode : 'N/A'),
            'Date: ' . $dateLabel,
            'Time: ' . ($startTime !== '' && $endTime !== '' ? (substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5)) : 'TBD'),
            'Location: ' . ($location !== '' ? $location : 'TBD'),
        ];

        if (!smtp_send_email($email, $subjectLine, implode("\n", $bodyLines))) {
            error_log('Kuppi schedule email failed for: ' . $email . ' (' . $event . ')');
        }
    }
}

