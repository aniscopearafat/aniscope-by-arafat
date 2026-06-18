<?php
declare(strict_types=1);
require_once __DIR__ . '/api.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS'])]);
    session_start();
}

function logged_in(): bool { return !empty($_SESSION['auth_token']); }
function current_user(): array { return $_SESSION['user'] ?? []; }
function auth_token(): string { return (string)($_SESSION['auth_token'] ?? ''); }
function is_staff(): bool { return in_array(current_user()['role'] ?? '', ['admin', 'moderator'], true); }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function csrf_token(): string { if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): void { if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('Invalid CSRF token.'); } }
function flash(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function pull_flash(): ?array { $value = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $value; }
function api_message(array $response, string $fallback): string { return $response['data']['detail'] ?? $response['error'] ?? $fallback; }

function establish_login(array $data): void
{
    session_regenerate_id(true);
    $_SESSION['auth_token'] = $data['access_token'];
    $_SESSION['user'] = $data['user'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
