<?php
$batchId = (int) ($user['batch_id'] ?? 0);
$subjectRows = (array) ($subjects ?? []);
$featuredSubjects = array_slice($subjectRows, 0, 4);
$batchMeta = (array) ($batch_meta ?? []);
$recentActivityItems = (array) ($recent_activity_items ?? []);
$nextSession = (array) ($next_session ?? []);
$hasNextSession = !empty($nextSession['id']);

$subjectCount = (int) ($subject_count ?? count($subjectRows));
$resourceCount = (int) ($resource_count ?? 0);
$quizCount = (int) ($quiz_count ?? 0);
$communityCount = (int) ($community_count ?? 0);
$openKuppiCount = (int) ($open_kuppi_count ?? 0);
$upcomingSessionCount = (int) ($upcoming_session_count ?? 0);
$myPostCount = (int) ($my_post_count ?? 0);
$myResourceCount = (int) ($my_resource_count ?? 0);
$myQuizCount = (int) ($my_quiz_count ?? 0);
$myKuppiRequestCount = (int) ($my_kuppi_request_count ?? 0);
$todayBlockedCount = (int) ($today_blocked_count ?? 0);
$todayBlockedMinutes = (int) ($today_blocked_minutes ?? 0);

$quizSummary = (array) ($quiz_summary ?? []);
$quizAttemptCount = (int) ($quizSummary['attempt_count'] ?? 0);
$quizAvgScore = isset($quizSummary['avg_score']) ? (float) $quizSummary['avg_score'] : null;
$quizBestScore = isset($quizSummary['best_score']) ? (float) $quizSummary['best_score'] : null;
$quizTotalCorrect = (int) ($quizSummary['total_correct'] ?? 0);
$quizTotalQuestions = (int) ($quizSummary['total_questions'] ?? 0);
$quizAccuracy = $quizTotalQuestions > 0 ? ($quizTotalCorrect / $quizTotalQuestions) * 100 : null;

$gpaSummary = (array) ($gpa_summary ?? []);
$latestGpa = isset($gpaSummary['latest_gpa']) ? (float) $gpaSummary['latest_gpa'] : null;
$bestGpa = isset($gpaSummary['best_gpa']) ? (float) $gpaSummary['best_gpa'] : null;
$gpaLatestYear = isset($gpaSummary['latest_academic_year']) ? (int) $gpaSummary['latest_academic_year'] : null;
$gpaLatestSemester = isset($gpaSummary['latest_semester']) ? (int) $gpaSummary['latest_semester'] : null;

$batchCode = trim((string) ($batchMeta['batch_code'] ?? ''));
$batchName = trim((string) ($batchMeta['name'] ?? ''));
$batchUniversityName = trim((string) ($batchMeta['university_name'] ?? ''));
$todayBlockedHours = $todayBlockedMinutes > 0 ? $todayBlockedMinutes / 60 : 0;

$truncate = static function (string $text, int $length = 120): string {
    $normalized = trim((string) preg_replace('/\s+/', ' ', $text));
    if (strlen($normalized) <= $length) {
        return $normalized;
    }
    return substr($normalized, 0, $length - 3) . '...';
};
?>

