<?php

/**
 * Kuppi Module — Controllers
 */

function kuppi_feed_per_page(): int
{
    return 10;
}

function kuppi_relative_time_label(string $timestamp): string
{
    $ts = strtotime($timestamp);
    if ($ts === false) {
        return 'just now';
    }

    $delta = time() - $ts;
    if ($delta <= 0) {
        return 'just now';
    }

    if ($delta < 60) {
        return 'just now';
    }

    $units = [
        ['seconds' => 604800, 'label' => 'week'],
        ['seconds' => 86400, 'label' => 'day'],
        ['seconds' => 3600, 'label' => 'hour'],
        ['seconds' => 60, 'label' => 'minute'],
    ];

    foreach ($units as $unit) {
        $seconds = (int) ($unit['seconds'] ?? 0);
        if ($seconds <= 0 || $delta < $seconds) {
            continue;
        }

        $value = (int) floor($delta / $seconds);
        $label = (string) ($unit['label'] ?? 'minute');
        return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
    }

    return 'just now';
}

function kuppi_user_can_create(): bool
{
    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) > 0;
}

function kuppi_user_can_moderate_batch(int $batchId): bool
{
    if ($batchId <= 0) {
        return false;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        return true;
    }

    if ($role !== 'moderator') {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === $batchId;
}

function kuppi_user_can_vote_request(array $request): bool
{
    $requestBatchId = (int) ($request['batch_id'] ?? 0);
    if ($requestBatchId <= 0) {
        return false;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        $selectedBatchId = (int) request_input('batch_id', 0);
        return $selectedBatchId > 0 && $selectedBatchId === $requestBatchId;
    }

    if (!in_array($role, ['student', 'coordinator', 'moderator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === $requestBatchId;
}

function kuppi_user_can_apply_as_conductor(array $request): bool
{
    if (!kuppi_request_is_open($request)) {
        return false;
    }

    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === (int) ($request['batch_id'] ?? 0);
}

function kuppi_user_can_vote_conductor(array $request): bool
{
    if (!kuppi_request_is_open($request)) {
        return false;
    }

    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator'], true)) {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === (int) ($request['batch_id'] ?? 0);
}

function kuppi_request_is_open(array $request): bool
{
    return (string) ($request['status'] ?? '') === 'open';
}

function kuppi_conductor_availability_options(): array
{
    return [
        'weekday_mornings' => 'Weekday Mornings',
        'weekday_afternoons' => 'Weekday Afternoons',
        'weekday_evenings' => 'Weekday Evenings',
        'weekend_mornings' => 'Weekend Mornings',
        'weekend_afternoons' => 'Weekend Afternoons',
        'weekend_evenings' => 'Weekend Evenings',
    ];
}

function kuppi_conductor_availability_to_csv(array $selected): string
{
    $allowed = array_keys(kuppi_conductor_availability_options());
    $normalized = [];
    foreach ($selected as $value) {
        $value = trim((string) $value);
        if ($value === '' || !in_array($value, $allowed, true)) {
            continue;
        }
        $normalized[] = $value;
    }

    $normalized = array_values(array_unique($normalized));
    sort($normalized);
    return implode(',', $normalized);
}

function kuppi_conductor_availability_from_csv(string $csv): array
{
    $csv = trim($csv);
    if ($csv === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn(string $value): string => trim($value),
        explode(',', $csv)
    ), static fn(string $value): bool => $value !== ''));
}

function kuppi_schedule_normalize_host_ids(array $hostUserIds): array
{
    return array_values(array_filter(array_unique(array_map(
        static fn($value): int => (int) $value,
        $hostUserIds
    )), static fn(int $id): bool => $id > 0));
}

function kuppi_schedule_slot_key_for_datetime(string $sessionDate, string $startTime): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate) || !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        return null;
    }

    $dayTs = strtotime($sessionDate . ' 00:00:00');
    $timeTs = strtotime('1970-01-01 ' . $startTime . ':00');
    if ($dayTs === false || $timeTs === false) {
        return null;
    }

    $prefix = ((int) date('N', $dayTs) >= 6) ? 'weekend' : 'weekday';
    $hour = (int) date('G', $timeTs);
    $suffix = $hour < 12 ? 'mornings' : ($hour < 17 ? 'afternoons' : 'evenings');
    return $prefix . '_' . $suffix;
}

function kuppi_schedule_selected_hosts(array $draft): array
{
    $candidateMap = kuppi_schedule_candidate_map(kuppi_schedule_host_candidates($draft));
    $selectedHostIds = kuppi_schedule_normalize_host_ids((array) ($draft['host_user_ids'] ?? []));
    $selectedHosts = [];

    foreach ($selectedHostIds as $hostUserId) {
        if (isset($candidateMap[$hostUserId])) {
            $selectedHosts[] = $candidateMap[$hostUserId];
        }
    }

    return [
        'candidate_map' => $candidateMap,
        'selected_host_ids' => $selectedHostIds,
        'selected_hosts' => $selectedHosts,
    ];
}

function kuppi_schedule_selected_host_availability_stats(array $selectedHosts): array
{
    $allowedSlots = array_keys(kuppi_conductor_availability_options());
    $counts = array_fill_keys($allowedSlots, 0);
    $hostsWithAvailability = 0;

    foreach ($selectedHosts as $host) {
        $availability = array_values(array_filter(array_unique(array_map(
            static fn($slot): string => trim((string) $slot),
            (array) ($host['availability'] ?? [])
        )), static fn(string $slot): bool => $slot !== '' && isset($counts[$slot])));

        if (empty($availability)) {
            continue;
        }

        $hostsWithAvailability++;
        foreach ($availability as $slot) {
            $counts[$slot]++;
        }
    }

    $nonZero = array_filter($counts, static fn(int $count): bool => $count > 0);
    arsort($nonZero);

    $maxCount = 0;
    $recommendedSlots = [];
    foreach ($nonZero as $slot => $count) {
        if ($count > $maxCount) {
            $maxCount = $count;
            $recommendedSlots = [$slot];
            continue;
        }

        if ($count === $maxCount) {
            $recommendedSlots[] = $slot;
        }
    }

    return [
        'hosts_with_availability' => $hostsWithAvailability,
        'counts' => $counts,
        'ranked_counts' => $nonZero,
        'max_count' => $maxCount,
        'recommended_slots' => $recommendedSlots,
    ];
}

function kuppi_schedule_selected_host_slot_match(array $selectedHosts, ?string $slotKey): array
{
    if ($slotKey === null) {
        return [
            'slot_key' => null,
            'hosts_with_availability' => 0,
            'matched_hosts' => 0,
            'is_full_match' => false,
            'has_any_match' => false,
        ];
    }

    $hostsWithAvailability = 0;
    $matchedHosts = 0;

    foreach ($selectedHosts as $host) {
        $availability = array_values(array_filter(array_unique(array_map(
            static fn($slot): string => trim((string) $slot),
            (array) ($host['availability'] ?? [])
        )), static fn(string $slot): bool => $slot !== ''));

        if (empty($availability)) {
            continue;
        }

        $hostsWithAvailability++;
        if (in_array($slotKey, $availability, true)) {
            $matchedHosts++;
        }
    }

    return [
        'slot_key' => $slotKey,
        'hosts_with_availability' => $hostsWithAvailability,
        'matched_hosts' => $matchedHosts,
        'is_full_match' => $hostsWithAvailability > 0 && $matchedHosts === $hostsWithAvailability,
        'has_any_match' => $matchedHosts > 0,
    ];
}

function kuppi_timetable_day_labels(): array
{
    return [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];
}

function kuppi_timetable_day_label(int $dayOfWeek): string
{
    $labels = kuppi_timetable_day_labels();
    return (string) ($labels[$dayOfWeek] ?? 'Unknown Day');
}

function kuppi_timetable_normalize_time_input(string $value): ?string
{
    $value = trim($value);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return null;
    }

    return $value . ':00';
}

function kuppi_timetable_time_to_minutes(string $timeValue): ?int
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $timeValue)) {
        return null;
    }

    $hours = (int) substr($timeValue, 0, 2);
    $minutes = (int) substr($timeValue, 3, 2);
    return ($hours * 60) + $minutes;
}

