<?php
$batch = (array) ($batch ?? []);
$batchCode = trim((string) ($batch['batch_code'] ?? ''));
$batchName = trim((string) ($batch['name'] ?? ''));
$batchStatus = trim((string) ($batch['status'] ?? 'pending'));
$statusLabel = ucfirst($batchStatus !== '' ? $batchStatus : 'pending');

$recentSubjects = (array) ($recent_subjects ?? $subjects ?? []);
$featuredSubjects = array_slice($recentSubjects, 0, 6);
$recentActivityItems = (array) ($recent_activity_items ?? []);
$nextSession = (array) ($next_session ?? []);
$hasNextSession = !empty($nextSession['id']);

$subjectCount = (int) ($subject_count ?? 0);
$resourceCount = (int) ($resource_count ?? 0);
$quizCount = (int) ($quiz_count ?? 0);
$openKuppiCount = (int) ($open_kuppi_count ?? 0);
$upcomingSessionCount = (int) ($upcoming_session_count ?? 0);
$pendingStudentRequests = (int) ($pending_student_requests ?? 0);
$pendingQuizRequests = (int) ($pending_quiz_requests ?? 0);
$openReportCount = (int) ($open_report_count ?? 0);
$todayBlockedCount = (int) ($today_blocked_count ?? 0);
$todayBlockedMinutes = (int) ($today_blocked_minutes ?? 0);
$todayBlockedHours = $todayBlockedMinutes > 0 ? $todayBlockedMinutes / 60 : 0;

$truncate = static function (string $text, int $length = 120): string {
    $normalized = trim((string) preg_replace('/\s+/', ' ', $text));
    if (strlen($normalized) <= $length) {
        return $normalized;
    }

    return substr($normalized, 0, $length - 3) . '...';
};
?>

<section class="student-dash-hero role-hero role-hero--moderator">
    <div class="student-dash-hero-main">
        <p class="student-dash-eyebrow">Moderator Dashboard</p>
        <h1>Operate your batch with quality control and fast decisions.</h1>
        <p class="student-dash-copy">
            Welcome back, <?= e((string) ($user['name'] ?? 'Moderator')) ?>.
            <?php if ($batchCode !== ''): ?>
                You are moderating <span class="student-dash-inline-strong"><?= e($batchCode) ?></span><?php if ($batchName !== ''): ?> · <?= e($batchName) ?><?php endif; ?>.
            <?php else: ?>
                Your moderator batch assignment is still pending.
            <?php endif; ?>
        </p>

        <div class="student-dash-chips">
            <span><?= ui_lucide_icon('users') ?> <?= $pendingStudentRequests ?> student requests</span>
            <span><?= ui_lucide_icon('check-check') ?> <?= $pendingQuizRequests ?> quiz requests</span>
            <span><?= ui_lucide_icon('flag') ?> <?= $openReportCount ?> open reports</span>
            <span><?= ui_lucide_icon('layers') ?> <?= $subjectCount ?> subjects</span>
        </div>
    </div>

    <aside class="student-dash-today">
        <h2><?= ui_lucide_icon('shield-check') ?> Moderator Queue</h2>
        <ul>
            <li><span>Join Requests</span><strong><?= $pendingStudentRequests ?></strong></li>
            <li><span>Quiz Requests</span><strong><?= $pendingQuizRequests ?></strong></li>
            <li><span>Community Reports</span><strong><?= $openReportCount ?></strong></li>
            <li><span>Lecture Blocks Today</span><strong><?= $todayBlockedCount ?></strong></li>
        </ul>
        <div class="student-dash-inline-actions">
            <a href="/moderator/join-requests" class="btn btn-sm btn-primary">Review Join Requests</a>
            <a href="/dashboard/quiz-requests" class="btn btn-sm btn-outline">Review Quizzes</a>
        </div>
    </aside>
</section>

