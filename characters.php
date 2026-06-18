<?php
require_once __DIR__ . '/includes/api.php';
$characters = api_data('/api/characters');
$pageTitle = 'Characters — AniScope by Arafat'; $activePage = 'characters';
require __DIR__ . '/includes/header.php'; require_once __DIR__ . '/includes/cards.php';
?>
<section class="page-hero character-page-hero"><div class="container reveal"><span class="eyebrow">Original character archive</span><h1>Characters</h1><p>Meet heroes, rivals, mentors, and wanderers from original anime-inspired worlds.</p></div></section>
<section class="section section-dark"><div class="container"><div class="filter-bar"><span><?= count($characters) ?> profiles</span><div class="search-shell"><span>⌕</span><input id="character-search" type="search" placeholder="Search characters…"></div></div><div class="character-grid" id="character-grid"><?php foreach ($characters as $character) character_card($character); ?><?php if (!$characters) empty_state('Start the backend to load character profiles.'); ?></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
