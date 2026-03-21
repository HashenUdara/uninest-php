<div class="page-header">
    <?php if ($is_admin): ?>
        <h1>Manage Students</h1>
        <a href="/students/create" class="btn btn-primary">+ New Student</a>
    <?php else: ?>
        <h1>Batch Students</h1>
        <?php if (!empty($moderator_batch['batch_code'])): ?>
            <span class="badge badge-info">Batch: <?= e($moderator_batch['batch_code']) ?></span>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (empty($students)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">
                <?= $is_admin
                    ? 'No students found. Create the first student account.'
                    : 'No students are currently assigned to your batch.' ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Academic Year</th>
                        <th>University</th>
                        <th>Current Batch</th>
                        <th>Locked Batch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <strong><?= e($student['name']) ?></strong><br>
                                <small class="text-muted"><?= e($student['email']) ?></small>
                            </td>
                            <td><?= (int) ($student['academic_year'] ?? 0) ?></td>
                            <td><?= e($student['university_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($student['batch_code'])): ?>
                                    <strong><?= e($student['batch_name'] ?? '-') ?></strong><br>
                                    <small class="text-muted"><?= e($student['batch_code']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Not assigned</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($student['locked_batch_code'])): ?>
                                    <strong><?= e($student['locked_batch_name'] ?? '-') ?></strong><br>
                                    <small class="text-muted"><?= e($student['locked_batch_code']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Not locked</small>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <?php if ($is_admin): ?>
                                    <a href="/students/<?= (int) $student['id'] ?>/edit" class="btn btn-sm btn-outline">Edit</a>
                                    <form method="POST" action="/students/<?= (int) $student['id'] ?>/delete" class="table-action-form" onsubmit="return confirm('Delete this student account?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="/students/<?= (int) $student['id'] ?>/remove" class="table-action-form" onsubmit="return confirm('Remove this student from your batch?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
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