<section class="student-dash-hero role-hero role-hero--student">
    <div class="student-dash-hero-main">
        <p class="student-dash-eyebrow">Student Dashboard</p>
        <h1>Stay on track with your batch goals.</h1>
        <p class="student-dash-copy">
            Welcome back, <?= e((string) ($user['name'] ?? 'Student')) ?>.
            <?php if ($batchCode !== ''): ?>
                You are learning in <span class="student-dash-inline-strong"><?= e($batchCode) ?></span>
                <?php if ($batchName !== ''): ?> · <?= e($batchName) ?><?php endif; ?>
                <?php if ($batchUniversityName !== ''): ?> · <?= e($batchUniversityName) ?><?php endif; ?>.
            <?php elseif ($batchId > 0): ?>
                You are learning in <span class="student-dash-inline-strong">Batch #<?= $batchId ?></span>.
            <?php endif; ?>
        </p>

        <div class="student-dash-chips">
            <span><?= ui_lucide_icon('book-open') ?> <?= $subjectCount ?> subjects</span>
            <span><?= ui_lucide_icon('folder-open') ?> <?= $resourceCount ?> resources</span>
            <span><?= ui_lucide_icon('clipboard-check') ?> <?= $quizCount ?> approved quizzes</span>
            <span><?= ui_lucide_icon('message-square') ?> <?= $communityCount ?> posts</span>
        </div>
    </div>

    <aside class="student-dash-today">
        <h2><?= ui_lucide_icon('calendar-range') ?> Today at a Glance</h2>
        <ul>
            <li><span>Open Kuppi Requests</span><strong><?= $openKuppiCount ?></strong></li>
            <li><span>Upcoming Sessions</span><strong><?= $upcomingSessionCount ?></strong></li>
            <li><span>Official Lecture Blocks</span><strong><?= $todayBlockedCount ?></strong></li>
            <li><span>Blocked Hours</span><strong><?= number_format($todayBlockedHours, 1) ?>h</strong></li>
        </ul>
        <a href="/dashboard/kuppi/timetable" class="btn btn-sm btn-outline">View University Timetable</a>
    </aside>
</section>

<section class="student-dash-actions">
    <a href="/dashboard/feed" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('newspaper') ?></span>
        <strong>Central Feed</strong>
        <small>Catch all latest batch activity</small>
    </a>
    <a href="/dashboard/announcements" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('megaphone') ?></span>
        <strong>Announcements</strong>
        <small>Read official batch updates</small>
    </a>
    <a href="/dashboard/subjects" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('book-open') ?></span>
        <strong>Subjects</strong>
        <small>Open notes, topics, and resources</small>
    </a>
    <a href="/dashboard/quizzes" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('clipboard-check') ?></span>
        <strong>Quiz Hub</strong>
        <small>Practice and improve your score</small>
    </a>
    <a href="/dashboard/kuppi" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('calendar-plus') ?></span>
        <strong>Kuppi Sessions</strong>
        <small>Request and join peer sessions</small>
    </a>
    <a href="/dashboard/gpa/analytics" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('line-chart') ?></span>
        <strong>GPA Analytics</strong>
        <small>Track academic progression</small>
    </a>
    <a href="/dashboard/community" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('messages-square') ?></span>
        <strong>Community</strong>
        <small>Join questions and discussions</small>
    </a>
</section>

