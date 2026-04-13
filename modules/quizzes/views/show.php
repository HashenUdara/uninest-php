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
$quizMode = (string) ($quiz['mode'] ?? 'practice');
$leaderboardTop = (array) ($leaderboard_top ?? []);

$quizComments = (array) ($quiz_comments ?? []);
$quizCommentCount = (int) ($quiz_comment_count ?? 0);
$questionComments = (array) ($question_comments ?? []);
$questionCommentCounts = (array) ($question_comment_counts ?? []);
$commentMaxLevel = (int) ($comment_max_level ?? (comments_max_depth_for_target('quiz') + 1));

if ($bestAttempt && !empty($bestAttempt['submitted_at'])) {
    $timestamp = strtotime((string) $bestAttempt['submitted_at']);
    if ($timestamp !== false) {
        $bestAttemptAt = date('M d, Y h:i A', $timestamp);
    }
}

$renderComments = function (
    array $nodes,
    string $scope,
    int $targetId,
    int $subjectId,
    int $quizId,
    int $commentMaxLevel,
    int $currentLevel = 1
) use (&$renderComments): void {
    foreach ($nodes as $comment) {
        $commentId = (int) ($comment['id'] ?? 0);
        $author = trim((string) ($comment['user_name'] ?? 'User'));
        if ($author === '') {
            $author = 'User';
        }

        $createdAtText = (string) ($comment['created_at'] ?? '');
        $createdAtLabel = 'Just now';
        $createdAtTs = strtotime($createdAtText);
        if ($createdAtTs !== false) {
            $createdAtLabel = date('M d, Y h:i A', $createdAtTs);
        }

        if ($scope === 'quiz') {
            $storeAction = '/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizId . '/comments';
            $updateAction = '/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizId . '/comments/' . $commentId;
            $deleteAction = '/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizId . '/comments/' . $commentId . '/delete';
        } else {
            $storeAction = '/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizId . '/questions/' . $targetId . '/comments';
            $updateAction = '/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizId . '/questions/' . $targetId . '/comments/' . $commentId;
            $deleteAction = '/dashboard/subjects/' . $subjectId . '/quizzes/' . $quizId . '/questions/' . $targetId . '/comments/' . $commentId . '/delete';
        }
        ?>
        <article class="resource-comment-card" id="quiz-comment-<?= $commentId ?>">
            <header class="resource-comment-header">
                <div class="resource-comment-author">
                    <strong><?= e($author) ?></strong>
                    <span class="resource-comment-level">Level <?= (int) ($comment['depth'] ?? 0) + 1 ?></span>
                </div>
                <small class="text-muted"><?= e($createdAtLabel) ?></small>
            </header>

            <div class="resource-comment-body"><?= nl2br(e((string) ($comment['body'] ?? ''))) ?></div>

            <div class="resource-comment-actions">
                <?php if (!empty($comment['can_reply']) && $currentLevel < $commentMaxLevel): ?>
                    <details>
                        <summary>Reply</summary>
                        <form method="POST" action="<?= e($storeAction) ?>" class="resource-comment-inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="parent_comment_id" value="<?= $commentId ?>">
                            <textarea name="body" rows="2" maxlength="<?= comments_max_body_length() ?>" required></textarea>
                            <button type="submit" class="btn btn-sm btn-primary">Post Reply</button>
                        </form>
                    </details>
                <?php endif; ?>

                <?php if (!empty($comment['can_edit'])): ?>
                    <details>
                        <summary>Edit</summary>
                        <form method="POST" action="<?= e($updateAction) ?>" class="resource-comment-inline-form">
                            <?= csrf_field() ?>
                            <textarea name="body" rows="2" maxlength="<?= comments_max_body_length() ?>" required><?= e((string) ($comment['body'] ?? '')) ?></textarea>
                            <button type="submit" class="btn btn-sm btn-outline">Save</button>
                        </form>
                    </details>
                <?php endif; ?>

                <?php if (!empty($comment['can_delete'])): ?>
                    <form method="POST" action="<?= e($deleteAction) ?>" onsubmit="return confirm('Delete this comment and all replies?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php $children = (array) ($comment['children'] ?? []); ?>
            <?php if (!empty($children)): ?>
                <div class="resource-comment-children">
                    <?php $renderComments($children, $scope, $targetId, $subjectId, $quizId, $commentMaxLevel, $currentLevel + 1); ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }
};
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Subjects / Quizzes</p>
        <h1><?= e((string) ($quiz['title'] ?? 'Quiz')) ?></h1>
        <p class="page-subtitle"><?= e((string) ($quiz['description'] ?? 'Practice your understanding with this quiz.')) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/leaderboard" class="btn btn-outline"><?= ui_lucide_icon('trophy') ?> Leaderboard</a>
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Quizzes</a>
    </div>
</div>

