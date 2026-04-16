<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard-modern.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest" defer></script>
</head>
<?php
$role = user_role() ?? 'student';
$roleLabels = [
    'admin' => 'Admin',
    'moderator' => 'Moderator',
    'coordinator' => 'Coordinator',
    'student' => 'Student',
];
$roleLabel = $roleLabels[$role] ?? ucfirst($role);
$user = auth_user() ?? ['name' => 'User', 'email' => ''];
$nameParts = preg_split('/\s+/', trim((string) ($user['name'] ?? 'User')));
$initials = '';
if (!empty($nameParts[0])) {
    $initials .= strtoupper(substr($nameParts[0], 0, 1));
}
if (!empty($nameParts[1])) {
    $initials .= strtoupper(substr($nameParts[1], 0, 1));
}
if ($initials === '') {
    $initials = 'U';
}
$avatarToneClass = ui_avatar_tone_class((string) (($user['email'] ?? '') . '-' . ($user['name'] ?? 'User')));
?>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <aside class="sidebar" id="app-sidebar" aria-label="Dashboard navigation">
            <div class="sidebar-header">
                <a href="/dashboard" class="sidebar-brand">
                    <img src="<?= asset('img/black-logo.png') ?>" alt="<?= e(config('app.name')) ?>" class="sidebar-brand-logo">
                </a>
                <span class="role-badge role-<?= e($role) ?>"><?= e($roleLabel) ?></span>
            </div>

            <?php
            $sidebarFile = BASE_PATH . '/views/layouts/partials/sidebar_' . $role . '.php';
            if (file_exists($sidebarFile)) {
                require $sidebarFile;
            }
            ?>

            <div class="sidebar-footer">
                <a href="/logout" class="sidebar-logout-btn"><?= ui_lucide_icon('log-out') ?> <span>Sign Out</span></a>
            </div>
        </aside>

        <div class="dashboard-stage">
            <header class="dashboard-topbar">
                <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false">
                    <?= ui_lucide_icon('menu') ?>
                </button>

                <div class="topbar-search" role="search">
                    <span class="search-icon" aria-hidden="true"><?= ui_lucide_icon('search') ?></span>
                    <input type="text" placeholder="Search your workspace" aria-label="Search your workspace">
                </div>

                <div class="topbar-actions">
                    <button type="button" class="topbar-user-chip topbar-profile-trigger" id="topbar-profile-toggle" aria-haspopup="menu" aria-expanded="false">
                        <span class="topbar-avatar <?= e($avatarToneClass) ?>" aria-hidden="true"><?= e($initials) ?></span>
                        <div>
                            <strong><?= e($user['name']) ?></strong>
                            <small><?= e($roleLabel) ?></small>
                        </div>
                        <span class="topbar-profile-caret" aria-hidden="true"><?= ui_lucide_icon('chevron-down') ?></span>
                    </button>

                    <div class="topbar-profile-menu" id="topbar-profile-menu" role="menu" hidden>
                        <div class="topbar-profile-menu-header">
                            <span class="topbar-profile-menu-avatar <?= e($avatarToneClass) ?>" aria-hidden="true"><?= e($initials) ?></span>
                            <div>
                                <strong><?= e($user['name']) ?></strong>
                                <small><?= e((string) ($user['email'] ?? '')) ?></small>
                            </div>
                        </div>
                        <div class="topbar-profile-menu-actions">
                            <a href="/dashboard/profile" class="topbar-profile-menu-link" role="menuitem"><?= ui_lucide_icon('settings') ?> <span>Manage Account</span></a>
                            <a href="/logout" class="topbar-profile-menu-link is-danger" role="menuitem"><?= ui_lucide_icon('log-out') ?> <span>Sign Out</span></a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard-main">
                <div class="dashboard-content">
                    <?php if ($success = get_flash('success')): ?>
                        <div class="alert alert-success"><?= e($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error = get_flash('error')): ?>
                        <div class="alert alert-error"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($warning = get_flash('warning')): ?>
                        <div class="alert alert-warning"><?= e($warning) ?></div>
                    <?php endif; ?>
                    <?= $content ?>
                </div>
            </main>
        </div>
    </div>

    <div class="dashboard-overlay" id="dashboard-overlay" hidden></div>

    <script>
        (function () {
            function initLucide() {
                if (!window.lucide || typeof window.lucide.createIcons !== 'function') return;
                window.lucide.createIcons();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initLucide);
            } else {
                initLucide();
            }

            const sidebar = document.getElementById('app-sidebar');
            const toggle = document.getElementById('sidebar-toggle');
            const overlay = document.getElementById('dashboard-overlay');
            const profileToggle = document.getElementById('topbar-profile-toggle');
            const profileMenu = document.getElementById('topbar-profile-menu');
            if (!sidebar || !toggle || !overlay) return;

            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }

            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 980) {
                    closeSidebar();
                }
            });

            function closeProfileMenu() {
                if (!profileToggle || !profileMenu) return;
                profileMenu.hidden = true;
                profileMenu.classList.remove('is-open');
                profileToggle.setAttribute('aria-expanded', 'false');
            }

            function openProfileMenu() {
                if (!profileToggle || !profileMenu) return;
                profileMenu.hidden = false;
                profileMenu.classList.add('is-open');
                profileToggle.setAttribute('aria-expanded', 'true');
            }

            if (profileToggle && profileMenu) {
                profileToggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    if (profileMenu.hidden) {
                        openProfileMenu();
                    } else {
                        closeProfileMenu();
                    }
                });

                document.addEventListener('click', function (event) {
                    const target = event.target;
                    if (!(target instanceof Node)) return;
                    if (profileMenu.hidden) return;
                    if (profileMenu.contains(target) || profileToggle.contains(target)) return;
                    closeProfileMenu();
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closeProfileMenu();
                    }
                });

                window.addEventListener('resize', function () {
                    closeProfileMenu();
                });
            }
        })();
    </script>
</body>
</html>
