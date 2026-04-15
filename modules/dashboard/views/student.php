<?php
$batchId = (int) ($user['batch_id'] ?? 0);
$featuredSubjects = array_slice($subjects, 0, 6);
?>

<section class="dash-hero">
    <p class="dash-eyebrow">Student Workspace</p>
    <h1>Learn with your batch. Grow through Kuppi sessions.</h1>
    <p class="dash-copy">
        Welcome back, <?= e($user['name']) ?>.
        <?php if ($batchId > 0): ?>
            You are currently in <span class="inline-strong">Batch #<?= $batchId ?></span>.
        <?php endif; ?>
    </p>
    <div class="dash-action-row">
        <a href="/dashboard/feed" class="btn btn-primary">Central Feed</a>
        <a href="/dashboard/subjects" class="btn btn-primary">Browse Subjects</a>
        <a href="/dashboard/quizzes" class="btn btn-outline">Quiz Hub</a>
        <a href="/dashboard/gpa" class="btn btn-outline">GPA Calculator</a>
        <a href="/dashboard/gpa/analytics" class="btn btn-outline">GPA Analytics</a>
        <a href="/dashboard/kuppi" class="btn btn-outline">Requested Kuppi</a>
        <a href="/dashboard/community" class="btn btn-outline">Community Feed</a>
        <a href="/my-posts" class="btn btn-outline">My Posts</a>
        <a href="/my-resources" class="btn btn-outline">My Resources</a>
        <a href="/my-quizzes" class="btn btn-outline">My Quizzes</a>
        <a href="/onboarding" class="btn btn-outline">View Onboarding Status</a>
    </div>
</section>

<section class="dash-kpi-grid">
    <article class="kpi-card">
        <span class="kpi-label">Available Subjects</span>
        <strong><?= count($subjects) ?></strong>
        <p>Subjects scoped to your approved batch.</p>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Batch ID</span>
        <strong><?= $batchId > 0 ? $batchId : '-' ?></strong>
        <p>Your access boundary for content and sessions.</p>
    </article>
</section>

<section class="dash-panel">
    <header class="dash-panel-header">
        <h2>My Batch Subjects</h2>
        <a href="/dashboard/subjects" class="btn btn-sm btn-outline">View Full List</a>
    </header>

    <?php if (empty($featuredSubjects)): ?>
        <p class="text-muted">No subjects are available in your batch yet.</p>
    <?php else: ?>
        <div class="subject-tiles">
            <?php foreach ($featuredSubjects as $subject): ?>
                <?php
                $status = (string) ($subject['status'] ?? 'upcoming');
                $statusClass = match ($status) {
                    'in_progress' => 'badge-info',
                    'completed' => 'badge-warning',
                    default => '',
                };
                ?>
                <a href="/dashboard/subjects/<?= (int) $subject['id'] ?>/topics" class="subject-tile-link" aria-label="Open topics for <?= e($subject['name']) ?>">
                    <article class="subject-tile">
                        <span class="badge"><?= e($subject['code']) ?></span>
                        <h3><?= e($subject['name']) ?></h3>
                        <p><?= !empty($subject['description']) ? e($subject['description']) : 'Description will be added by your moderator.' ?></p>
                        <small><?= (int) $subject['credits'] ?> credits</small>
                        <div class="subject-meta">
                            <span class="badge">Y<?= (int) ($subject['academic_year'] ?? 1) ?> / S<?= (int) ($subject['semester'] ?? 1) ?></span>
                            <span class="badge <?= e($statusClass) ?>"><?= e(subjects_status_label($status)) ?></span>
                        </div>
                    </article>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
