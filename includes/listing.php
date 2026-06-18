<?php
require_once __DIR__ . '/api.php';
$posts = api_data('/api/posts?category=' . urlencode($category) . '&status=published');
require __DIR__ . '/header.php';
require_once __DIR__ . '/cards.php';
$descriptions = ['Anime' => 'Deep dives, thoughtful reviews, and beginner-friendly guides.', 'Manga' => 'Chapter updates, story analysis, and page-turning discoveries.', 'News' => 'The latest signals, trends, and conversations from anime culture.'];
?>
<section class="page-hero"><div class="container reveal"><span class="eyebrow">AniScope collection</span><h1><?= e($category) ?></h1><p><?= e($descriptions[$category]) ?></p></div></section>
<section class="section section-dark"><div class="container"><div class="filter-bar"><span><?= count($posts) ?> stories</span><div><button class="filter active">Latest</button><button class="filter">Popular</button></div></div><div class="card-grid"><?php foreach ($posts as $post) post_card($post); ?><?php if (!$posts) empty_state('Start the backend to load published stories.'); ?></div></div></section>
<?php require __DIR__ . '/footer.php'; ?>
