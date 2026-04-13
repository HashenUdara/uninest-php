<?php
$subjectId = (int) ($subject['id'] ?? 0);
$quizId = (int) ($quiz['id'] ?? 0);
$questions = (array) ($questions ?? []);
$questionCount = (int) ($quiz['question_count'] ?? count($questions));
$attemptCount = (int) ($attempt_count ?? 0);
$bestAttempt = is_array($best_attempt ?? null) ? $best_attempt : null;
$inProgressAttempt = is_array($in_progress_attempt ?? null) ? $in_progress_attempt : null;
$bestScore = $bestAttempt ? (float) ($bestAttempt['score_percent'] ?? 0) : null;
$bestAttemptAt = null;

if ($bestAttempt && !empty($bestAttempt['submitted_at'])) {
    $timestamp = strtotime((string) $bestAttempt['submitted_at']);
    if ($timestamp !== false) {
        $bestAttemptAt = date('M d, Y h:i A', $timestamp);
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Subjects / Quizzes</p>
        <h1><?= e((string) ($quiz['title'] ?? 'Quiz')) ?></h1>
        <p class="page-subtitle"><?= e((string) ($quiz['description'] ?? 'Practice your understanding with this quiz.')) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Quizzes</a>
    </div>
</div>

<div class="quiz-detail-layout">
    <section class="quiz-detail-main">
        <article class="card quiz-detail-overview-card">
            <div class="card-body">
                <div class="quiz-detail-overview-head">
                    <div class="quiz-detail-overview-status">
                        <span class="badge badge-info">Approved Quiz</span>
                        <span class="quiz-detail-overview-caption">Ready to attempt</span>
                    </div>
                    <?php if ($bestScore !== null): ?>
                        <div class="quiz-detail-best-pill">
                            <?= ui_lucide_icon('trophy') ?>
                            Best Score: <?= e(number_format($bestScore, 2)) ?>%
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quiz-detail-overview-metrics">
                    <div class="quiz-detail-overview-metric">
                        <span><?= ui_lucide_icon('circle-help') ?> Questions</span>
                        <strong><?= $questionCount ?></strong>
                    </div>
                    <div class="quiz-detail-overview-metric">
                        <span><?= ui_lucide_icon('timer') ?> Duration</span>
                        <strong><?= (int) ($quiz['duration_minutes'] ?? 0) ?> min</strong>
                    </div>
                    <div class="quiz-detail-overview-metric">
                        <span><?= ui_lucide_icon('rotate-ccw') ?> Attempts</span>
                        <strong><?= $attemptCount ?></strong>
                    </div>
                    <div class="quiz-detail-overview-metric">
                        <span><?= ui_lucide_icon('user') ?> Author</span>
                        <strong><?= e((string) ($quiz['creator_name'] ?? 'Unknown')) ?></strong>
                    </div>
                </div>
            </div>
        </article>

        <article class="card quiz-detail-card">
            <div class="card-body">
                <div class="quiz-detail-section-head">
                    <h2>Question Preview</h2>
                    <p>Review the question style before you start. Correct answers are hidden until submission.</p>
                </div>

                <div class="quiz-detail-question-list">
                    <?php foreach ($questions as $index => $question): ?>
                        <article class="quiz-detail-question-item">
                            <header class="quiz-detail-question-head">
                                <span class="quiz-detail-question-number">Q<?= (int) $index + 1 ?></span>
                                <h3><?= e((string) ($question['question_text'] ?? '')) ?></h3>
                            </header>

                            <ul class="quiz-detail-option-list">
                                <?php foreach ((array) ($question['options'] ?? []) as $optionIndex => $option): ?>
                                    <li>
                                        <span class="quiz-detail-option-letter"><?= e(chr(65 + (int) $optionIndex)) ?></span>
                                        <span><?= e((string) ($option['option_text'] ?? '')) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
    </section>

    <aside class="quiz-detail-side">
        <article class="card quiz-detail-side-card">
            <div class="card-body">
                <h3><?= ui_lucide_icon('chart-no-axes-column') ?> Your Performance</h3>
                <ul class="quiz-detail-stats">
                    <li><span>Attempts</span><strong><?= $attemptCount ?></strong></li>
                    <li><span>Best Score</span><strong><?= $bestScore !== null ? e(number_format($bestScore, 2)) . '%' : '-' ?></strong></li>
                    <li><span>Last Best Attempt</span><strong><?= e($bestAttemptAt ?? '-') ?></strong></li>
                </ul>

                <?php if ($bestScore !== null): ?>
                    <div class="quiz-detail-score-track" role="presentation">
                        <span style="width: <?= max(0, min(100, $bestScore)) ?>%;"></span>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="card quiz-detail-side-card">
            <div class="card-body">
                <h3><?= ui_lucide_icon('rocket') ?> Ready to Attempt?</h3>
                <p class="quiz-detail-side-note">Timer starts once you begin. You can retake and your best score is kept.</p>

                <div class="quiz-detail-actions">
                    <?php if ($inProgressAttempt): ?>
                        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/attempts/<?= (int) ($inProgressAttempt['id'] ?? 0) ?>" class="btn btn-primary">Resume Attempt</a>
                    <?php endif; ?>

                    <form method="POST" action="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/attempts/start">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn <?= $inProgressAttempt ? 'btn-outline' : 'btn-primary' ?>">
                            <?= $inProgressAttempt ? 'Start New Attempt' : 'Start Attempt' ?>
                        </button>
                    </form>
                </div>
            </div>
        </article>

        <article class="card quiz-detail-side-card quiz-detail-guidelines">
            <div class="card-body">
                <h3><?= ui_lucide_icon('info') ?> Attempt Guidelines</h3>
                <ul>
                    <li><?= (int) ($quiz['duration_minutes'] ?? 0) ?> minutes per attempt</li>
                    <li>Single-correct MCQ format</li>
                    <li>Auto-submit when time expires</li>
                    <li>Best score shown on your profile</li>
                </ul>
            </div>
        </article>
    </aside>
</div>
