<?php
$announcement = (array) ($announcement ?? []);
$announcementId = (int) ($announcement['id'] ?? 0);
$title = trim((string) ($announcement['title'] ?? 'Untitled Announcement'));
$body = trim((string) ($announcement['body'] ?? ''));
$authorName = trim((string) ($announcement['author_name'] ?? ''));
if ($authorName === '') {
    $authorName = 'Unknown User';
}
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? ('/dashboard/announcements/' . $announcementId));
$isPinned = (int) ($announcement['is_pinned'] ?? 0) === 1;
$subjectCode = trim((string) ($announcement['subject_code'] ?? ''));
$subjectName = trim((string) ($announcement['subject_name'] ?? ''));
$batchCode = trim((string) ($announcement['batch_code'] ?? ''));
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Announcements</p>
        <h1><?= e($title) ?></h1>
        <p class="page-subtitle">
            Posted by <strong><?= e($authorName) ?></strong>
            on <?= e(date('M d, Y • H:i', strtotime((string) ($announcement['created_at'] ?? 'now')))) ?>
        </p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e((string) ($back_url ?? '/dashboard/announcements')) ?>" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Announcements</a>
        <?php if (!empty($can_manage)): ?>
            <a href="/dashboard/announcements/<?= $announcementId ?>/edit<?= !empty($is_admin) ? '?batch_id=' . (int) ($announcement['batch_id'] ?? 0) : '' ?>" class="btn btn-primary">Edit Announcement</a>
        <?php endif; ?>
    </div>
</div>

<article class="announcement-detail-card">
    <header class="announcement-detail-head">
        <div class="announcement-detail-meta">
            <?php if ($batchCode !== ''): ?>
                <span class="badge"><?= e($batchCode) ?></span>
            <?php endif; ?>
            <?php if ($subjectCode !== '' || $subjectName !== ''): ?>
                <span class="badge badge-info"><?= e($subjectCode !== '' ? $subjectCode : $subjectName) ?></span>
            <?php endif; ?>
            <?php if ($isPinned): ?>
                <span class="badge badge-warning">Pinned</span>
            <?php endif; ?>
        </div>
        <?php if (!empty($announcement['updated_at']) && (string) ($announcement['updated_at'] ?? '') !== (string) ($announcement['created_at'] ?? '')): ?>
            <small class="text-muted">Updated <?= e(date('M d, Y • H:i', strtotime((string) $announcement['updated_at']))) ?></small>
        <?php endif; ?>
    </header>

    <div class="announcement-detail-body">
        <p><?= nl2br(e($body)) ?></p>
    </div>

    <?php if (!empty($can_manage)): ?>
        <footer class="announcement-detail-actions">
            <form method="POST" action="/dashboard/announcements/<?= $announcementId ?>/<?= $isPinned ? 'unpin' : 'pin' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                <button type="submit" class="btn btn-sm btn-outline"><?= $isPinned ? 'Unpin' : 'Pin' ?></button>
            </form>
            <form method="POST" action="/dashboard/announcements/<?= $announcementId ?>/delete" onsubmit="return confirm('Delete this announcement?');">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e((string) ($back_url ?? '/dashboard/announcements')) ?>">
                <button type="submit" class="btn btn-sm btn-outline">Delete</button>
            </form>
        </footer>
    <?php endif; ?>
</article>
