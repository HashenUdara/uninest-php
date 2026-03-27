<?php
$toneClass = ui_avatar_tone_class((string) (($resource['title'] ?? '') . '-' . ($resource['id'] ?? '')));
$isFileSource = (string) ($resource['source_type'] ?? '') === 'file';
$previewLabel = $isFileSource
    ? resources_file_extension_label((string) ($resource['file_name'] ?? ''), (string) ($resource['file_path'] ?? ''))
    : resources_link_host_label((string) ($resource['external_url'] ?? ''));
$resourceId = (int) ($resource['id'] ?? 0);
$averageRating = (float) ($resource['average_rating'] ?? 0);
$ratingCount = (int) ($resource['rating_count'] ?? 0);
$commentCount = (int) ($resource['comment_count'] ?? 0);
$maxCommentLevel = (int) ($comment_max_level ?? (comments_max_depth() + 1));
$ratingDistribution = (array) ($rating_distribution ?? []);
$ratingPeak = max(1, (int) ($rating_distribution_peak ?? 0));
$filledStars = max(0, min(5, (int) round($averageRating)));
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Subjects / Topics / Resources</p>
        <h1><?= e($resource['title']) ?></h1>
        <p class="page-subtitle">Detailed resource view for <strong><?= e($topic['title']) ?></strong>.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/subjects/<?= (int) $subject['id'] ?>/topics/<?= (int) $topic['id'] ?>/resources/create" class="btn btn-primary">+ Upload Resource</a>
        <a href="/dashboard/subjects/<?= (int) $subject['id'] ?>/topics/<?= (int) $topic['id'] ?>/resources" class="btn btn-outline">← Back to Resources</a>
    </div>
</div>