function kuppi_timetable_time_label(string $timeValue): string
{
    $trimmed = trim($timeValue);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $trimmed)) {
        return substr($trimmed, 0, 5);
    }

    return $trimmed;
}

function kuppi_timetable_day_of_week_from_date(string $sessionDate): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
        return 0;
    }

    $dayTs = strtotime($sessionDate . ' 00:00:00');
    if ($dayTs === false) {
        return 0;
    }

    return (int) date('N', $dayTs);
}

function kuppi_timetable_reason_label(array $slot): string
{
    $reason = trim((string) ($slot['reason'] ?? ''));
    return $reason !== '' ? $reason : 'Official lecture slot';
}

function kuppi_timetable_slot_summary(array $slot): string
{
    $dayLabel = kuppi_timetable_day_label((int) ($slot['day_of_week'] ?? 0));
    $startLabel = kuppi_timetable_time_label((string) ($slot['start_time'] ?? ''));
    $endLabel = kuppi_timetable_time_label((string) ($slot['end_time'] ?? ''));
    $reason = kuppi_timetable_reason_label($slot);

    $range = trim($startLabel . ' - ' . $endLabel);
    if ($range === '-') {
        $range = 'Unknown time';
    }

    return $dayLabel . ' ' . $range . ' (' . $reason . ')';
}

function kuppi_user_can_read_batch(int $batchId): bool
{
    if ($batchId <= 0) {
        return false;
    }

    if ((string) user_role() === 'admin') {
        return true;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === $batchId;
}

function kuppi_user_can_view_timetable_for_batch(int $batchId): bool
{
    $role = (string) user_role();
    if (!in_array($role, ['student', 'coordinator', 'moderator', 'admin'], true)) {
        return false;
    }

    return kuppi_user_can_read_batch($batchId);
}

function kuppi_user_can_manage_timetable_for_batch(int $batchId): bool
{
    if ($batchId <= 0) {
        return false;
    }

    $role = (string) user_role();
    if ($role === 'admin') {
        return true;
    }

    if ($role !== 'moderator') {
        return false;
    }

    return (int) (auth_user()['batch_id'] ?? 0) === $batchId;
}

function kuppi_timetable_url(int $batchId = 0, array $extraQuery = []): string
{
    $query = [];
    if ((string) user_role() === 'admin' && $batchId > 0) {
        $query['batch_id'] = $batchId;
    }

    foreach ($extraQuery as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }

    return '/dashboard/kuppi/timetable' . (!empty($query) ? '?' . http_build_query($query) : '');
}

function kuppi_timetable_validate_slot_input(int $batchId, ?int $excludeSlotId = null): array
{
    $errors = [];
    $dayOfWeek = (int) request_input('day_of_week', 0);
    $startTimeRaw = trim((string) request_input('start_time', ''));
    $endTimeRaw = trim((string) request_input('end_time', ''));
    $reason = trim((string) request_input('reason', ''));

    if ($batchId <= 0) {
        $errors[] = 'Batch is required.';
    }

    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        $errors[] = 'Valid day of week is required.';
    }

    $startTime = kuppi_timetable_normalize_time_input($startTimeRaw);
    if ($startTime === null) {
        $errors[] = 'Valid start time is required.';
    }

    $endTime = kuppi_timetable_normalize_time_input($endTimeRaw);
    if ($endTime === null) {
        $errors[] = 'Valid end time is required.';
    }

    if ($reason !== '' && strlen($reason) > 255) {
        $errors[] = 'Reason must be at most 255 characters.';
    }

    if ($startTime !== null && $endTime !== null) {
        $startMinutes = kuppi_timetable_time_to_minutes($startTime);
        $endMinutes = kuppi_timetable_time_to_minutes($endTime);
        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            $errors[] = 'End time must be after start time.';
        }
    }

    if (empty($errors) && kuppi_university_timetable_has_overlap($batchId, $dayOfWeek, (string) $startTime, (string) $endTime, $excludeSlotId)) {
        $errors[] = 'This slot overlaps an existing official lecture slot for the selected day.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'batch_id' => $batchId,
            'day_of_week' => $dayOfWeek,
            'start_time' => (string) $startTime,
            'end_time' => (string) $endTime,
            'reason' => $reason,
        ],
    ];
}

function kuppi_timetable_build_weekly_grid(array $slots): array
{
    $rows = [];
    $blockedCellCount = 0;
    $slotsByDay = [];
    foreach ($slots as $slot) {
        $day = (int) ($slot['day_of_week'] ?? 0);
        if ($day < 1 || $day > 7) {
            continue;
        }
        $slotsByDay[$day][] = $slot;
    }

    for ($hour = 8; $hour < 21; $hour++) {
        $row = [
            'start_hour' => $hour,
            'time_label' => sprintf('%02d:00 - %02d:00', $hour, $hour + 1),
            'cells' => [],
        ];

        $rowStart = $hour * 60;
        $rowEnd = ($hour + 1) * 60;

        foreach (range(1, 7) as $dayOfWeek) {
            $matched = null;
            foreach ((array) ($slotsByDay[$dayOfWeek] ?? []) as $slot) {
                $slotStart = kuppi_timetable_time_to_minutes((string) ($slot['start_time'] ?? ''));
                $slotEnd = kuppi_timetable_time_to_minutes((string) ($slot['end_time'] ?? ''));
                if ($slotStart === null || $slotEnd === null) {
                    continue;
                }

                if ($slotStart < $rowEnd && $slotEnd > $rowStart) {
                    $matched = $slot;
                    break;
                }
            }

            if ($matched !== null) {
                $blockedCellCount++;
            }

            $row['cells'][$dayOfWeek] = $matched;
        }

        $rows[] = $row;
    }

    return [
        'rows' => $rows,
        'blocked_cell_count' => $blockedCellCount,
    ];
}

function kuppi_timetable_metrics(array $slots, int $blockedCellCount): array
{
    $totalBlockedMinutes = 0;
    foreach ($slots as $slot) {
        $startMinutes = kuppi_timetable_time_to_minutes((string) ($slot['start_time'] ?? ''));
        $endMinutes = kuppi_timetable_time_to_minutes((string) ($slot['end_time'] ?? ''));
        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            continue;
        }
        $totalBlockedMinutes += ($endMinutes - $startMinutes);
    }

    $totalGridCells = 13 * 7;
    $availableCells = max(0, $totalGridCells - max(0, $blockedCellCount));

    return [
        'blocked_slot_count' => count($slots),
        'total_blocked_hours' => $totalBlockedMinutes / 60,
        'available_slot_count' => $availableCells,
    ];
}

function kuppi_validate_conductor_application_input(): array
{
    $motivationRaw = (string) request_input('motivation', '');
    $motivation = trim(str_replace(["\r\n", "\r"], "\n", $motivationRaw));
    $availabilityInput = $_POST['availability'] ?? [];
    $availabilityList = is_array($availabilityInput) ? $availabilityInput : [];
    $availabilityCsv = kuppi_conductor_availability_to_csv($availabilityList);
    $availability = kuppi_conductor_availability_from_csv($availabilityCsv);
    $errors = [];

    if ($motivation === '') {
        $errors[] = 'Motivation is required.';
    } elseif (strlen($motivation) > 300) {
        $errors[] = 'Motivation must be at most 300 characters.';
    }

    if (empty($availability)) {
        $errors[] = 'Select at least one availability option.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'motivation' => $motivation,
            'availability_csv' => $availabilityCsv,
            'availability' => $availability,
        ],
    ];
}

function kuppi_tags_to_array(string $tagsCsv): array
{
    $tagsCsv = trim($tagsCsv);
    if ($tagsCsv === '') {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn(string $tag): string => trim($tag),
        explode(',', $tagsCsv)
    ), static fn(string $tag): bool => $tag !== ''));
}

function kuppi_normalize_tags_csv(string $raw): string
{
    $parts = explode(',', strtolower($raw));
    $normalized = [];
    foreach ($parts as $part) {
        $tag = trim($part);
        if ($tag === '') {
            continue;
        }

        $tag = preg_replace('/[\s_]+/', '-', $tag) ?? '';
        $tag = preg_replace('/[^a-z0-9-]/', '', $tag) ?? '';
        $tag = trim($tag, '-');
        if ($tag === '') {
            continue;
        }

        $normalized[] = $tag;
    }

    $normalized = array_values(array_unique($normalized));
    return implode(',', $normalized);
}

