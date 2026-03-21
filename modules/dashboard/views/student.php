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
        <a href="/dashboard/subjects" class="btn btn-primary">Browse Subjects</a>
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
                <article class="subject-tile">
                    <span class="badge"><?= e($subject['code']) ?></span>
                    <h3><?= e($subject['name']) ?></h3>
                    <p><?= !empty($subject['description']) ? e($subject['description']) : 'Description will be added by your moderator.' ?></p>
                    <small><?= (int) $subject['credits'] ?> credits</small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
