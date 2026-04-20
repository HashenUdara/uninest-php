<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= $is_admin ? 'Admin / Subjects' : 'Moderator / Subjects' ?></p>
        <h1>Subjects</h1>
        <p class="page-subtitle">
            <?= $is_admin
                ? 'Manage subject catalogs across approved batches.'
                : 'Create and maintain the subject catalog for your own batch.' ?>
        </p>
    </div>
    <div class="page-header-actions">
        <a href="/subjects/create" class="btn btn-primary">+ New Subject</a>
    </div>
</div>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No subjects found. <a href="/subjects/create">Create the first one</a>.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <?php if ($is_admin): ?>
                            <th>Batch</th>
                        <?php endif; ?>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Year</th>
                        <th>Sem</th>
                        <th>Credits</th>
                        <th>Coordinators</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <?php
                        $avatarText = ui_initials((string) $subject['name']);
                        $avatarTone = ui_avatar_tone_class((string) (($subject['code'] ?? '') . '-' . ($subject['name'] ?? '')));
                        ?>
                        <tr>
                            <?php if ($is_admin): ?>
                                <td>
                                    <strong><?= e($subject['batch_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($subject['batch_code']) ?></small>
                                </td>
                            <?php endif; ?>
                            <td><span class="badge"><?= e($subject['code']) ?></span></td>
                            <td>
                                <div class="table-identity">
                                    <span class="table-avatar <?= e($avatarTone) ?>"><?= e($avatarText) ?></span>
                                    <div class="table-identity-text">
                                        <strong><?= e($subject['name']) ?></strong>
                                        <?php if (!empty($subject['description'])): ?>
                                            <br><small class="text-muted"><?= e(substr($subject['description'], 0, 80)) ?><?= strlen($subject['description']) > 80 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= (int) ($subject['academic_year'] ?? 1) ?></td>
                            <td><?= (int) ($subject['semester'] ?? 1) ?></td>
                            <td><?= (int) $subject['credits'] ?></td>
                            <td><?= (int) ($subject['coordinators_count'] ?? 0) ?></td>
                            <td><?= e($subject['creator_name'] ?? 'Unknown') ?></td>
                            <td class="actions">
                                <a href="/subjects/<?= (int) $subject['id'] ?>/topics" class="table-icon-btn" title="Manage topics" aria-label="Manage topics">
                                    <?= ui_lucide_icon('layers') ?>
                                </a>
                                <a href="/subjects/<?= (int) $subject['id'] ?>/coordinators" class="table-icon-btn" title="Manage coordinators" aria-label="Manage coordinators">
                                    <?= ui_lucide_icon('users') ?>
                                </a>
                                <a href="/subjects/<?= $subject['id'] ?>/edit" class="table-icon-btn" title="Edit subject" aria-label="Edit subject">
                                    <?= ui_lucide_icon('pencil') ?>
                                </a>
                                <form method="POST" action="/subjects/<?= $subject['id'] ?>/delete" class="table-action-form" onsubmit="return confirm('Delete this subject?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="table-icon-btn is-danger" title="Delete subject" aria-label="Delete subject">
                                        <?= ui_lucide_icon('trash-2') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