<div class="quiz-detail-layout">
    <section class="quiz-detail-main">
        <article class="card quiz-detail-overview-card">
            <div class="card-body">
                <div class="quiz-detail-overview-head">
                    <div class="quiz-detail-overview-status">
                        <div class="quiz-detail-overview-badges">
                            <span class="badge badge-info">Approved Quiz</span>
                            <span class="badge <?= e(quizzes_mode_badge_class($quizMode)) ?>"><?= e(quizzes_mode_label($quizMode)) ?> Mode</span>
                        </div>
                        <span class="quiz-detail-overview-caption">
                            <?= $quizMode === 'practice'
                                ? 'Immediate answer checking is available during attempt.'
                                : 'Correct answers appear only in the result screen.' ?>
                        </span>
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
                    <p>Preview question style before you start. Correct answers are hidden until submission.</p>
                </div>

                <div class="quiz-detail-question-list">
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                        $questionId = (int) ($question['id'] ?? 0);
                        $questionLevelComments = (array) ($questionComments[$questionId] ?? []);
                        $questionCommentCount = (int) ($questionCommentCounts[$questionId] ?? 0);
                        ?>
                        <article class="quiz-detail-question-item" id="question-discussion-<?= $questionId ?>">
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

                            <details class="quiz-question-discussion-panel">
                                <summary>
                                    <?= ui_lucide_icon('message-square') ?>
                                    Question Discussion
                                    <span class="badge"><?= $questionCommentCount ?></span>
                                </summary>

                                <div class="resource-comments-shell quiz-comments-shell">
                                    <form method="POST" action="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/questions/<?= $questionId ?>/comments" class="resource-comments-composer">
                                        <?= csrf_field() ?>
                                        <label for="quiz-question-comment-<?= $questionId ?>" class="sr-only">Add question comment</label>
                                        <textarea
                                            id="quiz-question-comment-<?= $questionId ?>"
                                            name="body"
                                            rows="3"
                                            maxlength="<?= comments_max_body_length() ?>"
                                            placeholder="Ask a question or share an insight about this item..."
                                            required></textarea>
                                        <div class="resource-comments-composer-footer">
                                            <small>Up to <?= comments_max_body_length() ?> characters.</small>
                                            <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
                                        </div>
                                    </form>

                                    <div class="resource-comments-divider"></div>

                                    <?php if (empty($questionLevelComments)): ?>
                                        <p class="text-muted">No comments for this question yet.</p>
                                    <?php else: ?>
                                        <div class="resource-comments-list">
                                            <?php $renderComments($questionLevelComments, 'question', $questionId, $subjectId, $quizId, $commentMaxLevel); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>

        <section class="resource-comments-section" id="quiz-discussion">
            <div class="resource-comments-shell quiz-comments-shell">
                <form method="POST" action="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/comments" class="resource-comments-composer">
                    <?= csrf_field() ?>
                    <label for="quiz-comment-body" class="sr-only">Add quiz comment</label>
                    <textarea
                        id="quiz-comment-body"
                        name="body"
                        rows="3"
                        maxlength="<?= comments_max_body_length() ?>"
                        placeholder="Start a general discussion about this quiz..."
                        required></textarea>
                    <div class="resource-comments-composer-footer">
                        <small>Up to <?= comments_max_body_length() ?> characters.</small>
                        <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
                    </div>
                </form>

                <div class="resource-comments-divider"></div>
                <div class="resource-comments-header-row">
                    <h3>Quiz Discussion <span class="badge resource-comments-count"><?= $quizCommentCount ?></span></h3>
                    <span class="resource-comments-sort">Most recent</span>
                </div>

                <?php if (empty($quizComments)): ?>
                    <p class="text-muted">No comments yet. Start the discussion.</p>
                <?php else: ?>
                    <div class="resource-comments-list">
                        <?php $renderComments($quizComments, 'quiz', $quizId, $subjectId, $quizId, $commentMaxLevel); ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
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
                <h3><?= ui_lucide_icon('trophy') ?> Subject Leaderboard</h3>
                <?php if (empty($leaderboardTop)): ?>
                    <p class="text-muted">No exam attempts recorded yet.</p>
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

        <article class="card quiz-detail-side-card quiz-detail-guidelines">
            <div class="card-body">
                <h3><?= ui_lucide_icon('info') ?> Attempt Guidelines</h3>
                <ul>
                    <li><?= (int) ($quiz['duration_minutes'] ?? 0) ?> minutes per attempt</li>
                    <li>Single-correct MCQ format</li>
                    <li><?= $quizMode === 'practice' ? 'Immediate check available per question' : 'Feedback shown only in results' ?></li>
                    <li>Auto-submit when time expires</li>
                    <li>Best score shown on your profile</li>
                </ul>
            </div>
        </article>
    </aside>
</div>
