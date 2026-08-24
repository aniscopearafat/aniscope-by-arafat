<?php
require_once __DIR__ . '/includes/api.php';
$posts = api_data('/api/posts?status=published');
$characters = api_data('/api/characters');
$byCategory = fn(string $category) => array_values(array_filter($posts, fn($post) => $post['category'] === $category));
$pageTitle = 'AniScope by Arafat — Anime, Manga & Characters';
$activePage = 'home';
$bodyClass = 'home-page';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/cards.php';
?>
<section class="hero">
    <div class="hero-orb orb-one"></div><div class="hero-orb orb-two"></div>
    <div class="container hero-content reveal">
        <span class="eyebrow">Beyond the frame · Inside the story</span>
        <h1>AniScope<br><span>by Arafat</span></h1>
        <p><?= e($siteSettings['site_tagline'] ?? 'Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News') ?></p>
        <div class="hero-actions"><a class="button primary" href="/anime.php">Explore Anime <span>→</span></a><a class="button ghost" href="/characters.php">Meet Characters</a></div>
    </div>
    <div class="hero-art"><img src="<?= e(image_url($siteSettings['home_background_url'] ?: '/assets/images/hero-original.svg')) ?>" alt="AniScope homepage hero artwork"></div>
    <div class="scroll-cue"><span></span>SCROLL TO DISCOVER</div>
</section>

<section class="section section-dark">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">What everyone is reading</span><h2>Trending Anime</h2></div><a class="text-link" href="/anime.php">View all <span>→</span></a></div>
        <div class="card-grid"><?php foreach (array_slice($byCategory('Anime'), 0, 4) as $post) post_card($post); ?><?php if (!$byCategory('Anime')) empty_state(); ?></div>
    </div>
</section>
<section class="section section-gradient">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">Faces behind the legends</span><h2>Popular Characters</h2></div><a class="text-link" href="/characters.php">View all <span>→</span></a></div>
        <div class="character-grid"><?php foreach (array_slice($characters, 0, 4) as $character) character_card($character); ?><?php if (!$characters) empty_state(); ?></div>
    </div>
</section>
<?php foreach ([['Manga', 'Fresh from the page', 'Manga Updates', '/manga.php'], ['News', 'Signals from the anime world', 'Anime News', '/news.php']] as [$category, $eyebrow, $heading, $link]): ?>
<section class="section section-dark">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow"><?= $eyebrow ?></span><h2><?= $heading ?></h2></div><a class="text-link" href="<?= $link ?>">View all <span>→</span></a></div>
        <div class="card-grid"><?php foreach (array_slice($byCategory($category), 0, 4) as $post) post_card($post); ?><?php if (!$byCategory($category)) empty_state(); ?></div>
    </div>
</section>
<?php endforeach; ?>
<section class="cta-band"><div class="container"><span class="eyebrow">Your next obsession starts here</span><h2>Explore a universe of stories.</h2><a class="button primary" href="/anime.php">Start exploring →</a></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
