<?php
$subjectId = (int) ($subject['id'] ?? 0);
$quizId = (int) ($quiz['id'] ?? 0);
$attemptId = (int) ($attempt['id'] ?? 0);
$questions = (array) ($questions ?? []);
$selectedAnswers = (array) ($selected_answers ?? []);
$secondsRemaining = max(0, (int) ($seconds_remaining ?? 0));
$totalQuestions = count($questions);
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

    <div class="quiz-attempt-layout">
        <section class="quiz-attempt-main">
            <div class="quiz-attempt-shell">
                <?php foreach ($questions as $index => $question): ?>
                    <?php $questionId = (int) ($question['id'] ?? 0); ?>
                    <article class="card quiz-attempt-question">
                        <div class="card-body">
                            <div class="quiz-attempt-question-head">
                                <span class="quiz-attempt-question-no">Question <?= (int) $index + 1 ?> / <?= $totalQuestions ?></span>
                                <h3><?= e((string) ($question['question_text'] ?? '')) ?></h3>
                            </div>

                            <div class="quiz-attempt-options">
                                <?php foreach ((array) ($question['options'] ?? []) as $optionIndex => $option): ?>
                                    <?php $optionId = (int) ($option['id'] ?? 0); ?>
                                    <label class="quiz-attempt-option">
                                        <input
                                            type="radio"
                                            name="answers[<?= $questionId ?>]"
                                            value="<?= $optionId ?>"
                                            <?= isset($selectedAnswers[$questionId]) && (int) $selectedAnswers[$questionId] === $optionId ? 'checked' : '' ?>>
                                        <span class="quiz-attempt-option-letter"><?= e(chr(65 + (int) $optionIndex)) ?></span>
                                        <span class="quiz-attempt-option-text"><?= e((string) ($option['option_text'] ?? '')) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="quiz-attempt-side">
            <article class="card quiz-attempt-side-card">
                <div class="card-body">
                    <h3><?= ui_lucide_icon('clock-3') ?> Attempt Progress</h3>
                    <ul class="quiz-attempt-stats">
                        <li><span>Total Questions</span><strong><?= $totalQuestions ?></strong></li>
                        <li><span>Answered</span><strong id="quiz-attempt-answered-count">0</strong></li>
                        <li><span>Remaining</span><strong id="quiz-attempt-remaining-count"><?= $totalQuestions ?></strong></li>
                    </ul>
                    <div class="quiz-attempt-progress-track">
                        <span id="quiz-attempt-progress-bar" style="width: 0%;"></span>
                    </div>
                </div>
            </article>

            <article class="card quiz-attempt-side-card">
                <div class="card-body">
                    <h3><?= ui_lucide_icon('shield-alert') ?> Before Submit</h3>
                    <ul class="quiz-attempt-guidelines">
                        <li>Unanswered questions are marked wrong.</li>
                        <li>Attempt auto-submits when timer ends.</li>
                        <li>Best score remains on your record.</li>
                    </ul>
                </div>
            </article>

            <div class="quiz-attempt-actions">
                <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary" id="quiz-attempt-submit">Submit Answers</button>
            </div>
        </aside>
    </div>
</form>

<script>
(function () {
    const timerRoot = document.getElementById('quiz-attempt-timer');
    const label = document.getElementById('quiz-attempt-timer-label');
    const form = document.getElementById('quiz-attempt-form');
    const submitBtn = document.getElementById('quiz-attempt-submit');
    const answeredCount = document.getElementById('quiz-attempt-answered-count');
    const remainingCount = document.getElementById('quiz-attempt-remaining-count');
    const progressBar = document.getElementById('quiz-attempt-progress-bar');
    const totalQuestions = <?= $totalQuestions ?>;

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

    function updateAnswerProgress() {
        if (totalQuestions <= 0) return;

        const answerGroups = form.querySelectorAll('.quiz-attempt-options');
        let answered = 0;

        answerGroups.forEach((group) => {
            const checked = group.querySelector('input[type="radio"]:checked');
            if (checked) {
                answered += 1;
            }
        });

        const remaining = Math.max(0, totalQuestions - answered);
        const percent = Math.round((answered / totalQuestions) * 100);

        if (answeredCount) answeredCount.textContent = String(answered);
        if (remainingCount) remainingCount.textContent = String(remaining);
        if (progressBar) progressBar.style.width = percent + '%';
    }

    render();
    updateAnswerProgress();

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

    form.addEventListener('change', updateAnswerProgress);
})();
</script>
