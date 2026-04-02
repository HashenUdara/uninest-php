<?php
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/my-kuppi-requests');
$currentUserName = trim((string) (auth_user()['name'] ?? 'Me'));
if ($currentUserName === '') {
    $currentUserName = 'Me';
}
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Requested Kuppi Sessions / My Requests</p>
        <h1>My Kuppi Requests</h1>
        <p class="page-subtitle">Manage the session requests you created.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/kuppi/create" class="btn btn-primary">+ Request Session</a>
        <a href="/dashboard/kuppi" class="btn btn-outline">← Back to Requests</a>
    </div>
</div>

<?php if (empty($requests)): ?>
    <article class="community-post-card community-empty-state">
        <h3>No requests yet</h3>
        <p class="text-muted">Create your first Kuppi request to gather votes from your batch.</p>
    </article>
<?php else: ?>
    <section class="kuppi-request-list">
        <?php foreach ((array) $requests as $request): ?>
            <?php
            $requestId = (int) ($request['id'] ?? 0);
            $tags = kuppi_tags_to_array((string) ($request['tags_csv'] ?? ''));
            $commentCount = (int) ($request['comment_count'] ?? 0);
            $interestedCount = max(0, (int) ($request['upvote_count'] ?? 0));
            ?>
            <article class="kuppi-request-card kuppi-request-card--list">
                <aside class="kuppi-vote-rail">
                    <strong class="kuppi-vote-score"><?= (int) ($request['vote_score'] ?? 0) ?></strong>
                    <small>Score</small>
                </aside>
                <div class="kuppi-request-main kuppi-request-main--list">
                    <header class="kuppi-request-header">
                        <div class="kuppi-request-badges">
                            <?php if (!empty($request['subject_code'])): ?>
                                <span class="badge"><?= e((string) $request['subject_code']) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="/dashboard/kuppi/<?= $requestId ?>" class="kuppi-request-title kuppi-request-title--list"><?= e((string) ($request['title'] ?? 'Untitled')) ?></a>
                        <div class="kuppi-request-author-row">
                            <span class="kuppi-request-avatar"><?= e(ui_initials($currentUserName)) ?></span>
                            <p class="kuppi-request-meta kuppi-request-meta--list">
                                <span>Requested by <strong><?= e($currentUserName) ?></strong></span>
                                <span class="kuppi-meta-dot">•</span>
                                <span><?= e(kuppi_relative_time_label((string) ($request['created_at'] ?? 'now'))) ?></span>
                            </p>
                        </div>
                    </header>

                    <p class="kuppi-request-description kuppi-request-description--list"><?= nl2br(e((string) ($request['description'] ?? ''))) ?></p>

                    <?php if (!empty($tags)): ?>
                        <div class="kuppi-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="badge"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <footer class="kuppi-request-footer kuppi-request-footer--list">
                        <div class="kuppi-request-metrics">
                            <span class="kuppi-request-metric">
                                <svg class="kuppi-request-metric-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H7l-4 4v-5.5A8.5 8.5 0 1 1 21 11.5Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <?= $commentCount ?> comments
                            </span>
                            <span class="kuppi-request-metric">
                                <svg class="kuppi-request-metric-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M16 11a4 4 0 1 0-8 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></path>
                                    <path d="M5.5 19a6.5 6.5 0 0 1 13 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></path>
                                    <path d="M17.5 4.5a3 3 0 0 1 0 6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></path>
                                </svg>
                                <?= $interestedCount ?> interested
                            </span>
                            <span class="kuppi-request-metric">
                                <svg class="kuppi-request-metric-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M15 10a3 3 0 1 0-6 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></path>
                                    <path d="M3 20a6 6 0 0 1 12 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"></path>
                                    <path d="m17 19 2 2 4-4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <?= (int) ($request['conductor_count'] ?? 0) ?> conductors
                            </span>
                        </div>
                        <div class="kuppi-request-actions kuppi-request-actions--list">
                            <a href="/dashboard/kuppi/<?= $requestId ?>#kuppi-comments" class="btn btn-outline kuppi-request-action-btn">
                                <svg class="kuppi-btn-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H7l-4 4v-5.5A8.5 8.5 0 1 1 21 11.5Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                Comment
                            </a>
                            <a href="/dashboard/kuppi/<?= $requestId ?>" class="btn btn-outline kuppi-request-action-btn">Open Session</a>
                        </div>
                    </footer>

                    <div class="kuppi-request-manage-links">
                        <?php if (kuppi_can_edit_request($request)): ?>
                            <a href="/dashboard/kuppi/<?= $requestId ?>/edit">Edit</a>
                        <?php endif; ?>
                        <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/delete" onsubmit="return confirm('Delete this request?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
