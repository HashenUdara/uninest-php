<div class="page-header page-header--compact">
    <div class="page-header-content">
        <p class="page-breadcrumb">Student / Subjects</p>
        <h1>My Subjects</h1>
        <p class="page-subtitle">Explore the subjects available in your approved batch.</p>
    </div>
    <div class="page-header-actions">
        <a href="/dashboard" class="btn btn-outline">← Back to Dashboard</a>
    </div>
</div>

<?php
$activeYear = (int) ($active_term['academic_year'] ?? 0);
$activeSemester = (int) ($active_term['semester'] ?? 0);
$activeStatus = (string) ($filters['status'] ?? '');
$activeQuery = (string) ($filters['q'] ?? '');

$buildTermUrl = static function (int $year, int $semester) use ($activeStatus, $activeQuery): string {
    return '/dashboard/subjects?' . http_build_query([
        'year' => $year,
        'semester' => $semester,
        'status' => $activeStatus,
        'q' => $activeQuery,
    ]);
};
?>

<?php if (!empty($term_tabs)): ?>
    <div class="subjects-tabs">
        <?php foreach ($term_tabs as $term): ?>
            <?php
            $tabYear = (int) $term['academic_year'];
            $tabSemester = (int) $term['semester'];
            $isActiveTab = $tabYear === $activeYear && $tabSemester === $activeSemester;
            ?>
            <a href="<?= e($buildTermUrl($tabYear, $tabSemester)) ?>" class="subjects-tab <?= $isActiveTab ? 'is-active' : '' ?>">
                Y<?= $tabYear ?> · S<?= $tabSemester ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="/dashboard/subjects" class="subjects-filter-form">
            <input type="hidden" name="year" value="<?= $activeYear ?>">
            <input type="hidden" name="semester" value="<?= $activeSemester ?>">

            <div class="subjects-filter-grid subjects-filter-grid--compact">
                <div class="form-group">
                    <select id="status" name="status" aria-label="Status">
                        <option value="">Status</option>
                        <?php foreach ($status_options as $status): ?>
                            <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                                <?= e(subjects_status_label($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <input type="text" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search by code or name" aria-label="Search subjects">
                </div>
            </div>

            <div class="subjects-filter-footer">
                <div class="subjects-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="/dashboard/subjects?<?= http_build_query(['year' => $activeYear, 'semester' => $activeSemester]) ?>" class="btn btn-outline">Reset</a>
                </div>
                <p class="text-muted subjects-filter-summary">
                    Showing <?= count($subjects) ?> of <?= (int) $total_subjects ?> subjects
                    <?php if ($activeYear > 0 && $activeSemester > 0): ?>
                        in Y<?= $activeYear ?> / S<?= $activeSemester ?>.
                    <?php else: ?>
                        .
                    <?php endif; ?>
                </p>
            </div>
        </form>
    </div>
</div>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">No subjects found for the selected filters.</p>
        </div>
    </div>
<?php else: ?>
    <div class="subjects-grid">
        <?php foreach ($subjects as $subject): ?>
            <?php
            $status = (string) ($subject['status'] ?? 'upcoming');
            $statusClass = match ($status) {
                'in_progress' => 'badge-info',
                'completed' => 'badge-warning',
                default => '',
            };
            $thumbnailTone = ui_avatar_tone_class((string) (($subject['code'] ?? '') . '-' . ($subject['name'] ?? '')));
            ?>
            <a href="/dashboard/subjects/<?= (int) $subject['id'] ?>/topics" class="subject-card-link" aria-label="Open topics for <?= e($subject['name']) ?>">
                <article class="subject-card subject-card--thumb">
                    <div class="subject-card-thumb <?= e($thumbnailTone) ?>">
                        <span class="subject-card-thumb-code"><?= e($subject['code']) ?></span>
                    </div>
                    <div class="subject-card-content">
                        <div class="subject-meta">
                            <span class="badge <?= e($statusClass) ?>"><?= e(subjects_status_label($status)) ?></span>
                            <span class="badge"><?= (int) $subject['credits'] ?> Credits</span>
                        </div>
                        <h3><?= e($subject['code']) ?> - <?= e($subject['name']) ?></h3>
                        <p class="subject-card-term">Academic year <?= (int) ($subject['academic_year'] ?? 1) ?> · Semester <?= (int) ($subject['semester'] ?? 1) ?></p>
                        <?php if (!empty($subject['description'])): ?>
                            <p class="subject-card-description"><?= e($subject['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (($total_pages ?? 1) > 1): ?>
    <?php
    $paginationQuery = [
        'year' => (string) ($filters['year'] ?? ''),
        'semester' => (string) ($filters['semester'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'q' => (string) ($filters['q'] ?? ''),
    ];
    $buildPageUrl = static function (int $targetPage) use ($paginationQuery): string {
        $params = $paginationQuery;
        $params['page'] = max(1, $targetPage);
        return '/dashboard/subjects?' . http_build_query($params);
    };
    ?>
    <div class="subjects-pagination">
        <?php if (($page ?? 1) > 1): ?>
            <a href="<?= e($buildPageUrl(((int) $page) - 1)) ?>" class="btn btn-outline">← Previous</a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>

        <span class="subjects-pagination-meta">
            Page <?= (int) $page ?> of <?= (int) $total_pages ?>
        </span>

        <?php if ((int) $page < (int) $total_pages): ?>
            <a href="<?= e($buildPageUrl(((int) $page) + 1)) ?>" class="btn btn-outline">Next →</a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
