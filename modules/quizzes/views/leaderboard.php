<?php
$subjectId = (int) ($subject['id'] ?? 0);
$subjectCode = (string) ($subject['code'] ?? 'SUB');
$subjectName = (string) ($subject['name'] ?? 'Subject');
$leaderboard = (array) ($leaderboard ?? []);

$participantCount = count($leaderboard);
$topScore = null;
$sumBestScores = 0.0;

foreach ($leaderboard as $row) {
    $score = (float) ($row['best_score'] ?? 0);
    $sumBestScores += $score;

    if ($topScore === null || $score > $topScore) {
        $topScore = $score;
    }
}

$averageBestScore = $participantCount > 0 ? ($sumBestScores / $participantCount) : null;
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Subjects / Quizzes / Leaderboard</p>
        <h1><?= e($subjectCode) ?> Leaderboard</h1>
        <p class="page-subtitle">Exam-mode ranking for <strong><?= e($subjectName) ?></strong> based on best score.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Quizzes</a>
    </div>
</div>

<section class="quiz-leaderboard-kpis">
    <article class="card quiz-leaderboard-kpi-card">
        <div class="card-body">
            <span>Participants</span>
            <strong><?= $participantCount ?></strong>
        </div>
    </article>
    <article class="card quiz-leaderboard-kpi-card">
        <div class="card-body">
            <span>Top Score</span>
            <strong><?= $topScore !== null ? e(number_format($topScore, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
    <article class="card quiz-leaderboard-kpi-card">
        <div class="card-body">
            <span>Average Best Score</span>
            <strong><?= $averageBestScore !== null ? e(number_format($averageBestScore, 2)) . '%' : '-' ?></strong>
        </div>
    </article>
</section>

<?php if (empty($leaderboard)): ?>
    <div class="card quiz-leaderboard-empty-card">
        <div class="card-body">
            <h3><?= ui_lucide_icon('trophy') ?> No leaderboard entries yet</h3>
            <p class="text-muted">This leaderboard will appear after students complete approved exam quizzes in this subject.</p>
        </div>
    </div>
<?php else: ?>
    <section class="card quiz-leaderboard-table-card">
        <div class="card-body">
            <div class="quiz-leaderboard-table-wrap">
                <table class="quiz-leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Best Score</th>
                            <th>Attempts</th>
                            <th>Latest High Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $row): ?>
                            <?php
                            $submittedLabel = '-';
                            if (!empty($row['latest_high_submitted_at'])) {
                                $ts = strtotime((string) $row['latest_high_submitted_at']);
                                if ($ts !== false) {
                                    $submittedLabel = date('Y-m-d H:i', $ts);
                                }
                            }
                            ?>
                            <tr>
                                <td><span class="quiz-rank-pill">#<?= (int) ($row['rank'] ?? 0) ?></span></td>
                                <td><?= e((string) ($row['student_name'] ?? 'Student')) ?></td>
                                <td><strong><?= e(number_format((float) ($row['best_score'] ?? 0), 2)) ?>%</strong></td>
                                <td><?= (int) ($row['attempt_count'] ?? 0) ?></td>
                                <td><?= e($submittedLabel) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>
