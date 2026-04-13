<?php
$form = (array) ($form ?? []);
$formQuestions = (array) ($form['questions'] ?? []);
if (empty($formQuestions)) {
    $formQuestions = [[
        'text' => '',
        'options' => ['', '', '', ''],
        'correct_index' => -1,
    ]];
}

foreach ($formQuestions as $index => $question) {
    $options = array_values((array) ($question['options'] ?? []));
    if (count($options) < 4) {
        while (count($options) < 4) {
            $options[] = '';
        }
    }
    $formQuestions[$index]['options'] = $options;
}

$subjectName = trim((string) (($subject['code'] ?? '') . ' - ' . ($subject['name'] ?? '')));
if ($subjectName === '-') {
    $subjectName = (string) ($subject['name'] ?? 'Subject');
}

$selectedMode = (string) ($form['mode'] ?? 'practice');
if (!quizzes_mode_is_valid($selectedMode)) {
    $selectedMode = 'practice';
}

$draftLabel = (bool) ($is_coordinator_creator ?? false) ? 'Save & Publish' : 'Save Draft';
$submitLabel = (bool) ($is_coordinator_creator ?? false) ? 'Publish Quiz' : 'Submit For Review';
$formAction = (string) ($form_action ?? '');
$backUrl = (string) ($back_url ?? '/my-quizzes');
$breadcrumb = (string) ($breadcrumb ?? 'Quizzes');
$pageTitle = (string) ($page_title ?? 'Quiz Builder');
$pageSubtitle = (string) ($page_subtitle ?? 'Create a quiz.');
?>

<div class="page-header quiz-builder-header">
    <div class="page-header-content">
        <p class="page-breadcrumb"><?= e($breadcrumb) ?></p>
        <h1><?= e($pageTitle) ?></h1>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="<?= e($backUrl) ?>" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back</a>
    </div>
</div>

