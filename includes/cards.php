<?php
function post_card(array $post): void { ?>
<article class="content-card reveal">
    <a class="card-image" href="/article.php?id=<?= (int)$post['id'] ?>">
        <img src="<?= e(image_url($post['image_url'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
        <span class="category-tag"><?= e($post['category']) ?></span>
    </a>
    <div class="card-body">
        <span class="card-meta"><?= e(format_date($post['created_at'])) ?> · 5 min read</span>
        <h3><a href="/article.php?id=<?= (int)$post['id'] ?>"><?= e($post['title']) ?></a></h3>
        <p><?= e($post['excerpt']) ?></p>
        <a class="text-link" href="/article.php?id=<?= (int)$post['id'] ?>">Read story <span>→</span></a>
    </div>
</article>
<?php }

function character_card(array $character): void { ?>
<article class="character-card reveal">
    <a href="/character.php?id=<?= (int)$character['id'] ?>"><img src="<?= e(image_url($character['image_url'])) ?>" alt="<?= e($character['name']) ?>" loading="lazy"></a>
    <div class="character-overlay">
        <span><?= e($character['anime_name']) ?></span>
        <h3><a href="/character.php?id=<?= (int)$character['id'] ?>"><?= e($character['name']) ?></a></h3>
        <a href="/character.php?id=<?= (int)$character['id'] ?>">View profile →</a>
    </div>
</article>
<?php }

function empty_state(string $message = 'Nothing has been published here yet.'): void { ?>
<div class="empty-state"><div>✦</div><h3>Still gathering stories</h3><p><?= e($message) ?></p></div>
<?php }
