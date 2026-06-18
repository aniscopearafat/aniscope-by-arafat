<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/session.php';

function require_admin(): void
{
    if (!is_staff()) { header('Location: /login.php'); exit; }
}
function admin_token(): string { return auth_token(); }
