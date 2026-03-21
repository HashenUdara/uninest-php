<?php

/**
 * Core Helpers
 * 
 * Utility functions available globally via Composer autoload.
 */

// ──────────────────────────────────────
// Environment
// ──────────────────────────────────────

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false) return $default;

    // Cast common string booleans
    return match (strtolower((string) $value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $value,
    };
}

// ──────────────────────────────────────
// Config
// ──────────────────────────────────────

function config(string $key, mixed $default = null): mixed
{
    static $configs = [];
    $parts = explode('.', $key);
    $file  = $parts[0];

    if (!isset($configs[$file])) {
        $path = BASE_PATH . '/config/' . $file . '.php';
        $configs[$file] = file_exists($path) ? require $path : [];
    }

    return $configs[$file][$parts[1] ?? null] ?? $default;
}

// ──────────────────────────────────────
// Views & Layouts
// ──────────────────────────────────────

function view(string $path, array $data = [], ?string $layout = null): void
{
    extract($data);

    // Determine full path — check modules first, then global views
    if (str_contains($path, '::')) {
        // module::view format → modules/{module}/views/{view}.php
        [$module, $viewName] = explode('::', $path);
        $viewFile = BASE_PATH . '/modules/' . $module . '/views/' . $viewName . '.php';
    } else {
        $viewFile = BASE_PATH . '/views/' . $path . '.php';
    }

    if (!file_exists($viewFile)) {
        abort(500, "View not found: {$path}");
    }

    if ($layout) {
        // Capture view content, then wrap in layout
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = BASE_PATH . '/views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            abort(500, "Layout not found: {$layout}");
        }
        require $layoutFile;
    } else {
        require $viewFile;
    }
}

// ──────────────────────────────────────
// HTTP Helpers
// ──────────────────────────────────────

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $title = match ($code) {
        404 => 'Page Not Found',
        403 => 'Forbidden',
        500 => 'Server Error',
        default => 'Error',
    };
    if (empty($message)) $message = $title;
    require BASE_PATH . '/views/layouts/error.php';
    exit;
}

function back(): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
    redirect($referer);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

function request_input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

// ──────────────────────────────────────
// CSRF Protection
// ──────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        abort(403, 'Invalid CSRF token.');
    }
    // Regenerate after successful check
    unset($_SESSION['_csrf_token']);
}

// ──────────────────────────────────────
// Session Flash Messages
// ──────────────────────────────────────

