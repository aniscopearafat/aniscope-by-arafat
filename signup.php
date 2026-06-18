<?php
require_once __DIR__ . '/includes/session.php';
if (logged_in()) { header('Location: /index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        $response = api_request('POST', '/api/signup', ['username'=>trim($_POST['username']??''), 'email'=>trim($_POST['email']??''), 'password'=>$_POST['password']??'']);
        if ($response['ok']) { establish_login($response['data']); header('Location: /index.php'); exit; }
        $error = api_message($response, 'Signup failed.');
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Join AniScope</title><link rel="icon" href="/assets/images/logo-mark.svg" type="image/svg+xml"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/style.css"></head><body class="login-body"><div class="login-art"><img src="/assets/images/login-original.svg" alt="Original anime-inspired moonlit artwork"><a class="brand" href="/index.php"><img src="/assets/images/logo-mark.svg" alt="" width="38"><span>AniScope <em>by Arafat</em></span></a><div><span class="eyebrow">Join the conversation</span><h1>Your take<br>matters.</h1><p>Like stories and leave thoughtful comments—no OTP detour.</p></div></div><main class="login-panel"><div class="login-box signup-box"><span class="eyebrow">Member signup</span><h2>Create your account</h2><p>Use Gmail, iCloud, Hotmail, or Yahoo.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Username<input name="username" pattern="[A-Za-z0-9_]+" minlength="3" maxlength="40" required></label><label>Email<input type="email" name="email" placeholder="you@gmail.com" required></label><div class="form-row"><label>Password<input type="password" name="password" minlength="6" required></label><label>Confirm password<input type="password" name="confirm_password" minlength="6" required></label></div><button class="button primary full" type="submit">Create account →</button></form><p class="auth-switch">Already a member? <a href="/login.php">Sign in</a></p></div></main><script src="/assets/js/main.js"></script></body></html>
