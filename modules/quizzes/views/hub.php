<?php
$subjects = (array) ($subjects ?? []);
$canCreate = (bool) ($can_create ?? false);
$canReview = (bool) ($can_review ?? false);
$pendingReviewCount = (int) ($pending_review_count ?? 0);
$myQuizCount = (int) ($my_quiz_count ?? 0);
$canViewPersonalAnalytics = (bool) ($can_view_personal_analytics ?? true);
$canViewReviewerAnalytics = (bool) ($can_view_reviewer_analytics ?? false);

$subjectCount = count($subjects);
$approvedTotal = 0;
$pendingTotal = 0;
$practiceTotal = 0;
$examTotal = 0;

foreach ($subjects as $subject) {
    $approvedTotal += (int) ($subject['approved_quizzes'] ?? 0);
    $pendingTotal += (int) ($subject['pending_quizzes'] ?? 0);
    $practiceTotal += (int) ($subject['practice_quizzes'] ?? 0);
    $examTotal += (int) ($subject['exam_quizzes'] ?? 0);
}
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Quizzes</p>
        <h1>Quiz Hub</h1>
        <p class="page-subtitle">Find quizzes by subject, publish new ones, and monitor performance in one place.</p>
    </div>
    <div class="page-header-actions">
        <?php if ($canViewPersonalAnalytics): ?>
            <a href="/my-quiz-analytics" class="btn btn-outline"><?= ui_lucide_icon('line-chart') ?> My Analytics</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a href="/my-quizzes" class="btn btn-outline"><?= ui_lucide_icon('clipboard-list') ?> My Quizzes (<?= $myQuizCount ?>)</a>
        <?php endif; ?>
        <?php if ($canReview): ?>
            <a href="/dashboard/quiz-requests" class="btn btn-primary"><?= ui_lucide_icon('check-check') ?> Quiz Requests (<?= $pendingReviewCount ?>)</a>
        <?php endif; ?>
        <?php if ($canViewReviewerAnalytics): ?>
            <a href="/dashboard/quiz-analytics" class="btn btn-outline"><?= ui_lucide_icon('chart-no-axes-column') ?> Review Analytics</a>
        <?php endif; ?>
        <a href="/dashboard" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Dashboard</a>
    </div>
</div>

<section class="quiz-hub-kpis">
    <article class="card quiz-hub-kpi-card">
        <div class="card-body">
            <span class="quiz-hub-kpi-label">Subjects</span>
            <strong><?= $subjectCount ?></strong>
            <p>Available in your scope</p>
        </div>
    </article>
    <article class="card quiz-hub-kpi-card">
        <div class="card-body">
            <span class="quiz-hub-kpi-label">Published Quizzes</span>
            <strong><?= $approvedTotal ?></strong>
            <p>Ready for learners</p>
        </div>
    </article>
    <article class="card quiz-hub-kpi-card">
        <div class="card-body">
            <span class="quiz-hub-kpi-label">Pending Reviews</span>
            <strong><?= $pendingTotal ?></strong>
            <p>Waiting for approvals</p>
        </div>
    </article>
    <article class="card quiz-hub-kpi-card">
        <div class="card-body">
            <span class="quiz-hub-kpi-label">Practice / Exam</span>
            <strong><?= $practiceTotal ?> / <?= $examTotal ?></strong>
            <p>Mode distribution</p>
        </div>
    </article>
</section>

<?php if (empty($subjects)): ?>
    <div class="card quiz-hub-empty-card">
        <div class="card-body">
            <h3><?= ui_lucide_icon('circle-help') ?> No subjects found</h3>
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
            $practiceCount = (int) ($subject['practice_quizzes'] ?? 0);
            $examCount = (int) ($subject['exam_quizzes'] ?? 0);
            ?>
            <article class="card quiz-hub-card">
                <div class="card-body">
                    <div class="quiz-hub-head">
                        <h3><?= e((string) ($subject['code'] ?? 'SUB')) ?></h3>
                        <span class="badge">Total <?= $totalCount ?></span>
                    </div>
                    <p class="quiz-hub-subject-name"><?= e((string) ($subject['name'] ?? 'Subject')) ?></p>

                    <div class="quiz-hub-stats">
                        <span class="badge badge-info">Approved <?= $approvedCount ?></span>
                        <span class="badge">Practice <?= $practiceCount ?></span>
                        <span class="badge">Exam <?= $examCount ?></span>
                        <?php if ($canReview): ?>
                            <span class="badge badge-warning">Pending <?= $pendingCount ?></span>
                        <?php endif; ?>
                        <?php if ($canCreate): ?>
                            <span class="badge">Mine <?= $myCount ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="quiz-hub-actions">
                        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes" class="btn btn-primary">Open Subject Quizzes</a>
                        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/leaderboard" class="btn btn-outline">Leaderboard</a>
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
