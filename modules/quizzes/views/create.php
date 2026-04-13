<?php
$breadcrumb = (string) ($role_label ?? 'Student') . ' / Subjects / Quizzes / Create';
$pageTitle = 'Create Quiz';
$pageSubtitle = 'Create interactive quizzes to help peers practice and learn.';
$formAction = '/dashboard/subjects/' . (int) ($subject['id'] ?? 0) . '/quizzes';
$backUrl = '/dashboard/subjects/' . (int) ($subject['id'] ?? 0) . '/quizzes';
$form_action = $formAction;
$back_url = $backUrl;
require __DIR__ . '/_builder_form.php';
