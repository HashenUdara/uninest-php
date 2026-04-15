<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isSubjectCreate = $currentPath === '/subjects/create';
$isSubjects = str_starts_with($currentPath, '/subjects') && !$isSubjectCreate;
$isStudentCreate = $currentPath === '/students/create';
$isStudents = str_starts_with($currentPath, '/students') && !$isStudentCreate;
$isBatches = str_starts_with($currentPath, '/admin/batches');
$isModerators = str_starts_with($currentPath, '/admin/moderators');
$isCommunityReports = str_starts_with($currentPath, '/dashboard/community/reports');
$isCommunityFeed = str_starts_with($currentPath, '/dashboard/community') && !$isCommunityReports;
$isCentralFeed = $currentPath === '/dashboard/feed';
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
$isQuizRequests = str_starts_with($currentPath, '/dashboard/quiz-requests');
$isQuizAnalytics = str_starts_with($currentPath, '/dashboard/quiz-analytics');
$isGpaGradeScale = str_starts_with($currentPath, '/dashboard/gpa/grade-scale');
$isProfileSettings = $currentPath === '/dashboard/profile';
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('home') ?></span><span>Dashboard</span></a></li>
        <li><a href="/dashboard/feed" class="<?= $isCentralFeed ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('newspaper') ?></span><span>Central Feed</span></a></li>
    </ul>

    <div class="sidebar-section-label">Approvals</div>
    <ul>
        <li><a href="/admin/batch-requests" class="<?= is_current_url('/admin/batch-requests') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('inbox') ?></span><span>Batch Requests</span></a></li>
        <li><a href="/admin/student-requests" class="<?= is_current_url('/admin/student-requests') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('users') ?></span><span>Student Requests</span></a></li>
        <li><a href="/dashboard/quiz-requests" class="<?= $isQuizRequests ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('check-check') ?></span><span>Quiz Requests</span></a></li>
        <li><a href="/dashboard/quiz-analytics" class="<?= $isQuizAnalytics ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('chart-no-axes-column') ?></span><span>Quiz Analytics</span></a></li>
    </ul>

    <div class="sidebar-section-label">Content</div>
    <ul>
        <li><a href="/dashboard/community" class="<?= $isCommunityFeed ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('message-square') ?></span><span>Community Feed</span></a></li>
        <li><a href="/dashboard/quizzes" class="<?= $isQuizHub ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('clipboard-check') ?></span><span>Quiz Hub</span></a></li>
        <li><a href="/dashboard/kuppi" class="<?= $isKuppiRequested ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('book-open') ?></span><span>Requested Kuppi</span></a></li>
        <li><a href="/dashboard/kuppi/scheduled" class="<?= $isKuppiScheduled ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar') ?></span><span>Scheduled Kuppi</span></a></li>
        <li><a href="/dashboard/kuppi/schedule" class="<?= $isKuppiScheduleFlow ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar-plus') ?></span><span>Schedule Session</span></a></li>
        <li><a href="/dashboard/kuppi/timetable" class="<?= $isKuppiTimetable ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calendar-clock') ?></span><span>University Timetable</span></a></li>
        <li><a href="/dashboard/gpa/grade-scale" class="<?= $isGpaGradeScale ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('calculator') ?></span><span>Grade Point Config</span></a></li>
        <li><a href="/saved-posts" class="<?= str_starts_with($currentPath, '/saved-posts') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('bookmark') ?></span><span>Saved Posts</span></a></li>
        <li><a href="/dashboard/community/reports" class="<?= $isCommunityReports ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('flag') ?></span><span>Reports Queue</span></a></li>
        <li><a href="/subjects" class="<?= $isSubjects ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('layers') ?></span><span>Subjects</span></a></li>
        <li><a href="/subjects/create" class="<?= $isSubjectCreate ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('plus') ?></span><span>New Subject</span></a></li>
        <li><a href="/my-resources" class="<?= str_starts_with($currentPath, '/my-resources') ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('file-text') ?></span><span>My Resources</span></a></li>
        <li><a href="/students" class="<?= $isStudents ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('users') ?></span><span>Students</span></a></li>
        <li><a href="/students/create" class="<?= $isStudentCreate ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('user-plus') ?></span><span>New Student</span></a></li>
    </ul>

    <div class="sidebar-section-label">Provisioning</div>
    <ul>
        <li><a href="/admin/moderators" class="<?= $isModerators ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('shield') ?></span><span>Moderators</span></a></li>
        <li><a href="/admin/batches" class="<?= $isBatches ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('folder') ?></span><span>Batches</span></a></li>
    </ul>

    <div class="sidebar-section-label">Account</div>
    <ul>
        <li><a href="/dashboard/profile" class="<?= $isProfileSettings ? 'active' : '' ?>"><span class="sidebar-nav-icon"><?= ui_lucide_icon('user-cog') ?></span><span>Profile Settings</span></a></li>
    </ul>
</nav>
