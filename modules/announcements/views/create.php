<?php
$activeBatch = (array) ($active_batch ?? []);
$batchCode = trim((string) ($activeBatch['batch_code'] ?? ''));
$batchName = trim((string) ($activeBatch['name'] ?? ''));
$batchTitle = $batchCode !== '' ? $batchCode : ($batchName !== '' ? $batchName : 'Selected Batch');
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Dashboard / Announcements</p>
        <h1>Create Announcement</h1>
        <p class="page-subtitle">Publish an official batch update. This will be visible immediately.</p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e((string) ($back_url ?? '/dashboard/announcements')) ?>" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Announcements</a>
    </div>
</div>

<section class="announcement-editor-shell">
    <article class="announcement-editor-meta">
        <h3>Publishing To</h3>
        <p><strong><?= e($batchTitle) ?></strong></p>
        <?php if ($batchName !== '' && $batchCode !== ''): ?>
            <p class="text-muted"><?= e($batchName) ?></p>
        <?php endif; ?>
    </article>

    <?php
    $formAction = '/dashboard/announcements';
    $submitLabel = 'Publish Announcement';
    $isEdit = false;
    require __DIR__ . '/_form.php';
    ?>
</section>
