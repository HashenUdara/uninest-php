<?php
$requestId = (int) ($request['id'] ?? 0);
$requesterName = trim((string) ($request['requester_name'] ?? 'Unknown User'));
$viewerVote = (string) ($request['viewer_vote'] ?? '');
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? ('/dashboard/kuppi/' . $requestId));
$isOwnRequest = (int) ($request['requested_by_user_id'] ?? 0) === (int) auth_id();
$requestVoteScore = (int) ($request['vote_score'] ?? 0);
$requestUpvotes = (int) ($request['upvote_count'] ?? 0);
$requestDownvotes = (int) ($request['downvote_count'] ?? 0);
$createdAtRaw = (string) ($request['created_at'] ?? '');
$createdAtLabel = $createdAtRaw !== ''
    ? (function_exists('kuppi_relative_time_label') ? kuppi_relative_time_label($createdAtRaw) : date('Y-m-d H:i', strtotime($createdAtRaw)))
    : 'recently';
$conductors = (array) ($conductor_applications ?? []);
$viewerApplication = $viewer_conductor_application ?? null;
$topVoteApplicationId = (int) ($top_vote_application_id ?? 0);
$availabilityOptions = (array) ($availability_options ?? []);
$comments = (array) ($comments ?? []);
$commentCount = (int) ($comment_count ?? 0);
$commentMaxLevel = (int) ($comment_max_level ?? (comments_max_depth() + 1));
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Kuppi Sessions / Requested Session Details</p>
        <h1>Session Request Details</h1>
        <p class="page-subtitle">Review details, vote on demand, and choose the best conductor for this session.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e((string) $back_list_url) ?>" class="btn btn-outline">← Back to All Sessions</a>
    </div>
</div>

