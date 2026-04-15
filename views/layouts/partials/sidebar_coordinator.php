<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isKuppiScheduleFlow = $currentPath === '/dashboard/kuppi/schedule' || str_starts_with($currentPath, '/dashboard/kuppi/schedule/');
$isKuppiScheduled = str_starts_with($currentPath, '/dashboard/kuppi/scheduled');
$isKuppiTimetable = str_starts_with($currentPath, '/dashboard/kuppi/timetable');
$isKuppiRequested = (
    str_starts_with($currentPath, '/dashboard/kuppi')
    && !$isKuppiScheduleFlow
    && !$isKuppiScheduled
    && !$isKuppiTimetable
) || str_starts_with($currentPath, '/my-kuppi-requests');
$isQuizHub = $currentPath === '/dashboard/quizzes'
    || (str_starts_with($currentPath, '/dashboard/subjects/') && str_contains($currentPath, '/quizzes'));
$isCentralFeed = $currentPath === '/dashboard/feed';
$isMyQuizzes = str_starts_with($currentPath, '/my-quizzes');
$isMyQuizAnalytics = $currentPath === '/my-quiz-analytics';
$isGpaCalculator = $currentPath === '/dashboard/gpa';
$isGpaAnalytics = $currentPath === '/dashboard/gpa/analytics';
$isProfileSettings = $currentPath === '/dashboard/profile';
?>
<nav class="sidebar-nav">
    <?php $isCoordinatorSubjects = str_starts_with($currentPath, '/coordinator/subjects'); ?>
    <?php $isResourceRequests = str_starts_with($currentPath, '/coordinator/resource-requests'); ?>
    <?php $isQuizRequests = str_starts_with($currentPath, '/dashboard/quiz-requests'); ?>
    <?php $isQuizAnalytics = str_starts_with($currentPath, '/dashboard/quiz-analytics'); ?>
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('home') ?></span><span>Dashboard</span></a></li>
        <li><a href="/dashboard/feed" class="<?= $isCentralFeed ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('newspaper') ?></span><span>Central Feed</span></a></li>
        <li><a href="/dashboard/subjects" class="<?= str_starts_with($currentPath, '/dashboard/subjects') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('book-open') ?></span><span>Browse Subjects</span></a></li>
        <li><a href="/dashboard/quizzes" class="<?= $isQuizHub ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('clipboard-check') ?></span><span>Quiz Hub</span></a></li>
        <li><a href="/dashboard/community" class="<?= str_starts_with($currentPath, '/dashboard/community') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('message-square') ?></span><span>Community Feed</span></a></li>
        <li><a href="/dashboard/kuppi" class="<?= $isKuppiRequested ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('book-open') ?></span><span>Requested Kuppi</span></a></li>
        <li><a href="/dashboard/kuppi/scheduled" class="<?= $isKuppiScheduled ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar') ?></span><span>Scheduled Kuppi</span></a></li>
        <li><a href="/dashboard/kuppi/schedule" class="<?= $isKuppiScheduleFlow ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar-plus') ?></span><span>Schedule Session</span></a></li>
        <li><a href="/dashboard/kuppi/timetable" class="<?= $isKuppiTimetable ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar-clock') ?></span><span>University Timetable</span></a></li>
        <li><a href="/saved-posts" class="<?= str_starts_with($currentPath, '/saved-posts') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('bookmark') ?></span><span>Saved Posts</span></a></li>
        <li><a href="/my-posts" class="<?= str_starts_with($currentPath, '/my-posts') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('file-text') ?></span><span>My Posts</span></a></li>
        <li><a href="/my-resources" class="<?= str_starts_with($currentPath, '/my-resources') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('file-text') ?></span><span>My Resources</span></a></li>
        <li><a href="/my-quizzes" class="<?= $isMyQuizzes ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('clipboard-list') ?></span><span>My Quizzes</span></a></li>
        <li><a href="/my-quiz-analytics" class="<?= $isMyQuizAnalytics ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('line-chart') ?></span><span>Quiz Analytics</span></a></li>
        <li><a href="/dashboard/gpa" class="<?= $isGpaCalculator ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calculator') ?></span><span>GPA Calculator</span></a></li>
        <li><a href="/dashboard/gpa/analytics" class="<?= $isGpaAnalytics ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('line-chart') ?></span><span>GPA Analytics</span></a></li>
    </ul>

    <div class="sidebar-section-label">Coordinator</div>
    <ul>
        <li><a href="/coordinator/subjects" class="<?= $isCoordinatorSubjects ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('layers') ?></span><span>Manage Subjects</span></a></li>
        <li><a href="/coordinator/resource-requests" class="<?= $isResourceRequests ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('inbox') ?></span><span>Resource Requests</span></a></li>
        <li><a href="/dashboard/quiz-requests" class="<?= $isQuizRequests ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('check-check') ?></span><span>Quiz Requests</span></a></li>
        <li><a href="/dashboard/quiz-analytics" class="<?= $isQuizAnalytics ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('chart-no-axes-column') ?></span><span>Review Analytics</span></a></li>
    </ul>

    <div class="sidebar-section-label">Account</div>
    <ul>
        <li><a href="/dashboard/profile" class="<?= $isProfileSettings ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('user-cog') ?></span><span>Profile Settings</span></a></li>
    </ul>
</nav>
