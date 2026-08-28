<?php

require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/pagination.php';

$characters = api_data('/api/characters');

if (!is_array($characters)) {
    $characters = [];
}


/*
|--------------------------------------------------------------------------
| Global Character Search
|--------------------------------------------------------------------------
|
| Search ALL characters first.
| Pagination happens only after filtering.
|
*/

$searchQuery = isset($_GET['q'])
    ? trim((string) $_GET['q'])
    : '';

$filteredCharacters = $characters;

if ($searchQuery !== '') {

    $filteredCharacters = array_values(
        array_filter(
            $characters,
            function ($character) use ($searchQuery) {

                if (!is_array($character)) {
                    return false;
                }

                $name = (string) (
                    $character['name']
                    ?? ''
                );

                $series = (string) (
                    $character['anime_name']
                    ?? ''
                );

                /*
                 * Search character name + anime/series.
                 * stripos gives case-insensitive matching.
                 */
                return
                    stripos($name, $searchQuery) !== false
                    ||
                    stripos($series, $searchQuery) !== false;
            }
        )
    );
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$pagination = paginate_items(
    $filteredCharacters,
    20
);

$visibleCharacters =
    $pagination['items'];


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Characters — AniScope by Arafat';

$activePage =
    'characters';

require __DIR__
    . '/includes/header.php';

require_once __DIR__
    . '/includes/cards.php';

$charactersCover = setting_value(
    $siteSettings,
    'characters_cover_url',
    '/assets/images/characters-banner.svg'
);

?>


<section
    class="page-hero character-page-hero"
    style="background:linear-gradient(rgba(8,7,18,.1),var(--ink)),url('<?= e(image_url($charactersCover)) ?>') center/cover"
>

    <div class="container reveal">

        <span class="eyebrow">
            Character archive
        </span>

        <h1>
            Characters
        </h1>

        <p>
            Explore anime characters, their stories,
            abilities, and original series.
        </p>

    </div>

</section>


<section class="section section-dark">

    <div class="container">


        <!-- ==============================================
             FILTER / SEARCH
        =============================================== -->

        <div class="filter-bar">

            <span>

                <?= (int) $pagination['total_items'] ?>

                <?= $pagination['total_items'] === 1
                    ? 'profile'
                    : 'profiles' ?>

                <?php if ($pagination['total_items']): ?>

                    · Showing
                    <?= (int) $pagination['from'] ?>
                    –
                    <?= (int) $pagination['to'] ?>

                <?php endif; ?>

            </span>


            <form
                class="search-shell"
                method="get"
                action="/characters.php"
                role="search"
            >

                <span>
                    ⌕
                </span>

                <input
                    type="search"
                    name="q"
                    value="<?= e($searchQuery) ?>"
                    placeholder="Search characters or anime…"
                    aria-label="Search characters"
                    autocomplete="off"
                >

                <?php if ($searchQuery !== ''): ?>

                    <a
                        class="character-search-clear"
                        href="/characters.php"
                        aria-label="Clear search"
                        title="Clear search"
                    >
                        ×
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- Search information -->

        <?php if ($searchQuery !== ''): ?>

            <div class="character-search-status">

                <span>
                    Search results for
                    <strong>
                        “<?= e($searchQuery) ?>”
                    </strong>
                </span>

                <a href="/characters.php">
                    Clear search
                </a>

            </div>

        <?php endif; ?>


        <!-- ==============================================
             CHARACTER GRID
        =============================================== -->

        <?php if ($visibleCharacters): ?>

            <div
                class="character-grid"
                id="character-grid"
            >

                <?php foreach ($visibleCharacters as $character): ?>

                    <?php character_card($character); ?>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <?php if ($searchQuery !== ''): ?>

                <?php
                empty_state(
                    'No character matched “'
                    . $searchQuery
                    . '”. Try another name or anime title.'
                );
                ?>

            <?php else: ?>

                <?php
                empty_state(
                    'No character profiles have been added yet.'
                );
                ?>

            <?php endif; ?>

        <?php endif; ?>


        <!-- ==============================================
             PAGINATION
        =============================================== -->

        <?php
        render_pagination(
            $pagination['page'],
            $pagination['total_pages']
        );
        ?>


        <?php show_native_ad(); ?>


    </div>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>