<section class="student-dash-actions">
    <a href="/subjects" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('layers') ?></span>
        <strong>Batch Subjects</strong>
        <small>Create and maintain your subject catalog</small>
    </a>
    <a href="/subjects/create" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('plus') ?></span>
        <strong>Create Subject</strong>
        <small>Add new subject entries for your batch</small>
    </a>
    <a href="/students" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('users') ?></span>
        <strong>Manage Students</strong>
        <small>Track student roster and participation</small>
    </a>
    <a href="/dashboard/quiz-requests" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('check-check') ?></span>
        <strong>Quiz Requests</strong>
        <small>Approve or reject submitted quizzes</small>
    </a>
    <a href="/dashboard/quiz-analytics" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('chart-no-axes-column') ?></span>
        <strong>Review Analytics</strong>
        <small>Inspect question-level performance trends</small>
    </a>
    <a href="/dashboard/community/reports" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('flag') ?></span>
        <strong>Reports Queue</strong>
        <small>Resolve reported community issues</small>
    </a>
    <a href="/dashboard/gpa/grade-scale" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('calculator') ?></span>
        <strong>Grade Point Config</strong>
        <small>Maintain official batch grade scales</small>
    </a>
    <a href="/dashboard/kuppi/timetable" class="student-dash-action-card">
        <span class="student-dash-action-icon"><?= ui_lucide_icon('calendar-clock') ?></span>
        <strong>University Timetable</strong>
        <small>Block lecture slots for scheduling safety</small>
    </a>
</section>

<section class="student-dash-kpis">
    <article class="student-dash-kpi">
        <span>Published Resources</span>
        <strong><?= $resourceCount ?></strong>
        <p>Approved resources in your batch</p>
    </article>
    <article class="student-dash-kpi">
        <span>Approved Quizzes</span>
        <strong><?= $quizCount ?></strong>
        <p>Visible quizzes for your learners</p>
    </article>
    <article class="student-dash-kpi">
        <span>Open Kuppi Requests</span>
        <strong><?= $openKuppiCount ?></strong>
        <p>Current demand for learning sessions</p>
    </article>
    <article class="student-dash-kpi">
        <span>Blocked Lecture Hours</span>
        <strong><?= number_format($todayBlockedHours, 1) ?>h</strong>
        <p>Today from official university timetable</p>
    </article>
</section>

<section class="student-dash-grid">
    <article class="student-dash-card">
        <header class="student-dash-card-head">
            <h2><?= ui_lucide_icon('link') ?> Student Invite</h2>
        </header>

        <?php if (!empty($invite_link) && !empty($invite_qr_url)): ?>
            <p class="text-muted">Share this link with students so they can join your batch with pre-filled details.</p>
            <ul class="student-dash-personal-list">
                <li><span>Batch ID</span><strong><?= e($batchCode) ?></strong></li>
                <li><span>Batch Status</span><strong><?= e($statusLabel) ?></strong></li>
            </ul>
            <div class="dashboard-inline-copy">
                <input type="text" id="invite-link-input" value="<?= e((string) $invite_link) ?>" readonly>
                <button type="button" class="btn btn-sm btn-primary" id="copy-invite-btn">Copy Link</button>
            </div>
            <div class="moderator-invite-qr">
                <img src="<?= e((string) $invite_qr_url) ?>" alt="Batch invite QR code">
            </div>
        <?php else: ?>
            <p class="text-muted">Invite link appears after your batch is approved.</p>
        <?php endif; ?>
    </article>

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
            <p class="text-muted">No scheduled sessions yet.</p>
            <a href="/dashboard/kuppi/schedule" class="btn btn-sm btn-outline">Schedule Session</a>
        <?php endif; ?>
    </article>
</section>

<section class="student-dash-card">
    <header class="student-dash-card-head">
        <h2><?= ui_lucide_icon('book-open') ?> Batch Subject Snapshot</h2>
        <a href="/subjects" class="btn btn-sm btn-outline">Manage Subjects</a>
    </header>

    <?php if (empty($featuredSubjects)): ?>
        <p class="text-muted">No subjects are available for this batch yet.</p>
    <?php else: ?>
        <div class="student-dash-subject-grid">
            <?php foreach ($featuredSubjects as $subject): ?>
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
                        <a href="/subjects/<?= $subjectId ?>/coordinators" class="btn btn-sm btn-outline">Coordinators</a>
                        <a href="/subjects/<?= $subjectId ?>/edit" class="btn btn-sm btn-outline">Edit</a>
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

<script>
    (function () {
        const input = document.getElementById('invite-link-input');
        const button = document.getElementById('copy-invite-btn');
        if (!input || !button) {
            return;
        }

        button.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                input.focus();
                input.select();
                document.execCommand('copy');
            }

            const oldText = button.textContent;
            button.textContent = 'Copied';
            setTimeout(function () {
                button.textContent = oldText;
            }, 1400);
        });
    })();
</script>
