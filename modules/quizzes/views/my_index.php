<?php
$quizzes = (array) ($quizzes ?? []);
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb">Quizzes / My Quizzes</p>
        <h1>My Quizzes</h1>
        <p class="page-subtitle">Manage your drafts, submissions, and published quizzes.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Dashboard</a>
    </div>
</div>

<?php if (empty($quizzes)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">You have not created any quizzes yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body no-padding">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Status</th>
                        <th>Subject</th>
                        <th>Questions</th>
                        <th>Duration</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                        <?php
                        $quizId = (int) ($quiz['id'] ?? 0);
                        $subjectId = (int) ($quiz['subject_id'] ?? 0);
                        $status = (string) ($quiz['status'] ?? 'draft');
                        $isEditable = in_array($status, ['draft', 'rejected'], true);
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($quiz['title'] ?? 'Untitled Quiz')) ?></strong>
                                <?php if (!empty($quiz['description'])): ?>
                                    <br><small class="text-muted"><?= e((string) $quiz['description']) ?></small>
                                <?php endif; ?>
                                <?php if ($status === 'rejected' && !empty($quiz['rejection_reason'])): ?>
                                    <br><small class="text-muted">Rejected: <?= e((string) $quiz['rejection_reason']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= e(quizzes_status_badge_class($status)) ?>"><?= e(quizzes_status_label($status)) ?></span>
                            </td>
                            <td>
                                <strong><?= e((string) ($quiz['subject_code'] ?? 'SUB')) ?></strong><br>
                                <small class="text-muted"><?= e((string) ($quiz['subject_name'] ?? 'Subject')) ?></small>
                            </td>
                            <td><?= (int) ($quiz['question_count'] ?? 0) ?></td>
                            <td><?= (int) ($quiz['duration_minutes'] ?? 0) ?> min</td>
                            <td><?= e(date('Y-m-d H:i', strtotime((string) ($quiz['updated_at'] ?? 'now')))) ?></td>
                            <td class="actions">
                                <?php if ($status === 'approved'): ?>
                                    <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="table-icon-btn" title="View quiz" aria-label="View quiz">
                                        <?= ui_lucide_icon('eye') ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($isEditable): ?>
                                    <a href="/my-quizzes/<?= $quizId ?>/edit" class="table-icon-btn" title="Edit" aria-label="Edit">
                                        <?= ui_lucide_icon('pencil') ?>
                                    </a>
                                    <form method="POST" action="/my-quizzes/<?= $quizId ?>/submit" class="table-action-form">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="table-icon-btn is-success" title="Submit" aria-label="Submit">
                                            <?= ui_lucide_icon('send') ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="/my-quizzes/<?= $quizId ?>/delete" class="table-action-form" onsubmit="return confirm('Delete this quiz?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="table-icon-btn is-danger" title="Delete" aria-label="Delete">
                                            <?= ui_lucide_icon('trash-2') ?>
                                        </button>
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
