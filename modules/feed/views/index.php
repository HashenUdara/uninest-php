<?php
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard/feed');
$selectedBatchId = (int) ($selected_batch_id ?? 0);
$selectedSubjectId = (int) ($selected_subject_id ?? 0);
$selectedType = (string) ($selected_type ?? 'all');
$selectedSearchQuery = (string) ($selected_search_query ?? '');
$selectedPage = max(1, (int) ($selected_page ?? 1));
$subjectOptions = (array) ($subject_options ?? []);
$typeOptions = (array) ($type_options ?? []);
$typeCounts = (array) ($type_counts ?? []);
$items = (array) ($items ?? []);
$hasMoreItems = !empty($has_more_items);
$todayCount = (int) ($today_count ?? 0);
$selectedTypeCount = (int) ($selected_type_count ?? 0);
$selectedSubjectLabel = (string) ($selected_subject_label ?? 'All Subjects');
$activeBatch = (array) ($active_batch ?? []);
$allCount = (int) ($typeCounts['_all'] ?? 0);

$activeBatchCode = trim((string) ($activeBatch['batch_code'] ?? ''));
$activeBatchName = trim((string) ($activeBatch['name'] ?? ''));
$activeUniversityName = trim((string) ($activeBatch['university_name'] ?? ''));
$selectedTypeLabel = (string) ($typeOptions[$selectedType] ?? 'All');

