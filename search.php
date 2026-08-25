<?php

require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/cards.php';


/*
|--------------------------------------------------------------------------
| Search query
|--------------------------------------------------------------------------
*/

$query = trim($_GET['q'] ?? '');

$pageTitle = $query
    ? 'Search: ' . $query . ' — AniScope by Arafat'
    : 'Search — AniScope by Arafat';

$activePage = '';


/*
|--------------------------------------------------------------------------
| Results
|--------------------------------------------------------------------------
*/

$posts = [];
$characters = [];


if ($query !== '' && mb_strlen($query) >= 2) {

    /*
     * Get published posts.
     */
    $allPosts = api_data(
        '/api/posts?status=published'
    );

    if (is_array($allPosts)) {

        foreach ($allPosts as $post) {

            $haystack = strtolower(
                ($post['title'] ?? '') . ' ' .
                ($post['excerpt'] ?? '') . ' ' .
                ($post['content'] ?? '') . ' ' .
                ($post['category'] ?? '')
            );

            if (
                str_contains(
                    $haystack,
                    strtolower($query)
                )
            ) {

                $posts[] = $post;
            }
        }
    }


    /*
     * Get characters.
     */
    $allCharacters = api_data(
        '/api/characters'
    );

    if (is_array($allCharacters)) {

        foreach ($allCharacters as $character) {

            $haystack = strtolower(
                ($character['name'] ?? '') . ' ' .
                ($character['original_series'] ?? '') . ' ' .
                ($character['biography'] ?? '') . ' ' .
                ($character['abilities'] ?? '')
            );

            if (
                str_contains(
                    $haystack,
                    strtolower($query)
                )
            ) {

                $characters[] = $character;
            }
        }
    }
}


require __DIR__ . '/includes/header.php';

?>


<!-- =========================================================
     SEARCH HERO
========================================================= -->

<section class="page-hero">

    <div class="container reveal">

        <span class="eyebrow">
            AniScope search
        </span>

        <h1>
            Search AniScope
        </h1>

        <p>
            Find anime, manga, news, characters and stories.
        </p>


        <!-- Large search -->

        <form
            class="search-page-form"
            action="/search.php"
            method="get"
        >

            <input
                type="search"
                name="q"
                value="<?= e($query) ?>"
                placeholder="Search anime, characters, manga..."
                autocomplete="off"
                minlength="2"
                required
            >

            <button
                class="button primary"
                type="submit"
            >
                Search
            </button>

        </form>

    </div>

</section>


<!-- =========================================================
     SEARCH RESULTS
========================================================= -->

<section class="section section-dark">

    <div class="container">


        <?php if ($query === ''): ?>

            <div class="search-empty">

                <h2>
                    What are you looking for?
                </h2>

                <p>
                    Search for an anime, manga, character,
                    or news story.
                </p>

            </div>


        <?php elseif (mb_strlen($query) < 2): ?>

            <div class="search-empty">

                <h2>
                    Search is too short
                </h2>

                <p>
                    Please enter at least 2 characters.
                </p>

            </div>


        <?php else: ?>


            <!-- =================================================
                 RESULT COUNT
            ================================================== -->

            <div class="filter-bar">

                <span>

                    Search results for
                    <strong>
                        "<?= e($query) ?>"
                    </strong>

                </span>

                <span>

                    <?= count($posts) + count($characters) ?>

                    results

                </span>

            </div>


            <!-- =================================================
                 CHARACTERS
            ================================================== -->

            <?php if (!empty($characters)): ?>

                <div class="search-section">

                    <div class="section-heading">

                        <span class="eyebrow">
                            Characters
                        </span>

                        <h2>
                            Character Profiles
                        </h2>

                    </div>


                    <div
                        class="character-grid"
                    >

                        <?php foreach ($characters as $character): ?>

                            <?php
                            character_card($character);
                            ?>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 POSTS
            ================================================== -->

            <?php if (!empty($posts)): ?>

                <div class="search-section">

                    <div class="section-heading">

                        <span class="eyebrow">
                            Stories
                        </span>

                        <h2>
                            Anime, Manga & News
                        </h2>

                    </div>


                    <div class="card-grid">

                        <?php foreach ($posts as $post): ?>

                            <?php
                            post_card($post);
                            ?>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 NO RESULTS
            ================================================== -->

            <?php if (empty($posts) && empty($characters)): ?>

                <div class="search-empty">

                    <div class="search-empty-icon">
                        🔍
                    </div>

                    <h2>
                        No results found
                    </h2>

                    <p>
                        We couldn't find anything matching
                        <strong>
                            "<?= e($query) ?>"
                        </strong>.
                    </p>

                    <p>
                        Try another anime, character,
                        manga or news title.
                    </p>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 NATIVE AD
            ================================================== -->

            <?php show_native_ad(); ?>


        <?php endif; ?>

    </div>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>