function kuppi_validate_request_input(int $batchId): array
{
    $title = trim((string) request_input('title', ''));
    $descriptionRaw = (string) request_input('description', '');
    $description = trim(str_replace(["\r\n", "\r"], "\n", $descriptionRaw));
    $subjectId = (int) request_input('subject_id', 0);
    $tagsCsv = kuppi_normalize_tags_csv((string) request_input('tags_csv', ''));
    $tags = kuppi_tags_to_array($tagsCsv);
    $errors = [];

    if ($subjectId <= 0) {
        $errors[] = 'Subject is required.';
    } elseif (!kuppi_subject_exists_in_batch($subjectId, $batchId)) {
        $errors[] = 'Selected subject is invalid for your batch.';
    }

    if ($title === '') {
        $errors[] = 'Title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Title must be at most 200 characters.';
    }

    if ($description === '') {
        $errors[] = 'Description is required.';
    } elseif (strlen($description) > 2000) {
        $errors[] = 'Description must be at most 2000 characters.';
    }

    if (count($tags) > 8) {
        $errors[] = 'You can add at most 8 tags.';
    }

    foreach ($tags as $tag) {
        if (strlen($tag) > 24) {
            $errors[] = 'Each tag must be at most 24 characters.';
            break;
        }
    }

    return [
        'errors' => $errors,
        'data' => [
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'tags_csv' => $tagsCsv,
        ],
    ];
}

function kuppi_index_url_for_batch(int $batchId): string
{
    if (user_role() === 'admin' && $batchId > 0) {
        return '/dashboard/kuppi?batch_id=' . $batchId;
    }

    return '/dashboard/kuppi';
}

function kuppi_index_url_for_request(array $request): string
{
    return kuppi_index_url_for_batch((int) ($request['batch_id'] ?? 0));
}

function kuppi_request_url(array $request): string
{
    $requestId = (int) ($request['id'] ?? 0);
    $url = '/dashboard/kuppi/' . $requestId;

    if (user_role() === 'admin') {
        $batchId = (int) ($request['batch_id'] ?? 0);
        if ($batchId > 0) {
            $url .= '?batch_id=' . $batchId;
        }
    }

    return $url;
}

function kuppi_resolve_valid_return_to(string $returnTo, array $request): string
{
    $raw = trim($returnTo);
    if ($raw !== '') {
        $path = (string) parse_url($raw, PHP_URL_PATH);
        if (
            str_starts_with($path, '/dashboard/kuppi')
            || str_starts_with($path, '/dashboard/feed')
            || str_starts_with($path, '/my-kuppi-requests')
        ) {
            return $raw;
        }
    }

    return kuppi_index_url_for_request($request);
}

function kuppi_resolve_readable_request(int $requestId): ?array
{
    if (user_role() === 'admin') {
        return kuppi_find_request_admin($requestId, (int) auth_id());
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        return null;
    }

    return kuppi_find_request_for_batch($requestId, $batchId, (int) auth_id());
}

function kuppi_can_edit_request(array $request): bool
{
    return (int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id()
        && kuppi_request_is_open($request);
}

function kuppi_can_delete_request(array $request): bool
{
    if ((int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id()) {
        return true;
    }

    return kuppi_user_can_moderate_batch((int) ($request['batch_id'] ?? 0));
}

function kuppi_comment_target_type(): string
{
    return 'kuppi_request';
}

function kuppi_comment_can_delete(array $request, array $comment): bool
{
    $currentUserId = (int) auth_id();
    $commentAuthorId = (int) ($comment['user_id'] ?? 0);
    if ($commentAuthorId > 0 && $commentAuthorId === $currentUserId) {
        return true;
    }

    if (kuppi_user_can_moderate_batch((int) ($request['batch_id'] ?? 0))) {
        return true;
    }

    if ((string) user_role() === 'coordinator') {
        return subjects_find_for_coordinator((int) ($request['subject_id'] ?? 0), $currentUserId) !== null;
    }

    return false;
}

function kuppi_enrich_comment_tree(array $nodes, array $request): array
{
    $currentUserId = (int) auth_id();
    $maxDepth = comments_max_depth_for_target(kuppi_comment_target_type());
    $enriched = [];

    foreach ($nodes as $node) {
        $authorId = (int) ($node['user_id'] ?? 0);
        $depth = (int) ($node['depth'] ?? 0);
        $node['can_edit'] = $authorId > 0 && $authorId === $currentUserId;
        $node['can_delete'] = kuppi_comment_can_delete($request, $node);
        $node['can_reply'] = auth_check() && $depth < $maxDepth;
        $node['children'] = kuppi_enrich_comment_tree((array) ($node['children'] ?? []), $request);
        $enriched[] = $node;
    }

    return $enriched;
}

function kuppi_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $viewerId = (int) auth_id();
    $batchOptions = $isAdmin ? kuppi_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }
        $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
    }

    $subjectOptions = $selectedBatchId > 0
        ? kuppi_subject_options_for_batch($selectedBatchId)
        : [];

    $selectedSubjectId = (int) request_input('subject_id', 0);
    if ($selectedSubjectId > 0 && !kuppi_subject_exists_in_batch($selectedSubjectId, $selectedBatchId)) {
        $selectedSubjectId = 0;
    }

    $selectedSort = trim((string) request_input('sort', 'most_votes'));
    if (!in_array($selectedSort, kuppi_sort_options(), true)) {
        $selectedSort = 'most_votes';
    }

    $selectedSearchQuery = trim((string) request_input('q', ''));
    if (strlen($selectedSearchQuery) > 120) {
        $selectedSearchQuery = substr($selectedSearchQuery, 0, 120);
    }

    $selectedPage = max(1, min(50, (int) request_input('page', 1)));
    $selectedSubjectFilter = $selectedSubjectId > 0 ? $selectedSubjectId : null;

    $feedPage = $selectedBatchId > 0
        ? kuppi_requests_for_batch(
            $selectedBatchId,
            $selectedSubjectFilter,
            $selectedSort,
            $viewerId,
            $selectedSearchQuery,
            $selectedPage,
            kuppi_feed_per_page()
        )
        : ['requests' => [], 'has_more' => false];

    $requestCount = $selectedBatchId > 0
        ? kuppi_requests_count_for_batch($selectedBatchId, $selectedSubjectFilter, $selectedSearchQuery)
        : 0;

    view('kuppi::index', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'active_batch' => $activeBatch,
        'selected_batch_id' => $selectedBatchId,
        'subject_options' => $subjectOptions,
        'selected_subject_id' => $selectedSubjectId,
        'selected_sort' => $selectedSort,
        'selected_search_query' => $selectedSearchQuery,
        'selected_page' => $selectedPage,
        'requests' => (array) ($feedPage['requests'] ?? []),
        'has_more_requests' => !empty($feedPage['has_more']),
        'request_count' => $requestCount,
        'can_create' => kuppi_user_can_create(),
    ], 'dashboard');
}

function kuppi_timetable_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $batchOptions = $isAdmin ? kuppi_batch_options_for_admin() : [];
    $selectedBatchId = 0;
    $activeBatch = null;

    if ($isAdmin) {
        $selectedBatchId = (int) request_input('batch_id', 0);
        if ($selectedBatchId > 0) {
            $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
            if (!$activeBatch) {
                $selectedBatchId = 0;
                $activeBatch = null;
            }
        }
    } else {
        $selectedBatchId = (int) (auth_user()['batch_id'] ?? 0);
        if ($selectedBatchId <= 0) {
            abort(403, 'You are not assigned to a batch.');
        }

        if (!kuppi_user_can_view_timetable_for_batch($selectedBatchId)) {
            abort(403, 'You do not have permission to view this timetable.');
        }

        $activeBatch = kuppi_find_batch_option_by_id($selectedBatchId);
    }

    if ($selectedBatchId > 0 && !kuppi_user_can_view_timetable_for_batch($selectedBatchId)) {
        abort(403, 'You do not have permission to view this timetable.');
    }

    $canManage = $selectedBatchId > 0 && kuppi_user_can_manage_timetable_for_batch($selectedBatchId);
    $slots = $selectedBatchId > 0
        ? kuppi_university_timetable_slots_for_batch($selectedBatchId)
        : [];

    $editSlot = null;
    $editSlotId = (int) request_input('edit', 0);
    if ($canManage && $editSlotId > 0) {
        $editSlot = kuppi_university_timetable_find_for_batch($editSlotId, $selectedBatchId);
        if (!$editSlot) {
            flash('warning', 'Selected slot was not found for this batch.');
            redirect(kuppi_timetable_url($selectedBatchId));
        }
    }

    $weeklyGridData = kuppi_timetable_build_weekly_grid($slots);
    $metrics = kuppi_timetable_metrics($slots, (int) ($weeklyGridData['blocked_cell_count'] ?? 0));

    view('kuppi::timetable', [
        'is_admin' => $isAdmin,
        'batch_options' => $batchOptions,
        'selected_batch_id' => $selectedBatchId,
        'active_batch' => $activeBatch,
        'can_manage' => $canManage,
        'is_read_only' => !$canManage,
        'slots' => $slots,
        'edit_slot' => $editSlot,
        'day_labels' => kuppi_timetable_day_labels(),
        'grid_rows' => (array) ($weeklyGridData['rows'] ?? []),
        'metrics' => $metrics,
    ], 'dashboard');
}

