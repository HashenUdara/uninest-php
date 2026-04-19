<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$isKuppiScheduleFlow = $currentPath === '/dashboard/kuppi/schedule' || str_starts_with($currentPath, '/dashboard/kuppi/schedule/');
$isKuppiScheduled = str_starts_with($currentPath, '/dashboard/kuppi/scheduled');
$isKuppiTimetable = str_starts_with($currentPath, '/dashboard/kuppi/timetable');
$isMyKuppiRequests = str_starts_with($currentPath, '/my-kuppi-requests');
$isKuppiRequested = (
    str_starts_with($currentPath, '/dashboard/kuppi')
    && !$isKuppiScheduleFlow
    && !$isKuppiScheduled
    && !$isKuppiTimetable
) || $isMyKuppiRequests;

$isQuizHub = $currentPath === '/dashboard/quizzes'
    || (str_starts_with($currentPath, '/dashboard/subjects/') && str_contains($currentPath, '/quizzes'));
$isMyQuizzes = str_starts_with($currentPath, '/my-quizzes');
$isMyQuizAnalytics = $currentPath === '/my-quiz-analytics';
$isQuizRequests = str_starts_with($currentPath, '/dashboard/quiz-requests');
$isQuizReviewAnalytics = str_starts_with($currentPath, '/dashboard/quiz-analytics');

$isGpaCalculator = $currentPath === '/dashboard/gpa';
$isGpaAnalytics = $currentPath === '/dashboard/gpa/analytics';

$isCentralFeed = $currentPath === '/dashboard/feed';
$isAnnouncements = str_starts_with($currentPath, '/dashboard/announcements');
$isSubjectsBrowse = str_starts_with($currentPath, '/dashboard/subjects');
$isCommunityFeed = str_starts_with($currentPath, '/dashboard/community');
$isMyPosts = str_starts_with($currentPath, '/my-posts');
$isSavedPosts = str_starts_with($currentPath, '/saved-posts');
$isMyResources = str_starts_with($currentPath, '/my-resources');
$isCoordinatorSubjects = str_starts_with($currentPath, '/coordinator/subjects');
$isResourceRequests = str_starts_with($currentPath, '/coordinator/resource-requests');
$isProfileSettings = $currentPath === '/dashboard/profile';

