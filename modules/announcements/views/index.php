<?php
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard/announcements');
$selectedBatchId = (int) ($selected_batch_id ?? 0);
$selectedSubjectId = (int) ($selected_subject_id ?? 0);
$selectedSearchQuery = (string) ($selected_search_query ?? '');
$selectedPage = max(1, (int) ($selected_page ?? 1));
$subjectOptions = (array) ($subject_options ?? []);
$items = (array) ($items ?? []);
$hasMoreItems = !empty($has_more_items);
$filteredCount = (int) ($filtered_count ?? count($items));
$todayCount = (int) ($today_count ?? 0);
$pinnedCount = (int) ($pinned_count ?? 0);
$activeBatch = (array) ($active_batch ?? []);
$canManage = !empty($can_manage);

$activeBatchCode = trim((string) ($activeBatch['batch_code'] ?? ''));
$activeBatchName = trim((string) ($activeBatch['name'] ?? ''));
$activeUniversityName = trim((string) ($activeBatch['university_name'] ?? ''));

$selectedSubjectLabel = 'All Subjects';
if ($selectedSubjectId > 0) {
    foreach ($subjectOptions as $subjectOption) {
        if ((int) ($subjectOption['id'] ?? 0) === $selectedSubjectId) {
            $selectedSubjectLabel = trim((string) ($subjectOption['code'] ?? ''));
            if ($selectedSubjectLabel === '') {
                $selectedSubjectLabel = trim((string) ($subjectOption['name'] ?? 'Filtered Subject'));
            }
            break;
        }
    }
}

$buildIndexUrl = static function (array $params = []) use ($is_admin, $selectedBatchId, $selectedSubjectId, $selectedSearchQuery): string {
    $query = [];

    if (!empty($is_admin) && $selectedBatchId > 0) {
        $query['batch_id'] = $selectedBatchId;
    }

    if ($selectedSubjectId > 0) {
        $query['subject_id'] = $selectedSubjectId;
    }

    if ($selectedSearchQuery !== '') {
        $query['q'] = $selectedSearchQuery;
    }

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }

        $query[$key] = $value;
    }

    return '/dashboard/announcements' . (!empty($query) ? '?' . http_build_query($query) : '');
};
?>

