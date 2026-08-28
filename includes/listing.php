<?php

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/pagination.php';

/*
|--------------------------------------------------------------------------
| Current category
|--------------------------------------------------------------------------
*/

$category = $category ?? 'Anime';

/*
|--------------------------------------------------------------------------
| Load published posts
|--------------------------------------------------------------------------
*/

$posts = api_data(
    '/api/posts?category=' . urlencode($category) . '&status=published'
);

if (!is_array($posts)) {
    $posts = [];
}

$pagination = paginate_items(
    $posts,
    20
);

$visiblePosts = $pagination['items'];

/*
|--------------------------------------------------------------------------
| Page header
|--------------------------------------------------------------------------
*/

require __DIR__ . '/header.php';
require_once __DIR__ . '/cards.php';

/*
|--------------------------------------------------------------------------
| Category descriptions
|--------------------------------------------------------------------------
*/

$descriptions = [
    'Anime' => 'Deep dives, thoughtful reviews, and beginner-friendly guides.',
    'Manga' => 'Chapter updates, story analysis, and page-turning discoveries.',
    'News'  => 'The latest signals, trends, and conversations from anime culture.'
];

$categoryDescription = isset($descriptions[$category])
    ? $descriptions[$category]
    : 'Explore the latest stories from AniScope by Arafat.';

/*
|--------------------------------------------------------------------------
| Category cover
|--------------------------------------------------------------------------
*/

$coverKey = strtolower($category) . '_cover_url';
$coverUrl = setting_value($siteSettings, $coverKey);

$heroStyle = '';

if (!empty($coverUrl)) {

    $imageUrl = image_url($coverUrl);

    if (!empty($imageUrl)) {

        $heroStyle =
            ' style="background:linear-gradient(rgba(8,7,18,.18),var(--ink)),url(\'' .
            e($imageUrl) .
            '\') center/cover"';
    }
}

?>

<!-- =========================================================
     CATEGORY HERO
========================================================= -->

<section class="page-hero"<?= $heroStyle ?>>

    <div class="container reveal">

        <span class="eyebrow">
            AniScope collection
        </span>

        <h1>
            <?= e($category) ?>
        </h1>

        <p>
            <?= e($categoryDescription) ?>
        </p>

    </div>

</section>


<!-- =========================================================
     STORIES SECTION
========================================================= -->

<section class="section section-dark">

    <div class="container">

        <!-- Filter bar -->

        <div class="filter-bar">

            <span>
                <?= (int) $pagination['total_items'] ?>
                <?= $pagination['total_items'] === 1 ? 'story' : 'stories' ?>

                <?php if ($pagination['total_items']): ?>
                    · Showing
                    <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?>
                <?php endif; ?>
            </span>

            <div>

                <button
                    class="filter active"
                    type="button"
                >
                    Latest
                </button>

                <button
                    class="filter"
                    type="button"
                >
                    Popular
                </button>

            </div>

        </div>


        <!-- =================================================
             STORY CARDS
        ================================================== -->

        <?php if (!empty($visiblePosts)): ?>

            <div class="card-grid">

                <?php foreach ($visiblePosts as $post): ?>

                    <?php
                    if (is_array($post)) {
                        post_card($post);
                    }
                    ?>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <?php
            empty_state(
                'No published stories in this collection yet.'
            );
            ?>

        <?php endif; ?>


        <?php
        render_pagination(
            $pagination['page'],
            $pagination['total_pages']
        );
        ?>


        <!-- =================================================
             ADSTERRA NATIVE BANNER
             
             Keep this section.
             Currently shown on Anime listing only.
        ================================================== -->

        <?php if ($category === 'Anime'): ?>

            <?php
            if (function_exists('show_native_ad')) {
                show_native_ad();
            }
            ?>

        <?php endif; ?>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php require __DIR__ . '/footer.php'; ?>