$buildFeedUrl = static function (array $params = []) use ($is_admin, $selectedBatchId, $selectedType, $selectedSubjectId, $selectedSearchQuery): string {
    $query = [];

    if (!empty($is_admin) && $selectedBatchId > 0) {
        $query['batch_id'] = $selectedBatchId;
    }

    if ($selectedType !== 'all') {
        $query['type'] = $selectedType;
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

    return '/dashboard/feed' . (!empty($query) ? '?' . http_build_query($query) : '');
};
?>

<?php if (!empty($is_admin) && $selectedBatchId <= 0): ?>
    <section class="community-admin-gate">
        <h3>Select Batch to Open Central Feed</h3>
        <p class="text-muted">Choose an approved batch first to view unified activity.</p>
        <form method="GET" action="/dashboard/feed" class="community-topbar-form">
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
            <button type="submit" class="btn btn-primary">Open Feed</button>
        </form>
    </section>
<?php else: ?>
    <section class="feed-social-hero">
        <div class="feed-social-hero-copy">
            <p class="feed-social-eyebrow">Central Feed</p>
            <h1>What’s New in Your Learning Community</h1>
            <p>
                Live stream of official announcements, community posts, published resources, approved quizzes, and Kuppi activity.
                <?php if ($activeBatchCode !== ''): ?>
                    <span class="feed-hero-batch"><?= e($activeBatchCode) ?></span>
                    <?php if ($activeBatchName !== ''): ?>· <?= e($activeBatchName) ?><?php endif; ?>
                    <?php if ($activeUniversityName !== ''): ?>· <?= e($activeUniversityName) ?><?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="feed-social-hero-meta">
            <div>
                <strong><?= $selectedTypeCount ?></strong>
                <small><?= e($selectedTypeLabel) ?></small>
            </div>
            <div>
                <strong><?= $todayCount ?></strong>
                <small>Today</small>
            </div>
            <div>
                <strong><?= $allCount ?></strong>
                <small>Total</small>
            </div>
        </div>
    </section>

    <section class="feed-social-shell">
        <main class="feed-social-main">
            <article class="feed-filter-composer">
                <form method="GET" action="/dashboard/feed" class="feed-filter-grid">
                    <?php if (!empty($is_admin)): ?>
                        <div class="form-group">
                            <label for="batch_id">Batch</label>
                            <select id="batch_id" name="batch_id" required>
                                <?php foreach ((array) ($batch_options ?? []) as $batch): ?>
                                    <?php $batchId = (int) ($batch['id'] ?? 0); ?>
                                    <option value="<?= $batchId ?>" <?= $selectedBatchId === $batchId ? 'selected' : '' ?>>
                                        <?= e((string) ($batch['batch_code'] ?? 'BATCH')) ?> — <?= e((string) ($batch['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

                    <div class="form-group feed-search-group">
                        <label for="q">Search Stream</label>
                        <input type="search" id="q" name="q" value="<?= e($selectedSearchQuery) ?>" placeholder="Search title, author, subject, summary">
                    </div>

                    <?php if ($selectedType !== 'all'): ?>
                        <input type="hidden" name="type" value="<?= e($selectedType) ?>">
                    <?php endif; ?>

                    <div class="feed-filter-actions">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="<?= e($buildFeedUrl(['subject_id' => null, 'q' => null, 'page' => null])) ?>" class="btn btn-outline">Reset</a>
                    </div>
                </form>

                <nav class="feed-type-strip" aria-label="Feed type filters">
                    <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                        <?php
                        $isActive = $selectedType === $typeKey;
                        $count = $typeKey === 'all'
                            ? (int) ($typeCounts['_all'] ?? 0)
                            : (int) ($typeCounts[$typeKey] ?? 0);
                        ?>
                        <a href="<?= e($buildFeedUrl(['type' => $typeKey === 'all' ? null : $typeKey, 'page' => null])) ?>" class="feed-type-pill <?= $isActive ? 'is-active' : '' ?>">
                            <span><?= e($typeLabel) ?></span>
                            <strong><?= $count ?></strong>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </article>

            <?php if (empty($items)): ?>
                <article class="community-post-card community-empty-state">
                    <h3>No activity items found</h3>
                    <p class="text-muted">Try another type, subject, or search query.</p>
                </article>
            <?php else: ?>
                <section class="feed-stream">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $itemType = (string) ($item['item_type'] ?? '');
                        $itemTypeLabel = (string) ($item['item_type_label'] ?? 'Feed Item');
                        $itemTypeBadgeClass = trim((string) ($item['item_type_badge_class'] ?? ''));
                        $itemId = (int) ($item['item_id'] ?? 0);
                        $subjectCode = trim((string) ($item['subject_code'] ?? ''));
                        $subjectName = trim((string) ($item['subject_name'] ?? ''));
                        $actorName = trim((string) ($item['actor_name'] ?? ''));
                        if ($actorName === '') {
                            $actorName = 'Unknown User';
                        }
                        $actorUserId = (int) ($item['actor_user_id'] ?? 0);
                        $actorToneClass = ui_avatar_tone_class($actorName . ':' . $actorUserId);

                        $title = trim((string) ($item['title'] ?? 'Untitled'));
                        $summary = trim((string) ($item['summary'] ?? ''));
                        $targetUrl = (string) ($item['target_url'] ?? '/dashboard/feed');
                        $eventLabel = (string) ($item['event_label'] ?? 'just now');
                        $eventAtDisplay = (string) ($item['event_at_display'] ?? '');

                        $quizMode = trim((string) ($item['quiz_mode'] ?? ''));
                        $resourceCategory = trim((string) ($item['resource_category'] ?? ''));
                        $resourceSourceType = trim((string) ($item['resource_source_type'] ?? ''));
                        $communityPostType = trim((string) ($item['community_post_type'] ?? ''));
                        $scheduledSessionDate = trim((string) ($item['scheduled_session_date'] ?? ''));
                        $scheduledStartTime = trim((string) ($item['scheduled_start_time'] ?? ''));
                        $scheduledEndTime = trim((string) ($item['scheduled_end_time'] ?? ''));
                        $scheduledDateDisplay = $scheduledSessionDate !== '' ? date('D, M d Y', strtotime($scheduledSessionDate)) : 'Date TBD';
                        $scheduledTimeDisplay = ($scheduledStartTime !== '' && $scheduledEndTime !== '')
                            ? (substr($scheduledStartTime, 0, 5) . ' - ' . substr($scheduledEndTime, 0, 5))
                            : 'Time TBD';

                        $communityLikeCount = (int) ($item['community_like_count'] ?? 0);
                        $communityCommentCount = (int) ($item['community_comment_count'] ?? 0);
                        $communityIsLiked = (int) ($item['community_is_liked_by_viewer'] ?? 0) === 1;
                        $communityIsSaved = (int) ($item['community_is_saved_by_viewer'] ?? 0) === 1;
                        $communityHasImage = (int) ($item['community_has_image'] ?? 0) === 1;

                        $kuppiVoteScore = (int) ($item['kuppi_vote_score'] ?? 0);
                        $kuppiUpvoteCount = (int) ($item['kuppi_upvote_count'] ?? 0);
                        $kuppiDownvoteCount = (int) ($item['kuppi_downvote_count'] ?? 0);
                        $kuppiCommentCount = (int) ($item['kuppi_comment_count'] ?? 0);
                        $kuppiConductorCount = (int) ($item['kuppi_conductor_count'] ?? 0);
                        $kuppiViewerVote = trim((string) ($item['kuppi_viewer_vote'] ?? ''));
                        $canVoteRequest = !empty($item['can_vote_request']);

                        $typeIcon = match ($itemType) {
                            'announcement' => 'megaphone',
                            'community' => 'message-square-text',
                            'resource' => 'folder-open',
                            'quiz' => 'clipboard-check',
                            'kuppi_request' => 'sparkles',
                            'kuppi_scheduled' => 'calendar-days',
                            default => 'newspaper',
                        };
                        ?>
                        <article class="feed-post feed-post--<?= e($itemType) ?>">
                            <header class="feed-post-header">
                                <div class="feed-post-author">
                                    <span class="feed-post-avatar <?= e($actorToneClass) ?>"><?= e(ui_initials($actorName)) ?></span>
                                    <div class="feed-post-author-copy">
                                        <strong><?= e($actorName) ?></strong>
                                        <small title="<?= e($eventAtDisplay) ?>"><?= e($eventLabel) ?></small>
                                    </div>
                                </div>
                                <div class="feed-post-header-right">
                                    <span class="feed-post-type-pill <?= e($itemTypeBadgeClass) ?>">
                                        <?= ui_lucide_icon($typeIcon, 'feed-type-icon') ?> <?= e($itemTypeLabel) ?>
                                    </span>
                                    <?php if ($subjectCode !== '' || $subjectName !== ''): ?>
                                        <span class="feed-post-subject-pill" title="<?= e($subjectName !== '' ? $subjectName : $subjectCode) ?>">
                                            <?= e($subjectCode !== '' ? $subjectCode : $subjectName) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </header>

                            <div class="feed-post-body">
                                <h3><a href="<?= e($targetUrl) ?>"><?= e($title) ?></a></h3>
                                <p><?= e($summary) ?></p>
                            </div>

                            <?php if ($itemType === 'community'): ?>
                                <div class="feed-post-attachment feed-post-attachment--community">
                                    <div class="feed-attachment-row">
                                        <?php if ($communityPostType !== ''): ?>
                                            <span class="feed-attachment-chip"><?= e(community_post_type_label($communityPostType)) ?></span>
                                        <?php endif; ?>
                                        <span class="feed-attachment-chip"><?= ui_lucide_icon('heart', 'feed-mini-icon') ?> <?= $communityLikeCount ?> likes</span>
                                        <span class="feed-attachment-chip"><?= ui_lucide_icon('message-circle', 'feed-mini-icon') ?> <?= $communityCommentCount ?> comments</span>
                                    </div>
                                </div>
                                <?php if ($communityHasImage): ?>
                                    <a href="<?= e($targetUrl) ?>" class="feed-community-media" aria-label="Open community post image">
                                        <img src="/community/<?= $itemId ?>/image" alt="Community post image by <?= e($actorName) ?>" loading="lazy">
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($itemType === 'resource'): ?>
                                <div class="feed-post-attachment feed-post-attachment--resource">
                                    <div class="feed-attachment-row">
                                        <?php if ($resourceCategory !== ''): ?>
                                            <span class="feed-attachment-chip"><?= ui_lucide_icon('tag', 'feed-mini-icon') ?> <?= e($resourceCategory) ?></span>
                                        <?php endif; ?>
                                        <?php if ($resourceSourceType !== ''): ?>
                                            <span class="feed-attachment-chip"><?= ui_lucide_icon('link-2', 'feed-mini-icon') ?> <?= e(ucfirst($resourceSourceType)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="feed-resource-scoreboard">
                                        <span><strong><?= number_format((float) ($item['resource_rating_avg'] ?? 0), 2) ?></strong> Avg Rating</span>
                                        <span><strong><?= (int) ($item['resource_rating_count'] ?? 0) ?></strong> Ratings</span>
                                        <span><strong><?= (int) ($item['resource_comment_count'] ?? 0) ?></strong> Comments</span>
                                    </div>
                                </div>
                            <?php elseif ($itemType === 'quiz'): ?>
                                <div class="feed-post-attachment feed-post-attachment--quiz">
                                    <div class="feed-quiz-metrics">
                                        <div>
                                            <span>Questions</span>
                                            <strong><?= (int) ($item['quiz_question_count'] ?? 0) ?></strong>
                                        </div>
                                        <div>
                                            <span>Duration</span>
                                            <strong><?= (int) ($item['quiz_duration_minutes'] ?? 0) ?> min</strong>
                                        </div>
                                        <div>
                                            <span>Mode</span>
                                            <strong><?= e($quizMode !== '' ? ucfirst($quizMode) : 'Quiz') ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($itemType === 'kuppi_request'): ?>
                                <div class="feed-post-attachment feed-post-attachment--kuppi-request">
                                    <div class="feed-kuppi-vote-strip">
                                        <span><strong><?= $kuppiVoteScore ?></strong> score</span>
                                        <span><?= ui_lucide_icon('arrow-up', 'feed-mini-icon') ?> <?= $kuppiUpvoteCount ?></span>
                                        <span><?= ui_lucide_icon('arrow-down', 'feed-mini-icon') ?> <?= $kuppiDownvoteCount ?></span>
                                        <span><?= ui_lucide_icon('users', 'feed-mini-icon') ?> <?= $kuppiConductorCount ?> conductors</span>
                                        <span><?= ui_lucide_icon('message-circle', 'feed-mini-icon') ?> <?= $kuppiCommentCount ?> comments</span>
                                    </div>
                                </div>
                            <?php elseif ($itemType === 'kuppi_scheduled'): ?>
                                <div class="feed-post-attachment feed-post-attachment--kuppi-scheduled">
                                    <div class="feed-scheduled-event-line">
                                        <span><?= ui_lucide_icon('calendar-days', 'feed-mini-icon') ?> <?= e($scheduledDateDisplay) ?></span>
                                        <span><?= ui_lucide_icon('clock-3', 'feed-mini-icon') ?> <?= e($scheduledTimeDisplay) ?></span>
                                        <span><?= ui_lucide_icon('user-check', 'feed-mini-icon') ?> <?= (int) ($item['scheduled_host_count'] ?? 0) ?> hosts</span>
                                    </div>
                                </div>
                            <?php elseif ($itemType === 'announcement'): ?>
                                <div class="feed-post-attachment feed-post-attachment--announcement">
                                    <div class="feed-attachment-row">
                                        <span class="feed-attachment-chip"><?= ui_lucide_icon('megaphone', 'feed-mini-icon') ?> Official Notice</span>
                                        <?php if ($subjectCode !== ''): ?>
                                            <span class="feed-attachment-chip"><?= ui_lucide_icon('book-open', 'feed-mini-icon') ?> <?= e($subjectCode) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <footer class="feed-post-actions">
                                <div class="feed-post-actions-left">
                                    <?php if ($itemType === 'community'): ?>
                                        <form method="POST" action="/dashboard/community/<?= $itemId ?>/like" class="feed-inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                            <button type="submit" class="feed-react-btn <?= $communityIsLiked ? 'is-active' : '' ?>">
                                                <?= ui_lucide_icon('heart', 'feed-mini-icon') ?> <?= $communityIsLiked ? 'Liked' : 'Like' ?>
                                            </button>
                                        </form>
                                        <?php if (!empty($can_save_posts)): ?>
                                            <form method="POST" action="/dashboard/community/<?= $itemId ?>/save" class="feed-inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                                <button type="submit" class="feed-react-btn <?= $communityIsSaved ? 'is-active' : '' ?>">
                                                    <?= ui_lucide_icon('bookmark', 'feed-mini-icon') ?> <?= $communityIsSaved ? 'Saved' : 'Save' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php elseif ($itemType === 'kuppi_request'): ?>
                                        <form method="POST" action="/dashboard/kuppi/<?= $itemId ?>/vote" class="feed-inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="vote" value="up">
                                            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                            <?php if (!empty($is_admin) && $selectedBatchId > 0): ?>
                                                <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="feed-react-btn <?= $kuppiViewerVote === 'up' ? 'is-active' : '' ?>" <?= $canVoteRequest ? '' : 'disabled' ?>>
                                                <?= ui_lucide_icon('arrow-up', 'feed-mini-icon') ?> Upvote
                                            </button>
                                        </form>
                                        <form method="POST" action="/dashboard/kuppi/<?= $itemId ?>/vote" class="feed-inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="vote" value="down">
                                            <input type="hidden" name="return_to" value="<?= e($currentUri) ?>">
                                            <?php if (!empty($is_admin) && $selectedBatchId > 0): ?>
                                                <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="feed-react-btn <?= $kuppiViewerVote === 'down' ? 'is-active' : '' ?>" <?= $canVoteRequest ? '' : 'disabled' ?>>
                                                <?= ui_lucide_icon('arrow-down', 'feed-mini-icon') ?> Downvote
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="feed-post-actions-right">
                                    <a href="<?= e($targetUrl) ?>" class="btn btn-sm btn-outline">Open</a>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </section>

                <?php if ($hasMoreItems): ?>
                    <div class="community-load-more feed-load-more">
                        <a href="<?= e($buildFeedUrl(['page' => $selectedPage + 1])) ?>" class="btn btn-outline">Load More</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <aside class="feed-social-rail">
            <article class="feed-rail-card">
                <h3>Stream Snapshot</h3>
                <ul class="feed-rail-list">
                    <li><span>Visible</span><strong><?= $selectedTypeCount ?></strong></li>
                    <li><span>Today</span><strong><?= $todayCount ?></strong></li>
                    <li><span>Subjects</span><strong><?= count($subjectOptions) ?></strong></li>
                    <li><span>Selected Type</span><strong><?= e($selectedTypeLabel) ?></strong></li>
                </ul>
            </article>

            <article class="feed-rail-card">
                <h3>Active Filters</h3>
                <ul class="feed-rail-list">
                    <li><span>Type</span><strong><?= e($selectedTypeLabel) ?></strong></li>
                    <li><span>Subject</span><strong><?= e($selectedSubjectLabel) ?></strong></li>
                    <li><span>Search</span><strong><?= e($selectedSearchQuery !== '' ? $selectedSearchQuery : 'None') ?></strong></li>
                </ul>
            </article>

            <article class="feed-rail-card">
                <h3>Quick Open</h3>
                <div class="feed-quick-links">
                    <a href="/dashboard/announcements">Announcements</a>
                    <a href="/dashboard/community">Community Feed</a>
                    <a href="/dashboard/quizzes">Quiz Hub</a>
                    <a href="/dashboard/kuppi">Requested Kuppi</a>
                    <a href="/dashboard/kuppi/scheduled">Scheduled Kuppi</a>
                </div>
            </article>
        </aside>
    </section>
<?php endif; ?>
