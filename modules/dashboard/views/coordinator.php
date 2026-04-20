<?php
$assignedSubjects = (array) ($assigned_subjects ?? $subjects ?? []);
$batchSubjects = (array) ($batch_subjects ?? []);
$featuredAssignedSubjects = array_slice($assignedSubjects, 0, 6);
$recentActivityItems = (array) ($recent_activity_items ?? []);
$nextSession = (array) ($next_session ?? []);
$hasNextSession = !empty($nextSession['id']);

$assignedSubjectCount = count($assignedSubjects);
$batchSubjectCount = (int) ($subject_count ?? count($batchSubjects));
$resourceCount = (int) ($resource_count ?? 0);
$quizCount = (int) ($quiz_count ?? 0);
$openKuppiCount = (int) ($open_kuppi_count ?? 0);
$upcomingSessionCount = (int) ($upcoming_session_count ?? 0);
$pendingResourceRequests = (int) ($pending_resource_requests ?? 0);
$pendingQuizRequests = (int) ($pending_quiz_requests ?? 0);
$myPostCount = (int) ($my_post_count ?? 0);
$myResourceCount = (int) ($my_resource_count ?? 0);
$myQuizCount = (int) ($my_quiz_count ?? 0);
$myKuppiRequestCount = (int) ($my_kuppi_request_count ?? 0);

$quizSummary = (array) ($quiz_summary ?? []);
$quizAttemptCount = (int) ($quizSummary['attempt_count'] ?? 0);
$quizAvgScore = isset($quizSummary['avg_score']) ? (float) $quizSummary['avg_score'] : null;
$quizBestScore = isset($quizSummary['best_score']) ? (float) $quizSummary['best_score'] : null;

$gpaSummary = (array) ($gpa_summary ?? []);
$latestGpa = isset($gpaSummary['latest_gpa']) ? (float) $gpaSummary['latest_gpa'] : null;
$bestGpa = isset($gpaSummary['best_gpa']) ? (float) $gpaSummary['best_gpa'] : null;
$batchMeta = (array) ($batch_meta ?? []);
$batchCode = trim((string) ($batchMeta['batch_code'] ?? ''));
$batchName = trim((string) ($batchMeta['name'] ?? ''));

$truncate = static function (string $text, int $length = 120): string {
    $normalized = trim((string) preg_replace('/\s+/', ' ', $text));
    if (strlen($normalized) <= $length) {
        return $normalized;
    }

    return substr($normalized, 0, $length - 3) . '...';
};
?>

<section class="student-dash-hero role-hero role-hero--coordinator">
    <div class="student-dash-hero-main">
        <p class="student-dash-eyebrow">Coordinator Dashboard</p>
        <h1>Lead your assigned subjects and keep learning flow smooth.</h1>
        <p class="student-dash-copy">
            Welcome back, <?= e((string) ($user['name'] ?? 'Coordinator')) ?>.
            <?php if ($batchCode !== ''): ?>
                You are coordinating in <span class="student-dash-inline-strong"><?= e($batchCode) ?></span><?php if ($batchName !== ''): ?> · <?= e($batchName) ?><?php endif; ?>.
            <?php endif; ?>
        </p>

        <div class="student-dash-chips">
            <span><?= ui_lucide_icon('layers') ?> <?= $assignedSubjectCount ?> assigned subjects</span>
            <span><?= ui_lucide_icon('inbox') ?> <?= $pendingResourceRequests ?> resource requests</span>
            <span><?= ui_lucide_icon('check-check') ?> <?= $pendingQuizRequests ?> quiz requests</span>
            <span><?= ui_lucide_icon('calendar-plus') ?> <?= $openKuppiCount ?> open kuppi requests</span>
        </div>
    </div>

    <aside class="student-dash-today">
        <h2><?= ui_lucide_icon('shield-check') ?> Review Queue</h2>
        <ul>
            <li><span>Resource Requests</span><strong><?= $pendingResourceRequests ?></strong></li>
            <li><span>Quiz Requests</span><strong><?= $pendingQuizRequests ?></strong></li>
            <li><span>Batch Subjects</span><strong><?= $batchSubjectCount ?></strong></li>
            <li><span>Upcoming Sessions</span><strong><?= $upcomingSessionCount ?></strong></li>
        </ul>
        <div class="student-dash-inline-actions">
            <a href="/coordinator/resource-requests" class="btn btn-sm btn-outline">Review Resources</a>
            <a href="/dashboard/quiz-requests" class="btn btn-sm btn-primary">Review Quizzes</a>
        </div>
    </aside>
</section>