<section class="student-dash-kpis">
    <article class="student-dash-kpi">
        <span>Quiz Attempts</span>
        <strong><?= $quizAttemptCount ?></strong>
        <p>Best <?= $quizBestScore !== null ? number_format($quizBestScore, 1) . '%' : '-' ?> · Avg <?= $quizAvgScore !== null ? number_format($quizAvgScore, 1) . '%' : '-' ?></p>
    </article>
    <article class="student-dash-kpi">
        <span>Quiz Accuracy</span>
        <strong><?= $quizAccuracy !== null ? number_format($quizAccuracy, 1) . '%' : '-' ?></strong>
        <p><?= $quizTotalCorrect ?> correct from <?= $quizTotalQuestions ?> answered</p>
    </article>
    <article class="student-dash-kpi">
        <span>Latest GPA</span>
        <strong><?= $latestGpa !== null ? number_format($latestGpa, 2) : '-' ?></strong>
        <p>
            <?php if ($gpaLatestYear !== null && $gpaLatestSemester !== null): ?>
                Y<?= $gpaLatestYear ?> · S<?= $gpaLatestSemester ?>
            <?php else: ?>
                Save your first GPA term
            <?php endif; ?>
        </p>
    </article>
    <article class="student-dash-kpi">
        <span>Best GPA</span>
        <strong><?= $bestGpa !== null ? number_format($bestGpa, 2) : '-' ?></strong>
        <p>From your saved GPA records</p>
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
            $sessionLocationType = (string) ($nextSession['location_type'] ?? '');
            $sessionLocationText = trim((string) ($nextSession['location_text'] ?? ''));
            $sessionMeetingLink = trim((string) ($nextSession['meeting_link'] ?? ''));
            $locationLabel = $sessionLocationType === 'online'
                ? ($sessionMeetingLink !== '' ? 'Online link ready' : 'Online session')
                : ($sessionLocationText !== '' ? $sessionLocationText : 'Physical location');
            ?>
            <div class="student-dash-session-card">
                <p class="student-dash-session-title">
                    <?php if ($sessionSubjectCode !== ''): ?><span class="badge"><?= e($sessionSubjectCode) ?></span><?php endif; ?>
                    <strong><?= e($sessionTitle) ?></strong>
                </p>
                <ul class="student-dash-session-meta">
                    <li><?= ui_lucide_icon('calendar-days') ?> <?= e($sessionDateLabel) ?></li>
                    <li><?= ui_lucide_icon('clock-3') ?> <?= e($sessionTimeLabel) ?></li>
                    <li><?= ui_lucide_icon('map-pin') ?> <?= e($locationLabel) ?></li>
                </ul>
                <a href="/dashboard/kuppi/scheduled/<?= $sessionId ?>" class="btn btn-sm btn-primary">Open Session</a>
            </div>
        <?php else: ?>
            <p class="text-muted">No scheduled sessions yet. Start by browsing open requests from your batch.</p>
            <a href="/dashboard/kuppi" class="btn btn-sm btn-outline">Browse Open Requests</a>
        <?php endif; ?>
    </article>

    <article class="student-dash-card">
        <header class="student-dash-card-head">
            <h2><?= ui_lucide_icon('target') ?> Personal Activity</h2>
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
        <h2><?= ui_lucide_icon('sparkles') ?> Recent Batch Activity</h2>
        <a href="/dashboard/feed" class="btn btn-sm btn-outline">Open Feed</a>
    </header>

    <?php if (empty($recentActivityItems)): ?>
        <p class="text-muted">No recent activity in your batch yet.</p>
    <?php else: ?>
        <div class="student-dash-activity-list">
            <?php foreach ($recentActivityItems as $item): ?>
                <?php
                $itemType = (string) ($item['item_type'] ?? 'community');
                $itemTypeLabel = (string) ($item['item_type_label'] ?? ucfirst(str_replace('_', ' ', $itemType)));
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

<section class="student-dash-card">
    <header class="student-dash-card-head">
        <h2><?= ui_lucide_icon('book-open') ?> Subject Focus</h2>
        <a href="/dashboard/subjects" class="btn btn-sm btn-outline">View All Subjects</a>
    </header>

    <?php if (empty($featuredSubjects)): ?>
        <p class="text-muted">No subjects are available in your batch yet.</p>
    <?php else: ?>
        <div class="student-dash-subject-grid">
            <?php foreach ($featuredSubjects as $subject): ?>
                <?php
                $subjectId = (int) ($subject['id'] ?? 0);
                $subjectCode = trim((string) ($subject['code'] ?? 'SUBJECT'));
                $subjectName = trim((string) ($subject['name'] ?? 'Untitled Subject'));
                $subjectDescription = trim((string) ($subject['description'] ?? ''));
                ?>
                <a href="/dashboard/subjects/<?= $subjectId ?>/topics" class="student-dash-subject-card">
                    <div class="student-dash-subject-head">
                        <span class="badge"><?= e($subjectCode) ?></span>
                    </div>
                    <h3><?= e($subjectName) ?></h3>
                    <p><?= e($subjectDescription !== '' ? $truncate($subjectDescription, 110) : 'Description will be added by your moderator.') ?></p>
                    <div class="student-dash-subject-meta">
                        <span><?= (int) ($subject['credits'] ?? 0) ?> credits</span>
                        <span>Y<?= (int) ($subject['academic_year'] ?? 1) ?> / S<?= (int) ($subject['semester'] ?? 1) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