function kuppi_timetable_store(): void
{
    csrf_check();

    $role = (string) user_role();
    if (!in_array($role, ['moderator', 'admin'], true)) {
        abort(403, 'Only moderators and admins can manage official timetable slots.');
    }

    $batchId = $role === 'admin'
        ? (int) request_input('batch_id', 0)
        : (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        flash('error', 'Select a batch before adding timetable slots.');
        redirect(kuppi_timetable_url());
    }

    if (!kuppi_user_can_manage_timetable_for_batch($batchId)) {
        abort(403, 'You do not have permission to manage this timetable.');
    }

    if ($role === 'admin' && !kuppi_find_batch_option_by_id($batchId)) {
        flash('error', 'Selected batch is not available.');
        redirect(kuppi_timetable_url());
    }

    $validated = kuppi_timetable_validate_slot_input($batchId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect(kuppi_timetable_url($batchId));
    }

    try {
        kuppi_university_timetable_create([
            'batch_id' => $batchId,
            'day_of_week' => (int) $validated['data']['day_of_week'],
            'start_time' => (string) $validated['data']['start_time'],
            'end_time' => (string) $validated['data']['end_time'],
            'reason' => (string) $validated['data']['reason'],
            'created_by_user_id' => (int) auth_id(),
            'updated_by_user_id' => (int) auth_id(),
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to add the timetable slot right now.');
        redirect(kuppi_timetable_url($batchId));
    }

    clear_old_input();
    flash('success', 'Official timetable slot added.');
    redirect(kuppi_timetable_url($batchId));
}

function kuppi_timetable_update(string $id): void
{
    csrf_check();

    $role = (string) user_role();
    if (!in_array($role, ['moderator', 'admin'], true)) {
        abort(403, 'Only moderators and admins can manage official timetable slots.');
    }

    $slotId = (int) $id;
    $slot = kuppi_university_timetable_find_by_id($slotId);
    if (!$slot) {
        abort(404, 'Timetable slot not found.');
    }

    $batchId = (int) ($slot['batch_id'] ?? 0);
    if (!kuppi_user_can_manage_timetable_for_batch($batchId)) {
        abort(403, 'You do not have permission to update this timetable slot.');
    }
    if ($role === 'admin') {
        $contextBatchId = (int) request_input('batch_id', 0);
        if ($contextBatchId <= 0 || $contextBatchId !== $batchId) {
            abort(403, 'Select the correct batch context before updating this slot.');
        }
    }

    $validated = kuppi_timetable_validate_slot_input($batchId, $slotId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect(kuppi_timetable_url($batchId, ['edit' => $slotId]));
    }

    try {
        $updated = kuppi_university_timetable_update($slotId, $batchId, [
            'day_of_week' => (int) $validated['data']['day_of_week'],
            'start_time' => (string) $validated['data']['start_time'],
            'end_time' => (string) $validated['data']['end_time'],
            'reason' => (string) $validated['data']['reason'],
            'updated_by_user_id' => (int) auth_id(),
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to update the timetable slot right now.');
        redirect(kuppi_timetable_url($batchId, ['edit' => $slotId]));
    }

    if (!$updated) {
        flash('error', 'Unable to update this timetable slot.');
        redirect(kuppi_timetable_url($batchId, ['edit' => $slotId]));
    }

    clear_old_input();
    flash('success', 'Official timetable slot updated.');
    redirect(kuppi_timetable_url($batchId));
}

function kuppi_timetable_delete(string $id): void
{
    csrf_check();

    $role = (string) user_role();
    if (!in_array($role, ['moderator', 'admin'], true)) {
        abort(403, 'Only moderators and admins can manage official timetable slots.');
    }

    $slotId = (int) $id;
    $slot = kuppi_university_timetable_find_by_id($slotId);
    if (!$slot) {
        abort(404, 'Timetable slot not found.');
    }

    $batchId = (int) ($slot['batch_id'] ?? 0);
    if (!kuppi_user_can_manage_timetable_for_batch($batchId)) {
        abort(403, 'You do not have permission to delete this timetable slot.');
    }
    if ($role === 'admin') {
        $contextBatchId = (int) request_input('batch_id', 0);
        if ($contextBatchId <= 0 || $contextBatchId !== $batchId) {
            abort(403, 'Select the correct batch context before deleting this slot.');
        }
    }

    try {
        $deleted = kuppi_university_timetable_delete($slotId, $batchId);
    } catch (Throwable) {
        flash('error', 'Unable to delete the timetable slot right now.');
        redirect(kuppi_timetable_url($batchId));
    }

    if (!$deleted) {
        flash('error', 'Unable to delete this timetable slot.');
        redirect(kuppi_timetable_url($batchId));
    }

    flash('success', 'Official timetable slot removed.');
    redirect(kuppi_timetable_url($batchId));
}

function kuppi_create_form(): void
{
    if (!kuppi_user_can_create()) {
        abort(403, 'Only students and coordinators can request Kuppi sessions.');
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        abort(403, 'You are not assigned to a batch.');
    }

    view('kuppi::create', [
        'active_batch' => kuppi_find_batch_option_by_id($batchId),
        'subject_options' => kuppi_subject_options_for_batch($batchId),
    ], 'dashboard');
}

function kuppi_store(): void
{
    csrf_check();

    if (!kuppi_user_can_create()) {
        abort(403, 'Only students and coordinators can request Kuppi sessions.');
    }

    $batchId = (int) (auth_user()['batch_id'] ?? 0);
    if ($batchId <= 0) {
        abort(403, 'You are not assigned to a batch.');
    }

    $validated = kuppi_validate_request_input($batchId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/create');
    }

    try {
        $requestId = kuppi_create_request([
            'batch_id' => $batchId,
            'subject_id' => (int) $validated['data']['subject_id'],
            'requested_by_user_id' => (int) auth_id(),
            'title' => $validated['data']['title'],
            'description' => $validated['data']['description'],
            'tags_csv' => $validated['data']['tags_csv'],
            'status' => 'open',
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to create request right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/kuppi/create');
    }

    clear_old_input();
    flash('success', 'Kuppi request created.');
    redirect('/dashboard/kuppi/' . $requestId);
}

function kuppi_show(string $id): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $viewerId = (int) auth_id();
    $conductorApplications = kuppi_conductor_applications_for_request($requestId, $viewerId);
    $viewerApplication = kuppi_find_user_conductor_application($requestId, $viewerId);

    $topVoteApplicationId = 0;
    $topVoteCount = -1;
    foreach ($conductorApplications as &$application) {
        $application['availability'] = kuppi_conductor_availability_from_csv((string) ($application['availability_csv'] ?? ''));
        $voteCount = (int) ($application['vote_count'] ?? 0);
        if ($voteCount > $topVoteCount) {
            $topVoteCount = $voteCount;
            $topVoteApplicationId = (int) ($application['id'] ?? 0);
        }
    }
    unset($application);

    $commentsTree = comments_tree_for_target(kuppi_comment_target_type(), $requestId);
    $commentsTree = kuppi_enrich_comment_tree($commentsTree, $request);
    $commentCount = comments_count_for_target(kuppi_comment_target_type(), $requestId);

    view('kuppi::show', [
        'request' => $request,
        'tags' => kuppi_tags_to_array((string) ($request['tags_csv'] ?? '')),
        'can_edit_request' => kuppi_can_edit_request($request),
        'can_delete_request' => kuppi_can_delete_request($request),
        'can_vote_request' => kuppi_user_can_vote_request($request),
        'can_apply_as_conductor' => kuppi_user_can_apply_as_conductor($request),
        'can_vote_conductor' => kuppi_user_can_vote_conductor($request),
        'conductor_applications' => $conductorApplications,
        'conductor_count' => count($conductorApplications),
        'viewer_conductor_application' => $viewerApplication,
        'top_vote_application_id' => $topVoteApplicationId,
        'availability_options' => kuppi_conductor_availability_options(),
        'comments' => $commentsTree,
        'comment_count' => $commentCount,
        'comment_max_level' => comments_max_depth_for_target(kuppi_comment_target_type()) + 1,
        'back_list_url' => kuppi_index_url_for_request($request),
    ], 'dashboard');
}

function kuppi_conductor_apply_form(string $id): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_user_can_apply_as_conductor($request)) {
        abort(403, 'Only students can apply as conductors for open requests in their batch.');
    }

    $existingApplication = kuppi_find_user_conductor_application($requestId, (int) auth_id());
    if ($existingApplication) {
        flash('warning', 'You have already applied as a conductor for this request.');
        redirect(kuppi_request_url($request));
    }

    view('kuppi::conductor_apply', [
        'request' => $request,
        'availability_options' => kuppi_conductor_availability_options(),
        'back_request_url' => kuppi_request_url($request),
    ], 'dashboard');
}

function kuppi_conductor_apply_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_user_can_apply_as_conductor($request)) {
        abort(403, 'Only students can apply as conductors for open requests in their batch.');
    }

    $existingApplication = kuppi_find_user_conductor_application($requestId, (int) auth_id());
    if ($existingApplication) {
        flash('warning', 'You have already applied as a conductor for this request.');
        redirect(kuppi_request_url($request));
    }

    $validated = kuppi_validate_conductor_application_input();
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/apply');
    }

    try {
        kuppi_create_conductor_application([
            'request_id' => $requestId,
            'applicant_user_id' => (int) auth_id(),
            'motivation' => $validated['data']['motivation'],
            'availability_csv' => $validated['data']['availability_csv'],
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to submit conductor application right now.');
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/conductors/apply');
    }

    clear_old_input();
    flash('success', 'Conductor application submitted.');
    redirect(kuppi_request_url($request));
}

function kuppi_conductor_vote_action(string $id, string $applicationId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $returnTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);
    if (!kuppi_user_can_vote_conductor($request)) {
        abort(403, 'Only students in this batch can vote for conductors.');
    }

    $application = kuppi_find_conductor_application_for_request((int) $applicationId, $requestId);
    if (!$application) {
        abort(404, 'Conductor application not found.');
    }

    if ((int) ($application['applicant_user_id'] ?? 0) === (int) auth_id()) {
        flash('error', 'You cannot vote for your own conductor application.');
        redirect($returnTo);
    }

    try {
        $isVoted = kuppi_toggle_conductor_vote((int) $applicationId, (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to save conductor vote right now.');
        redirect($returnTo);
    }

    flash('success', $isVoted ? 'Conductor vote added.' : 'Conductor vote removed.');
    redirect($returnTo);
}

function kuppi_edit_form(string $id): void
{
    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_can_edit_request($request)) {
        abort(403, 'Only the request owner can edit open requests.');
    }

    view('kuppi::edit', [
        'request' => $request,
        'subject_options' => kuppi_subject_options_for_batch((int) ($request['batch_id'] ?? 0)),
        'back_list_url' => kuppi_index_url_for_request($request),
    ], 'dashboard');
}

function kuppi_update_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_can_edit_request($request)) {
        abort(403, 'Only the request owner can edit open requests.');
    }

    $batchId = (int) ($request['batch_id'] ?? 0);
    $validated = kuppi_validate_request_input($batchId);
    if (!empty($validated['errors'])) {
        flash('error', implode(' ', $validated['errors']));
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/edit');
    }

    try {
        kuppi_update_request_by_owner($requestId, (int) auth_id(), [
            'subject_id' => (int) $validated['data']['subject_id'],
            'title' => $validated['data']['title'],
            'description' => $validated['data']['description'],
            'tags_csv' => $validated['data']['tags_csv'],
        ]);
    } catch (Throwable) {
        flash('error', 'Unable to update request right now. Please try again.');
        flash_old_input();
        redirect('/dashboard/kuppi/' . $requestId . '/edit');
    }

    clear_old_input();
    flash('success', 'Kuppi request updated.');
    redirect('/dashboard/kuppi/' . $requestId);
}

