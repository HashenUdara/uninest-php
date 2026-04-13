<?php
$subjectId = (int) ($subject['id'] ?? 0);
$subjectCode = (string) ($subject['code'] ?? 'SUB');
$subjectName = (string) ($subject['name'] ?? 'Subject');
$quizzes = (array) ($quizzes ?? []);

$totalQuizzes = count($quizzes);
$totalAttempts = 0;
$bestSeenScore = null;

foreach ($quizzes as $quizRow) {
    $totalAttempts += (int) ($quizRow['viewer_attempt_count'] ?? 0);
    if ($quizRow['viewer_best_score'] !== null) {
        $score = (float) $quizRow['viewer_best_score'];
        if ($bestSeenScore === null || $score > $bestSeenScore) {
            $bestSeenScore = $score;
        }
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Subjects / Quizzes</p>
        <h1><?= e($subjectCode) ?> Quizzes</h1>
        <p class="page-subtitle">Approved quizzes available for <strong><?= e($subjectName) ?></strong>.</p>
    </div>
    <div class="page-header-actions">
        <?php if (!empty($can_create)): ?>
            <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/create" class="btn btn-primary"><?= ui_lucide_icon('plus') ?> Create Quiz</a>
        <?php endif; ?>
        <a href="/dashboard/subjects/<?= $subjectId ?>/topics" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Topics</a>
    </div>
</div>

<section class="quiz-catalog-kpis">
    <article class="card quiz-catalog-kpi-card">
        <div class="card-body">
            <span>Available Quizzes</span>
            <strong><?= $totalQuizzes ?></strong>
        </div>
    </article>
    <article class="card quiz-catalog-kpi-card">
        <div class="card-body">
            <span>Your Attempts</span>
            <strong><?= $totalAttempts ?></strong>
        </div>
    </article>
    <article class="card quiz-catalog-kpi-card">
        <div class="card-body">
            <span>Your Best</span>
            <strong><?= $bestSeenScore !== null ? e(number_format($bestSeenScore, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
</section>

<?php if (empty($quizzes)): ?>
    <div class="card quiz-catalog-empty-card">
        <div class="card-body">
            <h3><?= ui_lucide_icon('circle-help') ?> No quizzes published yet</h3>
            <p class="text-muted">No approved quizzes yet for this subject.</p>
            <?php if (!empty($can_create)): ?>
                <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/create" class="btn btn-primary">Create the First Quiz</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="quiz-catalog-grid">
        <?php foreach ($quizzes as $quiz): ?>
            <?php
            $quizId = (int) ($quiz['id'] ?? 0);
            $attemptCount = (int) ($quiz['viewer_attempt_count'] ?? 0);
            $bestScore = $quiz['viewer_best_score'];
            ?>
            <article class="card quiz-catalog-card">
                <div class="card-body">
                    <div class="quiz-catalog-head">
                        <h3><?= e((string) ($quiz['title'] ?? 'Untitled Quiz')) ?></h3>
                        <span class="badge badge-info">Approved</span>
                    </div>

                    <p class="quiz-catalog-description">
                        <?= e((string) ($quiz['description'] ?? 'No description provided.')) ?>
                    </p>

                    <div class="quiz-catalog-meta">
                        <span><?= ui_lucide_icon('circle-help') ?> <?= (int) ($quiz['question_count'] ?? 0) ?> Questions</span>
                        <span><?= ui_lucide_icon('timer') ?> <?= (int) ($quiz['duration_minutes'] ?? 0) ?> min</span>
                        <span><?= ui_lucide_icon('user') ?> <?= e((string) ($quiz['creator_name'] ?? 'Unknown')) ?></span>
                    </div>

                    <div class="quiz-catalog-stats">
                        <span class="badge">Attempts <?= $attemptCount ?></span>
                        <?php if ($bestScore !== null): ?>
                            <span class="badge">Best <?= e(number_format((float) $bestScore, 2)) ?>%</span>
                        <?php endif; ?>
                    </div>

                    <div class="quiz-catalog-actions">
                        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline">View Quiz</a>
                        <form method="POST" action="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/attempts/start">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary">Take Quiz</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