<div class="resource-detail-layout">
    <article class="resource-detail-preview">
        <div class="resource-detail-thumb <?= e($toneClass) ?>">
            <span><?= e($previewLabel) ?></span>
        </div>
        <div class="resource-detail-preview-meta">
            <span class="badge"><?= e(resources_source_label((string) $resource['source_type'])) ?></span>
            <span class="badge"><?= e(resources_category_display((string) $resource['category'], (string) ($resource['category_other'] ?? ''))) ?></span>
        </div>
    </article>

    <article class="card resource-detail-content-card">
        <div class="card-body">
            <?php if (!empty($resource['description'])): ?>
                <p><?= nl2br(e((string) $resource['description'])) ?></p>
            <?php else: ?>
                <p class="text-muted">No description added for this resource.</p>
            <?php endif; ?>

            <div class="resource-detail-meta-grid">
                <div>
                    <small class="text-muted">Uploaded By</small>
                    <strong><?= e($resource['uploader_name'] ?? 'Unknown') ?></strong>
                </div>
                <div>
                    <small class="text-muted">Created</small>
                    <strong><?= e(date('Y-m-d H:i', strtotime((string) $resource['created_at']))) ?></strong>
                </div>
                <div>
                    <small class="text-muted">Updated</small>
                    <strong><?= e(date('Y-m-d H:i', strtotime((string) $resource['updated_at']))) ?></strong>
                </div>
            </div>

            <div class="form-actions">
                <?php if ($isFileSource): ?>
                    <a href="/resources/<?= (int) $resource['id'] ?>/download" class="btn btn-primary">Download File</a>
                    <span class="text-muted">
                        <?= e((string) ($resource['file_name'] ?? 'File')) ?>
                        (<?= e(resources_format_file_size((int) ($resource['file_size'] ?? 0))) ?>)
                    </span>
                <?php else: ?>
                    <a href="<?= e((string) $resource['external_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary">Open Link</a>
                    <span class="text-muted"><?= e((string) $resource['external_url']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </article>
</div>

<?php if (resources_can_embed_in_iframe($resource)): ?>
    <div class="card resource-embed-card">
        <div class="card-body">
            <h3>Preview</h3>
            <?php if ($isFileSource): ?>
                <iframe
                    class="resource-embed-frame"
                    src="/resources/<?= (int) $resource['id'] ?>/inline"
                    title="Resource preview: <?= e($resource['title']) ?>"
                    loading="lazy"></iframe>
            <?php else: ?>
                <iframe
                    class="resource-embed-frame"
                    src="<?= e((string) $resource['external_url']) ?>"
                    title="Resource link preview: <?= e($resource['title']) ?>"
                    loading="lazy"></iframe>
                <p class="text-muted">If this website blocks embedding, use the <strong>Open Link</strong> button above.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card resource-embed-empty">
        <div class="card-body">
            <p class="text-muted">Inline preview is not available for this resource type. Use the action above to open or download it.</p>
        </div>
    </div>
<?php endif; ?>

<section class="resource-interactions-card" id="resource-interactions">
    <div class="card-body">
        <h3>Reviews</h3>
        <div class="resource-review-layout">
            <div class="resource-review-score">
                <strong><?= e(resources_format_rating_value($averageRating)) ?></strong>
                <div class="resource-review-stars" aria-label="Average rating <?= e(resources_format_rating_value($averageRating)) ?> out of 5">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                        <span class="<?= $star <= $filledStars ? 'is-filled' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <small><?= e($ratingCount . ' rating' . ($ratingCount === 1 ? '' : 's')) ?></small>
            </div>
            <div class="resource-review-bars">
                <?php for ($score = 5; $score >= 1; $score--): ?>
                    <?php
                    $bucketCount = (int) ($ratingDistribution[$score] ?? 0);
                    $bucketPercent = $ratingPeak > 0 ? ($bucketCount / $ratingPeak) * 100 : 0;
                    ?>
                    <div class="resource-review-row">
                        <div class="resource-review-track">
                            <span class="resource-review-fill" style="width: <?= e(number_format($bucketPercent, 2, '.', '')) ?>%;"></span>
                        </div>
                        <div class="resource-review-meta">
                            <strong><?= $score ?>.0</strong>
                            <span><?= e($bucketCount . ' review' . ($bucketCount === 1 ? '' : 's')) ?></span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="resource-interactions-summary">
            <span class="badge"><?= e(resources_comment_count_label($commentCount)) ?></span>
        </div>

        <?php if (!empty($can_rate)): ?>
            <form method="POST" action="/resources/<?= $resourceId ?>/rating" class="resource-rating-form">
                <?= csrf_field() ?>
                <label for="rating">Your Rating</label>
                <select id="rating" name="rating" required>
                    <option value="">Select rating</option>
                    <?php for ($score = 1; $score <= 5; $score++): ?>
                        <option value="<?= $score ?>" <?= (int) ($current_user_rating ?? 0) === $score ? 'selected' : '' ?>>
                            <?= $score ?> / 5
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary">Save Rating</button>
            </form>
        <?php elseif (user_role() === 'student' && (int) ($resource['uploaded_by_user_id'] ?? 0) === (int) auth_id()): ?>
            <p class="text-muted">You cannot rate your own uploaded resource.</p>
        <?php else: ?>
            <p class="text-muted">Only students can submit ratings.</p>
        <?php endif; ?>
    </div>
</section>

<section class="resource-comments-card" id="resource-comments">
    <div class="card-body">
        <div class="resource-comments-shell">
            <form method="POST" action="/resources/<?= $resourceId ?>/comments" class="resource-comments-composer">
                <?= csrf_field() ?>
                <textarea
                    id="comment_body"
                    name="body"
                    rows="4"
                    maxlength="<?= comments_max_body_length() ?>"
                    placeholder="Add comment..."
                    required></textarea>
                <div class="resource-comments-composer-footer">
                    <small class="text-muted">Threaded replies up to <?= $maxCommentLevel ?> levels.</small>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>

            <div class="resource-comments-divider"></div>

            <div class="resource-comments-header-row">
                <h3>Comments <span class="badge resource-comments-count"><?= (int) $commentCount ?></span></h3>
                <span class="resource-comments-sort">Most recent</span>
            </div>

            <?php if (empty($comments)): ?>
                <p class="text-muted">No comments yet. Start the discussion.</p>
            <?php else: ?>
                <div class="resource-comments-list">
                    <?php
                    $renderComments = function (array $nodes) use (&$renderComments, $resourceId): void {
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
                                                    <form method="POST" action="/resources/<?= $resourceId ?>/comments" class="resource-comment-inline-form">
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
                                                    <form method="POST" action="/resources/<?= $resourceId ?>/comments/<?= $commentId ?>" class="resource-comment-inline-form">
                                                        <?= csrf_field() ?>
                                                        <textarea name="body" rows="2" maxlength="<?= comments_max_body_length() ?>" required><?= e((string) ($comment['body'] ?? '')) ?></textarea>
                                                        <button type="submit" class="btn btn-sm btn-outline">Save</button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>

                                            <?php if (!empty($comment['can_delete'])): ?>
                                                <form method="POST" action="/resources/<?= $resourceId ?>/comments/<?= $commentId ?>/delete" onsubmit="return confirm('Delete this comment and all replies?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="resource-comment-action-btn is-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($comment['children'])): ?>
                                    <div class="resource-comment-children">
                                        <?php $renderComments($comment['children']); ?>
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
    </div>
</section>