function kuppi_delete_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    if (!kuppi_can_delete_request($request)) {
        abort(403, 'You do not have permission to delete this request.');
    }

    $redirectTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);

    if (!kuppi_delete_request_by_id($requestId)) {
        flash('error', 'Unable to delete this request.');
        redirect($redirectTo);
    }

    flash('success', 'Kuppi request deleted.');
    redirect($redirectTo);
}

function kuppi_vote_action(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $returnTo = kuppi_resolve_valid_return_to((string) request_input('return_to', ''), $request);
    if (!kuppi_user_can_vote_request($request)) {
        abort(403, 'You do not have permission to vote on this request.');
    }

    if ((int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id()) {
        flash('error', 'You cannot vote on your own request.');
        redirect($returnTo);
    }

    $direction = trim((string) request_input('vote', ''));
    if (!in_array($direction, ['up', 'down'], true)) {
        flash('error', 'Invalid vote action.');
        redirect($returnTo);
    }

    try {
        $appliedVote = kuppi_apply_vote($requestId, (int) auth_id(), $direction);
    } catch (Throwable) {
        flash('error', 'Unable to save your vote right now.');
        redirect($returnTo);
    }

    if ($appliedVote === null) {
        flash('success', 'Vote removed.');
    } elseif ($appliedVote === 'up') {
        flash('success', 'Upvoted.');
    } else {
        flash('success', 'Downvoted.');
    }

    redirect($returnTo);
}

function kuppi_comment_store(string $id): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $requestPath = kuppi_request_url($request);
    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($requestPath . '#kuppi-comments');
    }

    $targetType = kuppi_comment_target_type();
    $parentCommentId = (int) request_input('parent_comment_id', 0);
    $parentId = null;
    $depth = 0;
    $maxDepth = comments_max_depth_for_target($targetType);

    if ($parentCommentId > 0) {
        $parent = comments_find_target_comment($parentCommentId, $targetType, $requestId);
        if (!$parent) {
            flash('error', 'Reply target not found.');
            redirect($requestPath . '#kuppi-comments');
        }

        $depth = (int) ($parent['depth'] ?? 0) + 1;
        if ($depth > $maxDepth) {
            flash('error', 'Reply depth limit reached.');
            redirect($requestPath . '#kuppi-comments');
        }

        $parentId = $parentCommentId;
    }

    try {
        comments_insert($targetType, $requestId, (int) auth_id(), $validation['body'], $parentId, $depth);
    } catch (Throwable) {
        flash('error', 'Unable to post comment right now. Please try again.');
        redirect($requestPath . '#kuppi-comments');
    }

    flash('success', 'Comment posted.');
    redirect($requestPath . '#kuppi-comments');
}

function kuppi_comment_update(string $id, string $commentId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $requestPath = kuppi_request_url($request);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, kuppi_comment_target_type(), $requestId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if ((int) ($comment['user_id'] ?? 0) !== (int) auth_id()) {
        abort(403, 'You can only edit your own comments.');
    }

    $validation = comments_validate_body((string) request_input('body', ''));
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect($requestPath . '#kuppi-comments');
    }

    if (!comments_update_body_by_author($commentIdInt, (int) auth_id(), $validation['body'])) {
        flash('error', 'Unable to update this comment.');
        redirect($requestPath . '#kuppi-comments');
    }

    flash('success', 'Comment updated.');
    redirect($requestPath . '#kuppi-comments');
}

