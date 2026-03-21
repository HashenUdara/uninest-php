<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isSubjectCreate = $currentPath === '/subjects/create';
$isSubjects = str_starts_with($currentPath, '/subjects') && !$isSubjectCreate;
$isStudentCreate = $currentPath === '/students/create';
$isStudents = str_starts_with($currentPath, '/students') && !$isStudentCreate;
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
        <li><a href="/subjects" data-icon="SB" class="<?= $isSubjects ? 'active' : '' ?>"><span>Subjects</span></a></li>
        <li><a href="/subjects/create" data-icon="NW" class="<?= $isSubjectCreate ? 'active' : '' ?>"><span>New Subject</span></a></li>
        <li><a href="/students" data-icon="ST" class="<?= $isStudents ? 'active' : '' ?>"><span>Students</span></a></li>
        <li><a href="/students/create" data-icon="NS" class="<?= $isStudentCreate ? 'active' : '' ?>"><span>New Student</span></a></li>
    </ul>
</nav>
