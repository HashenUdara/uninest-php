<?php
$activeBatch = (array) ($active_batch ?? []);
$backFeedUrl = (string) ($back_feed_url ?? '/dashboard/community');

$activeBatchCode = trim((string) ($activeBatch['batch_code'] ?? ''));
$activeBatchName = trim((string) ($activeBatch['name'] ?? ''));
if ($activeBatchName === '') {
    $activeBatchName = 'Your Batch';
}
$activeUniversityName = trim((string) ($activeBatch['university_name'] ?? ''));
$authorName = trim((string) (auth_user()['name'] ?? 'User'));
if ($authorName === '') {
    $authorName = 'User';
}
?>

<div class="page-header community-create-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Community Feed / Create Post</p>
        <h1>Start A New Post</h1>
        <p class="page-subtitle">Share a useful update, ask a question, or post a resource your batch can use.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($backFeedUrl) ?>" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Feed</a>
    </div>
</div>

<section class="community-create-layout">
    <article class="community-composer-card community-create-composer">
        <header class="social-composer-head">
            <h3>Posting To <?= e($activeBatchCode !== '' ? $activeBatchCode : $activeBatchName) ?></h3>
            <p>
                <?= e($activeBatchName) ?>
                <?php if ($activeUniversityName !== ''): ?>
                    · <?= e($activeUniversityName) ?>
                <?php endif; ?>
            </p>
        </header>

        <form method="POST" action="/dashboard/community" enctype="multipart/form-data" class="community-composer-form">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="/dashboard/community/create">

            <div class="community-create-editor">
                <div class="community-create-author-row">
                    <span class="community-post-avatar"><?= e(ui_initials($authorName)) ?></span>
                    <div class="community-create-author-meta">
                        <strong><?= e($authorName) ?></strong>
                        <small>Posting to <?= e($activeBatchCode !== '' ? $activeBatchCode : $activeBatchName) ?></small>
                    </div>
                </div>
                <textarea id="body" name="body" rows="8" placeholder="What do you want to share with your batch today?"><?= e(old('body', '')) ?></textarea>
            </div>

            <div class="social-composer-inline-fields">
                <select id="post_type" name="post_type" required>
                    <?php $selectedType = old('post_type', 'general'); ?>
                    <?php foreach ($post_types as $postType): ?>
                        <option value="<?= e($postType) ?>" <?= $selectedType === $postType ? 'selected' : '' ?>>
                            <?= e(community_post_type_label($postType)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="subject_id" name="subject_id">
                    <option value="">General (No Subject)</option>
                    <?php $selectedSubjectId = (int) old('subject_id', '0'); ?>
                    <?php foreach ($subject_options as $subject): ?>
                        <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                        <option value="<?= $subjectId ?>" <?= $selectedSubjectId === $subjectId ? 'selected' : '' ?>>
                            <?= e((string) ($subject['code'] ?? 'SUB')) ?> — <?= e((string) ($subject['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="social-upload-btn" for="community-image-input">
                    <input type="file" id="community-image-input" name="image" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                    <span id="community-upload-label">Add Photo</span>
                </label>
            </div>
            <p class="community-upload-hint">JPG, PNG, WEBP, GIF • up to 10MB</p>

            <div class="community-image-preview" id="community-image-preview" hidden>
                <img id="community-image-preview-img" alt="Selected image preview">
                <div class="community-image-preview-footer">
                    <span id="community-image-preview-name"></span>
                    <button type="button" class="community-image-remove-btn" id="community-image-remove-btn">Remove</button>
                </div>
            </div>

            <div class="community-create-actions">
                <a href="<?= e($backFeedUrl) ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Post to Feed</button>
            </div>
        </form>
    </article>

    <aside class="community-rail-card community-create-guide">
        <header class="community-rail-header">
            <h3>Before You Post</h3>
        </header>
        <ul class="community-tip-list">
            <li>Lead with one clear point so people can respond quickly.</li>
            <li>Use a matching post type and subject so your post is easy to discover.</li>
            <li>When sharing notes or discussions, attach a photo for better reach.</li>
        </ul>
    </aside>
</section>

<script>
    (function () {
        const input = document.getElementById('community-image-input');
        const preview = document.getElementById('community-image-preview');
        const previewImg = document.getElementById('community-image-preview-img');
        const previewName = document.getElementById('community-image-preview-name');
        const removeBtn = document.getElementById('community-image-remove-btn');
        const uploadLabel = document.getElementById('community-upload-label');

        if (!input || !preview || !previewImg || !previewName || !removeBtn || !uploadLabel) {
            return;
        }

        let objectUrl = '';

        function formatFileSize(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) {
                return '0 KB';
            }
            if (bytes >= 1024 * 1024) {
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            }
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        function clearPreview() {
            if (objectUrl !== '') {
                URL.revokeObjectURL(objectUrl);
                objectUrl = '';
            }
            preview.hidden = true;
            previewImg.removeAttribute('src');
            previewName.textContent = '';
            uploadLabel.textContent = 'Add Photo';
        }

        input.addEventListener('change', function () {
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!file || (typeof file.type === 'string' && !file.type.startsWith('image/'))) {
                clearPreview();
                return;
            }

            if (objectUrl !== '') {
                URL.revokeObjectURL(objectUrl);
            }
            objectUrl = URL.createObjectURL(file);
            previewImg.src = objectUrl;
            previewName.textContent = file.name + ' • ' + formatFileSize(file.size);
            preview.hidden = false;
            uploadLabel.textContent = 'Change Photo';
        });

        removeBtn.addEventListener('click', function () {
            input.value = '';
            clearPreview();
        });

        window.addEventListener('beforeunload', function () {
            if (objectUrl !== '') {
                URL.revokeObjectURL(objectUrl);
            }
        });
    })();
</script>
