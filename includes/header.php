<?php
require_once __DIR__ . '/session.php';
$siteSettings = site_settings();
$pageTitle = $pageTitle ?? 'AniScope by Arafat';
$activePage = $activePage ?? '';
$bodyClass = $bodyClass ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bangla anime reviews, character analysis, manga updates and anime news.">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="/assets/images/logo-mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header" id="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="/index.php" aria-label="AniScope home">
            <img src="/assets/images/logo-mark.svg" alt="" width="38" height="38">
            <span><?= e($siteSettings['site_name'] ?? 'AniScope by Arafat') ?><em>anime editorial</em></span>
        </a>
        <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false"><span></span><span></span><span></span></button>
        <nav class="main-nav" aria-label="Main navigation">
            <?php foreach (['Home' => '/index.php', 'Anime' => '/anime.php', 'Characters' => '/characters.php', 'Manga' => '/manga.php', 'News' => '/news.php'] as $label => $href): ?>
                <a class="<?= $activePage === strtolower($label) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <?php if (logged_in()): ?>
                <?php if (is_staff()): ?><a href="/admin/dashboard.php">Dashboard</a><?php endif; ?>
                <a class="nav-admin" href="/logout.php"><?= e(current_user()['username'] ?? 'Account') ?> · Logout</a>
            <?php else: ?>
                <a class="nav-admin" href="/login.php">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
