<?php
require_once __DIR__ . '/includes/api.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$character = $id ? api_data('/api/characters/' . $id) : [];
if (!$character) { http_response_code(404); $pageTitle='Character Not Found — AniScope'; require __DIR__.'/includes/header.php'; require_once __DIR__.'/includes/cards.php'; echo '<section class="section not-found"><div class="container">'; empty_state('That character profile could not be found.'); echo '</div></section>'; require __DIR__.'/includes/footer.php'; exit; }
$pageTitle = $character['name'] . ' — AniScope by Arafat'; $activePage='characters'; require __DIR__.'/includes/header.php';
?>
<section class="profile-page"><div class="profile-glow"></div><div class="container profile-grid"><div class="profile-image reveal"><img src="<?= e(image_url($character['image_url'])) ?>" alt="<?= e($character['name']) ?>"></div><div class="profile-copy reveal"><span class="eyebrow"><?= e($character['anime_name']) ?></span><h1><?= e($character['name']) ?></h1><p class="lead"><?= e($character['bio']) ?></p><div class="profile-block"><span>Signature abilities</span><h2><?= e($character['abilities']) ?></h2></div><div class="profile-facts"><div><strong>Origin</strong><span>Original AniScope universe</span></div><div><strong>Alignment</strong><span>Heroic wanderer</span></div><div><strong>Status</strong><span>Active</span></div></div><a class="button ghost" href="/characters.php">← All characters</a></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