function kuppi_comment_delete(string $id, string $commentId): void
{
    csrf_check();

    $requestId = (int) $id;
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request) {
        abort(404, 'Kuppi request not found.');
    }

    $requestPath = kuppi_request_url($request);
    $commentIdInt = (int) $commentId;
    $comment = comments_find_target_comment($commentIdInt, kuppi_comment_target_type(), $requestId);
    if (!$comment) {
        abort(404, 'Comment not found.');
    }

    if (!kuppi_comment_can_delete($request, $comment)) {
        abort(403, 'You do not have permission to delete this comment.');
    }

    if (!comments_delete_by_id($commentIdInt)) {
        flash('error', 'Unable to delete this comment.');
        redirect($requestPath . '#kuppi-comments');
    }

    flash('success', 'Comment deleted.');
    redirect($requestPath . '#kuppi-comments');
}

function kuppi_my_index(): void
{
    if (!kuppi_user_can_create()) {
        abort(403, 'Only students and coordinators can access this page.');
    }

    view('kuppi::my_index', [
        'requests' => kuppi_my_requests((int) auth_id()),
    ], 'dashboard');
}

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

function kuppi_schedule_select_request_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $role = (string) user_role();
    $currentUser = (array) auth_user();
    $userId = (int) ($currentUser['id'] ?? 0);
    $userBatchId = (int) ($currentUser['batch_id'] ?? 0);
    $adminBatchId = $role === 'admin' ? (int) request_input('batch_id', 0) : 0;
    $selectedSort = trim((string) request_input('sort', 'most_votes'));
    if (!in_array($selectedSort, kuppi_sort_options(), true)) {
        $selectedSort = 'most_votes';
    }
    $searchQuery = trim((string) request_input('q', ''));
    if (strlen($searchQuery) > 120) {
        $searchQuery = substr($searchQuery, 0, 120);
    }

    $directRequestId = (int) request_input('request_id', 0);
    if ($directRequestId > 0) {
        $request = kuppi_resolve_readable_request($directRequestId);
        if ($request && kuppi_user_can_schedule_request($request) && !kuppi_scheduled_session_has_active_for_request($directRequestId)) {
            $draft = kuppi_schedule_default_draft();
            $draft['mode'] = 'request';
            $draft['request_id'] = $directRequestId;
            $draft['batch_id'] = (int) ($request['batch_id'] ?? 0);
            $draft['subject_id'] = (int) ($request['subject_id'] ?? 0);
            $draft['title'] = (string) ($request['title'] ?? '');
            $draft['description'] = (string) ($request['description'] ?? '');
            kuppi_schedule_set_draft($draft);
            redirect('/dashboard/kuppi/schedule/assign');
        }
    }

    $requests = kuppi_schedule_open_requests_for_scheduler(
        $role,
        $userId,
        $userBatchId,
        $searchQuery,
        $selectedSort,
        $adminBatchId
    );

    view('kuppi::schedule_select_request', [
        'requests' => $requests,
        'selected_sort' => $selectedSort,
        'selected_search_query' => $searchQuery,
        'is_admin' => $role === 'admin',
        'admin_batch_id' => $adminBatchId,
        'batch_options' => $role === 'admin' ? kuppi_batch_options_for_admin() : [],
        'active_batch' => $adminBatchId > 0 ? kuppi_find_batch_option_by_id($adminBatchId) : null,
    ], 'dashboard');
}

function kuppi_schedule_manual_start(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $role = (string) user_role();
    $userBatchId = (int) (auth_user()['batch_id'] ?? 0);
    $selectedBatchId = $role === 'admin' ? (int) request_input('batch_id', 0) : $userBatchId;

    if ($role === 'admin' && $selectedBatchId <= 0) {
        flash('warning', 'Select a batch first before starting a manual session.');
        redirect('/dashboard/kuppi/schedule');
    }

    $draft = kuppi_schedule_default_draft();
    $draft['mode'] = 'manual';
    $draft['batch_id'] = $selectedBatchId;
    kuppi_schedule_set_draft($draft);
    redirect('/dashboard/kuppi/schedule/assign');
}

function kuppi_schedule_select_request_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $requestId = (int) request_input('request_id', 0);
    $request = kuppi_resolve_readable_request($requestId);
    if (!$request || !kuppi_user_can_schedule_request($request)) {
        abort(403, 'Selected request is not available for scheduling.');
    }

    if (kuppi_scheduled_session_has_active_for_request($requestId)) {
        flash('error', 'This request already has an active scheduled session.');
        redirect('/dashboard/kuppi/schedule');
    }

    $draft = kuppi_schedule_default_draft();
    $draft['mode'] = 'request';
    $draft['request_id'] = $requestId;
    $draft['batch_id'] = (int) ($request['batch_id'] ?? 0);
    $draft['subject_id'] = (int) ($request['subject_id'] ?? 0);
    $draft['title'] = (string) ($request['title'] ?? '');
    $draft['description'] = (string) ($request['description'] ?? '');
    kuppi_schedule_set_draft($draft);

    redirect('/dashboard/kuppi/schedule/assign');
}

function kuppi_schedule_set_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $mode = (string) ($draft['mode'] ?? 'request');
    $role = (string) user_role();
    $currentUser = (array) auth_user();
    $userId = (int) ($currentUser['id'] ?? 0);
    $userBatchId = (int) ($currentUser['batch_id'] ?? 0);
    $linkedRequest = null;

    if ($mode === 'request') {
        $linkedRequest = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$linkedRequest) {
            kuppi_schedule_clear_draft();
            flash('error', 'Selected request is no longer available for scheduling.');
            redirect('/dashboard/kuppi/schedule');
        }

        if (kuppi_scheduled_session_has_active_for_request((int) ($linkedRequest['id'] ?? 0))) {
            kuppi_schedule_clear_draft();
            flash('error', 'This request already has an active scheduled session.');
            redirect('/dashboard/kuppi/schedule');
        }

        $draft['batch_id'] = (int) ($linkedRequest['batch_id'] ?? 0);
        $draft['subject_id'] = (int) ($linkedRequest['subject_id'] ?? 0);
        $draft['title'] = (string) ($linkedRequest['title'] ?? '');
        $draft['description'] = (string) ($linkedRequest['description'] ?? '');
        kuppi_schedule_set_draft($draft);
    }

    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids'])) {
        flash('warning', 'Select one or more hosts first.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (empty($selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = [];
        kuppi_schedule_set_draft($draft);
        flash('warning', 'Selected hosts are no longer available. Please select hosts again.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (count($selectedHostData['selected_host_ids']) !== count($selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = array_values(array_map(
            static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
            $selectedHostData['selected_hosts']
        ));
        kuppi_schedule_set_draft($draft);
    }

    $adminBatchId = $role === 'admin' ? (int) ($draft['batch_id'] ?? 0) : 0;
    $subjectOptions = kuppi_scheduler_subject_options_for_user($role, $userId, $userBatchId, $adminBatchId);
    $availabilityStats = kuppi_schedule_selected_host_availability_stats((array) $selectedHostData['selected_hosts']);
    $selectedSlotKey = kuppi_schedule_slot_key_for_datetime(
        trim((string) ($draft['session_date'] ?? '')),
        trim((string) ($draft['start_time'] ?? ''))
    );
    $slotMatch = kuppi_schedule_selected_host_slot_match((array) $selectedHostData['selected_hosts'], $selectedSlotKey);
    $timetableSlots = [];
    $timetableSelectedDaySlots = [];
    $timetableConflicts = [];
    $timetableBatchId = (int) ($draft['batch_id'] ?? 0);
    $selectedDayOfWeek = kuppi_timetable_day_of_week_from_date(trim((string) ($draft['session_date'] ?? '')));
    if ($timetableBatchId > 0 && kuppi_user_can_view_timetable_for_batch($timetableBatchId)) {
        $timetableSlots = kuppi_university_timetable_slots_for_batch($timetableBatchId);
        if ($selectedDayOfWeek > 0) {
            $timetableSelectedDaySlots = array_values(array_filter($timetableSlots, static function (array $slot) use ($selectedDayOfWeek): bool {
                return (int) ($slot['day_of_week'] ?? 0) === $selectedDayOfWeek;
            }));
        }

        $sessionDateDraft = trim((string) ($draft['session_date'] ?? ''));
        $startTimeDraft = trim((string) ($draft['start_time'] ?? ''));
        $endTimeDraft = trim((string) ($draft['end_time'] ?? ''));
        if ($sessionDateDraft !== '' && $startTimeDraft !== '' && $endTimeDraft !== '') {
            $timetableConflicts = kuppi_university_timetable_conflicts_for_session(
                $timetableBatchId,
                $sessionDateDraft,
                $startTimeDraft,
                $endTimeDraft
            );
        }
    }

    view('kuppi::schedule_set', [
        'draft' => $draft,
        'mode' => $mode,
        'linked_request' => $linkedRequest,
        'selected_hosts' => $selectedHostData['selected_hosts'],
        'availability_stats' => $availabilityStats,
        'selected_slot_key' => $selectedSlotKey,
        'selected_slot_match' => $slotMatch,
        'availability_options' => kuppi_conductor_availability_options(),
        'subject_options' => $subjectOptions,
        'is_admin' => $role === 'admin',
        'batch_options' => $role === 'admin' ? kuppi_batch_options_for_admin() : [],
        'timetable_slots' => $timetableSlots,
        'timetable_selected_day_slots' => $timetableSelectedDaySlots,
        'timetable_conflicts' => $timetableConflicts,
        'timetable_day_labels' => kuppi_timetable_day_labels(),
    ], 'dashboard');
}

