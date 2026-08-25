<?php

function post_card(array $post): void { ?>

<article class="content-card reveal">

    <a
        class="card-image"
        href="/article.php?id=<?= (int)$post['id'] ?>"
    >
        <img
            src="<?= e(image_url($post['image_url'])) ?>"
            alt="<?= e($post['title']) ?>"
            loading="lazy"
        >

        <span class="category-tag">
            <?= e($post['category']) ?>
        </span>
    </a>


    <div class="card-body">

        <span class="card-meta">
            <?= e(format_date($post['created_at'])) ?>
            · 5 min read
        </span>

        <h3>
            <a href="/article.php?id=<?= (int)$post['id'] ?>">
                <?= e($post['title']) ?>
            </a>
        </h3>

        <p>
            <?= e($post['excerpt']) ?>
        </p>


        <div class="card-actions">

            <a
                class="text-link"
                href="/article.php?id=<?= (int)$post['id'] ?>"
            >
                Read story <span>→</span>
            </a>


            <?php if (!empty($post['stream_anime_id'])): ?>

                <a
                    class="card-watch-link"
                    href="/watch.php?anime=<?= (int)$post['stream_anime_id'] ?>"
                >
                    ▶ Watch
                </a>

            <?php endif; ?>

        </div>

    </div>

</article>

<?php }


function character_card(array $character): void { ?>

<article class="character-card reveal">

    <a href="/character.php?id=<?= (int)$character['id'] ?>">
        <img
            src="<?= e(image_url($character['image_url'])) ?>"
            alt="<?= e($character['name']) ?>"
            loading="lazy"
        >
    </a>

    <div class="character-overlay">

        <span>
            <?= e($character['anime_name']) ?>
        </span>

        <h3>
            <a href="/character.php?id=<?= (int)$character['id'] ?>">
                <?= e($character['name']) ?>
            </a>
        </h3>

        <a href="/character.php?id=<?= (int)$character['id'] ?>">
            View profile →
        </a>

    </div>

</article>

<?php }


function stream_anime_card(array $anime): void { ?>

<article class="stream-card reveal">

    <a
        class="stream-card-poster"
        href="/watch.php?anime=<?= (int)$anime['id'] ?>"
    >

        <?php if (!empty($anime['poster_url'])): ?>

            <img
                src="<?= e($anime['poster_url']) ?>"
                alt="<?= e($anime['title']) ?>"
                loading="lazy"
            >

        <?php endif; ?>

        <span class="stream-card-play">
            ▶
        </span>

    </a>


    <div class="stream-card-body">

        <span class="eyebrow">
            Watch Anime
        </span>

        <h3>
            <a href="/watch.php?anime=<?= (int)$anime['id'] ?>">
                <?= e($anime['title']) ?>
            </a>
        </h3>

        <?php if (!empty($anime['genres'])): ?>

            <p>
                <?= e($anime['genres']) ?>
            </p>

        <?php endif; ?>

        <a
            class="button primary stream-watch-button"
            href="/watch.php?anime=<?= (int)$anime['id'] ?>"
        >
            ▶ Watch Now
        </a>

    </div>

</article>

<?php }


function empty_state(string $message = 'Nothing has been published here yet.'): void { ?>

<div class="empty-state">
    <div>✦</div>
    <h3>Still gathering stories</h3>
    <p><?= e($message) ?></p>
</div>

<?php }
