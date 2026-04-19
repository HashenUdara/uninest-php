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
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$isSearchPage = $requestPath === '/dashboard/search';
$topbarSearchQuery = $isSearchPage ? trim((string) request_input('q', '')) : '';
$topbarSearchBatchId = $isSearchPage ? (int) request_input('batch_id', 0) : 0;
$topbarSearchMinLength = function_exists('dashboard_search_min_query_length')
    ? dashboard_search_min_query_length()
    : 2;
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

                <form class="topbar-search topbar-search-global" role="search" id="topbar-global-search-form" method="GET" action="/dashboard/search" autocomplete="off" data-is-admin="<?= $role === 'admin' ? '1' : '0' ?>">
                    <span class="search-icon" aria-hidden="true"><?= ui_lucide_icon('search') ?></span>
                    <input type="search" id="topbar-global-search-input" name="q" value="<?= e($topbarSearchQuery) ?>" placeholder="Search subjects, resources, quizzes, kuppi..." aria-label="Search your workspace" data-min-length="<?= (int) $topbarSearchMinLength ?>">
                    <?php if ($role === 'admin'): ?>
                        <input type="hidden" id="topbar-global-search-batch-id" name="batch_id" value="<?= $topbarSearchBatchId > 0 ? (int) $topbarSearchBatchId : '' ?>">
                    <?php endif; ?>
                    <button type="submit" class="topbar-search-submit" aria-label="Search"><?= ui_lucide_icon('arrow-right') ?></button>

                    <div class="topbar-search-results" id="topbar-global-search-results" hidden>
                        <div class="topbar-search-results-state" data-search-state>Type to search...</div>
                        <ul class="topbar-search-results-list" id="topbar-global-search-results-list"></ul>
                        <a href="/dashboard/search" class="topbar-search-results-footer" data-search-view-all>View all results</a>
                    </div>
                </form>

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
            const globalSearchForm = document.getElementById('topbar-global-search-form');
            const globalSearchInput = document.getElementById('topbar-global-search-input');
            const globalSearchBatchInput = document.getElementById('topbar-global-search-batch-id');
            const globalSearchResults = document.getElementById('topbar-global-search-results');
            const globalSearchResultsList = document.getElementById('topbar-global-search-results-list');

            function initGlobalSearch() {
                if (!globalSearchForm || !globalSearchInput || !globalSearchResults || !globalSearchResultsList) {
                    return;
                }

                const isAdminSearch = globalSearchForm.dataset.isAdmin === '1';
                const minLength = Math.max(1, Number(globalSearchInput.dataset.minLength || '2'));
                const stateNode = globalSearchResults.querySelector('[data-search-state]');
                const viewAllLink = globalSearchResults.querySelector('[data-search-view-all]');
                let debounceTimer = 0;
                let fetchToken = 0;

                function currentBatchIdFromUrl() {
                    const params = new URLSearchParams(window.location.search);
                    const candidate = Number(params.get('batch_id') || 0);
                    return Number.isFinite(candidate) && candidate > 0 ? candidate : 0;
                }

                function syncBatchContext() {
                    if (!isAdminSearch || !globalSearchBatchInput) {
                        return;
                    }

                    const batchId = currentBatchIdFromUrl();
                    if (batchId > 0) {
                        globalSearchBatchInput.value = String(batchId);
                    }
                }

                function openResults() {
                    globalSearchResults.hidden = false;
                    globalSearchForm.classList.add('is-open');
                }

                function closeResults() {
                    globalSearchResults.hidden = true;
                    globalSearchForm.classList.remove('is-open');
                }

                function setState(text) {
                    if (!stateNode) {
                        return;
                    }
                    stateNode.hidden = false;
                    stateNode.textContent = text;
                }

                function clearList() {
                    globalSearchResultsList.innerHTML = '';
                }

                function updateViewAllLink(query) {
                    if (!viewAllLink) {
                        return;
                    }

                    const url = new URL('/dashboard/search', window.location.origin);
                    if (query.trim() !== '') {
                        url.searchParams.set('q', query.trim());
                    }
                    if (isAdminSearch && globalSearchBatchInput && globalSearchBatchInput.value) {
                        url.searchParams.set('batch_id', globalSearchBatchInput.value);
                    }
                    viewAllLink.setAttribute('href', url.pathname + url.search);
                }

                function addResultItem(item) {
                    const itemUrl = String(item.target_url || '/dashboard/search');
                    const itemTitle = String(item.title || 'Untitled');
                    const itemType = String(item.item_type_label || 'Item');
                    const itemSubject = String(item.subject_code || '');
                    const itemTime = String(item.event_label || '');

                    const li = document.createElement('li');
                    li.className = 'topbar-search-result-item';

                    const link = document.createElement('a');
                    link.href = itemUrl;
                    link.className = 'topbar-search-result-link';

                    const meta = document.createElement('div');
                    meta.className = 'topbar-search-result-meta';

                    const badge = document.createElement('span');
                    badge.className = 'topbar-search-result-badge';
                    badge.textContent = itemType;
                    meta.appendChild(badge);

                    if (itemSubject !== '') {
                        const subject = document.createElement('span');
                        subject.className = 'topbar-search-result-subject';
                        subject.textContent = itemSubject;
                        meta.appendChild(subject);
                    }

                    if (itemTime !== '') {
                        const time = document.createElement('span');
                        time.className = 'topbar-search-result-time';
                        time.textContent = itemTime;
                        meta.appendChild(time);
                    }

                    const title = document.createElement('strong');
                    title.textContent = itemTitle;

                    link.appendChild(meta);
                    link.appendChild(title);
                    li.appendChild(link);
                    globalSearchResultsList.appendChild(li);
                }

                function renderItems(items) {
                    clearList();
                    if (!Array.isArray(items) || items.length === 0) {
                        setState('No results found.');
                        return;
                    }

                    if (stateNode) {
                        stateNode.hidden = true;
                    }

                    items.forEach(addResultItem);
                }

                function fetchResults(query) {
                    syncBatchContext();
                    updateViewAllLink(query);

                    if (query.length < minLength) {
                        clearList();
                        setState('Type at least ' + minLength + ' characters.');
                        if (query.length === 0) {
                            closeResults();
                        } else {
                            openResults();
                        }
                        return;
                    }

                    if (isAdminSearch && (!globalSearchBatchInput || !globalSearchBatchInput.value)) {
                        clearList();
                        setState('Select a batch context first.');
                        openResults();
                        return;
                    }

                    const token = ++fetchToken;
                    clearList();
                    setState('Searching...');
                    openResults();

                    const url = new URL('/dashboard/search', window.location.origin);
                    url.searchParams.set('ajax', '1');
                    url.searchParams.set('limit', '8');
                    url.searchParams.set('q', query);
                    if (isAdminSearch && globalSearchBatchInput && globalSearchBatchInput.value) {
                        url.searchParams.set('batch_id', globalSearchBatchInput.value);
                    }

                    fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('Search failed');
                            }
                            return response.json();
                        })
                        .then(function (payload) {
                            if (token !== fetchToken) {
                                return;
                            }

                            if (payload && payload.requires_batch) {
                                clearList();
                                setState('Select a batch context first.');
                                return;
                            }

                            renderItems(payload && Array.isArray(payload.items) ? payload.items : []);
                        })
                        .catch(function () {
                            if (token !== fetchToken) {
                                return;
                            }
                            clearList();
                            setState('Unable to load results right now.');
                        });
                }

                globalSearchInput.addEventListener('input', function () {
                    const query = globalSearchInput.value.trim();
                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(function () {
                        fetchResults(query);
                    }, 200);
                });

                globalSearchInput.addEventListener('focus', function () {
                    const query = globalSearchInput.value.trim();
                    if (query.length > 0) {
                        fetchResults(query);
                        return;
                    }
                    updateViewAllLink('');
                    clearList();
                    setState('Type at least ' + minLength + ' characters.');
                    openResults();
                });

                globalSearchForm.addEventListener('submit', function (event) {
                    const query = globalSearchInput.value.trim();
                    if (query.length < minLength) {
                        event.preventDefault();
                        clearList();
                        setState('Type at least ' + minLength + ' characters.');
                        openResults();
                        return;
                    }

                    if (isAdminSearch && (!globalSearchBatchInput || !globalSearchBatchInput.value)) {
                        event.preventDefault();
                        clearList();
                        setState('Select a batch context first.');
                        openResults();
                    }
                });

                document.addEventListener('click', function (event) {
                    const target = event.target;
                    if (!(target instanceof Node)) return;
                    if (globalSearchForm.contains(target)) return;
                    closeResults();
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closeResults();
                    }
                });
            }

            initGlobalSearch();
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