<form method="POST" action="<?= e($formAction) ?>" id="quiz-builder-form" class="quiz-builder-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="form_scope" value="quiz_builder">

    <section class="quiz-builder-main">
        <article class="card quiz-builder-card">
            <div class="card-body">
                <div class="quiz-builder-section-head">
                    <div>
                        <h2>Quiz Details</h2>
                        <p>Set basic info and prepare high-quality MCQs.</p>
                    </div>
                    <span class="quiz-builder-step-badge"><?= ui_lucide_icon('info') ?> Step 1</span>
                </div>

                <div class="quiz-builder-grid">
                    <div class="form-group quiz-builder-grid-span-2">
                        <label for="quiz-title">Quiz Title</label>
                        <input id="quiz-title" type="text" name="title" value="<?= e((string) ($form['title'] ?? '')) ?>" maxlength="200" required placeholder="e.g., Data Structures Mid-Term Practice">
                    </div>

                    <div class="form-group quiz-builder-grid-span-2">
                        <label for="quiz-description">Description (optional)</label>
                        <textarea id="quiz-description" name="description" rows="4" placeholder="What does this quiz cover?"><?= e((string) ($form['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <div class="quiz-builder-readonly"><?= ui_lucide_icon('book-open') ?> <?= e($subjectName) ?></div>
                    </div>

                    <div class="form-group">
                        <label for="quiz-duration">Duration (minutes)</label>
                        <input id="quiz-duration" type="number" name="duration_minutes" min="5" max="180" value="<?= (int) ($form['duration_minutes'] ?? 30) ?>" required>
                    </div>

                    <div class="form-group quiz-builder-grid-span-2">
                        <label>Quiz Mode</label>
                        <div class="quiz-builder-mode-grid" id="quiz-mode-grid">
                            <label class="quiz-builder-mode-option">
                                <input type="radio" name="mode" value="practice" <?= $selectedMode === 'practice' ? 'checked' : '' ?> required>
                                <span>
                                    <strong><?= ui_lucide_icon('circle-play') ?> Practice Mode</strong>
                                    <small>Immediate per-question checking with lock after check.</small>
                                </span>
                            </label>
                            <label class="quiz-builder-mode-option">
                                <input type="radio" name="mode" value="exam" <?= $selectedMode === 'exam' ? 'checked' : '' ?> required>
                                <span>
                                    <strong><?= ui_lucide_icon('shield-check') ?> Exam Mode</strong>
                                    <small>Feedback appears only on result screen after submit.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <div id="quiz-questions-shell" class="quiz-builder-questions-shell">
            <?php foreach ($formQuestions as $qIndex => $question): ?>
                <?php $correctIndex = (int) ($question['correct_index'] ?? -1); ?>
                <article class="card quiz-builder-question" data-question-index="<?= (int) $qIndex ?>">
                    <div class="card-body">
                        <div class="quiz-builder-question-head">
                            <h3>Question <span class="quiz-builder-question-number"><?= (int) $qIndex + 1 ?></span></h3>
                            <button type="button" class="btn btn-outline quiz-builder-remove-question" title="Remove question"><?= ui_lucide_icon('trash-2') ?> Remove</button>
                        </div>

                        <div class="form-group">
                            <label>Question Text</label>
                            <textarea name="questions[<?= (int) $qIndex ?>][text]" rows="3" required placeholder="Write clear, concise question text."><?= e((string) ($question['text'] ?? '')) ?></textarea>
                        </div>

                        <div class="quiz-builder-options" data-options-wrap>
                            <?php foreach ((array) ($question['options'] ?? []) as $oIndex => $optionText): ?>
                                <div class="quiz-builder-option" data-option-index="<?= (int) $oIndex ?>">
                                    <span class="quiz-builder-option-label"><?= e(chr(65 + (int) $oIndex)) ?></span>
                                    <input type="text" name="questions[<?= (int) $qIndex ?>][options][]" value="<?= e((string) $optionText) ?>" required placeholder="Enter option <?= e(chr(65 + (int) $oIndex)) ?>">
                                    <label class="quiz-builder-correct-mark" title="Mark as correct">
                                        <input type="radio" name="questions[<?= (int) $qIndex ?>][correct_index]" value="<?= (int) $oIndex ?>" <?= $correctIndex === (int) $oIndex ? 'checked' : '' ?> required>
                                        <span><?= ui_lucide_icon('check-circle-2') ?></span>
                                    </label>
                                    <button type="button" class="quiz-builder-remove-option" title="Remove option"><?= ui_lucide_icon('x') ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="btn btn-outline quiz-builder-add-option"><?= ui_lucide_icon('plus') ?> Add Option</button>

                        <p class="quiz-builder-helper"><?= ui_lucide_icon('info') ?> Each question needs 4-6 options and exactly one correct answer.</p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <button type="button" id="quiz-add-question" class="quiz-builder-add-question">
            <?= ui_lucide_icon('plus-circle') ?> Add New Question
        </button>
    </section>

    <aside class="quiz-builder-side">
        <article class="card quiz-builder-side-card">
            <div class="card-body">
                <h3><?= ui_lucide_icon('list-checks') ?> Quiz Progress</h3>
                <ul class="quiz-builder-progress-list">
                    <li><span>Title</span> <strong id="quiz-progress-title">-</strong></li>
                    <li><span>Subject</span> <strong><?= e($subjectName) ?></strong></li>
                    <li><span>Mode</span> <strong id="quiz-progress-mode">-</strong></li>
                    <li><span>Questions</span> <strong id="quiz-progress-count">0</strong></li>
                    <li><span>Status</span> <strong id="quiz-progress-status" class="quiz-progress-status">Incomplete</strong></li>
                </ul>
            </div>
        </article>

        <article class="card quiz-builder-side-card quiz-builder-tips-card">
            <div class="card-body">
                <h3><?= ui_lucide_icon('lightbulb') ?> Quick Tips</h3>
                <ul>
                    <li>Write clear and concise questions.</li>
                    <li>Add 4 to 6 meaningful options.</li>
                    <li>Mark exactly one correct option.</li>
                    <li>Use practice mode for guided learning and exam mode for assessments.</li>
                </ul>
            </div>
        </article>

        <div class="quiz-builder-actions">
            <button type="submit" class="btn btn-outline" name="intent" value="draft"><?= ui_lucide_icon('save') ?> <?= e($draftLabel) ?></button>
            <button type="submit" class="btn btn-primary" name="intent" value="submit"><?= ui_lucide_icon('send') ?> <?= e($submitLabel) ?></button>
        </div>
    </aside>
</form>

<script>
(function () {
    const form = document.getElementById('quiz-builder-form');
    if (!form) return;

    const questionShell = document.getElementById('quiz-questions-shell');
    const addQuestionBtn = document.getElementById('quiz-add-question');
    const titleInput = document.getElementById('quiz-title');

    const progressTitle = document.getElementById('quiz-progress-title');
    const progressMode = document.getElementById('quiz-progress-mode');
    const progressCount = document.getElementById('quiz-progress-count');
    const progressStatus = document.getElementById('quiz-progress-status');

    function optionLabel(index) {
        return String.fromCharCode(65 + index);
    }

    function buildOptionNode(questionIndex, optionIndex, value, checked) {
        const row = document.createElement('div');
        row.className = 'quiz-builder-option';
        row.setAttribute('data-option-index', String(optionIndex));

        row.innerHTML = `
            <span class="quiz-builder-option-label">${optionLabel(optionIndex)}</span>
            <input type="text" name="questions[${questionIndex}][options][]" required placeholder="Enter option ${optionLabel(optionIndex)}">
            <label class="quiz-builder-correct-mark" title="Mark as correct">
                <input type="radio" name="questions[${questionIndex}][correct_index]" value="${optionIndex}" required>
                <span><i data-lucide="check-circle-2" class="ui-lucide-icon" aria-hidden="true"></i></span>
            </label>
            <button type="button" class="quiz-builder-remove-option" title="Remove option"><i data-lucide="x" class="ui-lucide-icon" aria-hidden="true"></i></button>
        `;

        const input = row.querySelector('input[type="text"]');
        if (input) input.value = value || '';

        const radio = row.querySelector('input[type="radio"]');
        if (radio && checked) radio.checked = true;

        return row;
    }

    function buildQuestionNode(index) {
        const card = document.createElement('article');
        card.className = 'card quiz-builder-question';
        card.setAttribute('data-question-index', String(index));

        card.innerHTML = `
            <div class="card-body">
                <div class="quiz-builder-question-head">
                    <h3>Question <span class="quiz-builder-question-number">${index + 1}</span></h3>
                    <button type="button" class="btn btn-outline quiz-builder-remove-question" title="Remove question">
                        <i data-lucide="trash-2" class="ui-lucide-icon" aria-hidden="true"></i> Remove
                    </button>
                </div>
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="questions[${index}][text]" rows="3" required placeholder="Write clear, concise question text."></textarea>
                </div>
                <div class="quiz-builder-options" data-options-wrap></div>
                <button type="button" class="btn btn-outline quiz-builder-add-option"><i data-lucide="plus" class="ui-lucide-icon" aria-hidden="true"></i> Add Option</button>
                <p class="quiz-builder-helper"><i data-lucide="info" class="ui-lucide-icon" aria-hidden="true"></i> Each question needs 4-6 options and exactly one correct answer.</p>
            </div>
        `;

        const optionsWrap = card.querySelector('[data-options-wrap]');
        for (let i = 0; i < 4; i += 1) {
            optionsWrap.appendChild(buildOptionNode(index, i, '', false));
        }

        return card;
    }

    function syncQuestionNames() {
        const cards = Array.from(questionShell.querySelectorAll('.quiz-builder-question'));

        cards.forEach((card, qIndex) => {
            card.setAttribute('data-question-index', String(qIndex));
            const numberNode = card.querySelector('.quiz-builder-question-number');
            if (numberNode) numberNode.textContent = String(qIndex + 1);

            const questionText = card.querySelector('textarea');
            if (questionText) {
                questionText.name = `questions[${qIndex}][text]`;
            }

            const options = Array.from(card.querySelectorAll('.quiz-builder-option'));
            options.forEach((optionNode, oIndex) => {
                optionNode.setAttribute('data-option-index', String(oIndex));

                const label = optionNode.querySelector('.quiz-builder-option-label');
                if (label) label.textContent = optionLabel(oIndex);

                const optionInput = optionNode.querySelector('input[type="text"]');
                if (optionInput) {
                    optionInput.name = `questions[${qIndex}][options][]`;
                    optionInput.placeholder = `Enter option ${optionLabel(oIndex)}`;
                }

                const radio = optionNode.querySelector('input[type="radio"]');
                if (radio) {
                    radio.name = `questions[${qIndex}][correct_index]`;
                    radio.value = String(oIndex);
                }
            });

            const removeOptionButtons = Array.from(card.querySelectorAll('.quiz-builder-remove-option'));
            removeOptionButtons.forEach((btn) => {
                btn.disabled = options.length <= 4;
            });

            const addOptionBtn = card.querySelector('.quiz-builder-add-option');
            if (addOptionBtn) {
                addOptionBtn.disabled = options.length >= 6;
            }
        });

        const removeQuestionButtons = Array.from(questionShell.querySelectorAll('.quiz-builder-remove-question'));
        removeQuestionButtons.forEach((btn) => {
            btn.disabled = cards.length <= 1;
        });

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function selectedMode() {
        const checked = form.querySelector('input[name="mode"]:checked');
        return checked ? String(checked.value || '') : '';
    }

    function modeLabel(mode) {
        return mode === 'practice' ? 'Practice' : mode === 'exam' ? 'Exam' : '-';
    }

    function updateProgress() {
        const cards = Array.from(questionShell.querySelectorAll('.quiz-builder-question'));
        const titleText = (titleInput ? titleInput.value.trim() : '');
        const mode = selectedMode();

        if (progressTitle) {
            progressTitle.textContent = titleText === '' ? '-' : titleText;
        }

        if (progressMode) {
            progressMode.textContent = modeLabel(mode);
        }

        if (progressCount) {
            progressCount.textContent = String(cards.length);
        }

        let complete = titleText !== '' && (mode === 'practice' || mode === 'exam');

        cards.forEach((card) => {
            const questionText = card.querySelector('textarea');
            if (!questionText || questionText.value.trim() === '') {
                complete = false;
            }

            const options = Array.from(card.querySelectorAll('.quiz-builder-option input[type="text"]'));
            if (options.length < 4 || options.length > 6) {
                complete = false;
            }

            options.forEach((optionInput) => {
                if (optionInput.value.trim() === '') {
                    complete = false;
                }
            });

            const checked = card.querySelector('.quiz-builder-option input[type="radio"]:checked');
            if (!checked) {
                complete = false;
            }
        });

        if (progressStatus) {
            progressStatus.textContent = complete ? 'Ready' : 'Incomplete';
            progressStatus.classList.toggle('is-ready', complete);
        }
    }

    if (addQuestionBtn) {
        addQuestionBtn.addEventListener('click', function () {
            const nextIndex = questionShell.querySelectorAll('.quiz-builder-question').length;
            questionShell.appendChild(buildQuestionNode(nextIndex));
            syncQuestionNames();
            updateProgress();
        });
    }

    questionShell.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const removeQuestionBtn = target.closest('.quiz-builder-remove-question');
        if (removeQuestionBtn) {
            const cards = questionShell.querySelectorAll('.quiz-builder-question');
            if (cards.length <= 1) {
                return;
            }

            const card = removeQuestionBtn.closest('.quiz-builder-question');
            if (card) {
                card.remove();
                syncQuestionNames();
                updateProgress();
            }
            return;
        }

        const addOptionBtn = target.closest('.quiz-builder-add-option');
        if (addOptionBtn) {
            const card = addOptionBtn.closest('.quiz-builder-question');
            if (!card) return;

            const optionsWrap = card.querySelector('[data-options-wrap]');
            if (!optionsWrap) return;

            const qIndex = Number(card.getAttribute('data-question-index') || '0');
            const options = optionsWrap.querySelectorAll('.quiz-builder-option');
            if (options.length >= 6) {
                return;
            }

            optionsWrap.appendChild(buildOptionNode(qIndex, options.length, '', false));
            syncQuestionNames();
            updateProgress();
            return;
        }

        const removeOptionBtn = target.closest('.quiz-builder-remove-option');
        if (removeOptionBtn) {
            const card = removeOptionBtn.closest('.quiz-builder-question');
            if (!card) return;

            const optionsWrap = card.querySelector('[data-options-wrap]');
            if (!optionsWrap) return;

            const options = optionsWrap.querySelectorAll('.quiz-builder-option');
            if (options.length <= 4) {
                return;
            }

            const optionNode = removeOptionBtn.closest('.quiz-builder-option');
            if (!optionNode) return;

            const removedRadio = optionNode.querySelector('input[type="radio"]');
            const wasChecked = removedRadio ? removedRadio.checked : false;
            optionNode.remove();

            if (wasChecked) {
                const firstRadio = card.querySelector('.quiz-builder-option input[type="radio"]');
                if (firstRadio) firstRadio.checked = true;
            }

            syncQuestionNames();
            updateProgress();
        }
    });

    questionShell.addEventListener('input', updateProgress);
    questionShell.addEventListener('change', updateProgress);
    form.addEventListener('change', function (event) {
        const target = event.target;
        if (target instanceof Element && target.matches('input[name="mode"]')) {
            updateProgress();
        }
    });
    if (titleInput) {
        titleInput.addEventListener('input', updateProgress);
    }

    form.addEventListener('submit', function () {
        syncQuestionNames();
        updateProgress();
    });

    syncQuestionNames();
    updateProgress();
})();
</script>
