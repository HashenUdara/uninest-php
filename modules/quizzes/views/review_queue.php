<?php
$statusFilter = (string) ($status_filter ?? 'pending');
$metrics = (array) ($metrics ?? []);
$items = (array) ($items ?? []);
$samplesByQuiz = (array) ($samples_by_quiz ?? []);

$buildTabUrl = static function (string $status): string {
    return '/dashboard/quiz-requests?status=' . urlencode($status);
};
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Coordinator / Moderator / Admin / Quiz Requests</p>
        <h1>Quiz Approval Queue</h1>
        <p class="page-subtitle">Review submitted quizzes and publish approved content.</p>
    </div>
</div>

<div class="quiz-review-metrics">
    <article class="card quiz-review-metric-card">
        <div class="card-body">
            <span class="quiz-review-metric-icon"><?= ui_lucide_icon('clock-3') ?></span>
            <h3><?= (int) ($metrics['pending_count'] ?? 0) ?></h3>
            <p>Pending Review</p>
        </div>
    </article>
    <article class="card quiz-review-metric-card">
        <div class="card-body">
            <span class="quiz-review-metric-icon"><?= ui_lucide_icon('check-circle-2') ?></span>
            <h3><?= (int) ($metrics['approved_today_count'] ?? 0) ?></h3>
            <p>Approved Today</p>
        </div>
    </article>
    <article class="card quiz-review-metric-card">
        <div class="card-body">
            <span class="quiz-review-metric-icon"><?= ui_lucide_icon('x-circle') ?></span>
            <h3><?= (int) ($metrics['rejected_today_count'] ?? 0) ?></h3>
            <p>Rejected Today</p>
        </div>
    </article>
</div>

<div class="quiz-review-tabs">
    <a href="<?= e($buildTabUrl('pending')) ?>" class="btn <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline' ?>">Pending</a>
    <a href="<?= e($buildTabUrl('approved')) ?>" class="btn <?= $statusFilter === 'approved' ? 'btn-primary' : 'btn-outline' ?>">Approved</a>
    <a href="<?= e($buildTabUrl('rejected')) ?>" class="btn <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-outline' ?>">Rejected</a>
</div>

<?php if (empty($items)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No quizzes found for this filter.</p>
        </div>
    </div>
<?php else: ?>
    <div class="quiz-review-list">
        <?php foreach ($items as $item): ?>
            <?php
            $quizId = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? 'pending');
            $samples = (array) ($samplesByQuiz[$quizId] ?? []);
            ?>
            <article class="card quiz-review-item">
                <div class="card-body">
                    <div class="quiz-review-item-head">
                        <div>
                            <h3><?= e((string) ($item['title'] ?? 'Untitled Quiz')) ?></h3>
                            <p><?= e((string) ($item['description'] ?? 'No description provided.')) ?></p>
                        </div>
                        <span class="badge <?= e(quizzes_status_badge_class($status)) ?>"><?= e(quizzes_status_label($status)) ?></span>
                    </div>

                    <div class="quiz-review-item-meta">
                        <span><?= ui_lucide_icon('user') ?> <?= e((string) ($item['creator_name'] ?? 'Unknown')) ?></span>
                        <span><?= ui_lucide_icon('book-open') ?> <?= e((string) ($item['subject_code'] ?? 'SUB')) ?> - <?= e((string) ($item['subject_name'] ?? 'Subject')) ?></span>
                        <span><?= ui_lucide_icon('circle-help') ?> <?= (int) ($item['question_count'] ?? 0) ?> Questions</span>
                        <span><?= ui_lucide_icon('timer') ?> <?= (int) ($item['duration_minutes'] ?? 0) ?> min</span>
                    </div>

                    <?php if (!empty($samples)): ?>
                        <div class="quiz-review-samples">
                            <strong>Sample Questions</strong>
                            <ul>
                                <?php foreach ($samples as $sample): ?>
                                    <li><?= e((string) $sample) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($status === 'pending'): ?>
                        <div class="quiz-review-actions">
                            <form method="POST" action="/dashboard/quiz-requests/<?= $quizId ?>/approve">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-primary"><?= ui_lucide_icon('check') ?> Approve</button>
                            </form>

                            <form method="POST" action="/dashboard/quiz-requests/<?= $quizId ?>/reject" class="quiz-review-reject-form">
                                <?= csrf_field() ?>
                                <textarea name="rejection_reason" required rows="2" placeholder="Rejection reason (required)"></textarea>
                                <button type="submit" class="btn btn-outline"><?= ui_lucide_icon('x') ?> Reject</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="quiz-review-footnote">
                            <small>
                                Reviewed by <?= e((string) ($item['reviewer_name'] ?? 'Unknown')) ?>
                                at <?= e(date('Y-m-d H:i', strtotime((string) ($item['reviewed_at'] ?? 'now')))) ?>.
                            </small>
                            <?php if ($status === 'rejected' && !empty($item['rejection_reason'])): ?>
                                <small>Reason: <?= e((string) $item['rejection_reason']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
