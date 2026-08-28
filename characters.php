<?php
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/pagination.php';

$characters = api_data('/api/characters');

if (!is_array($characters)) {
    $characters = [];
}

$pagination = paginate_items(
    $characters,
    20
);

$visibleCharacters = $pagination['items'];
$pageTitle = 'Characters — AniScope by Arafat'; $activePage = 'characters';
require __DIR__ . '/includes/header.php'; require_once __DIR__ . '/includes/cards.php';
$charactersCover = setting_value($siteSettings, 'characters_cover_url', '/assets/images/characters-banner.svg');
?>
<section class="page-hero character-page-hero" style="background:linear-gradient(rgba(8,7,18,.1),var(--ink)),url('<?= e(image_url($charactersCover)) ?>') center/cover"><div class="container reveal"><span class="eyebrow">Original character archive</span><h1>Characters</h1><p>Meet heroes, rivals, mentors, and wanderers from original anime-inspired worlds.</p></div></section>
<section class="section section-dark"><div class="container"><div class="filter-bar"><span><?= (int) $pagination['total_items'] ?> profiles<?php if ($pagination['total_items']): ?> · Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?><?php endif; ?></span><div class="search-shell"><span>⌕</span><input id="character-search" type="search" placeholder="Search characters…"></div></div><div class="character-grid" id="character-grid"><?php foreach ($visibleCharacters as $character) character_card($character); ?><?php if (!$visibleCharacters) empty_state('No character profiles have been added yet.'); ?></div><?php render_pagination($pagination['page'], $pagination['total_pages']); ?><?php show_native_ad(); ?></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