<?php if (!empty($is_admin) && $selectedBatchId <= 0): ?>
    <section class="community-admin-gate">
        <h3>Select Batch to Open Announcements</h3>
        <p class="text-muted">Choose a batch first to manage official announcements.</p>
        <form method="GET" action="/dashboard/announcements" class="community-topbar-form">
            <div class="form-group">
                <label for="batch_id">Batch</label>
                <select id="batch_id" name="batch_id" required>
                    <option value="">Select a batch</option>
                    <?php foreach ((array) ($batch_options ?? []) as $batch): ?>
                        <?php $batchId = (int) ($batch['id'] ?? 0); ?>
                        <option value="<?= $batchId ?>">
                            <?= e((string) ($batch['batch_code'] ?? 'BATCH')) ?> — <?= e((string) ($batch['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Open Announcements</button>
        </form>
    </section>
<?php else: ?>
    <div class="page-header">
        <div class="page-header-content">
            <p class="page-breadcrumb">Dashboard / Announcements</p>
            <h1>Official Announcements</h1>
            <p class="page-subtitle">
                Central noticeboard for official updates.
                <?php if ($activeBatchCode !== ''): ?>
                    <?= e($activeBatchCode) ?><?php if ($activeBatchName !== ''): ?> · <?= e($activeBatchName) ?><?php endif; ?>
                    <?php if ($activeUniversityName !== ''): ?> · <?= e($activeUniversityName) ?><?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="/dashboard/feed<?= !empty($is_admin) && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '' ?>" class="btn btn-outline"><?= ui_lucide_icon('newspaper') ?> Central Feed</a>
            <?php if ($canManage): ?>
                <a href="/dashboard/announcements/create<?= !empty($is_admin) && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '' ?>" class="btn btn-primary"><?= ui_lucide_icon('plus') ?> New Announcement</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="announcements-kpi-row">
        <article class="announcements-kpi-card">
            <span class="kpi-label">Visible Announcements</span>
            <strong><?= $filteredCount ?></strong>
            <p>Current result set for your filters.</p>
        </article>
        <article class="announcements-kpi-card">
            <span class="kpi-label">Posted Today</span>
            <strong><?= $todayCount ?></strong>
            <p>New official notices published today.</p>
        </article>
        <article class="announcements-kpi-card">
            <span class="kpi-label">Pinned</span>
            <strong><?= $pinnedCount ?></strong>
            <p>At most one pinned announcement per batch.</p>
        </article>
    </section>

    <section class="announcements-filter-card">
        <form method="GET" action="/dashboard/announcements" class="community-topbar-form">
            <?php if (!empty($is_admin) && $selectedBatchId > 0): ?>
                <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjectOptions as $subject): ?>
                        <?php $subjectId = (int) ($subject['id'] ?? 0); ?>
                        <option value="<?= $subjectId ?>" <?= $selectedSubjectId === $subjectId ? 'selected' : '' ?>>
                            <?= e((string) ($subject['code'] ?? 'SUB')) ?> — <?= e((string) ($subject['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="q">Search</label>
                <input type="search" id="q" name="q" value="<?= e($selectedSearchQuery) ?>" placeholder="Search title, body, subject, author">
            </div>

            <div class="community-topbar-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="<?= e($buildIndexUrl(['subject_id' => null, 'q' => null, 'page' => null])) ?>" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <div class="announcements-filter-meta">
            <span>Subject: <strong><?= e($selectedSubjectLabel) ?></strong></span>
            <span>Search: <strong><?= e($selectedSearchQuery !== '' ? $selectedSearchQuery : 'None') ?></strong></span>
        </div>
    </section>

    <?php if (empty($items)): ?>
        <article class="community-post-card community-empty-state">
            <h3>No announcements found</h3>
            <p class="text-muted">Try another filter or publish a new announcement.</p>
        </article>
    <?php else: ?>
        <section class="announcement-list">
            <?php foreach ($items as $announcement): ?>
                <?php
                $announcementId = (int) ($announcement['id'] ?? 0);
                $title = trim((string) ($announcement['title'] ?? 'Untitled Announcement'));
                $body = trim((string) ($announcement['body'] ?? ''));
                $bodyPreview = strlen($body) > 280 ? substr($body, 0, 277) . '...' : $body;
                $authorName = trim((string) ($announcement['author_name'] ?? ''));
                if ($authorName === '') {
                    $authorName = 'Unknown User';
                }
                $isPinned = (int) ($announcement['is_pinned'] ?? 0) === 1;
                $subjectCode = trim((string) ($announcement['subject_code'] ?? ''));
                $subjectName = trim((string) ($announcement['subject_name'] ?? ''));
                $detailUrl = '/dashboard/announcements/' . $announcementId . (!empty($is_admin) && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '');
                ?>
                <article class="announcement-card <?= $isPinned ? 'is-pinned' : '' ?>">
                    <header class="announcement-card-head">
                        <div>
                            <h3><a href="<?= e($detailUrl) ?>"><?= e($title) ?></a></h3>
                            <p class="announcement-card-meta">
                                <span><?= e(date('M d, Y • H:i', strtotime((string) ($announcement['created_at'] ?? 'now')))) ?></span>
                                <span>•</span>
                                <span><?= e($authorName) ?></span>
                                <?php if ($subjectCode !== '' || $subjectName !== ''): ?>
                                    <span>•</span>
                                    <span><?= e($subjectCode !== '' ? $subjectCode : $subjectName) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="announcement-card-badges">
                            <?php if ($isPinned): ?><span class="badge badge-warning">Pinned</span><?php endif; ?>
                            <?php if ($subjectCode !== ''): ?><span class="badge badge-info"><?= e($subjectCode) ?></span><?php endif; ?>
                        </div>
                    </header>

                    <p class="announcement-card-body"><?= nl2br(e($bodyPreview)) ?></p>

                    <footer class="announcement-card-actions">
                        <a href="<?= e($detailUrl) ?>" class="btn btn-sm btn-outline">Open</a>

                        <?php if ($canManage): ?>
                            <a href="/dashboard/announcements/<?= $announcementId ?>/edit<?= !empty($is_admin) && $selectedBatchId > 0 ? '?batch_id=' . $selectedBatchId : '' ?>" class="btn btn-sm btn-outline">Edit</a>
                            <form method="POST" action="/dashboard/announcements/<?= $announcementId ?>/<?= $isPinned ? 'unpin' : 'pin' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                <button type="submit" class="btn btn-sm btn-outline"><?= $isPinned ? 'Unpin' : 'Pin' ?></button>
                            </form>
                            <form method="POST" action="/dashboard/announcements/<?= $announcementId ?>/delete" onsubmit="return confirm('Delete this announcement?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Delete</button>
                            </form>
                        <?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($hasMoreItems): ?>
            <div class="community-load-more">
                <a href="<?= e($buildIndexUrl(['page' => $selectedPage + 1])) ?>" class="btn btn-outline">Load More</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
