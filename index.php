<?php

require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/streaming.php';

/*
|--------------------------------------------------------------------------
| Load homepage data
|--------------------------------------------------------------------------
*/

$posts = api_data('/api/posts?status=published');
$characters = api_data('/api/characters');
$streamAnime = stream_anime_list(true);


/*
|--------------------------------------------------------------------------
| Group posts by category once
|--------------------------------------------------------------------------
*/

$postsByCategory = [
    'Anime' => [],
    'Manga' => [],
    'News'  => [],
];

foreach ($posts as $post) {
    $category = $post['category'] ?? '';

    if (isset($postsByCategory[$category])) {
        $postsByCategory[$category][] = $post;
    }
}


/*
|--------------------------------------------------------------------------
| Page settings
|--------------------------------------------------------------------------
*/

$pageTitle = 'AniScope by Arafat — Anime, Manga & Characters';
$activePage = 'home';
$bodyClass = 'home-page';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/cards.php';

?>

<!-- ======================================================
     HERO
====================================================== -->

<section class="hero">

    <div class="hero-orb orb-one"></div>
    <div class="hero-orb orb-two"></div>

    <div class="container hero-content reveal">

        <span class="eyebrow">
            Beyond the frame · Inside the story
        </span>

        <h1>
            AniScope<br>
            <span>by Arafat</span>
        </h1>

        <p>
            <?= e(
                $siteSettings['site_tagline']
                ?? 'Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News'
            ) ?>
        </p>

        <div class="hero-actions">

            <a
                class="button primary"
                href="/anime.php"
            >
                Explore Anime <span>→</span>
            </a>

            <a
                class="button ghost"
                href="/characters.php"
            >
                Meet Characters
            </a>

        </div>

    </div>


    <!-- Hero artwork -->

    <div class="hero-art">

        <img
            src="<?= e(
                image_url(
                    $siteSettings['home_background_url']
                    ?? '/assets/images/hero-original.svg'
                )
            ) ?>"
            alt="AniScope homepage hero artwork"
            loading="eager"
            fetchpriority="high"
        >

    </div>


    <div class="scroll-cue">
        <span></span>
        SCROLL TO DISCOVER
    </div>

</section>


<!-- ======================================================
     TRENDING ANIME
====================================================== -->

<section class="section section-dark">

    <div class="container">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    What everyone is reading
                </span>

                <h2>
                    Trending Anime
                </h2>

            </div>

            <a
                class="text-link"
                href="/anime.php"
            >
                View all <span>→</span>
            </a>

        </div>


        <div class="card-grid">

            <?php
            $animePosts = array_slice(
                $postsByCategory['Anime'],
                0,
                4
            );
            ?>

            <?php foreach ($animePosts as $post): ?>

                <?php post_card($post); ?>

            <?php endforeach; ?>


            <?php if (!$animePosts): ?>

                <?php empty_state(); ?>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ======================================================
     POPULAR CHARACTERS
====================================================== -->

<section class="section section-gradient">

    <div class="container">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    Faces behind the legends
                </span>

                <h2>
                    Popular Characters
                </h2>

            </div>

            <a
                class="text-link"
                href="/characters.php"
            >
                View all <span>→</span>
            </a>

        </div>


        <div class="character-grid">

            <?php
            $popularCharacters = array_slice(
                $characters,
                0,
                4
            );
            ?>


            <?php foreach ($popularCharacters as $character): ?>

                <?php character_card($character); ?>

            <?php endforeach; ?>


            <?php if (!$popularCharacters): ?>

                <?php empty_state(); ?>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ======================================================
     MANGA
====================================================== -->

<section class="section section-dark">

    <div class="container">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    Fresh from the page
                </span>

                <h2>
                    Manga Updates
                </h2>

            </div>

            <a
                class="text-link"
                href="/manga.php"
            >
                View all <span>→</span>
            </a>

        </div>


        <div class="card-grid">

            <?php
            $mangaPosts = array_slice(
                $postsByCategory['Manga'],
                0,
                4
            );
            ?>


            <?php foreach ($mangaPosts as $post): ?>

                <?php post_card($post); ?>

            <?php endforeach; ?>


            <?php if (!$mangaPosts): ?>

                <?php empty_state(); ?>

            <?php endif; ?>

        </div>


        <!-- Adsterra Native Banner -->

        <?php show_native_ad(); ?>

    </div>

</section>


<!-- ======================================================
     ANIME NEWS
====================================================== -->

<section class="section section-dark">

    <div class="container">

        <div class="section-heading">

            <div>

                <span class="eyebrow">
                    Signals from the anime world
                </span>

                <h2>
                    Anime News
                </h2>

            </div>

            <a
                class="text-link"
                href="/news.php"
            >
                View all <span>→</span>
            </a>

        </div>


        <div class="card-grid">

            <?php
            $newsPosts = array_slice(
                $postsByCategory['News'],
                0,
                4
            );
            ?>


            <?php foreach ($newsPosts as $post): ?>

                <?php post_card($post); ?>

            <?php endforeach; ?>


            <?php if (!$newsPosts): ?>

                <?php empty_state(); ?>

            <?php endif; ?>

        </div>


        <!-- Adsterra Native Banner -->

        <?php show_native_ad(); ?>

    </div>

</section>


<!-- ======================================================
     FINAL AD
====================================================== -->

<section class="section ad-section">

    <div class="container">

        <?php show_native_ad(); ?>

    </div>

</section>


<!-- ======================================================
     CTA
====================================================== -->

<section class="cta-band">

    <div class="container">

        <span class="eyebrow">
            Your next obsession starts here
        </span>

        <h2>
            Explore a universe of stories.
        </h2>

        <a
            class="button primary"
            href="/anime.php"
        >
            Start exploring →
        </a>

    </div>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>