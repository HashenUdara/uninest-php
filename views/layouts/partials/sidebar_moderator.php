<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isSubjectCreate = $currentPath === '/subjects/create';
$isSubjects = str_starts_with($currentPath, '/subjects') && !$isSubjectCreate;
$isStudents = str_starts_with($currentPath, '/students');
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" data-icon="DB" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span>Dashboard</span></a></li>
        <li><a href="/moderator/join-requests" data-icon="JR" class="<?= str_starts_with($currentPath, '/moderator/join-requests') ? 'active' : '' ?>"><span>Join Requests</span></a></li>
    </ul>

    <div class="sidebar-section-label">Content</div>
    <ul>
        <li><a href="/subjects" data-icon="SB" class="<?= $isSubjects ? 'active' : '' ?>"><span>Batch Subjects</span></a></li>
        <li><a href="/subjects/create" data-icon="NW" class="<?= $isSubjectCreate ? 'active' : '' ?>"><span>New Subject</span></a></li>
        <li><a href="/students" data-icon="ST" class="<?= $isStudents ? 'active' : '' ?>"><span>Batch Students</span></a></li>
    </ul>
</nav>
