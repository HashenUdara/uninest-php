<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isSubjectCreate = $currentPath === '/subjects/create';
$isSubjects = str_starts_with($currentPath, '/subjects') && !$isSubjectCreate;
$isStudents = str_starts_with($currentPath, '/students');
$isCommunityReports = str_starts_with($currentPath, '/dashboard/community/reports');
$isCommunityFeed = str_starts_with($currentPath, '/dashboard/community') && !$isCommunityReports;
$isKuppi = str_starts_with($currentPath, '/dashboard/kuppi') || str_starts_with($currentPath, '/my-kuppi-requests');
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" data-icon="DB" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span>Dashboard</span></a></li>
        <li><a href="/moderator/join-requests" data-icon="JR" class="<?= str_starts_with($currentPath, '/moderator/join-requests') ? 'active' : '' ?>"><span>Join Requests</span></a></li>
        <li><a href="/dashboard/community" data-icon="CF" class="<?= $isCommunityFeed ? 'active' : '' ?>"><span>Community Feed</span></a></li>
        <li><a href="/dashboard/kuppi" data-icon="KP" class="<?= $isKuppi ? 'active' : '' ?>"><span>Requested Kuppi</span></a></li>
        <li><a href="/saved-posts" data-icon="SV" class="<?= str_starts_with($currentPath, '/saved-posts') ? 'active' : '' ?>"><span>Saved Posts</span></a></li>
        <li><a href="/dashboard/community/reports" data-icon="RQ" class="<?= $isCommunityReports ? 'active' : '' ?>"><span>Reports Queue</span></a></li>
        <li><a href="/my-posts" data-icon="MP" class="<?= str_starts_with($currentPath, '/my-posts') ? 'active' : '' ?>"><span>My Posts</span></a></li>
        <li><a href="/my-resources" data-icon="RS" class="<?= str_starts_with($currentPath, '/my-resources') ? 'active' : '' ?>"><span>My Resources</span></a></li>
    </ul>

    <div class="sidebar-section-label">Content</div>
    <ul>
        <li><a href="/subjects" data-icon="SB" class="<?= $isSubjects ? 'active' : '' ?>"><span>Batch Subjects</span></a></li>
        <li><a href="/subjects/create" data-icon="NW" class="<?= $isSubjectCreate ? 'active' : '' ?>"><span>New Subject</span></a></li>
        <li><a href="/students" data-icon="ST" class="<?= $isStudents ? 'active' : '' ?>"><span>Batch Students</span></a></li>
    </ul>
</nav>