<section class="student-dash-actions">
    <a href="/coordinator/subjects" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('layers') ?></span>
        <strong>Manage Subjects</strong>
        <small>Update assigned subject details</small>
    </a>
    <a href="/coordinator/resource-requests" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('inbox') ?></span>
        <strong>Resource Requests</strong>
        <small>Approve or reject student uploads</small>
    </a>
    <a href="/dashboard/quiz-requests" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('check-check') ?></span>
        <strong>Quiz Requests</strong>
        <small>Review student-created quizzes</small>
    </a>
    <a href="/dashboard/quiz-analytics" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('chart-no-axes-column') ?></span>
        <strong>Review Analytics</strong>
        <small>Track difficult questions by subject</small>
    </a>
    <a href="/dashboard/kuppi/schedule" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('calendar-plus') ?></span>
        <strong>Set Kuppi Schedule</strong>
        <small>Create conflict-safe session plans</small>
    </a>
    <a href="/dashboard/feed" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('newspaper') ?></span>
        <strong>Central Feed</strong>
        <small>Follow latest batch activity</small>
    </a>
    <a href="/dashboard/subjects" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('book-open') ?></span>
        <strong>Browse Subjects</strong>
        <small>Open topics, resources, and quizzes</small>
    </a>
    <a href="/dashboard/community" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('messages-square') ?></span>
        <strong>Community Feed</strong>
        <small>Take part in batch discussions</small>
    </a>
</section>

<section class="student-dash-kpis">
    <article class="student-dash-kpi">
        <span>My Quiz Attempts</span>
        <strong><?= $quizAttemptCount ?></strong>
        <p>Best <?= $quizBestScore !== null ? number_format($quizBestScore, 1) . '%' : '-' ?> · Avg <?= $quizAvgScore !== null ? number_format($quizAvgScore, 1) . '%' : '-' ?></p>
    </article>
    <article class="student-dash-kpi">
        <span>Latest GPA</span>
        <strong><?= $latestGpa !== null ? number_format($latestGpa, 2) : '-' ?></strong>
        <p>Best GPA <?= $bestGpa !== null ? number_format($bestGpa, 2) : '-' ?></p>
    </article>
    <article class="student-dash-kpi">
        <span>Published Resources</span>
        <strong><?= $resourceCount ?></strong>
        <p>Visible across your batch subjects</p>
    </article>
    <article class="student-dash-kpi">
        <span>Approved Quizzes</span>
        <strong><?= $quizCount ?></strong>
        <p>Ready to attempt by learners</p>
    </article>
</section>

<section class="student-dash-grid">
    <article class="student-dash-card">
        <header class="student-dash-card-head">
            <h2><?= ui_lucide_icon('calendar-clock') ?> Next Scheduled Kuppi</h2>
            <a href="/dashboard/kuppi/scheduled" class="btn btn-sm btn-outline">All Sessions</a>
        </header>

        <?php if ($hasNextSession): ?>
            <?php
            $sessionId = (int) ($nextSession['id'] ?? 0);
            $sessionTitle = trim((string) ($nextSession['title'] ?? 'Scheduled Session'));
            $sessionSubjectCode = trim((string) ($nextSession['subject_code'] ?? ''));
            $sessionDate = trim((string) ($nextSession['session_date'] ?? ''));
            $sessionStart = trim((string) ($nextSession['start_time'] ?? ''));
            $sessionEnd = trim((string) ($nextSession['end_time'] ?? ''));
            $sessionDateLabel = $sessionDate !== '' ? date('D, M d', strtotime($sessionDate)) : 'TBD date';
            $sessionTimeLabel = ($sessionStart !== '' && $sessionEnd !== '')
                ? substr($sessionStart, 0, 5) . ' - ' . substr($sessionEnd, 0, 5)
                : 'TBD time';
            ?>
            <div class="student-dash-session-card">
                <p class="student-dash-session-title">
                    <?php if ($sessionSubjectCode !== ''): ?><span class="badge"><?= e($sessionSubjectCode) ?></span><?php endif; ?>
                    <strong><?= e($sessionTitle) ?></strong>
                </p>
                <ul class="student-dash-session-meta">
                    <li><?= ui_lucide_icon('calendar-days') ?> <?= e($sessionDateLabel) ?></li>
                    <li><?= ui_lucide_icon('clock-3') ?> <?= e($sessionTimeLabel) ?></li>
                </ul>
                <a href="/dashboard/kuppi/scheduled/<?= $sessionId ?>" class="btn btn-sm btn-primary">Open Session</a>
            </div>
        <?php else: ?>
            <p class="text-muted">No scheduled sessions yet. Start by setting up a new Kuppi slot.</p>
            <a href="/dashboard/kuppi/schedule" class="btn btn-sm btn-outline">Set Kuppi Schedule</a>
        <?php endif; ?>
    </article>

    <article class="student-dash-card">
        <header class="student-dash-card-head">
            <h2><?= ui_lucide_icon('target') ?> Personal Workspace</h2>
            <a href="/my-quiz-analytics" class="btn btn-sm btn-outline">My Quiz Analytics</a>
        </header>
        <ul class="student-dash-personal-list">
            <li><span>My Kuppi Requests</span><strong><?= $myKuppiRequestCount ?></strong></li>
            <li><span>My Quizzes</span><strong><?= $myQuizCount ?></strong></li>
            <li><span>My Resources</span><strong><?= $myResourceCount ?></strong></li>
            <li><span>My Posts</span><strong><?= $myPostCount ?></strong></li>
        </ul>
        <div class="student-dash-inline-actions">
            <a href="/my-kuppi-requests" class="btn btn-sm btn-outline">My Kuppi Requests</a>
            <a href="/my-quizzes" class="btn btn-sm btn-outline">My Quizzes</a>
            <a href="/my-resources" class="btn btn-sm btn-outline">My Resources</a>
            <a href="/my-posts" class="btn btn-sm btn-outline">My Posts</a>
        </div>
    </article>
