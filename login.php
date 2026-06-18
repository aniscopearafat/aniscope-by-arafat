<?php
require_once __DIR__ . '/includes/session.php';
if (logged_in()) { header('Location: ' . (is_staff() ? '/admin/dashboard.php' : '/index.php')); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $response = api_request('POST', '/api/login', ['username' => trim($_POST['username'] ?? ''), 'password' => $_POST['password'] ?? '']);
    if ($response['ok']) {
        establish_login($response['data']);
        header('Location: ' . (is_staff() ? '/admin/dashboard.php' : ($_SESSION['return_to'] ?? '/index.php')));
        unset($_SESSION['return_to']); exit;
    }
    $error = api_message($response, 'Login failed.');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — AniScope by Arafat</title><link rel="icon" href="/assets/images/logo-mark.svg" type="image/svg+xml"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/style.css"></head><body class="login-body"><div class="login-art"><img src="/assets/images/login-original.svg" alt="Original anime-inspired moonlit artwork"><a class="brand" href="/index.php"><img src="/assets/images/logo-mark.svg" alt="" width="38"><span>AniScope <em>by Arafat</em></span></a><div><span class="eyebrow">One doorway, every role</span><h1>Welcome<br>back.</h1><p>Members join the conversation. Staff shape the stories.</p></div></div><main class="login-panel"><div class="login-box"><span class="eyebrow">Sign in</span><h2>Continue to AniScope</h2><p>Use your username or member email.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Username or email<input type="text" name="username" autocomplete="username" required></label><label>Password<div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" required><button type="button" data-toggle-password>Show</button></div></label><button class="button primary full" type="submit">Sign in →</button></form><p class="auth-switch">New here? <a href="/signup.php">Create a member account</a></p><a class="back-link" href="/index.php">← Back to AniScope</a></div></main><script src="/assets/js/main.js"></script></body></html>