<section class="kuppi-detail-layout">
    <main class="kuppi-detail-main">
        <article class="kuppi-request-card kuppi-request-card--single">
            <div class="kuppi-request-main">
                <header class="kuppi-request-header">
                    <div class="kuppi-request-badges">
                        <?php if (!empty($request['subject_code'])): ?>
                            <span class="badge"><?= e((string) $request['subject_code']) ?></span>
                        <?php endif; ?>
                        <span class="badge badge-info"><?= e(ucfirst((string) ($request['status'] ?? 'open'))) ?></span>
                    </div>
                    <h2 class="kuppi-request-title kuppi-request-title--fixed"><?= e((string) ($request['title'] ?? 'Requested Session')) ?></h2>
                    <div class="kuppi-meta-row">
                        <span>Requested by <strong><?= e($requesterName) ?></strong></span>
                        <span class="kuppi-meta-dot">•</span>
                        <span><?= e($createdAtLabel) ?></span>
                        <span class="kuppi-meta-dot">•</span>
                        <span><?= $requestUpvotes ?> up / <?= $requestDownvotes ?> down</span>
                    </div>
                </header>

                <p class="kuppi-request-description"><?= nl2br(e((string) ($request['description'] ?? ''))) ?></p>

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
                        <?php if (!empty($request['batch_code'])): ?>
                            <span><strong>Batch:</strong> <?= e((string) $request['batch_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="kuppi-request-actions">
                        <?php if (!empty($can_edit_request)): ?>
                            <a href="/dashboard/kuppi/<?= $requestId ?>/edit" class="btn btn-sm btn-outline">Edit Request</a>
                        <?php endif; ?>
                        <?php if (!empty($can_delete_request)): ?>
                            <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/delete" onsubmit="return confirm('Delete this request?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e((string) $back_list_url) ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Delete Request</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </footer>
            </div>
        </article>

        <section class="kuppi-conductor-section kuppi-section-card">
            <header class="kuppi-conductor-header">
                <h2>Applied Conductors <span class="badge badge-info"><?= (int) ($conductor_count ?? count($conductors)) ?></span></h2>
                <?php if (!empty($can_apply_as_conductor) && !$viewerApplication): ?>
                    <a href="/dashboard/kuppi/<?= $requestId ?>/conductors/apply" class="btn btn-primary">Apply to Conduct</a>
                <?php endif; ?>
            </header>

            <?php if ($viewerApplication): ?>
                <div class="alert alert-success">You have already applied as a conductor for this session.</div>
            <?php endif; ?>

            <?php if (empty($conductors)): ?>
                <article class="community-post-card community-empty-state">
                    <h3>No conductor applications yet</h3>
                    <p class="text-muted">Be the first to apply and lead this session.</p>
                </article>
            <?php else: ?>
                <div class="kuppi-conductor-list">
                    <?php foreach ($conductors as $application): ?>
                        <?php
                        $applicationId = (int) ($application['id'] ?? 0);
                        $isOwnApplication = (int) ($application['applicant_user_id'] ?? 0) === (int) auth_id();
                        $isTopVote = $applicationId > 0 && $applicationId === $topVoteApplicationId;
                        $canVoteThisConductor = !empty($can_vote_conductor) && !$isOwnApplication;
                        $availability = (array) ($application['availability'] ?? []);
                        ?>
                        <article class="kuppi-conductor-card <?= $isTopVote ? 'is-top' : '' ?>">
                            <aside class="kuppi-conductor-vote">
                                <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/conductors/<?= $applicationId ?>/vote">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                    <button type="submit" class="kuppi-vote-btn <?= !empty($application['is_voted_by_viewer']) ? 'is-active' : '' ?>" <?= $canVoteThisConductor ? '' : 'disabled' ?> aria-label="Vote conductor">▲</button>
                                </form>
                                <strong class="kuppi-vote-score"><?= (int) ($application['vote_count'] ?? 0) ?></strong>
                            </aside>

                            <div class="kuppi-conductor-body">
                                <header class="kuppi-conductor-title-row">
                                    <div>
                                        <h3><?= e((string) ($application['applicant_name'] ?? 'Unknown User')) ?></h3>
                                        <p class="kuppi-request-meta">
                                            <?= e(ucfirst((string) ($application['applicant_role'] ?? 'student'))) ?>
                                            <?php if ((int) ($application['applicant_academic_year'] ?? 0) > 0): ?>
                                                • Year <?= (int) $application['applicant_academic_year'] ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <?php if ($isTopVote): ?>
                                        <span class="badge badge-warning">Top Vote</span>
                                    <?php endif; ?>
                                </header>

                                <p class="kuppi-request-description"><?= nl2br(e((string) ($application['motivation'] ?? ''))) ?></p>

                                <?php if (!empty($availability)): ?>
                                    <div class="kuppi-tags">
                                        <?php foreach ($availability as $slot): ?>
                                            <?php $label = $availabilityOptions[$slot] ?? $slot; ?>
                                            <span class="badge"><?= e((string) $label) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="resource-comments-section kuppi-comments-card" id="kuppi-comments">
            <div class="resource-comments-shell">
                <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/comments" class="resource-comments-composer">
                    <?= csrf_field() ?>
                    <textarea
                        name="body"
                        rows="4"
                        maxlength="<?= comments_max_body_length() ?>"
                        placeholder="Add your thoughts, questions, or preparation tips..."
                        required></textarea>
                    <div class="resource-comments-composer-footer">
                        <small class="text-muted">Threaded replies up to <?= $commentMaxLevel ?> levels.</small>
                        <button type="submit" class="btn btn-primary">Comment</button>
                    </div>
                </form>

                <div class="resource-comments-divider"></div>
                <div class="resource-comments-header-row">
                    <h3>Comments <span class="badge resource-comments-count"><?= $commentCount ?></span></h3>
                    <span class="resource-comments-sort">Most recent</span>
                </div>

                <?php if (empty($comments)): ?>
                    <p class="text-muted">No comments yet. Start the discussion.</p>
                <?php else: ?>
                    <div class="resource-comments-list">
                        <?php
                        $renderComments = function (array $nodes) use (&$renderComments, $requestId): void {
                            foreach ($nodes as $comment):
                                $commentId = (int) ($comment['id'] ?? 0);
                                $depth = (int) ($comment['depth'] ?? 0);
                                $authorName = trim((string) ($comment['user_name'] ?? ''));
                                if ($authorName === '') {
                                    $authorName = 'Unknown User';
                                }
                                ?>
                                <article class="resource-comment depth-<?= $depth ?>">
                                    <div class="resource-comment-main">
                                        <span class="resource-comment-avatar"><?= e(ui_initials($authorName)) ?></span>
                                        <div class="resource-comment-body">
                                            <header class="resource-comment-header">
                                                <strong><?= e($authorName) ?></strong>
                                                <small class="text-muted"><?= e(date('Y-m-d H:i', strtotime((string) ($comment['created_at'] ?? 'now')))) ?></small>
                                            </header>
                                            <p><?= nl2br(e((string) ($comment['body'] ?? ''))) ?></p>

                                            <div class="resource-comment-actions">
                                                <?php if (!empty($comment['can_reply'])): ?>
                                                    <details class="resource-comment-action-block">
                                                        <summary class="resource-comment-action-btn">Reply</summary>
                                                        <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/comments" class="resource-comment-inline-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="parent_comment_id" value="<?= $commentId ?>">
                                                            <textarea name="body" rows="2" maxlength="<?= comments_max_body_length() ?>" required></textarea>
                                                            <button type="submit" class="btn btn-sm btn-primary">Post Reply</button>
                                                        </form>
                                                    </details>
                                                <?php endif; ?>

                                                <?php if (!empty($comment['can_edit'])): ?>
                                                    <details class="resource-comment-action-block">
                                                        <summary class="resource-comment-action-btn">Edit</summary>
                                                        <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/comments/<?= $commentId ?>" class="resource-comment-inline-form">
                                                            <?= csrf_field() ?>
                                                            <textarea name="body" rows="2" maxlength="<?= comments_max_body_length() ?>" required><?= e((string) ($comment['body'] ?? '')) ?></textarea>
                                                            <button type="submit" class="btn btn-sm btn-outline">Save</button>
                                                        </form>
                                                    </details>
                                                <?php endif; ?>

                                                <?php if (!empty($comment['can_delete'])): ?>
                                                    <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/comments/<?= $commentId ?>/delete" onsubmit="return confirm('Delete this comment and all replies?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="resource-comment-action-btn is-danger">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($comment['children'])): ?>
                                        <div class="resource-comment-children">
                                            <?php $renderComments((array) ($comment['children'] ?? [])); ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php
                            endforeach;
                        };

                        $renderComments($comments);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <aside class="kuppi-detail-side">
        <article class="kuppi-side-card">
            <h3>Vote on this Request</h3>
            <div class="kuppi-side-vote-wrap">
                <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/vote">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vote" value="up">
                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                    <?php if (user_role() === 'admin'): ?>
                        <input type="hidden" name="batch_id" value="<?= (int) ($request['batch_id'] ?? 0) ?>">
                    <?php endif; ?>
                    <button type="submit" class="kuppi-vote-btn <?= $viewerVote === 'up' ? 'is-active' : '' ?>" <?= (!empty($can_vote_request) && !$isOwnRequest) ? '' : 'disabled' ?> aria-label="Upvote request">▲</button>
                </form>

                <div class="kuppi-side-vote-score">
                    <strong><?= $requestVoteScore ?></strong>
                    <small>votes</small>
                </div>

                <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/vote">
                    <?= csrf_field() ?>
                    <input type="hidden" name="vote" value="down">
                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                    <?php if (user_role() === 'admin'): ?>
                        <input type="hidden" name="batch_id" value="<?= (int) ($request['batch_id'] ?? 0) ?>">
                    <?php endif; ?>
                    <button type="submit" class="kuppi-vote-btn <?= $viewerVote === 'down' ? 'is-active is-down' : 'is-down' ?>" <?= (!empty($can_vote_request) && !$isOwnRequest) ? '' : 'disabled' ?> aria-label="Downvote request">▼</button>
                </form>
            </div>
        </article>

        <article class="kuppi-side-card">
            <h3>Session Statistics</h3>
            <ul class="kuppi-stat-list">
                <li><span>Request Upvotes</span><strong><?= $requestUpvotes ?></strong></li>
                <li><span>Request Downvotes</span><strong><?= $requestDownvotes ?></strong></li>
                <li><span>Conductors</span><strong><?= (int) ($conductor_count ?? count($conductors)) ?></strong></li>
                <li><span>Comments</span><strong><?= $commentCount ?></strong></li>
            </ul>
        </article>

        <article class="kuppi-side-card">
            <h3>Actions</h3>
            <div class="kuppi-side-actions">
                <?php if (!empty($can_apply_as_conductor) && !$viewerApplication): ?>
                    <a href="/dashboard/kuppi/<?= $requestId ?>/conductors/apply" class="btn btn-primary">Apply to Conduct</a>
                <?php endif; ?>
                <a href="<?= e((string) $back_list_url) ?>" class="btn btn-outline">Share Session</a>
                <button type="button" class="btn btn-outline" disabled>Report Issue</button>
            </div>
        </article>
    </aside>
</section>
