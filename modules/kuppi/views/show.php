<?php
$requestId = (int) ($request['id'] ?? 0);
$requesterName = trim((string) ($request['requester_name'] ?? 'Unknown User'));
$viewerVote = (string) ($request['viewer_vote'] ?? '');
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? ('/dashboard/kuppi/' . $requestId));
$isOwn = (int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id();
$batchId = (int) ($request['batch_id'] ?? 0);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Requested Kuppi Sessions</p>
        <h1><?= e((string) ($request['title'] ?? 'Requested Session')) ?></h1>
        <p class="page-subtitle">
            Requested by <strong><?= e($requesterName) ?></strong>
            on <?= e(date('Y-m-d H:i', strtotime((string) ($request['created_at'] ?? 'now')))) ?>.
        </p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e((string) $back_list_url) ?>" class="btn btn-outline">← Back to Requests</a>
        <?php if (!empty($can_edit_request)): ?>
            <a href="/dashboard/kuppi/<?= $requestId ?>/edit" class="btn btn-primary">Edit</a>
        <?php endif; ?>
    </div>
</div>

<article class="kuppi-request-card kuppi-request-card--single">
    <aside class="kuppi-vote-rail">
        <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/vote">
            <?= csrf_field() ?>
            <input type="hidden" name="vote" value="up">
            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
            <?php if (user_role() === 'admin'): ?>
                <input type="hidden" name="batch_id" value="<?= $batchId ?>">
            <?php endif; ?>
            <button type="submit" class="kuppi-vote-btn <?= $viewerVote === 'up' ? 'is-active' : '' ?>" <?= (!empty($can_vote_request) && !$isOwn) ? '' : 'disabled' ?> aria-label="Upvote request">▲</button>
        </form>

        <strong class="kuppi-vote-score"><?= (int) ($request['vote_score'] ?? 0) ?></strong>

        <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/vote">
            <?= csrf_field() ?>
            <input type="hidden" name="vote" value="down">
            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
            <?php if (user_role() === 'admin'): ?>
                <input type="hidden" name="batch_id" value="<?= $batchId ?>">
            <?php endif; ?>
            <button type="submit" class="kuppi-vote-btn <?= $viewerVote === 'down' ? 'is-active is-down' : 'is-down' ?>" <?= (!empty($can_vote_request) && !$isOwn) ? '' : 'disabled' ?> aria-label="Downvote request">▼</button>
        </form>
    </aside>

    <div class="kuppi-request-main">
        <header class="kuppi-request-header">
            <div class="kuppi-request-badges">
                <?php if (!empty($request['subject_code'])): ?>
                    <span class="badge"><?= e((string) $request['subject_code']) ?></span>
                <?php endif; ?>
                <?php if (!empty($request['batch_code'])): ?>
                    <span class="badge"><?= e((string) $request['batch_code']) ?></span>
                <?php endif; ?>
                <span class="badge badge-info"><?= e(ucfirst((string) ($request['status'] ?? 'open'))) ?></span>
            </div>
            <p class="kuppi-request-meta">
                <strong><?= (int) ($request['upvote_count'] ?? 0) ?></strong> upvotes
                •
                <strong><?= (int) ($request['downvote_count'] ?? 0) ?></strong> downvotes
            </p>
        </header>

        <div class="community-detail-body">
            <p><?= nl2br(e((string) ($request['description'] ?? ''))) ?></p>
        </div>

        <?php if (!empty($tags)): ?>
            <div class="kuppi-tags">
                <?php foreach ((array) $tags as $tag): ?>
                    <span class="badge"><?= e((string) $tag) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <footer class="kuppi-request-footer">
            <div class="kuppi-vote-stats">
                <?php if (!empty($request['subject_name'])): ?>
                    <span><strong>Subject:</strong> <?= e((string) $request['subject_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($request['batch_name'])): ?>
                    <span><strong>Batch:</strong> <?= e((string) $request['batch_name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="kuppi-request-actions">
                <?php if (!empty($can_edit_request)): ?>
                    <a href="/dashboard/kuppi/<?= $requestId ?>/edit" class="btn btn-sm btn-outline">Edit</a>
                <?php endif; ?>
                <?php if (!empty($can_delete_request)): ?>
                    <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/delete" onsubmit="return confirm('Delete this request?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= e((string) $back_list_url) ?>">
                        <button type="submit" class="btn btn-sm btn-outline">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        </footer>
    </div>
</article>