function flash(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function get_flash(string $key, mixed $default = null): mixed
{
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function has_flash(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

// ──────────────────────────────────────
// Old Input (form repopulation)
// ──────────────────────────────────────

function old(string $key, string $default = ''): string
{
    return htmlspecialchars($_SESSION['_old_input'][$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function flash_old_input(): void
{
    $_SESSION['_old_input'] = $_POST;
}

function clear_old_input(): void
{
    unset($_SESSION['_old_input']);
}

// ──────────────────────────────────────
// Asset URL
// ──────────────────────────────────────

function asset(string $path): string
{
    return config('app.url') . '/assets/' . ltrim($path, '/');
}

// ──────────────────────────────────────
// Auth Helpers
// ──────────────────────────────────────

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_user_by_id(int $id): ?array
{
    return db_fetch(
        'SELECT id, name, email, role, academic_year, university_id, batch_id, first_approved_batch_id, created_at, updated_at FROM users WHERE id = ?',
        [$id]
    );
}

function auth_set_session_user_by_id(int $id): void
{
    $user = auth_user_by_id($id);
    if ($user) {
        $_SESSION['user'] = $user;
    }
}

function auth_refresh_session_user(): void
{
    if (!isset($_SESSION['user']['id'])) {
        return;
    }

    $user = auth_user_by_id((int) $_SESSION['user']['id']);
    if ($user) {
        $_SESSION['user'] = $user;
    } else {
        unset($_SESSION['user']);
    }
}

function auth_check(): bool
{
    return isset($_SESSION['user']);
}

function auth_id(): ?int
{
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Check if the current user has the given role.
 * Supports role hierarchy: admin > moderator > coordinator > student
 */
function is_role(string $role): bool
{
    $user = auth_user();
    if (!$user) return false;

    $hierarchy = [
        'student'     => 1,
        'coordinator' => 2,
        'moderator'   => 3,
        'admin'       => 4,
    ];

    $userLevel = $hierarchy[$user['role']] ?? 0;
    $requiredLevel = $hierarchy[$role] ?? 0;

    return $userLevel >= $requiredLevel;
}

function user_role(): ?string
{
    return auth_user()['role'] ?? null;
}

// ──────────────────────────────────────
// Misc
// ──────────────────────────────────────

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    return config('app.url') . '/' . ltrim($path, '/');
}

function is_current_url(string $path): bool
{
    $current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return rtrim($current, '/') === rtrim($path, '/');
}

// ──────────────────────────────────────
// SMTP Mail (Gmail)
// ──────────────────────────────────────

function smtp_is_configured(): bool
{
    $username = trim((string) env('GMAIL_USERNAME', ''));
    $password = trim((string) env('GMAIL_APP_PASSWORD', ''));
    return $username !== '' && $password !== '';
}

function smtp_send_email(string $toEmail, string $subject, string $textBody): bool
{
    $username = trim((string) env('GMAIL_USERNAME', ''));
    $password = trim((string) env('GMAIL_APP_PASSWORD', ''));

    if ($username === '' || $password === '') {
        error_log('SMTP credentials are missing. Set GMAIL_USERNAME and GMAIL_APP_PASSWORD.');
        return false;
    }

    $host = 'smtp.gmail.com';
    $port = 587;
    $timeout = 20;

    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    if (!$socket) {
        error_log("SMTP connection failed: {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $heloHost = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
    $fromName = (string) config('app.name', 'Uninest');

    try {
        if (!smtp_expect_code($socket, [220])) return false;
        if (!smtp_send_command($socket, "EHLO {$heloHost}", [250])) return false;
        if (!smtp_send_command($socket, 'STARTTLS', [220])) return false;
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP STARTTLS handshake failed.');
            return false;
        }
        if (!smtp_send_command($socket, "EHLO {$heloHost}", [250])) return false;
        if (!smtp_send_command($socket, 'AUTH LOGIN', [334])) return false;
        if (!smtp_send_command($socket, base64_encode($username), [334])) return false;
        if (!smtp_send_command($socket, base64_encode($password), [235])) return false;
        if (!smtp_send_command($socket, "MAIL FROM:<{$username}>", [250])) return false;
        if (!smtp_send_command($socket, "RCPT TO:<{$toEmail}>", [250, 251])) return false;
        if (!smtp_send_command($socket, 'DATA', [354])) return false;

        $sanitizedSubject = str_replace(["\r", "\n"], '', $subject);
        $headers = [];
        $headers[] = "From: {$fromName} <{$username}>";
        $headers[] = "To: <{$toEmail}>";
        $headers[] = "Subject: {$sanitizedSubject}";
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $textBody;
        $payload = str_replace(["\r\n", "\r"], "\n", $payload);
        $payload = str_replace("\n", "\r\n", $payload);
        $payload = preg_replace('/^\./m', '..', $payload) ?? $payload;

        fwrite($socket, $payload . "\r\n.\r\n");
        if (!smtp_expect_code($socket, [250])) return false;

        smtp_send_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (\Throwable $e) {
        error_log('SMTP send failure: ' . $e->getMessage());
        if (is_resource($socket)) fclose($socket);
        return false;
    }
}

function smtp_send_command($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect_code($socket, $expectedCodes);
}

function smtp_expect_code($socket, array $expectedCodes): bool
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ($response === '') {
        error_log('SMTP empty response.');
        return false;
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        error_log('SMTP unexpected response: ' . trim($response));
        return false;
    }

    return true;
}