function kuppi_schedule_set_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids']) || empty($selectedHostData['selected_hosts'])) {
        flash('warning', 'Select one or more hosts first.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (count((array) $selectedHostData['selected_host_ids']) !== count((array) $selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = array_values(array_map(
            static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
            (array) $selectedHostData['selected_hosts']
        ));
        kuppi_schedule_set_draft($draft);
        flash('warning', 'Some selected hosts are no longer available. Please review host selection.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $validation = kuppi_schedule_validate_set_input($draft);
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect('/dashboard/kuppi/schedule/set');
    }

    $nextDraft = array_merge($draft, $validation['data']);
    kuppi_schedule_set_draft($nextDraft);

    redirect('/dashboard/kuppi/schedule/review');
}

function kuppi_schedule_assign_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $mode = (string) ($draft['mode'] ?? 'request');
    $linkedRequest = null;
    if ($mode === 'request') {
        $linkedRequest = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$linkedRequest) {
            kuppi_schedule_clear_draft();
            flash('error', 'Selected request is no longer available for scheduling.');
            redirect('/dashboard/kuppi/schedule');
        }

        if (kuppi_scheduled_session_has_active_for_request((int) ($linkedRequest['id'] ?? 0))) {
            kuppi_schedule_clear_draft();
            flash('error', 'This request already has an active scheduled session.');
            redirect('/dashboard/kuppi/schedule');
        }

        $draft['batch_id'] = (int) ($linkedRequest['batch_id'] ?? 0);
        $draft['subject_id'] = (int) ($linkedRequest['subject_id'] ?? 0);
        $draft['title'] = (string) ($linkedRequest['title'] ?? '');
        $draft['description'] = (string) ($linkedRequest['description'] ?? '');
        kuppi_schedule_set_draft($draft);
    }

    $candidates = kuppi_schedule_host_candidates($draft);
    $selectedHostIds = kuppi_schedule_default_host_ids($draft, $candidates);

    view('kuppi::schedule_assign', [
        'draft' => $draft,
        'mode' => $mode,
        'linked_request' => $linkedRequest,
        'candidates' => $candidates,
        'selected_host_ids' => $selectedHostIds,
        'availability_options' => kuppi_conductor_availability_options(),
    ], 'dashboard');
}

function kuppi_schedule_assign_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $candidateMap = kuppi_schedule_candidate_map(kuppi_schedule_host_candidates($draft));
    $selection = kuppi_schedule_selected_hosts_from_input($candidateMap);

    if (!empty($selection['errors'])) {
        flash('error', implode(' ', $selection['errors']));
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $draft['host_user_ids'] = $selection['selected_ids'];
    kuppi_schedule_set_draft($draft);
    redirect('/dashboard/kuppi/schedule/set');
}