</section>

<section class="student-dash-card">
    <header class="student-dash-card-head">
        <h2><?= ui_lucide_icon('layers') ?> Assigned Subject Focus</h2>
        <a href="/coordinator/subjects" class="btn btn-sm btn-outline">Open Subject Manager</a>
    </header>

    <?php if (empty($featuredAssignedSubjects)): ?>
        <p class="text-muted">No subjects are assigned yet.</p>
    <?php else: ?>
        <div class="student-dash-subject-grid">
            <?php foreach ($featuredAssignedSubjects as $subject): ?>
                <?php
                $subjectId = (int) ($subject['id'] ?? 0);
                $subjectCode = trim((string) ($subject['code'] ?? 'SUBJECT'));
                $subjectName = trim((string) ($subject['name'] ?? 'Untitled Subject'));
                $subjectDescription = trim((string) ($subject['description'] ?? ''));
                ?>
                <div class="student-dash-subject-card">
                    <div class="student-dash-subject-head">
                        <span class="badge"><?= e($subjectCode) ?></span>
                    </div>
                    <h3><?= e($subjectName) ?></h3>
                    <p><?= e($subjectDescription !== '' ? $truncate($subjectDescription, 110) : 'Description will be added by your moderator.') ?></p>
                    <div class="student-dash-subject-meta">
                        <span><?= (int) ($subject['credits'] ?? 0) ?> credits</span>
                        <span>Y<?= (int) ($subject['academic_year'] ?? 1) ?> / S<?= (int) ($subject['semester'] ?? 1) ?></span>
                    </div>
                    <div class="student-dash-inline-actions">
                        <a href="/subjects/<?= $subjectId ?>/topics" class="btn btn-sm btn-outline">Topics</a>
                        <a href="/coordinator/subjects/<?= $subjectId ?>/edit" class="btn btn-sm btn-outline">Edit</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="student-dash-card">
    <header class="student-dash-card-head">
        <h2><?= ui_lucide_icon('sparkles') ?> Recent Batch Activity</h2>
        <a href="/dashboard/feed" class="btn btn-sm btn-outline">Open Feed</a>
    </header>

    <?php if (empty($recentActivityItems)): ?>
        <p class="text-muted">No recent activity in your batch yet.</p>
    <?php else: ?>
        <div class="student-dash-activity-list">
            <?php foreach ($recentActivityItems as $item): ?>
                <?php
                $itemTypeLabel = (string) ($item['item_type_label'] ?? 'Update');
                $itemTitle = trim((string) ($item['title'] ?? 'Update'));
                $itemSummary = $truncate((string) ($item['summary'] ?? ''), 120);
                $itemActor = trim((string) ($item['actor_name'] ?? 'Unknown User'));
                $itemSubjectCode = trim((string) ($item['subject_code'] ?? ''));
                $itemUrl = trim((string) ($item['target_url'] ?? '/dashboard/feed'));
                $itemEventLabel = trim((string) ($item['event_label'] ?? 'Recently'));
                ?>
                <a href="<?= e($itemUrl) ?>" class="student-dash-activity-item">
                    <div class="student-dash-activity-head">
                        <span class="badge"><?= e($itemTypeLabel) ?></span>
                        <?php if ($itemSubjectCode !== ''): ?><span class="student-dash-activity-subject"><?= e($itemSubjectCode) ?></span><?php endif; ?>
                        <time><?= e($itemEventLabel) ?></time>
                    </div>
                    <h3><?= e($itemTitle) ?></h3>
                    <p><?= e($itemSummary !== '' ? $itemSummary : 'Open to view details.') ?></p>
                    <small><?= ui_lucide_icon('user-round') ?> <?= e($itemActor) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
