<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@8..144,100..1000&display=swap" rel="stylesheet">
</head>
<body>
    <div class="error-page">
        <a href="/" class="error-logo-link" aria-label="<?= e(config('app.name')) ?> Home">
            <img src="<?= asset('img/black-logo.png') ?>" alt="<?= e(config('app.name')) ?>" class="error-logo">
        </a>
        <h1><?= $code ?></h1>
        <p><?= e($message) ?></p>
        <a href="/" class="btn btn-primary">Go Home</a>
    </div>
</body>
</html>
