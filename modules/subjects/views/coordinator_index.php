<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Coordinator / Subjects</p>
        <h1>Assigned Subjects</h1>
        <p class="page-subtitle">Manage only the subjects you are assigned to coordinate.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard/subjects" class="btn btn-outline">Browse Batch Subjects</a>
    </div>
</div>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No subjects are assigned to you as coordinator yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Code</th>
                        <th>Subject</th>
                        <th>Year</th>
                        <th>Sem</th>
                        <th>Credits</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <?php
                        $avatarText = ui_initials((string) $subject['name']);
                        $avatarTone = ui_avatar_tone_class((string) (($subject['code'] ?? '') . '-' . ($subject['name'] ?? '')));
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($subject['batch_name']) ?></strong><br>
                                <small class="text-muted"><?= e($subject['batch_code']) ?></small>
                            </td>
                            <td><span class="badge"><?= e($subject['code']) ?></span></td>
                            <td>
                                <div class="table-identity">
                                    <span class="table-avatar <?= e($avatarTone) ?>"><?= e($avatarText) ?></span>
                                    <div class="table-identity-text">
                                        <strong><?= e($subject['name']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?= (int) ($subject['academic_year'] ?? 1) ?></td>
                            <td><?= (int) ($subject['semester'] ?? 1) ?></td>
                            <td><?= (int) $subject['credits'] ?></td>
                            <td class="actions">
                                <a href="/subjects/<?= (int) $subject['id'] ?>/topics" class="table-icon-btn" title="Manage topics" aria-label="Manage topics">
                                    <?= ui_lucide_icon('layers') ?>
                                </a>
                                <a href="/coordinator/subjects/<?= (int) $subject['id'] ?>/edit" class="table-icon-btn" title="Edit subject" aria-label="Edit subject">
                                    <?= ui_lucide_icon('pencil') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
