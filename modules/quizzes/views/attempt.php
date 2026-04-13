<?php
$subjectId = (int) ($subject['id'] ?? 0);
$quizId = (int) ($quiz['id'] ?? 0);
$attemptId = (int) ($attempt['id'] ?? 0);
$questions = (array) ($questions ?? []);
$selectedAnswers = (array) ($selected_answers ?? []);
$secondsRemaining = max(0, (int) ($seconds_remaining ?? 0));
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Quiz Attempt</p>
        <h1><?= e((string) ($quiz['title'] ?? 'Quiz')) ?></h1>
        <p class="page-subtitle">Answer all questions before the timer ends.</p>
    </div>
    <div class="page-header-actions">
        <div class="quiz-attempt-timer" id="quiz-attempt-timer" data-seconds="<?= $secondsRemaining ?>">
            <?= ui_lucide_icon('timer') ?> <strong id="quiz-attempt-timer-label">00:00</strong>
        </div>
    </div>
</div>

<form method="POST" action="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/attempts/<?= $attemptId ?>/submit" id="quiz-attempt-form">
    <?= csrf_field() ?>

    <div class="quiz-attempt-shell">
        <?php foreach ($questions as $index => $question): ?>
            <?php $questionId = (int) ($question['id'] ?? 0); ?>
            <article class="card quiz-attempt-question">
                <div class="card-body">
                    <h3>Q<?= (int) $index + 1 ?>. <?= e((string) ($question['question_text'] ?? '')) ?></h3>

                    <div class="quiz-attempt-options">
                        <?php foreach ((array) ($question['options'] ?? []) as $option): ?>
                            <?php $optionId = (int) ($option['id'] ?? 0); ?>
                            <label class="quiz-attempt-option">
                                <input
                                    type="radio"
                                    name="answers[<?= $questionId ?>]"
                                    value="<?= $optionId ?>"
                                    <?= isset($selectedAnswers[$questionId]) && (int) $selectedAnswers[$questionId] === $optionId ? 'checked' : '' ?>>
                                <span><?= e((string) ($option['option_text'] ?? '')) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="quiz-attempt-actions">
        <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" id="quiz-attempt-submit">Submit Answers</button>
    </div>
</form>

<script>
(function () {
    const timerRoot = document.getElementById('quiz-attempt-timer');
    const label = document.getElementById('quiz-attempt-timer-label');
    const form = document.getElementById('quiz-attempt-form');
    const submitBtn = document.getElementById('quiz-attempt-submit');

    if (!timerRoot || !label || !form || !submitBtn) {
        return;
    }

    let seconds = Number(timerRoot.getAttribute('data-seconds') || '0');
    if (!Number.isFinite(seconds) || seconds < 0) {
        seconds = 0;
    }

    function render() {
        const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
        const ss = String(seconds % 60).padStart(2, '0');
        label.textContent = mm + ':' + ss;
        timerRoot.classList.toggle('is-danger', seconds <= 60);
    }

    render();

    const ticker = setInterval(function () {
        if (seconds <= 0) {
            clearInterval(ticker);
            submitBtn.disabled = true;
            form.submit();
            return;
        }

        seconds -= 1;
        render();
    }, 1000);

    form.addEventListener('submit', function () {
        clearInterval(ticker);
        submitBtn.disabled = true;
    });
})();
</script>
