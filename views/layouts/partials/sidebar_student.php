<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<nav class="sidebar-nav">
    <div class="sidebar-section-label">Overview</div>
    <ul>
        <li><a href="/dashboard" data-icon="DB" class="<?= is_current_url('/dashboard') ? 'active' : '' ?>"><span>Dashboard</span></a></li>
        <li><a href="/dashboard/subjects" data-icon="SB" class="<?= str_starts_with($currentPath, '/dashboard/subjects') ? 'active' : '' ?>"><span>My Subjects</span></a></li>
        <li><a href="/dashboard/community" data-icon="CF" class="<?= str_starts_with($currentPath, '/dashboard/community') ? 'active' : '' ?>"><span>Community Feed</span></a></li>
        <li><a href="/saved-posts" data-icon="SV" class="<?= str_starts_with($currentPath, '/saved-posts') ? 'active' : '' ?>"><span>Saved Posts</span></a></li>
        <li><a href="/my-posts" data-icon="MP" class="<?= str_starts_with($currentPath, '/my-posts') ? 'active' : '' ?>"><span>My Posts</span></a></li>
        <li><a href="/my-resources" data-icon="RS" class="<?= str_starts_with($currentPath, '/my-resources') ? 'active' : '' ?>"><span>My Resources</span></a></li>
    </ul>

    <div class="sidebar-section-label">Account</div>
    <ul>
        <li><a href="/onboarding" data-icon="ON" class="<?= is_current_url('/onboarding') ? 'active' : '' ?>"><span>Onboarding Status</span></a></li>
    </ul>
</nav>
