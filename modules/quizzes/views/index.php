<?php
$subjectId = (int) ($subject['id'] ?? 0);
$subjectCode = (string) ($subject['code'] ?? 'SUB');
$subjectName = (string) ($subject['name'] ?? 'Subject');
$quizzes = (array) ($quizzes ?? []);
$leaderboardTop = (array) ($leaderboard_top ?? []);

$totalQuizzes = count($quizzes);
$totalAttempts = 0;
$bestSeenScore = null;
$practiceCount = 0;
$examCount = 0;

foreach ($quizzes as $quizRow) {
    $totalAttempts += (int) ($quizRow['viewer_attempt_count'] ?? 0);

    $mode = (string) ($quizRow['mode'] ?? 'practice');
    if ($mode === 'exam') {
        $examCount++;
    } else {
        $practiceCount++;
    }

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
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/leaderboard" class="btn btn-outline"><?= ui_lucide_icon('trophy') ?> Leaderboard</a>
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
    <article class="card quiz-catalog-kpi-card">
        <div class="card-body">
            <span>Practice / Exam</span>
            <strong><?= $practiceCount ?> / <?= $examCount ?></strong>
        </div>
    </article>
</section>

<div class="quiz-catalog-layout">
    <section>
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
                    $mode = (string) ($quiz['mode'] ?? 'practice');
                    ?>
                    <article class="card quiz-catalog-card">
                        <div class="card-body">
                            <div class="quiz-catalog-head">
                                <h3><?= e((string) ($quiz['title'] ?? 'Untitled Quiz')) ?></h3>
                                <div class="quiz-catalog-badges">
                                    <span class="badge <?= e(quizzes_mode_badge_class($mode)) ?>"><?= e(quizzes_mode_label($mode)) ?></span>
                                    <span class="badge badge-info">Approved</span>
                                </div>
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
    </section>

    <aside class="quiz-catalog-side">
        <article class="card quiz-catalog-side-card">
            <div class="card-body">
                <h3><?= ui_lucide_icon('trophy') ?> Subject Leaderboard</h3>
                <?php if (empty($leaderboardTop)): ?>
                    <p class="text-muted">No completed exam attempts yet.</p>
                <?php else: ?>
                    <ol class="quiz-mini-leaderboard">
                        <?php foreach ($leaderboardTop as $row): ?>
                            <li>
                                <span><?= e((string) ($row['student_name'] ?? 'Student')) ?></span>
                                <strong><?= e(number_format((float) ($row['best_score'] ?? 0), 2)) ?>%</strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/leaderboard" class="btn btn-outline">Open Full Leaderboard</a>
            </div>
        </article>

        <article class="card quiz-catalog-side-card">
            <div class="card-body">
                <h3><?= ui_lucide_icon('line-chart') ?> Analytics</h3>
                <p class="text-muted">Track your quiz performance and difficult questions from your analytics page.</p>
                <a href="/my-quiz-analytics" class="btn btn-outline">Open My Quiz Analytics</a>
            </div>
        </article>
    </aside>
</div>
