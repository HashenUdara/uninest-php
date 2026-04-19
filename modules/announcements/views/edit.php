<?php
$announcement = (array) ($announcement ?? []);
$activeBatch = (array) ($active_batch ?? []);
$batchCode = trim((string) ($activeBatch['batch_code'] ?? ''));
$batchName = trim((string) ($activeBatch['name'] ?? ''));
$batchTitle = $batchCode !== '' ? $batchCode : ($batchName !== '' ? $batchName : 'Selected Batch');
$announcementId = (int) ($announcement['id'] ?? 0);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Announcements / Edit</p>
        <h1>Edit Announcement</h1>
        <p class="page-subtitle">Update and republish your official announcement.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e((string) ($back_url ?? '/dashboard/announcements')) ?>" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Announcement</a>
    </div>
</div>

<section class="announcement-editor-shell">
    <article class="announcement-editor-meta">
        <h3>Batch Context</h3>
        <p><strong><?= e($batchTitle) ?></strong></p>
        <?php if ($batchName !== '' && $batchCode !== ''): ?>
            <p class="text-muted"><?= e($batchName) ?></p>
        <?php endif; ?>
    </article>

    <?php
    $formAction = '/dashboard/announcements/' . $announcementId;
    $submitLabel = 'Save Changes';
    $isEdit = true;
    require __DIR__ . '/_form.php';
    ?>
</section>
