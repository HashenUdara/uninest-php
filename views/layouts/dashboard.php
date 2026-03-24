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
?>
<body class="dashboard-body">
    <div class="dashboard-shell">
        <aside class="sidebar" id="app-sidebar" aria-label="Dashboard navigation">
            <div class="sidebar-header">
                <a href="/dashboard" class="sidebar-brand">
                    <img src="<?= asset('img/black-logo.png') ?>" alt="<?= e(config('app.name')) ?>" class="sidebar-brand-logo">
                    <span class="sidebar-brand-text">
                        <small>Kuppi Workspace</small>
                    </span>
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
                <div class="sidebar-user">
                    <span class="user-name"><?= e($user['name']) ?></span>
                    <span class="user-email"><?= e($user['email']) ?></span>
                </div>
                <a href="/logout" class="btn btn-sm btn-outline btn-block">Logout</a>
            </div>
        </aside>

        <div class="dashboard-stage">
            <header class="dashboard-topbar">
                <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false">
                    ☰
                </button>

                <div class="topbar-search" role="search">
                    <span class="search-icon" aria-hidden="true">⌕</span>
                    <input type="text" placeholder="Search your workspace" aria-label="Search your workspace">
                </div>

                <div class="topbar-actions">
                    <?php if ($role !== 'admin'): ?>
                        <a href="/onboarding" class="topbar-link">Onboarding</a>
                    <?php endif; ?>
                    <a href="/logout" class="topbar-link">Logout</a>
                    <div class="topbar-user-chip">
                        <span class="topbar-avatar" aria-hidden="true"><?= e($initials) ?></span>
                        <div>
                            <strong><?= e($user['name']) ?></strong>
                            <small><?= e($roleLabel) ?></small>
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
            const sidebar = document.getElementById('app-sidebar');
            const toggle = document.getElementById('sidebar-toggle');
            const overlay = document.getElementById('dashboard-overlay');
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
        })();
    </script>
</body>
</html>
