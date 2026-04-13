<?php
$subjectId = (int) ($subject['id'] ?? 0);
$quizId = (int) ($quiz['id'] ?? 0);
$attemptId = (int) ($attempt['id'] ?? 0);
$questions = (array) ($questions ?? []);
$selectedAnswers = (array) ($selected_answers ?? []);
$checkedAnswers = (array) ($checked_answers ?? []);
$secondsRemaining = max(0, (int) ($seconds_remaining ?? 0));
$totalQuestions = count($questions);
$quizMode = (string) ($quiz['mode'] ?? 'exam');
$isPracticeMode = $quizMode === 'practice';
$checkedCount = $isPracticeMode ? count($checkedAnswers) : 0;
?>

<div class="page-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e((string) ($role_label ?? 'Student')) ?> / Quiz Attempt</p>
        <h1><?= e((string) ($quiz['title'] ?? 'Quiz')) ?></h1>
        <p class="page-subtitle">
            <?= $isPracticeMode
                ? 'Practice mode: check each question and lock it before final submit.'
                : 'Exam mode: answer all questions before the timer ends.' ?>
        </p>
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
                    <?php
                    $questionId = (int) ($question['id'] ?? 0);
                    $checkedRow = $isPracticeMode && isset($checkedAnswers[$questionId]) ? (array) $checkedAnswers[$questionId] : null;
                    $isLocked = $checkedRow !== null;
                    $isCorrectLocked = $isLocked && !empty($checkedRow['is_correct']);

                    $selectedOptionId = isset($selectedAnswers[$questionId]) ? (int) $selectedAnswers[$questionId] : 0;
                    if ($isLocked && isset($checkedRow['selected_option_id'])) {
                        $selectedOptionId = (int) $checkedRow['selected_option_id'];
                    }
                    ?>
                    <article
                        class="card quiz-attempt-question<?= $isLocked ? ' is-checked' : '' ?><?= $isLocked && $isCorrectLocked ? ' is-correct' : '' ?><?= $isLocked && !$isCorrectLocked ? ' is-incorrect' : '' ?>"
                        id="quiz-question-<?= $questionId ?>"
                        data-locked="<?= $isLocked ? '1' : '0' ?>">
                        <div class="card-body">
                            <div class="quiz-attempt-question-head">
                                <span class="quiz-attempt-question-no">Question <?= (int) $index + 1 ?> / <?= $totalQuestions ?></span>
                                <h3><?= e((string) ($question['question_text'] ?? '')) ?></h3>
                            </div>

                            <?php if ($isLocked): ?>
                                <div class="quiz-attempt-question-status <?= $isCorrectLocked ? 'is-correct' : 'is-incorrect' ?>">
                                    <?= $isCorrectLocked ? ui_lucide_icon('check-circle-2') : ui_lucide_icon('x-circle') ?>
                                    <?= $isCorrectLocked
                                        ? 'Correct. This question is locked for this attempt.'
                                        : 'Checked. This question is locked for this attempt.' ?>
                                </div>
                            <?php elseif ($isPracticeMode): ?>
                                <div class="quiz-attempt-question-status is-pending">
                                    <?= ui_lucide_icon('info') ?> Select an option and click "Check & Lock".
                                </div>
                            <?php endif; ?>

                            <div class="quiz-attempt-options">
                                <?php foreach ((array) ($question['options'] ?? []) as $optionIndex => $option): ?>
                                    <?php $optionId = (int) ($option['id'] ?? 0); ?>
                                    <label class="quiz-attempt-option<?= $selectedOptionId === $optionId ? ' is-selected' : '' ?><?= $isLocked ? ' is-locked' : '' ?>">
                                        <input
                                            type="radio"
                                            name="answers[<?= $questionId ?>]"
                                            value="<?= $optionId ?>"
                                            <?= $selectedOptionId === $optionId ? 'checked' : '' ?>
                                            <?= $isLocked ? 'disabled' : '' ?>>
                                        <span class="quiz-attempt-option-letter"><?= e(chr(65 + (int) $optionIndex)) ?></span>
                                        <span class="quiz-attempt-option-text"><?= e((string) ($option['option_text'] ?? '')) ?></span>
                                        <?php if ($isLocked && $selectedOptionId === $optionId): ?>
                                            <span class="quiz-attempt-locked-mark <?= $isCorrectLocked ? 'is-correct' : 'is-incorrect' ?>">
                                                <?= $isCorrectLocked ? ui_lucide_icon('check') : ui_lucide_icon('x') ?>
                                            </span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($isPracticeMode && !$isLocked): ?>
                                <div class="quiz-attempt-question-actions">
                                    <button
                                        type="submit"
                                        class="btn btn-outline quiz-attempt-check-btn"
                                        data-question-id="<?= $questionId ?>"
                                        formaction="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>/attempts/<?= $attemptId ?>/questions/<?= $questionId ?>/check">
                                        <?= ui_lucide_icon('shield-check') ?> Check & Lock
                                    </button>
                                </div>
                            <?php endif; ?>
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
                        <li><span>Mode</span><strong><?= e(quizzes_mode_label($quizMode)) ?></strong></li>
                        <li><span>Total Questions</span><strong><?= $totalQuestions ?></strong></li>
                        <?php if ($isPracticeMode): ?>
                            <li><span>Checked & Locked</span><strong id="quiz-attempt-checked-count"><?= $checkedCount ?></strong></li>
                        <?php endif; ?>
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
                        <?php if ($isPracticeMode): ?>
                            <li>Use Check & Lock on each question to validate immediately.</li>
                            <li>Checked questions cannot be changed in this attempt.</li>
                        <?php else: ?>
                            <li>Correctness is shown only after submission.</li>
                        <?php endif; ?>
                        <li>Unanswered questions are marked wrong.</li>
                        <li>Attempt auto-submits when timer ends.</li>
                        <li>Best score remains on your record.</li>
                    </ul>
                </div>
            </article>

            <div class="quiz-attempt-actions">
                <a href="/dashboard/subjects/<?= $subjectId ?>/quizzes/<?= $quizId ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary" id="quiz-attempt-submit">Submit Attempt</button>
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
    const checkButtons = Array.from(form ? form.querySelectorAll('.quiz-attempt-check-btn') : []);
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

        const questionCards = form.querySelectorAll('.quiz-attempt-question');
        let answered = 0;

        questionCards.forEach((card) => {
            const checked = card.querySelector('input[type="radio"]:checked');
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

    function updateCheckButtons() {
        checkButtons.forEach((button) => {
            const questionCard = button.closest('.quiz-attempt-question');
            if (!questionCard) {
                return;
            }

            const selected = questionCard.querySelector('input[type="radio"]:checked');
            button.disabled = !selected;
        });
    }

    render();
    updateAnswerProgress();
    updateCheckButtons();

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
        checkButtons.forEach((button) => {
            button.disabled = true;
        });
    });

    form.addEventListener('change', function () {
        updateAnswerProgress();
        updateCheckButtons();
    });
})();
</script>