$isKuppiSectionActive = $isKuppiRequested || $isKuppiScheduleFlow || $isKuppiScheduled || $isKuppiTimetable;
$isQuizSectionActive = $isQuizHub || $isMyQuizzes || $isMyQuizAnalytics;
$isGpaSectionActive = $isGpaCalculator || $isGpaAnalytics;
$isCommunitySectionActive = $isCommunityFeed || $isMyPosts || $isSavedPosts;
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('home') ?></span><span>Dashboard</span></a></li>
        <li><a href="/dashboard/feed" class="<?= $isCentralFeed ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('newspaper') ?></span><span>Central Feed</span></a></li>
        <li><a href="/dashboard/announcements" class="<?= $isAnnouncements ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('megaphone') ?></span><span>Announcements</span></a></li>
    </ul>

    <div class="sidebar-section-label">Student Pages</div>
    <ul>
        <li><a href="/dashboard/subjects" class="<?= $isSubjectsBrowse ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('book-open') ?></span><span>Subjects</span></a></li>
        <li class="sidebar-accordion-item">
            <details class="sidebar-accordion" <?= $isKuppiSectionActive ? 'open' : '' ?>>
                <summary class="sidebar-accordion-toggle <?= $isKuppiSectionActive ? 'is-active' : '' ?>">
                    <span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar-plus') ?></span>
                    <span>Kuppi</span>
                    <span class="sidebar-accordion-caret"><?= ui_lucide_icon('chevron-down') ?></span>
                </summary>
                <ul class="sidebar-subnav">
                    <li><a href="/dashboard/kuppi" class="<?= $isKuppiRequested ? 'active' : '' ?>">Requested Sessions</a></li>
                    <li><a href="/my-kuppi-requests" class="<?= $isMyKuppiRequests ? 'active' : '' ?>">My Kuppi</a></li>
                    <li><a href="/dashboard/kuppi/scheduled" class="<?= $isKuppiScheduled ? 'active' : '' ?>">Scheduled Sessions</a></li>
                    <li><a href="/dashboard/kuppi/timetable" class="<?= $isKuppiTimetable ? 'active' : '' ?>">University Timetable</a></li>
                </ul>
            </details>
        </li>
        <li class="sidebar-accordion-item">
            <details class="sidebar-accordion" <?= $isQuizSectionActive ? 'open' : '' ?>>
                <summary class="sidebar-accordion-toggle <?= $isQuizSectionActive ? 'is-active' : '' ?>">
                    <span class="sidebar-nav-icon"><?= ui_lucide_icon('clipboard-check') ?></span>
                    <span>Quiz</span>
                    <span class="sidebar-accordion-caret"><?= ui_lucide_icon('chevron-down') ?></span>
                </summary>
                <ul class="sidebar-subnav">
                    <li><a href="/dashboard/quizzes" class="<?= $isQuizHub ? 'active' : '' ?>">Quiz Hub</a></li>
                    <li><a href="/my-quizzes" class="<?= $isMyQuizzes ? 'active' : '' ?>">My Quizzes</a></li>
                    <li><a href="/my-quiz-analytics" class="<?= $isMyQuizAnalytics ? 'active' : '' ?>">Quiz Analytics</a></li>
                </ul>
            </details>
        </li>
        <li class="sidebar-accordion-item">
            <details class="sidebar-accordion" <?= $isGpaSectionActive ? 'open' : '' ?>>
                <summary class="sidebar-accordion-toggle <?= $isGpaSectionActive ? 'is-active' : '' ?>">
                    <span class="sidebar-nav-icon"><?= ui_lucide_icon('line-chart') ?></span>
                    <span>GPA</span>
                    <span class="sidebar-accordion-caret"><?= ui_lucide_icon('chevron-down') ?></span>
                </summary>
                <ul class="sidebar-subnav">
                    <li><a href="/dashboard/gpa" class="<?= $isGpaCalculator ? 'active' : '' ?>">GPA Calculator</a></li>
                    <li><a href="/dashboard/gpa/analytics" class="<?= $isGpaAnalytics ? 'active' : '' ?>">GPA Analytics</a></li>
                </ul>
            </details>
        </li>
    </ul>

    <div class="sidebar-section-label">Community</div>
    <ul>
        <li class="sidebar-accordion-item">
            <details class="sidebar-accordion" <?= $isCommunitySectionActive ? 'open' : '' ?>>
                <summary class="sidebar-accordion-toggle <?= $isCommunitySectionActive ? 'is-active' : '' ?>">
                    <span class="sidebar-nav-icon"><?= ui_lucide_icon('message-square') ?></span>
                    <span>Community</span>
                    <span class="sidebar-accordion-caret"><?= ui_lucide_icon('chevron-down') ?></span>
                </summary>
                <ul class="sidebar-subnav">
                    <li><a href="/dashboard/community" class="<?= $isCommunityFeed ? 'active' : '' ?>">Community Feed</a></li>
                    <li><a href="/my-posts" class="<?= $isMyPosts ? 'active' : '' ?>">My Posts</a></li>
                    <li><a href="/saved-posts" class="<?= $isSavedPosts ? 'active' : '' ?>">Saved Posts</a></li>
                </ul>
            </details>
        </li>
        <li><a href="/my-resources" class="<?= $isMyResources ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('folder-open') ?></span><span>My Resources</span></a></li>
    </ul>

    <div class="sidebar-section-label">Coordinator Controls</div>
    <ul>
        <li><a href="/dashboard/kuppi/schedule" class="<?= $isKuppiScheduleFlow ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar-plus') ?></span><span>Set Kuppi Schedule</span></a></li>
        <li><a href="/coordinator/subjects" class="<?= $isCoordinatorSubjects ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('layers') ?></span><span>Manage Subjects</span></a></li>
        <li><a href="/coordinator/resource-requests" class="<?= $isResourceRequests ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('inbox') ?></span><span>Resource Requests</span></a></li>
        <li><a href="/dashboard/quiz-requests" class="<?= $isQuizRequests ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('check-check') ?></span><span>Quiz Requests</span></a></li>
        <li><a href="/dashboard/quiz-analytics" class="<?= $isQuizReviewAnalytics ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('chart-no-axes-column') ?></span><span>Review Analytics</span></a></li>
    </ul>

    <div class="sidebar-section-label">Account</div>
    <ul>
        <li><a href="/dashboard/profile" class="<?= $isProfileSettings ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('user-cog') ?></span><span>Profile Settings</span></a></li>
    </ul>
</nav>
