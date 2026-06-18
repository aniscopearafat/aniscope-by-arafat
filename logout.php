<?php
require_once __DIR__ . '/includes/session.php';
logout_user();
header('Location: /index.php');
exit;