function kuppi_schedule_review_step(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids']) || empty($selectedHostData['selected_hosts'])) {
        flash('warning', 'Select at least one host.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (count((array) $selectedHostData['selected_host_ids']) !== count((array) $selectedHostData['selected_hosts'])) {
        $draft['host_user_ids'] = array_values(array_map(
            static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
            (array) $selectedHostData['selected_hosts']
        ));
        kuppi_schedule_set_draft($draft);
        flash('warning', 'Some selected hosts are no longer available. Please review host selection.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (trim((string) ($draft['session_date'] ?? '')) === '') {
        flash('warning', 'Set schedule details before review.');
        redirect('/dashboard/kuppi/schedule/set');
    }

    view('kuppi::schedule_review', [
        'draft' => $draft,
        'selected_hosts' => $selectedHostData['selected_hosts'],
        'linked_request' => kuppi_schedule_resolve_request_for_draft($draft),
    ], 'dashboard');
}

function kuppi_schedule_confirm_action(): void
{
    csrf_check();

    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to schedule sessions.');
    }

    $draft = kuppi_schedule_require_draft();
    $selectedHostData = kuppi_schedule_selected_hosts($draft);
    if (empty($selectedHostData['selected_host_ids']) || empty($selectedHostData['selected_hosts'])) {
        flash('error', 'At least one host is required.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    if (trim((string) ($draft['session_date'] ?? '')) === '') {
        flash('warning', 'Set schedule details first.');
        redirect('/dashboard/kuppi/schedule/set');
    }

    if (count((array) $selectedHostData['selected_host_ids']) !== count((array) $selectedHostData['selected_hosts'])) {
        flash('error', 'Selected hosts are no longer available.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $hosts = [];
    foreach ((array) $selectedHostData['selected_hosts'] as $candidate) {
        $hostUserId = (int) ($candidate['host_user_id'] ?? 0);
        if ($hostUserId <= 0) {
            continue;
        }

        $hosts[] = [
            'host_user_id' => $hostUserId,
            'source_type' => (string) ($candidate['source_type'] ?? 'manual'),
            'source_application_id' => !empty($candidate['source_application_id']) ? (int) $candidate['source_application_id'] : null,
            'assigned_by_user_id' => (int) auth_id(),
        ];
    }

    if (empty($hosts)) {
        flash('error', 'At least one host is required.');
        redirect('/dashboard/kuppi/schedule/assign');
    }

    $request = null;
    $requestId = (int) ($draft['request_id'] ?? 0);
    if ((string) ($draft['mode'] ?? '') === 'request') {
        $request = kuppi_schedule_resolve_request_for_draft($draft);
        if (!$request) {
            kuppi_schedule_clear_draft();
            flash('error', 'Selected request is no longer available.');
            redirect('/dashboard/kuppi/schedule');
        }

        if (kuppi_scheduled_session_has_active_for_request($requestId)) {
            kuppi_schedule_clear_draft();
            flash('error', 'This request already has an active scheduled session.');
            redirect('/dashboard/kuppi/schedule');
        }
    } else {
        $requestId = 0;
    }

    try {
        $sessionId = kuppi_scheduled_create_with_hosts([
            'batch_id' => (int) ($draft['batch_id'] ?? 0),
            'subject_id' => (int) ($draft['subject_id'] ?? 0),
            'request_id' => $requestId,
            'title' => (string) ($draft['title'] ?? ''),
            'description' => (string) ($draft['description'] ?? ''),
            'session_date' => (string) ($draft['session_date'] ?? ''),
            'start_time' => (string) ($draft['start_time'] ?? ''),
            'end_time' => (string) ($draft['end_time'] ?? ''),
            'duration_minutes' => (int) ($draft['duration_minutes'] ?? 0),
            'max_attendees' => (int) ($draft['max_attendees'] ?? 0),
            'location_type' => (string) ($draft['location_type'] ?? 'physical'),
            'location_text' => (string) ($draft['location_text'] ?? ''),
            'meeting_link' => (string) ($draft['meeting_link'] ?? ''),
            'notes' => (string) ($draft['notes'] ?? ''),
            'status' => 'scheduled',
            'created_by_user_id' => (int) auth_id(),
        ], $hosts);
    } catch (Throwable) {
        flash('error', 'Unable to schedule this session right now.');
        redirect('/dashboard/kuppi/schedule/review');
    }

    if ($sessionId <= 0) {
        flash('error', 'This request already has an active scheduled session.');
        redirect('/dashboard/kuppi/schedule');
    }

    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    $sessionHosts = kuppi_scheduled_hosts_for_session($sessionId);
    if ($session) {
        kuppi_schedule_notify($session, $sessionHosts, 'created');
    }

    kuppi_schedule_clear_draft();
    flash('success', 'Kuppi session scheduled successfully.');
    redirect('/dashboard/kuppi/schedule/success?id=' . $sessionId);
}

function kuppi_schedule_success(): void
{
    if (!kuppi_user_is_scheduler()) {
        abort(403, 'You do not have permission to view this page.');
    }

    $sessionId = (int) request_input('id', 0);
    $session = null;
    if ($sessionId > 0) {
        $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    }

    view('kuppi::schedule_success', [
        'session' => $session,
    ], 'dashboard');
}

function kuppi_scheduled_index(): void
{
    $role = (string) user_role();
    $isAdmin = $role === 'admin';
    $userBatchId = (int) (auth_user()['batch_id'] ?? 0);
    $adminBatchId = $isAdmin ? (int) request_input('batch_id', 0) : 0;
    $searchQuery = trim((string) request_input('q', ''));
    $subjectId = (int) request_input('subject_id', 0);
    $statusFilter = trim((string) request_input('status', ''));

    $sessions = kuppi_scheduled_sessions_for_scope(
        $role,
        $userBatchId,
        $searchQuery,
        $subjectId,
        $statusFilter,
        $adminBatchId
    );

    $subjectOptions = $isAdmin
        ? ($adminBatchId > 0 ? kuppi_subject_options_for_batch($adminBatchId) : [])
        : kuppi_subject_options_for_batch($userBatchId);

    view('kuppi::scheduled_index', [
        'sessions' => $sessions,
        'selected_search_query' => $searchQuery,
        'selected_subject_id' => $subjectId,
        'selected_status' => $statusFilter,
        'subject_options' => $subjectOptions,
        'status_options' => kuppi_scheduled_statuses(),
        'is_admin' => $isAdmin,
        'admin_batch_id' => $adminBatchId,
        'batch_options' => $isAdmin ? kuppi_batch_options_for_admin() : [],
    ], 'dashboard');
}

function kuppi_scheduled_show(string $id): void
{
    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    $hosts = kuppi_scheduled_hosts_for_session($sessionId);

    view('kuppi::scheduled_show', [
        'session' => $session,
        'hosts' => $hosts,
        'can_manage' => kuppi_user_can_manage_scheduled_session($session),
        'availability_options' => kuppi_conductor_availability_options(),
    ], 'dashboard');
}

function kuppi_scheduled_edit_form(string $id): void
{
    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to edit this scheduled session.');
    }

    if ((string) ($session['status'] ?? '') === 'cancelled') {
        flash('warning', 'Cancelled sessions cannot be edited.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    $draftLike = [
        'mode' => (int) ($session['request_id'] ?? 0) > 0 ? 'request' : 'manual',
        'request_id' => (int) ($session['request_id'] ?? 0),
        'batch_id' => (int) ($session['batch_id'] ?? 0),
    ];
    $candidates = kuppi_schedule_host_candidates($draftLike);
    $hosts = kuppi_scheduled_hosts_for_session($sessionId);
    $selectedHostIds = array_values(array_filter(array_map(
        static fn(array $host): int => (int) ($host['host_user_id'] ?? 0),
        $hosts
    ), static fn(int $id): bool => $id > 0));

    view('kuppi::scheduled_edit', [
        'session' => $session,
        'hosts' => $hosts,
        'candidates' => $candidates,
        'selected_host_ids' => $selectedHostIds,
        'status_options' => ['scheduled', 'completed'],
        'availability_options' => kuppi_conductor_availability_options(),
    ], 'dashboard');
}

function kuppi_scheduled_update_action(string $id): void
{
    csrf_check();

    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to update this scheduled session.');
    }

    if ((string) ($session['status'] ?? '') === 'cancelled') {
        flash('error', 'Cancelled sessions cannot be edited.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    $draft = [
        'mode' => (int) ($session['request_id'] ?? 0) > 0 ? 'request' : 'manual',
        'batch_id' => (int) ($session['batch_id'] ?? 0),
        'subject_id' => (int) ($session['subject_id'] ?? 0),
        'request_id' => (int) ($session['request_id'] ?? 0),
        'title' => (string) ($session['title'] ?? ''),
        'description' => (string) ($session['description'] ?? ''),
    ];

    $_POST['batch_id'] = (string) ($session['batch_id'] ?? 0);
    $_POST['subject_id'] = (string) ($session['subject_id'] ?? 0);
    $validation = kuppi_schedule_validate_set_input($draft);
    if (!empty($validation['errors'])) {
        flash('error', implode(' ', $validation['errors']));
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    $candidateMap = kuppi_schedule_candidate_map(kuppi_schedule_host_candidates($draft));
    $selection = kuppi_schedule_selected_hosts_from_input($candidateMap);
    if (!empty($selection['errors'])) {
        flash('error', implode(' ', $selection['errors']));
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    $status = trim((string) request_input('status', 'scheduled'));
    if (!in_array($status, ['scheduled', 'completed'], true)) {
        $status = 'scheduled';
    }

    try {
        $updated = kuppi_scheduled_update_with_hosts($sessionId, [
            'title' => (string) $validation['data']['title'],
            'description' => (string) $validation['data']['description'],
            'session_date' => (string) $validation['data']['session_date'],
            'start_time' => (string) $validation['data']['start_time'],
            'end_time' => (string) $validation['data']['end_time'],
            'duration_minutes' => (int) $validation['data']['duration_minutes'],
            'max_attendees' => (int) $validation['data']['max_attendees'],
            'location_type' => (string) $validation['data']['location_type'],
            'location_text' => (string) $validation['data']['location_text'],
            'meeting_link' => (string) $validation['data']['meeting_link'],
            'notes' => (string) $validation['data']['notes'],
            'status' => $status,
        ], $selection['hosts']);
    } catch (Throwable) {
        flash('error', 'Unable to update scheduled session right now.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    if (!$updated) {
        flash('error', 'Unable to update this scheduled session. It may conflict with another active request session.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId . '/edit');
    }

    $updatedSession = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if ($updatedSession) {
        kuppi_schedule_notify($updatedSession, kuppi_scheduled_hosts_for_session($sessionId), 'updated');
    }

    flash('success', 'Scheduled session updated.');
    redirect('/dashboard/kuppi/scheduled/' . $sessionId);
}

function kuppi_scheduled_cancel_action(string $id): void
{
    csrf_check();

    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to cancel this scheduled session.');
    }

    if ((string) ($session['status'] ?? '') === 'cancelled') {
        flash('warning', 'Session is already cancelled.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    try {
        $ok = kuppi_scheduled_cancel($sessionId, (int) auth_id());
    } catch (Throwable) {
        flash('error', 'Unable to cancel this scheduled session right now.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    if (!$ok) {
        flash('error', 'Unable to cancel this scheduled session.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    $updatedSession = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if ($updatedSession) {
        kuppi_schedule_notify($updatedSession, kuppi_scheduled_hosts_for_session($sessionId), 'cancelled');
    }

    flash('success', 'Scheduled session cancelled.');
    redirect('/dashboard/kuppi/scheduled/' . $sessionId);
}

function kuppi_scheduled_delete_action(string $id): void
{
    csrf_check();

    $sessionId = (int) $id;
    $session = kuppi_find_scheduled_session_readable($sessionId, (string) user_role(), (int) (auth_user()['batch_id'] ?? 0));
    if (!$session) {
        abort(404, 'Scheduled session not found.');
    }

    if (!kuppi_user_can_manage_scheduled_session($session)) {
        abort(403, 'You do not have permission to delete this scheduled session.');
    }

    $hosts = kuppi_scheduled_hosts_for_session($sessionId);

    try {
        $deleted = kuppi_scheduled_delete($sessionId);
    } catch (Throwable) {
        flash('error', 'Unable to delete this scheduled session right now.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    if (!$deleted) {
        flash('error', 'Unable to delete this scheduled session.');
        redirect('/dashboard/kuppi/scheduled/' . $sessionId);
    }

    kuppi_schedule_notify($session, $hosts, 'deleted');
    flash('success', 'Scheduled session deleted.');
    redirect('/dashboard/kuppi/scheduled');
}
