<?php
$quizzes = (array) ($quizzes ?? []);

$draftCount = 0;
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($quizzes as $quizRow) {
    $status = (string) ($quizRow['status'] ?? 'draft');
    if ($status === 'approved') {
        $approvedCount++;
    } elseif ($status === 'pending') {
        $pendingCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } else {
        $draftCount++;
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Quizzes / My Quizzes</p>
        <h1>My Quizzes</h1>
        <p class="page-subtitle">Manage your drafts, submissions, and published quizzes.</p>
    </div>
    <div class="page-header-actions">
        <a href="/my-quiz-analytics" class="btn btn-outline"><?= ui_lucide_icon('line-chart') ?> My Analytics</a>
        <a href="/dashboard" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Dashboard</a>
    </div>
</div>

<section class="quiz-my-kpis">
    <article class="card quiz-my-kpi-card"><div class="card-body"><span>Drafts</span><strong><?= $draftCount ?></strong></div></article>
    <article class="card quiz-my-kpi-card"><div class="card-body"><span>Pending</span><strong><?= $pendingCount ?></strong></div></article>
    <article class="card quiz-my-kpi-card"><div class="card-body"><span>Approved</span><strong><?= $approvedCount ?></strong></div></article>
    <article class="card quiz-my-kpi-card"><div class="card-body"><span>Rejected</span><strong><?= $rejectedCount ?></strong></div></article>
</section>

<?php if (empty($quizzes)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">You have not created any quizzes yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="quiz-my-grid">
        <?php foreach ($quizzes as $quiz): ?>
            <?php
            $quizId = (int) ($quiz['id'] ?? 0);
            $subjectId = (int) ($quiz['subject_id'] ?? 0);
            $status = (string) ($quiz['status'] ?? 'draft');
            $mode = (string) ($quiz['mode'] ?? 'practice');
            $isEditable = in_array($status, ['draft', 'rejected'], true);
            ?>
            <article class="card quiz-my-card">
                <div class="card-body">
                    <div class="quiz-my-card-head">
                        <h3><?= e((string) ($quiz['title'] ?? 'Untitled Quiz')) ?></h3>
                        <div class="quiz-my-card-badges">
                            <span class="badge <?= e(quizzes_mode_badge_class($mode)) ?>"><?= e(quizzes_mode_label($mode)) ?></span>
                            <span class="badge <?= e(quizzes_status_badge_class($status)) ?>"><?= e(quizzes_status_label($status)) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($quiz['description'])): ?>
                        <p class="quiz-my-card-desc"><?= e((string) $quiz['description']) ?></p>
                    <?php endif; ?>

                    <div class="quiz-my-meta">
                        <span><?= ui_lucide_icon('book-open') ?> <?= e((string) ($quiz['subject_code'] ?? 'SUB')) ?> - <?= e((string) ($quiz['subject_name'] ?? 'Subject')) ?></span>
                        <span><?= ui_lucide_icon('circle-help') ?> <?= (int) ($quiz['question_count'] ?? 0) ?> Questions</span>
                        <span><?= ui_lucide_icon('timer') ?> <?= (int) ($quiz['duration_minutes'] ?? 0) ?> min</span>
                        <span><?= ui_lucide_icon('calendar') ?> <?= e(date('Y-m-d H:i', strtotime((string) ($quiz['updated_at'] ?? 'now')))) ?></span>
                    </div>

                    <?php if ($status === 'rejected' && !empty($quiz['rejection_reason'])): ?>
                        <p class="quiz-my-reject-note"><strong>Rejection reason:</strong> <?= e((string) $quiz['rejection_reason']) ?></p>
                    <?php endif; ?>

                    <div class="quiz-my-actions">
                        <?php if ($status === 'approved'): ?>
                            <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline">View Quiz</a>
                        <?php endif; ?>

                        <?php if ($isEditable): ?>
                            <a href="/my-quizzes/<?= $quizId ?>/edit" class="btn btn-outline"><?= ui_lucide_icon('pencil') ?> Edit</a>
                            <form method="POST" action="/my-quizzes/<?= $quizId ?>/submit">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary"><?= ui_lucide_icon('send') ?> Submit</button>
                            </form>
                            <form method="POST" action="/my-quizzes/<?= $quizId ?>/delete" onsubmit="return confirm('Delete this quiz?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline"><?= ui_lucide_icon('trash-2') ?> Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
