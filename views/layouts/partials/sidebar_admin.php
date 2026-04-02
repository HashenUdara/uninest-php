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
$isKuppi = str_starts_with($currentPath, '/dashboard/kuppi') || str_starts_with($currentPath, '/my-kuppi-requests');
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" data-icon="DB" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span>Dashboard</span></a></li>
    </ul>

    <div class="sidebar-section-label">Approvals</div>
    <ul>
        <li><a href="/admin/batch-requests" data-icon="BR" class="<?= is_current_url('/admin/batch-requests') ? 'active' : '' ?>"><span>Batch Requests</span></a></li>
        <li><a href="/admin/student-requests" data-icon="SR" class="<?= is_current_url('/admin/student-requests') ? 'active' : '' ?>"><span>Student Requests</span></a></li>
    </ul>

    <div class="sidebar-section-label">Content</div>
    <ul>
        <li><a href="/dashboard/community" data-icon="CF" class="<?= $isCommunityFeed ? 'active' : '' ?>"><span>Community Feed</span></a></li>
        <li><a href="/dashboard/kuppi" data-icon="KP" class="<?= $isKuppi ? 'active' : '' ?>"><span>Requested Kuppi</span></a></li>
        <li><a href="/saved-posts" data-icon="SV" class="<?= str_starts_with($currentPath, '/saved-posts') ? 'active' : '' ?>"><span>Saved Posts</span></a></li>
        <li><a href="/dashboard/community/reports" data-icon="RQ" class="<?= $isCommunityReports ? 'active' : '' ?>"><span>Reports Queue</span></a></li>
        <li><a href="/subjects" data-icon="SB" class="<?= $isSubjects ? 'active' : '' ?>"><span>Subjects</span></a></li>
        <li><a href="/subjects/create" data-icon="NW" class="<?= $isSubjectCreate ? 'active' : '' ?>"><span>New Subject</span></a></li>
        <li><a href="/my-resources" data-icon="RS" class="<?= str_starts_with($currentPath, '/my-resources') ? 'active' : '' ?>"><span>My Resources</span></a></li>
        <li><a href="/students" data-icon="ST" class="<?= $isStudents ? 'active' : '' ?>"><span>Students</span></a></li>
        <li><a href="/students/create" data-icon="NS" class="<?= $isStudentCreate ? 'active' : '' ?>"><span>New Student</span></a></li>
    </ul>

    <div class="sidebar-section-label">Provisioning</div>
    <ul>
        <li><a href="/admin/moderators" data-icon="MD" class="<?= $isModerators ? 'active' : '' ?>"><span>Moderators</span></a></li>
        <li><a href="/admin/batches" data-icon="BT" class="<?= $isBatches ? 'active' : '' ?>"><span>Batches</span></a></li>
    </ul>
</nav>
