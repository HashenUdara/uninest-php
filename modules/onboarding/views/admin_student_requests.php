<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Student Join Requests (Admin Override)</h1>
</div>

<?php if (empty($requests)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No student join requests available.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Batch</th>
                        <th>Moderator</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['student_name']) ?></strong><br>
                                <small class="text-muted"><?= e($item['student_email']) ?></small>
                            </td>
                            <td>
                                <strong><?= e($item['batch_name']) ?></strong><br>
                                <small class="text-muted"><?= e($item['batch_code']) ?> · <?= e($item['program']) ?></small>
                            </td>
                            <td><?= e($item['moderator_name']) ?></td>
                            <td>
                                <?php if ($item['status'] === 'approved'): ?>
                                    <span class="badge badge-info">Approved</span>
                                <?php elseif ($item['status'] === 'rejected'): ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= e($item['rejection_reason'] ?? '-') ?></small></td>
                            <td class="actions">
                                <?php if ($item['status'] === 'pending'): ?>
                                    <form method="POST" action="/admin/student-requests/<?= (int) $item['id'] ?>/approve" class="table-action-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                                    </form>
                                    <form method="POST" action="/admin/student-requests/<?= (int) $item['id'] ?>/reject" class="table-action-form">
                                        <?= csrf_field() ?>
                                        <input type="text" name="rejection_reason" placeholder="Reason (optional)" class="table-action-form-input">
                                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
