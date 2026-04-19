<?php
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/saved-posts');
$savedPosts = (array) ($posts ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Community / Saved Posts</p>
        <h1>Saved Posts</h1>
        <p class="page-subtitle">Posts you bookmarked for later.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/community" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Feed</a>
    </div>
</div>

<?php if (empty($savedPosts)): ?>
    <article class="community-post-card community-empty-state" style="margin-top: 12px;">
        <h3>No saved posts yet</h3>
        <p class="text-muted">Save posts from the community feed to quickly revisit them here.</p>
    </article>
<?php else: ?>
    <section class="community-post-list" style="margin-top: 12px;">
        <?php foreach ($savedPosts as $post): ?>
            <?php
            $postId = (int) ($post['id'] ?? 0);
            $postType = (string) ($post['post_type'] ?? 'general');
            $authorName = trim((string) ($post['author_name'] ?? ''));
            if ($authorName === '') {
                $authorName = 'Unknown User';
            }
            $postBody = trim((string) ($post['body'] ?? ''));
            $hasImage = trim((string) ($post['image_path'] ?? '')) !== '';
            $savedAt = (string) ($post['saved_at'] ?? $post['updated_at'] ?? $post['created_at'] ?? 'now');
            $isResolvedQuestion = $postType === 'question' && (int) ($post['is_resolved'] ?? 0) === 1;
            ?>
            <article class="community-post-card social-post-card">
                <header class="community-post-header">
                    <div class="community-post-author">
                        <span class="community-post-avatar"><?= e(ui_initials($authorName)) ?></span>
                        <div>
                            <strong><?= e($authorName) ?></strong>
                            <div class="community-post-meta-line">
                                <span>Saved on <?= e(date('M d, Y • H:i', strtotime($savedAt))) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="community-post-badges">
                        <span class="badge <?= e(community_post_type_badge_class($postType)) ?>"><?= e(community_post_type_label($postType)) ?></span>
                        <?php if ($isResolvedQuestion): ?>
                            <span class="badge badge-info">Solved</span>
                        <?php endif; ?>
                        <?php if (!empty($post['subject_code'])): ?>
                            <span class="badge"><?= e((string) $post['subject_code']) ?></span>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if ($postBody !== ''): ?>
                    <p class="community-post-body"><?= nl2br(e($postBody)) ?></p>
                <?php endif; ?>

                <?php if ($hasImage): ?>
                    <a href="<?= e(community_post_url($post)) ?>" class="community-post-image-link" aria-label="Open saved post image">
                        <img src="/community/<?= $postId ?>/image" alt="Saved post image">
                    </a>
                <?php endif; ?>

                <div class="community-post-stats-row">
                    <span><strong><?= (int) ($post['like_count'] ?? 0) ?></strong> likes</span>
                    <span><strong><?= (int) ($post['comment_count'] ?? 0) ?></strong> comments</span>
                </div>

                <footer class="community-post-footer social-post-actions">
                    <form method="POST" action="/dashboard/community/<?= $postId ?>/save">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                        <button type="submit" class="community-action-btn is-active">Unsave</button>
                    </form>
                    <a href="<?= e(community_post_url($post)) ?>" class="community-action-btn">Open Thread</a>
                </footer>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
