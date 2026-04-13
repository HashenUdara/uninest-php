<?php
$subjects = (array) ($subjects ?? []);
$selectedSubjectId = (int) ($selected_subject_id ?? 0);
$summary = (array) ($summary ?? []);
$modeBreakdownRows = (array) ($mode_breakdown ?? []);
$difficultQuestions = (array) ($difficult_questions ?? []);

$attemptCount = (int) ($summary['attempt_count'] ?? 0);
$participantCount = (int) ($summary['participant_count'] ?? 0);
$avgScore = $summary['avg_score'] !== null ? (float) $summary['avg_score'] : null;
$bestScore = $summary['best_score'] !== null ? (float) $summary['best_score'] : null;

$modeBreakdown = [];
foreach ($modeBreakdownRows as $row) {
    $modeBreakdown[(string) ($row['mode'] ?? 'practice')] = $row;
}

$practiceData = (array) ($modeBreakdown['practice'] ?? []);
$examData = (array) ($modeBreakdown['exam'] ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Reviewer')) ?> / Quizzes / Analytics</p>
        <h1>Quiz Review Analytics</h1>
        <p class="page-subtitle">Question-level performance insights across your review scope.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/quiz-requests" class="btn btn-outline"><?= ui_lucide_icon('check-check') ?> Quiz Requests</a>
        <a href="/dashboard/quizzes" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Quiz Hub</a>
    </div>
</div>

<section class="card quiz-analytics-filter-card">
    <div class="card-body">
        <form method="GET" action="/dashboard/quiz-analytics" class="quiz-analytics-filter-form">
            <div class="form-group">
                <label for="quiz-analytics-subject">Filter by Subject</label>
                <select id="quiz-analytics-subject" name="subject_id">
                    <option value="0">All scoped subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                        <option value="<?= $subjectId ?>" <?= $selectedSubjectId === $subjectId ? 'selected' : '' ?>>
                            <?= e((string) ($subject['code'] ?? 'SUB')) ?> - <?= e((string) ($subject['name'] ?? 'Subject')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?= ui_lucide_icon('filter') ?> Apply</button>
        </form>
    </div>
</section>

<section class="quiz-analytics-kpis">
    <article class="card quiz-analytics-kpi-card">
        <div class="card-body">
            <span>Student Attempts</span>
            <strong><?= $attemptCount ?></strong>
        </div>
    </article>
    <article class="card quiz-analytics-kpi-card">
        <div class="card-body">
            <span>Participants</span>
            <strong><?= $participantCount ?></strong>
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
            <span>Highest Score</span>
            <strong><?= $bestScore !== null ? e(number_format($bestScore, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
</section>

<section class="quiz-analytics-layout">
    <article class="card quiz-analytics-panel">
        <div class="card-body">
            <div class="quiz-analytics-panel-head">
                <h2><?= ui_lucide_icon('triangle-alert') ?> Difficult Questions</h2>
                <span class="text-muted">Highest wrong-rate first</span>
            </div>

            <?php if (empty($difficultQuestions)): ?>
                <p class="text-muted">No submitted attempts available in this scope yet.</p>
            <?php else: ?>
                <div class="quiz-analytics-table-wrap">
                    <table class="quiz-analytics-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Subject</th>
                                <th>Quiz</th>
                                <th>Mode</th>
                                <th>Wrong %</th>
                                <th>Attempts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($difficultQuestions as $row): ?>
                                <?php
                                $subjectId = (int) ($row['subject_id'] ?? 0);
                                $quizId = (int) ($row['quiz_id'] ?? 0);
                                $mode = (string) ($row['mode'] ?? 'practice');
                                ?>
                                <tr>
                                    <td><?= e((string) ($row['question_text'] ?? 'Question')) ?></td>
                                    <td><?= e((string) ($row['subject_code'] ?? 'SUB')) ?></td>
                                    <td>
                                        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>">
                                            <?= e((string) ($row['quiz_title'] ?? 'Quiz')) ?>
                                        </a>
                                    </td>
                                    <td><span class="badge <?= e(quizzes_mode_badge_class($mode)) ?>"><?= e(quizzes_mode_label($mode)) ?></span></td>
                                    <td><strong><?= e(number_format((float) ($row['wrong_percent'] ?? 0), 2)) ?>%</strong></td>
                                    <td><?= (int) ($row['attempt_count'] ?? 0) ?></td>
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
    </aside>
</section>
