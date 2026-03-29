<?php
$postId = (int) ($post['id'] ?? 0);
$authorName = trim((string) ($post['author_name'] ?? ''));
if ($authorName === '') {
    $authorName = 'Unknown User';
}
$hasImage = trim((string) ($post['image_path'] ?? '')) !== '';
$likedByViewer = (int) ($post['is_liked_by_viewer'] ?? 0) === 1;
$savedByViewer = (int) ($post['is_saved_by_viewer'] ?? 0) === 1;
$commentCount = (int) ($post['comment_count'] ?? 0);
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? ('/dashboard/community/' . $postId));
$postType = (string) ($post['post_type'] ?? 'general');
$isPinnedAnnouncement = $postType === 'announcement' && (int) ($post['is_pinned'] ?? 0) === 1;
$isResolvedQuestion = $postType === 'question' && (int) ($post['is_resolved'] ?? 0) === 1;
$viewerId = (int) auth_id();
$canReportPost = (int) ($post['author_user_id'] ?? 0) !== $viewerId;
$reportReasonOptions = (array) ($report_reason_options ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Community Feed</p>
        <h1><?= e(community_post_type_label($postType)) ?></h1>
        <p class="page-subtitle">
            Posted by <strong><?= e($authorName) ?></strong>
            on <?= e(date('Y-m-d H:i', strtotime((string) ($post['created_at'] ?? 'now')))) ?>.
        </p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($back_feed_url) ?>" class="btn btn-outline">← Back to Feed</a>
        <?php if (!empty($can_edit_post)): ?>
            <a href="/my-posts/<?= $postId ?>/edit" class="btn btn-primary">Edit Post</a>
        <?php endif; ?>
    </div>
</div>

<article class="community-detail-card">
    <header class="community-post-header">
        <div class="community-post-author">
            <span class="community-post-avatar"><?= e(ui_initials($authorName)) ?></span>
            <div>
                <strong><?= e($authorName) ?></strong>
                <div class="community-post-meta-line">
                    <?php if (!empty($post['batch_code'])): ?>
                        <span><?= e((string) $post['batch_code']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($post['subject_code'])): ?>
                        <span>• <?= e((string) $post['subject_code']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($post['edited_at'])): ?>
                        <span>• Edited</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="community-post-badges">
            <span class="badge <?= e(community_post_type_badge_class($postType)) ?>"><?= e(community_post_type_label($postType)) ?></span>
            <?php if ($isPinnedAnnouncement): ?>
                <span class="badge badge-warning">Pinned</span>
            <?php endif; ?>
            <?php if ($isResolvedQuestion): ?>
                <span class="badge badge-info">Solved</span>
            <?php endif; ?>
            <?php if (!empty($post['subject_name'])): ?>
                <span class="badge"><?= e((string) $post['subject_name']) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($post['body'])): ?>
        <div class="community-detail-body">
            <p><?= nl2br(e((string) $post['body'])) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($hasImage): ?>
        <div class="community-detail-image">
            <img src="/community/<?= $postId ?>/image" alt="Post image for <?= e($authorName) ?>">
        </div>
    <?php endif; ?>

    <footer class="community-post-footer">
        <div class="community-post-stats">
            <span><?= (int) ($post['like_count'] ?? 0) ?> likes</span>
            <span><?= $commentCount ?> comments</span>
        </div>
        <div class="community-post-actions">
            <form method="POST" action="/dashboard/community/<?= $postId ?>/like">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                <button type="submit" class="btn btn-sm <?= $likedByViewer ? 'btn-primary' : 'btn-outline' ?>">
                    <?= $likedByViewer ? 'Liked' : 'Like' ?>
                </button>
            </form>
            <?php if (!empty($can_save_posts)): ?>
                <form method="POST" action="/dashboard/community/<?= $postId ?>/save">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                    <button type="submit" class="btn btn-sm <?= $savedByViewer ? 'btn-primary' : 'btn-outline' ?>">
                        <?= $savedByViewer ? 'Saved' : 'Save' ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!empty($can_resolve_question)): ?>
                <form method="POST" action="/dashboard/community/<?= $postId ?>/question/<?= $isResolvedQuestion ? 'reopen' : 'resolve' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                    <button type="submit" class="btn btn-sm btn-outline">
                        <?= $isResolvedQuestion ? 'Reopen' : 'Mark Solved' ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!empty($can_pin_post)): ?>
                <form method="POST" action="/dashboard/community/<?= $postId ?>/<?= $isPinnedAnnouncement ? 'unpin' : 'pin' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                    <button type="submit" class="btn btn-sm btn-outline">
                        <?= $isPinnedAnnouncement ? 'Unpin' : 'Pin' ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!empty($can_delete_post)): ?>
                <form method="POST" action="/dashboard/community/<?= $postId ?>/delete" onsubmit="return confirm('Delete this post? This will also delete all comments.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline">Delete Post</button>
                </form>
            <?php endif; ?>
        </div>
    </footer>

    <?php if ($canReportPost): ?>
        <div class="community-post-footer">
            <details>
                <summary class="community-action-btn">Report Post</summary>
                <form method="POST" action="/dashboard/community/<?= $postId ?>/report" class="community-composer-form" style="margin-top: 8px; max-width: 520px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                    <div class="form-group">
                        <label for="post-report-reason">Reason</label>
                        <select id="post-report-reason" name="reason" required>
                            <?php foreach ($reportReasonOptions as $reasonValue => $reasonLabel): ?>
                                <option value="<?= e((string) $reasonValue) ?>"><?= e((string) $reasonLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="post-report-details">Details (Optional)</label>
                        <textarea id="post-report-details" name="details" rows="2" maxlength="1000" placeholder="Share context for moderators..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline">Submit Report</button>
                </form>
            </details>
        </div>
    <?php endif; ?>
</article>

<?php if (!empty($can_edit_post)): ?>
    <section class="community-inline-edit-card">
        <details>
            <summary>Quick Edit</summary>
            <form method="POST" action="/dashboard/community/<?= $postId ?>" enctype="multipart/form-data" class="community-composer-form">
                <?= csrf_field() ?>
                <div class="community-composer-grid">
                    <div class="form-group">
                        <label for="post_type">Post Type</label>
                        <?php $selectedType = old('post_type', (string) ($post['post_type'] ?? 'general')); ?>
                        <select id="post_type" name="post_type" required>
                            <?php foreach (community_post_types() as $postTypeOption): ?>
                                <option value="<?= e($postTypeOption) ?>" <?= $selectedType === $postTypeOption ? 'selected' : '' ?>>
                                    <?= e(community_post_type_label($postTypeOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subject_id">Subject (Optional)</label>
                        <?php $selectedSubject = (int) old('subject_id', (string) ((int) ($post['subject_id'] ?? 0))); ?>
                        <select id="subject_id" name="subject_id">
                            <option value="">General (No Subject)</option>
                            <?php foreach (community_subject_options_for_batch((int) ($post['batch_id'] ?? 0)) as $subject): ?>
                                <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                                <option value="<?= $subjectId ?>" <?= $selectedSubject === $subjectId ? 'selected' : '' ?>>
                                    <?= e((string) ($subject['code'] ?? 'SUB')) ?> — <?= e((string) ($subject['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="body">Post</label>
                    <textarea id="body" name="body" rows="4"><?= e(old('body', (string) ($post['body'] ?? ''))) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="image">Replace Image (Optional)</label>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                </div>

                <?php if (!empty($post['image_path'])): ?>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="remove_image" value="1"> Remove current image
                    </label>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </details>
    </section>
<?php endif; ?>

<section class="resource-comments-section" id="post-comments">
    <div class="resource-comments-shell">
        <form method="POST" action="/dashboard/community/<?= $postId ?>/comments" class="resource-comments-composer">
            <?= csrf_field() ?>
            <textarea
                name="body"
                rows="4"
                maxlength="<?= comments_max_body_length() ?>"
                placeholder="Write a comment..."
                required></textarea>
            <div class="resource-comments-composer-footer">
                <small class="text-muted">Deep threaded replies are supported.</small>
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
                $renderComments = function (array $nodes) use (&$renderComments, $postId, $viewerId, $reportReasonOptions): void {
                    foreach ($nodes as $comment):
                        $commentId = (int) ($comment['id'] ?? 0);
                        $author = trim((string) ($comment['user_name'] ?? ''));
                        if ($author === '') {
                            $author = 'Unknown User';
                        }
                        ?>
                        <article class="resource-comment depth-<?= (int) ($comment['depth'] ?? 0) ?>">
                            <div class="resource-comment-main">
                                <span class="resource-comment-avatar"><?= e(ui_initials($author)) ?></span>
                                <div class="resource-comment-body">
                                    <header class="resource-comment-header">
                                        <strong><?= e($author) ?></strong>
                                        <small class="text-muted"><?= e(date('Y-m-d H:i', strtotime((string) ($comment['created_at'] ?? 'now')))) ?></small>
                                    </header>
                                    <p><?= nl2br(e((string) ($comment['body'] ?? ''))) ?></p>

                                    <div class="resource-comment-actions">
                                        <?php if (!empty($comment['can_reply'])): ?>
                                            <details class="resource-comment-action-block">
                                                <summary class="resource-comment-action-btn">Reply</summary>
                                                <form method="POST" action="/dashboard/community/<?= $postId ?>/comments" class="resource-comment-inline-form">
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
                                                <form method="POST" action="/dashboard/community/<?= $postId ?>/comments/<?= $commentId ?>" class="resource-comment-inline-form">
                                                    <?= csrf_field() ?>
                                                    <textarea name="body" rows="2" maxlength="<?= comments_max_body_length() ?>" required><?= e((string) ($comment['body'] ?? '')) ?></textarea>
                                                    <button type="submit" class="btn btn-sm btn-outline">Save</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>

                                        <?php if (!empty($comment['can_delete'])): ?>
                                            <form method="POST" action="/dashboard/community/<?= $postId ?>/comments/<?= $commentId ?>/delete" onsubmit="return confirm('Delete this comment and all replies?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="resource-comment-action-btn is-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ((int) ($comment['user_id'] ?? 0) !== $viewerId): ?>
                                            <details class="resource-comment-action-block">
                                                <summary class="resource-comment-action-btn">Report</summary>
                                                <form method="POST" action="/dashboard/community/<?= $postId ?>/comments/<?= $commentId ?>/report" class="resource-comment-inline-form">
                                                    <?= csrf_field() ?>
                                                    <select name="reason" required>
                                                        <?php foreach ($reportReasonOptions as $reasonValue => $reasonLabel): ?>
                                                            <option value="<?= e((string) $reasonValue) ?>"><?= e((string) $reasonLabel) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <textarea name="details" rows="2" maxlength="1000" placeholder="Optional details..."></textarea>
                                                    <button type="submit" class="btn btn-sm btn-outline">Submit</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($comment['children'])): ?>
                                <div class="resource-comment-children">
                                    <?php $renderComments((array) $comment['children']); ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php
                    endforeach;
                };

                $renderComments((array) $comments);
                ?>
            </div>
        <?php endif; ?>
    </div>
</section>
