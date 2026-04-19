<?php
$searchQuery = (string) ($query ?? '');
$selectedType = (string) ($selected_type ?? 'all');
$selectedSubjectId = (int) ($selected_subject_id ?? 0);
$selectedBatchId = (int) ($selected_batch_id ?? 0);
$items = (array) ($items ?? []);
$counts = (array) ($counts ?? []);
$typeOptions = (array) ($type_options ?? []);
$subjectOptions = (array) ($subject_options ?? []);
$batchOptions = (array) ($batch_options ?? []);
$isAdmin = (bool) ($is_admin ?? false);
$hasSearchQuery = (bool) ($has_search_query ?? false);
$minLength = (int) ($min_query_length ?? 2);

$totalResults = (int) ($counts['all'] ?? count($items));
?>

<div class="page-header page-header--compact">
    <div class="page-header-content">
        <p class="page-breadcrumb">Workspace / Universal Search</p>
        <h1>Universal Search</h1>
        <p class="page-subtitle">Find subjects, resources, quizzes, and Kuppi activity from one place.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard" class="btn btn-outline"><?= ui_lucide_icon('arrow-left') ?> Back to Dashboard</a>
    </div>
</div>

<div class="card dashboard-search-page">
    <div class="card-body">
        <form method="GET" action="/dashboard/search" class="dashboard-search-page-form">
            <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label for="batch_id">Batch</label>
                    <select id="batch_id" name="batch_id">
                        <option value="0">Select batch</option>
                        <?php foreach ($batchOptions as $batch): ?>
                            <option value="<?= (int) ($batch['id'] ?? 0) ?>" <?= (int) ($batch['id'] ?? 0) === $selectedBatchId ? 'selected' : '' ?>>
                                <?= e((string) ($batch['batch_code'] ?? '')) ?> · <?= e((string) ($batch['name'] ?? 'Batch')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <?php foreach ($typeOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>" <?= $selectedType === (string) $value ? 'selected' : '' ?>>
                            <?= e((string) $label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id">
                    <option value="0">All subjects</option>
                    <?php foreach ($subjectOptions as $subject): ?>
                        <option value="<?= (int) ($subject['id'] ?? 0) ?>" <?= (int) ($subject['id'] ?? 0) === $selectedSubjectId ? 'selected' : '' ?>>
                            <?= e((string) ($subject['code'] ?? '')) ?> · <?= e((string) ($subject['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group dashboard-search-page-query">
                <label for="q">Search</label>
                <input type="search" id="q" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by title, description, code, subject..." minlength="<?= $minLength ?>">
            </div>

            <div class="dashboard-search-page-actions">
                <button type="submit" class="btn btn-primary"><?= ui_lucide_icon('search') ?> Search</button>
                <a href="/dashboard/search<?= $isAdmin && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '' ?>" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($isAdmin && $selectedBatchId <= 0): ?>
    <div class="alert alert-warning">Select a batch to search scoped content.</div>
<?php elseif (!$hasSearchQuery): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">Enter at least <?= $minLength ?> characters to search.</p>
        </div>
    </div>
<?php elseif (empty($items)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No matching results found. Try a broader term or change filters.</p>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-search-summary">
        <p class="text-muted">Showing <?= $totalResults ?> results for “<?= e($searchQuery) ?>”.</p>
    </div>

    <div class="dashboard-search-results">
        <?php foreach ($items as $item): ?>
            <?php
            $itemType = (string) ($item['item_type'] ?? '');
            $badgeClass = match ($itemType) {
                'resource' => 'badge-info',
                'quiz' => 'badge-warning',
                'kuppi_request' => 'badge-info',
                'kuppi_scheduled' => 'badge-warning',
                default => '',
            };
            ?>
            <a href="<?= e((string) ($item['target_url'] ?? '/dashboard/search')) ?>" class="dashboard-search-card">
                <div class="dashboard-search-card-icon" aria-hidden="true">
                    <?= ui_lucide_icon((string) ($item['item_type_icon'] ?? 'search')) ?>
                </div>
                <div class="dashboard-search-card-content">
                    <div class="dashboard-search-card-top">
                        <span class="badge <?= e($badgeClass) ?>"><?= e((string) ($item['item_type_label'] ?? 'Item')) ?></span>
                        <?php if (!empty($item['subject_code'])): ?>
                            <span class="dashboard-search-card-subject"><?= e((string) $item['subject_code']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['event_label'])): ?>
                            <span class="dashboard-search-card-time"><?= e((string) $item['event_label']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h3><?= e((string) ($item['title'] ?? 'Untitled')) ?></h3>
                    <?php if (!empty($item['summary'])): ?>
                        <p><?= e((string) $item['summary']) ?></p>
                    <?php endif; ?>
                </div>
                <span class="dashboard-search-card-cta"><?= ui_lucide_icon('arrow-up-right') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

