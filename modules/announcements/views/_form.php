<?php
$announcement = (array) ($announcement ?? []);
$formAction = (string) ($form_action ?? '/dashboard/announcements');
$submitLabel = (string) ($submit_label ?? 'Publish Announcement');
$isEdit = !empty($is_edit);
$subjectOptions = (array) ($subject_options ?? []);
$batchId = (int) ($batch_id ?? 0);
$isAdmin = !empty($is_admin);

$titleValue = old('title', (string) ($announcement['title'] ?? ''));
$bodyValue = old('body', (string) ($announcement['body'] ?? ''));
$selectedSubject = (int) old('subject_id', (string) ((int) ($announcement['subject_id'] ?? 0)));
?>

<form method="POST" action="<?= e($formAction) ?>" class="announcement-form-card">
    <?= csrf_field() ?>
    <?php if ($isAdmin && $batchId > 0): ?>
        <input type="hidden" name="batch_id" value="<?= $batchId ?>">
    <?php endif; ?>

    <div class="form-group">
        <label for="announcement-title">Title *</label>
        <input type="text" id="announcement-title" name="title" maxlength="200" required value="<?= e($titleValue) ?>" placeholder="e.g., Mid-semester schedule update">
    </div>

    <div class="form-group">
        <label for="announcement-subject">Subject (Optional)</label>
        <select id="announcement-subject" name="subject_id">
            <option value="">General (No Subject)</option>
            <?php foreach ($subjectOptions as $subject): ?>
                <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                <option value="<?= $subjectId ?>" <?= $selectedSubject === $subjectId ? 'selected' : '' ?>>
                    <?= e((string) ($subject['code'] ?? 'SUB')) ?> — <?= e((string) ($subject['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="announcement-body">Announcement Body *</label>
        <textarea id="announcement-body" name="body" rows="10" maxlength="6000" required placeholder="Share the official update clearly and concisely."><?= e($bodyValue) ?></textarea>
    </div>

    <div class="form-actions">
        <a href="<?= e((string) ($back_url ?? '/dashboard/announcements')) ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
    </div>

    <?php if ($isEdit): ?>
        <p class="text-muted">Announcement updates are published immediately.</p>
    <?php endif; ?>
</form>
