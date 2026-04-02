<?php $currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/my-kuppi-requests'); ?>

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
            ?>
            <article class="kuppi-request-card">
                <aside class="kuppi-vote-rail">
                    <strong class="kuppi-vote-score"><?= (int) ($request['vote_score'] ?? 0) ?></strong>
                    <small>Score</small>
                </aside>
                <div class="kuppi-request-main">
                    <header class="kuppi-request-header">
                        <div class="kuppi-request-badges">
                            <?php if (!empty($request['subject_code'])): ?>
                                <span class="badge"><?= e((string) $request['subject_code']) ?></span>
                            <?php endif; ?>
                            <span class="badge badge-info"><?= e(ucfirst((string) ($request['status'] ?? 'open'))) ?></span>
                        </div>
                        <a href="/dashboard/kuppi/<?= $requestId ?>" class="kuppi-request-title"><?= e((string) ($request['title'] ?? 'Untitled')) ?></a>
                        <p class="kuppi-request-meta">
                            Updated <?= e(date('Y-m-d H:i', strtotime((string) ($request['updated_at'] ?? 'now')))) ?>
                        </p>
                    </header>

                    <p class="kuppi-request-description"><?= nl2br(e((string) ($request['description'] ?? ''))) ?></p>

                    <?php if (!empty($tags)): ?>
                        <div class="kuppi-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="badge"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <footer class="kuppi-request-footer">
                        <div class="kuppi-vote-stats">
                            <span><strong><?= (int) ($request['upvote_count'] ?? 0) ?></strong> upvotes</span>
                            <span><strong><?= (int) ($request['downvote_count'] ?? 0) ?></strong> downvotes</span>
                        </div>
                        <div class="kuppi-request-actions">
                            <a href="/dashboard/kuppi/<?= $requestId ?>" class="btn btn-sm btn-outline">Open</a>
                            <?php if (kuppi_can_edit_request($request)): ?>
                                <a href="/dashboard/kuppi/<?= $requestId ?>/edit" class="btn btn-sm btn-outline">Edit</a>
                            <?php endif; ?>
                            <form method="POST" action="/dashboard/kuppi/<?= $requestId ?>/delete" onsubmit="return confirm('Delete this request?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Delete</button>
                            </form>
                        </div>
                    </footer>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
