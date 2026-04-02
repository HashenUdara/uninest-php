<?php
$activeBatch = (array) ($active_batch ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Requested Kuppi Sessions</p>
        <h1>Request Kuppi Session</h1>
        <p class="page-subtitle">
            Propose a peer-learning session based on what your batch needs next.
            <?php if (!empty($activeBatch['batch_code'])): ?>
                Active batch: <strong><?= e((string) $activeBatch['batch_code']) ?></strong>.
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/kuppi" class="btn btn-outline">← Back to Requests</a>
        <a href="/my-kuppi-requests" class="btn btn-outline">My Requests</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/dashboard/kuppi">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Select subject</option>
                    <?php foreach ((array) $subject_options as $subject): ?>
                        <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                        <option value="<?= $subjectId ?>" <?= (int) old('subject_id') === $subjectId ? 'selected' : '' ?>>
                            <?= e((string) ($subject['code'] ?? 'SUB')) ?> — <?= e((string) ($subject['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Session Title</label>
                <input type="text" id="title" name="title" value="<?= old('title') ?>" required maxlength="200" placeholder="e.g., Binary Search Trees and Traversal Techniques">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" maxlength="2000" required placeholder="Describe what should be covered and why this session is needed."><?= old('description') ?></textarea>
            </div>

            <div class="form-group">
                <label for="tags_csv">Tags (comma-separated)</label>
                <input type="text" id="tags_csv" name="tags_csv" value="<?= old('tags_csv') ?>" maxlength="300" placeholder="exam-prep, data-structures, intermediate">
                <small class="text-muted">Up to 8 tags. Tags are normalized to lowercase and hyphen style.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Request</button>
                <a href="/dashboard/kuppi" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
