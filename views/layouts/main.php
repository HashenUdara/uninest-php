<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(config('app.name')) ?></title>
    <meta name="description" content="Uninest — Your Learning Management System">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAuthPage = in_array($requestPath, ['/login', '/register'], true);
?>
<body class="<?= $isAuthPage ? 'auth-page-body' : '' ?>">
    <?php if (!$isAuthPage): ?>
        <header class="main-header">
            <div class="container">
                <a href="/" class="logo"><?= e(config('app.name')) ?></a>
                <nav class="main-nav">
                    <?php if (auth_check()): ?>
                        <a href="/dashboard">Dashboard</a>
                        <a href="/logout" class="btn btn-sm btn-outline">Logout</a>
                    <?php else: ?>
                        <a href="/login">Sign In</a>
                        <a href="/register" class="btn btn-sm btn-primary">Get Started</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="main-content">
            <div class="container">
                <?php if ($success = get_flash('success')): ?>
                    <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>
                <?= $content ?>
            </div>
        </main>

        <footer class="main-footer">
            <div class="container">
                <p>&copy; <?= date('Y') ?> <?= e(config('app.name')) ?>. All rights reserved.</p>
            </div>
        </footer>
    <?php else: ?>
        <main class="auth-main">
            <?= $content ?>
        </main>
    <?php endif; ?>
</body>
</html>
