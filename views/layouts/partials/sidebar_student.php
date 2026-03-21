<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" data-icon="DB" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span>Dashboard</span></a></li>
        <li><a href="/dashboard/subjects" data-icon="SB" class="<?= str_starts_with($currentPath, '/dashboard/subjects') ? 'active' : '' ?>"><span>My Subjects</span></a></li>
    </ul>

    <div class="sidebar-section-label">Account</div>
    <ul>
        <li><a href="/onboarding" data-icon="ON" class="<?= is_current_url('/onboarding') ? 'active' : '' ?>"><span>Onboarding Status</span></a></li>
    </ul>
</nav>
