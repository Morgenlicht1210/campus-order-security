<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function student(): ?array
{
    return $_SESSION['student'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        redirect('admin/');
    }
}

function require_student(): void
{
    if (!student()) {
        redirect('register.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF token invalid.');
    }
}

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'display_name' => $row['display_name'],
        'role' => $row['role'],
    ];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function student_logout(): void
{
    unset($_SESSION['student']);
}
