<?php
$subjectId = (int) ($subject['id'] ?? 0);
$quizId = (int) ($quiz['id'] ?? 0);
$attemptId = (int) ($attempt['id'] ?? 0);
$correctCount = (int) ($attempt['correct_count'] ?? 0);
$totalQuestions = max(0, (int) ($attempt['total_questions'] ?? 0));
$scorePercent = (float) ($attempt['score_percent'] ?? 0);
$resultRows = (array) ($result_rows ?? []);
$bestAttempt = is_array($best_attempt ?? null) ? $best_attempt : null;
$attemptCount = (int) ($attempt_count ?? 0);
$isBest = $bestAttempt && (int) ($bestAttempt['id'] ?? 0) === $attemptId;
$wrongCount = max(0, $totalQuestions - $correctCount);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Quiz Result</p>
        <h1><?= e((string) ($quiz['title'] ?? 'Quiz')) ?> Result</h1>
        <p class="page-subtitle">Review your score and correct answers.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Quiz</a>
    </div>
</div>

<section class="quiz-result-hero card">
    <div class="card-body">
        <div class="quiz-result-score">
            <strong><?= e(number_format($scorePercent, 2)) ?>%</strong>
            <p><?= $correctCount ?> / <?= $totalQuestions ?> correct</p>
            <small>Status: <?= e(quizzes_attempt_status_label((string) ($attempt['status'] ?? 'submitted'))) ?></small>
        </div>

        <div class="quiz-result-meta">
            <span class="badge">Attempts <?= $attemptCount ?></span>
            <span class="badge">Wrong <?= $wrongCount ?></span>
            <?php if ($bestAttempt): ?>
                <span class="badge">Best <?= e(number_format((float) ($bestAttempt['score_percent'] ?? 0), 2)) ?>%</span>
            <?php endif; ?>
            <?php if ($isBest): ?>
                <span class="badge badge-info">This is your best score</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="quiz-result-list">
    <?php foreach ($resultRows as $index => $row): ?>
        <?php $isCorrect = (int) ($row['is_correct'] ?? 0) === 1; ?>
        <article class="card quiz-result-item <?= $isCorrect ? 'is-correct' : 'is-incorrect' ?>">
            <div class="card-body">
                <div class="quiz-result-item-head">
                    <h3>Q<?= (int) $index + 1 ?>. <?= e((string) ($row['question_text'] ?? '')) ?></h3>
                    <span class="badge <?= $isCorrect ? 'badge-info' : 'badge-danger' ?>"><?= $isCorrect ? 'Correct' : 'Incorrect' ?></span>
                </div>

                <div class="quiz-result-answer-rows">
                    <p><strong>Your answer:</strong> <?= e((string) ($row['selected_option_text'] ?? 'No answer')) ?></p>
                    <p><strong>Correct answer:</strong> <?= e((string) ($row['correct_option_text'] ?? '-')) ?></p>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<div class="quiz-result-actions">
    <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline">Back to Quiz</a>
    <form method="POST" action="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/attempts/start">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Retake Quiz</button>
    </form>
</div>
