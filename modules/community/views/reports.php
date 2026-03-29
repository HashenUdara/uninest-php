<?php
$isAdmin = !empty($is_admin);
$selectedBatchId = (int) ($selected_batch_id ?? 0);
$reports = (array) ($reports ?? []);
$openCount = (int) ($open_count ?? 0);
$batchOptions = (array) ($batch_options ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Community / Moderation</p>
        <h1>Report Queue</h1>
        <p class="page-subtitle">Review reported posts and comments for your community feed.</p>
    </div>
    <div class="page-header-actions">
        <span class="badge badge-warning"><?= $openCount ?> Open</span>
        <a href="/dashboard/community<?= $isAdmin && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '' ?>" class="btn btn-outline">← Back to Feed</a>
    </div>
</div>

<?php if ($isAdmin): ?>
    <article class="community-rail-card" style="margin-top: 12px; margin-bottom: 12px;">
        <form method="GET" action="/dashboard/community/reports" class="community-topbar-form" style="grid-template-columns: minmax(220px, 360px) auto;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="batch_id">Batch</label>
                <select id="batch_id" name="batch_id">
                    <option value="">All batches</option>
                    <?php foreach ($batchOptions as $batch): ?>
                        <?php $batchId = (int) ($batch['id'] ?? 0); ?>
                        <option value="<?= $batchId ?>" <?= $selectedBatchId === $batchId ? 'selected' : '' ?>>
                            <?= e((string) ($batch['batch_code'] ?? 'BATCH')) ?> — <?= e((string) ($batch['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="community-topbar-actions" style="grid-column: auto; justify-content: flex-start;">
                <button type="submit" class="btn btn-primary">Filter Queue</button>
                <a href="/dashboard/community/reports" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </article>
<?php endif; ?>

<?php if (empty($reports)): ?>
    <article class="community-post-card community-empty-state" style="margin-top: 12px;">
        <h3>No reports in queue</h3>
        <p class="text-muted">
            <?php if ($isAdmin && $selectedBatchId > 0): ?>
                No reports found for this batch.
            <?php else: ?>
                New reports will appear here when users flag content.
            <?php endif; ?>
        </p>
    </article>
<?php else: ?>
    <div class="card" style="margin-top: 12px;">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th>Target</th>
                        <th>Reported By</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <?php
                        $targetType = (string) ($report['target_type'] ?? 'post');
                        $status = (string) ($report['status'] ?? 'open');
                        $batchId = (int) ($report['batch_id'] ?? 0);
                        $threadPostId = (int) ($report['thread_post_id'] ?? 0);
                        $threadUrl = $threadPostId > 0 ? '/dashboard/community/' . $threadPostId : '';
                        if ($threadUrl !== '' && $isAdmin && $batchId > 0) {
                            $threadUrl .= '?batch_id=' . $batchId;
                        }

                        $reason = (string) ($report['reason'] ?? 'other');
                        $details = trim((string) ($report['details'] ?? ''));
                        $targetExists = (int) ($report['target_exists'] ?? 0) === 1;
                        $subjectCode = trim((string) ($report['thread_subject_code'] ?? ''));
                        $threadBody = trim((string) ($report['thread_post_body'] ?? ''));
                        $commentBody = trim((string) ($report['target_comment_body'] ?? ''));
                        $reporterName = trim((string) ($report['reporter_name'] ?? 'Unknown User'));
                        if ($reporterName === '') {
                            $reporterName = 'Unknown User';
                        }

                        $statusBadgeClass = match ($status) {
                            'open' => 'badge-warning',
                            'dismissed' => 'badge-info',
                            default => '',
                        };
                        ?>
                        <tr>
                            <td>
                                <strong><?= e(ucfirst($targetType)) ?></strong>
                                <?php if ($subjectCode !== ''): ?>
                                    <span class="badge" style="margin-left: 6px;"><?= e($subjectCode) ?></span>
                                <?php endif; ?>
                                <?php if (!$targetExists): ?>
                                    <span class="badge badge-danger" style="margin-left: 6px;">Removed</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <?php if ($targetType === 'comment'): ?>
                                        <?= e($commentBody !== '' ? (strlen($commentBody) > 130 ? substr($commentBody, 0, 130) . '...' : $commentBody) : '[Comment unavailable]') ?>
                                    <?php else: ?>
                                        <?= e($threadBody !== '' ? (strlen($threadBody) > 130 ? substr($threadBody, 0, 130) . '...' : $threadBody) : '[Post unavailable]') ?>
                                    <?php endif; ?>
                                </small>
                                <?php if ($threadUrl !== ''): ?>
                                    <br><a href="<?= e($threadUrl) ?>">Open thread</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($reporterName) ?>
                                <?php if ($isAdmin && !empty($report['report_batch_code'])): ?>
                                    <br><small class="text-muted"><?= e((string) $report['report_batch_code']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e(community_report_reason_label($reason)) ?></strong>
                                <?php if ($details !== ''): ?>
                                    <br><small class="text-muted"><?= e($details) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= e($statusBadgeClass) ?>"><?= e(ucfirst($status)) ?></span>
                                <?php if (!empty($report['action_taken'])): ?>
                                    <br><small class="text-muted">Action: <?= e((string) $report['action_taken']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e(date('Y-m-d H:i', strtotime((string) ($report['created_at'] ?? 'now')))) ?>
                                <?php if (!empty($report['reviewed_at'])): ?>
                                    <br><small class="text-muted">Reviewed: <?= e(date('Y-m-d H:i', strtotime((string) $report['reviewed_at']))) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <?php if ($status === 'open'): ?>
                                    <form method="POST" action="/dashboard/community/reports/<?= (int) $report['id'] ?>/dismiss" class="table-action-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="table-icon-btn" title="Dismiss report" aria-label="Dismiss report">
                                            <?= ui_lucide_icon('check') ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="/dashboard/community/reports/<?= (int) $report['id'] ?>/remove" class="table-action-form" onsubmit="return confirm('Remove the reported content and resolve this report?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="table-icon-btn is-danger" title="Remove content" aria-label="Remove content">
                                            <?= ui_lucide_icon('trash') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <small class="text-muted">Closed</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
