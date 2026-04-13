<?php
$summary = (array) ($summary ?? []);
$trend = (array) ($trend ?? []);
$modeBreakdownRows = (array) ($mode_breakdown ?? []);
$mostMissed = (array) ($most_missed ?? []);

$attemptCount = (int) ($summary['attempt_count'] ?? 0);
$avgScore = $summary['avg_score'] !== null ? (float) $summary['avg_score'] : null;
$bestScore = $summary['best_score'] !== null ? (float) $summary['best_score'] : null;
$totalCorrect = (int) ($summary['total_correct'] ?? 0);
$totalQuestions = (int) ($summary['total_questions'] ?? 0);
$accuracy = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : null;

$modeBreakdown = [];
foreach ($modeBreakdownRows as $row) {
    $mode = (string) ($row['mode'] ?? 'practice');
    $modeBreakdown[$mode] = $row;
}

$practiceData = (array) ($modeBreakdown['practice'] ?? []);
$examData = (array) ($modeBreakdown['exam'] ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Quizzes / Analytics</p>
        <h1>My Quiz Analytics</h1>
        <p class="page-subtitle">Track your trends, compare practice vs exam performance, and focus on most-missed questions.</p>
    </div>
    <div class="page-header-actions">
        <a href="/my-quizzes" class="btn btn-outline"><?= ui_lucide_icon('clipboard-list') ?> My Quizzes</a>
        <a href="/dashboard/quizzes" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Quiz Hub</a>
    </div>
</div>

<section class="quiz-analytics-kpis">
    <article class="card quiz-analytics-kpi-card">
        <div class="card-body">
            <span>Attempts</span>
            <strong><?= $attemptCount ?></strong>
        </div>
    </article>
    <article class="card quiz-analytics-kpi-card">
        <div class="card-body">
            <span>Average Score</span>
            <strong><?= $avgScore !== null ? e(number_format($avgScore, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
    <article class="card quiz-analytics-kpi-card">
        <div class="card-body">
            <span>Best Score</span>
            <strong><?= $bestScore !== null ? e(number_format($bestScore, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
    <article class="card quiz-analytics-kpi-card">
        <div class="card-body">
            <span>Answer Accuracy</span>
            <strong><?= $accuracy !== null ? e(number_format($accuracy, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
</section>

<section class="quiz-analytics-layout">
    <article class="card quiz-analytics-panel">
        <div class="card-body">
            <div class="quiz-analytics-panel-head">
                <h2><?= ui_lucide_icon('activity') ?> Recent Score Trend</h2>
                <span class="text-muted">Latest <?= count($trend) ?> attempts</span>
            </div>

            <?php if (empty($trend)): ?>
                <p class="text-muted">No submitted attempts yet.</p>
            <?php else: ?>
                <div class="quiz-analytics-table-wrap">
                    <table class="quiz-analytics-table">
                        <thead>
                            <tr>
                                <th>Submitted At</th>
                                <th>Subject</th>
                                <th>Quiz</th>
                                <th>Mode</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trend as $row): ?>
                                <?php
                                $submittedLabel = '-';
                                if (!empty($row['submitted_at'])) {
                                    $ts = strtotime((string) $row['submitted_at']);
                                    if ($ts !== false) {
                                        $submittedLabel = date('Y-m-d H:i', $ts);
                                    }
                                }
                                $mode = (string) ($row['mode'] ?? 'practice');
                                ?>
                                <tr>
                                    <td><?= e($submittedLabel) ?></td>
                                    <td><?= e((string) ($row['subject_code'] ?? 'SUB')) ?></td>
                                    <td>
                                        <a href="/dashboard/subjects/<?= (int) ($row['subject_id'] ?? 0) ?>/quizzes/<?= (int) ($row['quiz_id'] ?? 0) ?>">
                                            <?= e((string) ($row['quiz_title'] ?? 'Quiz')) ?>
                                        </a>
                                    </td>
                                    <td><span class="badge <?= e(quizzes_mode_badge_class($mode)) ?>"><?= e(quizzes_mode_label($mode)) ?></span></td>
                                    <td><strong><?= e(number_format((float) ($row['score_percent'] ?? 0), 2)) ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <aside class="quiz-analytics-side">
        <article class="card quiz-analytics-panel">
            <div class="card-body">
                <h2><?= ui_lucide_icon('split') ?> Mode Breakdown</h2>
                <ul class="quiz-analytics-mode-list">
                    <li>
                        <span>Practice Attempts</span>
                        <strong><?= (int) ($practiceData['attempt_count'] ?? 0) ?></strong>
                    </li>
                    <li>
                        <span>Practice Avg</span>
                        <strong><?= isset($practiceData['avg_score']) ? e(number_format((float) $practiceData['avg_score'], 2)) . '%' : '-' ?></strong>
                    </li>
                    <li>
                        <span>Exam Attempts</span>
                        <strong><?= (int) ($examData['attempt_count'] ?? 0) ?></strong>
                    </li>
                    <li>
                        <span>Exam Avg</span>
                        <strong><?= isset($examData['avg_score']) ? e(number_format((float) $examData['avg_score'], 2)) . '%' : '-' ?></strong>
                    </li>
                </ul>
            </div>
        </article>

        <article class="card quiz-analytics-panel">
            <div class="card-body">
                <h2><?= ui_lucide_icon('circle-alert') ?> Most Missed Questions</h2>

                <?php if (empty($mostMissed)): ?>
                    <p class="text-muted">No question-level misses recorded yet.</p>
                <?php else: ?>
                    <ul class="quiz-analytics-missed-list">
                        <?php foreach ($mostMissed as $row): ?>
                            <?php
                            $quizId = (int) ($row['quiz_id'] ?? 0);
                            $subjectId = (int) ($row['subject_id'] ?? 0);
                            $mode = (string) ($row['mode'] ?? 'practice');
                            ?>
                            <li>
                                <p><?= e((string) ($row['question_text'] ?? 'Question')) ?></p>
                                <div class="quiz-analytics-missed-meta">
                                    <span class="badge <?= e(quizzes_mode_badge_class($mode)) ?>"><?= e(quizzes_mode_label($mode)) ?></span>
                                    <span><?= e(number_format((float) ($row['missed_percent'] ?? 0), 2)) ?>% missed</span>
                                    <span><?= (int) ($row['attempt_count'] ?? 0) ?> attempts</span>
                                </div>
                                <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>">Open Quiz</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </article>
    </aside>
</section>
