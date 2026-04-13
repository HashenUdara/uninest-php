<?php
$subjects = (array) ($subjects ?? []);
$canCreate = (bool) ($can_create ?? false);
$canReview = (bool) ($can_review ?? false);
$pendingReviewCount = (int) ($pending_review_count ?? 0);
$myQuizCount = (int) ($my_quiz_count ?? 0);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Quizzes</p>
        <h1>Quiz Hub</h1>
        <p class="page-subtitle">Find quizzes fast by subject, create new ones, and jump into review queues.</p>
    </div>
    <div class="page-header-actions">
        <?php if ($canCreate): ?>
            <a href="/my-quizzes" class="btn btn-outline"><?= ui_lucide_icon('clipboard-list') ?> My Quizzes (<?= $myQuizCount ?>)</a>
        <?php endif; ?>
        <?php if ($canReview): ?>
            <a href="/dashboard/quiz-requests" class="btn btn-primary"><?= ui_lucide_icon('check-check') ?> Quiz Requests (<?= $pendingReviewCount ?>)</a>
        <?php endif; ?>
        <a href="/dashboard" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Dashboard</a>
    </div>
</div>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No subjects available in your scope yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="quiz-hub-grid">
        <?php foreach ($subjects as $subject): ?>
            <?php
            $subjectId = (int) ($subject['id'] ?? 0);
            $approvedCount = (int) ($subject['approved_quizzes'] ?? 0);
            $pendingCount = (int) ($subject['pending_quizzes'] ?? 0);
            $totalCount = (int) ($subject['total_quizzes'] ?? 0);
            $myCount = (int) ($subject['my_quizzes'] ?? 0);
            ?>
            <article class="card quiz-hub-card">
                <div class="card-body">
                    <div class="quiz-hub-head">
                        <h3><?= e((string) ($subject['code'] ?? 'SUB')) ?> - <?= e((string) ($subject['name'] ?? 'Subject')) ?></h3>
                        <span class="badge">Total: <?= $totalCount ?></span>
                    </div>

                    <div class="quiz-hub-stats">
                        <span class="badge badge-info">Approved: <?= $approvedCount ?></span>
                        <?php if ($canReview): ?>
                            <span class="badge badge-warning">Pending: <?= $pendingCount ?></span>
                        <?php endif; ?>
                        <?php if ($canCreate): ?>
                            <span class="badge">My Quizzes: <?= $myCount ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="quiz-hub-actions">
                        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes" class="btn btn-primary">View Quizzes</a>
                        <?php if ($canCreate): ?>
                            <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/create" class="btn btn-outline">Create Quiz</a>
                        <?php endif; ?>
                        <a href="/dashboard/subjects/<?= $subjectId ?>/topics" class="btn btn-outline">Topics</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
