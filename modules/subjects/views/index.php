<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Manage Subjects</h1>
    <a href="/subjects/create" class="btn btn-primary">+ New Subject</a>
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
                        <th>Credits</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <?php if ($is_admin): ?>
                                <td>
                                    <strong><?= e($subject['batch_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($subject['batch_code']) ?></small>
                                </td>
                            <?php endif; ?>
                            <td><span class="badge"><?= e($subject['code']) ?></span></td>
                            <td>
                                <strong><?= e($subject['name']) ?></strong>
                                <?php if (!empty($subject['description'])): ?>
                                    <br><small class="text-muted"><?= e(substr($subject['description'], 0, 80)) ?><?= strlen($subject['description']) > 80 ? '...' : '' ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $subject['credits'] ?></td>
                            <td><?= e($subject['creator_name'] ?? 'Unknown') ?></td>
                            <td class="actions">
                                <a href="/subjects/<?= $subject['id'] ?>/edit" class="btn btn-sm btn-outline">Edit</a>
                                <form method="POST" action="/subjects/<?= $subject['id'] ?>/delete" class="table-action-form" onsubmit="return confirm('Delete this subject?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
