<?php
$quizStatus = (string) ($quiz['status'] ?? 'draft');
$breadcrumb = 'Quizzes / My Quizzes / Edit';
$pageTitle = 'Edit Quiz';
$pageSubtitle = 'Update your quiz details and questions before submit/publish.';
$formAction = '/my-quizzes/' . (int) ($quiz['id'] ?? 0);
$backUrl = '/my-quizzes';
$form_action = $formAction;
$back_url = $backUrl;
?>

<div class="quiz-edit-status-row">
    <span class="badge <?= e(quizzes_status_badge_class($quizStatus)) ?>"><?= e(quizzes_status_label($quizStatus)) ?></span>
    <?php if (!empty($quiz['rejection_reason']) && $quizStatus === 'rejected'): ?>
        <span class="text-muted">Reason: <?= e((string) $quiz['rejection_reason']) ?></span>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_builder_form.php'; ?>
