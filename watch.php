<?php

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/streaming.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/pagination.php';


/*
|--------------------------------------------------------------------------
| Request
|--------------------------------------------------------------------------
*/

$animeId = filter_input(
    INPUT_GET,
    'anime',
    FILTER_VALIDATE_INT
);

$requestedEpisode =
    isset($_GET['ep'])
        ? (float) $_GET['ep']
        : 0;


/*
|--------------------------------------------------------------------------
| Load Anime
|--------------------------------------------------------------------------
*/

$anime = $animeId
    ? stream_anime((int)$animeId)
    : [];


/*
|--------------------------------------------------------------------------
| Public Watch Library
|--------------------------------------------------------------------------
|
| When /watch.php is opened without ?anime=,
| show all published streaming anime/seasons as cards.
|
*/

if (!$animeId) {

    $watchAnimeList = stream_anime_list(true);

    if (!is_array($watchAnimeList)) {
        $watchAnimeList = [];
    }

    $pagination = paginate_items(
        $watchAnimeList,
        20
    );

    $visibleWatchAnime = $pagination['items'];

    $pageTitle = 'Watch Anime — AniScope by Arafat';
    $activePage = 'watch';

    require __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/cards.php';

    ?>

    <section class="page-hero watch-library-hero">

        <div class="container reveal">

            <span class="eyebrow">
                AniScope Streaming
            </span>

            <h1>
                Watch Anime
            </h1>

            <p>
                Browse all available anime and seasons,
                then start watching instantly.
            </p>

        </div>

    </section>


    <section class="section section-dark">

        <div class="container">

            <div class="section-heading">

                <div>

                    <span class="eyebrow">
                        Streaming Library
                    </span>

                    <h2>
                        Available Anime
                    </h2>

                </div>

                <span class="watch-library-count">
                    <?= (int) $pagination['total_items'] ?>
                    title<?= $pagination['total_items'] === 1 ? '' : 's' ?>

                    <?php if ($pagination['total_items']): ?>
                        · <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?>
                    <?php endif; ?>
                </span>

            </div>


            <?php if ($visibleWatchAnime): ?>

                <div class="stream-card-grid">

                    <?php foreach ($visibleWatchAnime as $watchAnime): ?>

                        <?php
                        stream_anime_card($watchAnime);
                        ?>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <?php
                empty_state(
                    'No anime videos have been published yet.'
                );
                ?>

            <?php endif; ?>


            <?php
            render_pagination(
                $pagination['page'],
                $pagination['total_pages']
            );
            ?>


            <?php show_native_ad(); ?>

        </div>

    </section>


    <?php

    require __DIR__ . '/includes/footer.php';

    exit;
}


if (
    !$anime ||
    empty($anime['is_published'])
) {

    http_response_code(404);

    $pageTitle = 'Anime Not Found — AniScope';

    require __DIR__ . '/includes/header.php';

    ?>

    <section class="section not-found">

        <div class="container">

            <div class="empty-state">

                <div>✦</div>

                <h3>
                    Anime unavailable
                </h3>

                <p>
                    This anime may not exist or is currently unpublished.
                </p>

                <a
                    class="button primary"
                    href="/index.php"
                >
                    Back Home
                </a>

            </div>

        </div>

    </section>

    <?php

    require __DIR__ . '/includes/footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| Episodes
|--------------------------------------------------------------------------
*/

$episodes = stream_episode_list(
    (int)$anime['id'],
    true
);


if (!$episodes) {

    http_response_code(404);

    $pageTitle =
        $anime['title']
        . ' — AniScope';

    require __DIR__ . '/includes/header.php';

    ?>

    <section class="section not-found">

        <div class="container">

            <div class="empty-state">

                <div>▶</div>

                <h3>
                    No episodes yet
                </h3>

                <p>
                    Episodes have not been published for this anime yet.
                </p>

                <a
                    class="button primary"
                    href="/index.php"
                >
                    Back Home
                </a>

            </div>

        </div>

    </section>

    <?php

    require __DIR__ . '/includes/footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| Select Episode
|--------------------------------------------------------------------------
*/

$currentEpisode = null;


if ($requestedEpisode > 0) {

    foreach ($episodes as $episode) {

        if (
            (float)$episode['episode_number']
            ===
            $requestedEpisode
        ) {

            $currentEpisode = $episode;

            break;
        }
    }
}


/*
 * Default to first published episode.
 */

if (!$currentEpisode) {
    $currentEpisode = $episodes[0];
}


$currentEpisodeNumber =
    (float)$currentEpisode['episode_number'];


/*
|--------------------------------------------------------------------------
| Previous / Next
|--------------------------------------------------------------------------
*/

$currentIndex = 0;

foreach ($episodes as $index => $episode) {

    if (
        (int)$episode['id']
        ===
        (int)$currentEpisode['id']
    ) {

        $currentIndex = $index;

        break;
    }
}


$previousEpisode =
    $episodes[$currentIndex - 1]
    ?? null;

$nextEpisode =
    $episodes[$currentIndex + 1]
    ?? null;


/*
|--------------------------------------------------------------------------
| Player
|--------------------------------------------------------------------------
*/

$videoTitle =
    $anime['title']
    . ' — '
    . (
        $currentEpisode['title']
        ?: (
            'Episode '
            . $currentEpisode['episode_number']
        )
    );


$videoPoster =
    $currentEpisode['thumbnail_url']
    ?: (
        $anime['poster_url']
        ?? ''
    );


$videoSources =
    stream_player_sources(
        (int)$currentEpisode['id']
    );


$subtitleTracks =
    stream_episode_subtitles(
        (int)$currentEpisode['id']
    );


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle =
    $videoTitle
    . ' — AniScope';

$activePage = 'watch';

require __DIR__ . '/includes/header.php';

?>


<section class="watch-page">

    <div class="container">


        <!-- =================================================
             BREADCRUMB
        ================================================== -->

        <div class="watch-breadcrumb">

            <a href="/index.php">
                Home
            </a>

            <span>›</span>

            <a href="/anime.php">
                Anime
            </a>

            <span>›</span>

            <strong>
                <?= e($anime['title']) ?>
            </strong>

        </div>


        <div class="watch-layout">


            <!-- =================================================
                 EPISODE SIDEBAR
            ================================================== -->

            <aside class="watch-episodes">

                <div class="watch-panel-title">

                    <span class="eyebrow">
                        Episodes
                    </span>

                    <h3>
                        Episode List
                    </h3>

                </div>


                <div class="episode-search">

                    <span>
                        ⌕
                    </span>

                    <input
                        type="search"
                        placeholder="Episode number..."
                        aria-label="Search episodes"
                        data-episode-search
                    >

                </div>


                <div
                    class="episode-list"
                    data-episode-list
                >

                    <?php foreach ($episodes as $episode): ?>

                        <?php

                        $episodeNumber =
                            (float)$episode['episode_number'];

                        $isCurrent =
                            (int)$episode['id']
                            ===
                            (int)$currentEpisode['id'];

                        ?>

                        <a
                            class="episode-item <?= $isCurrent ? 'active' : '' ?>"
                            href="/watch.php?anime=<?= (int)$anime['id'] ?>&ep=<?= e((string)$episode['episode_number']) ?>"
                            data-episode-item
                            data-episode-number="<?= e((string)$episode['episode_number']) ?>"
                        >

                            <span class="episode-number">

                                <?php

                                if (
                                    floor($episodeNumber)
                                    ===
                                    $episodeNumber
                                ) {

                                    echo str_pad(
                                        (string)(int)$episodeNumber,
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    );

                                } else {

                                    echo e(
                                        (string)$episodeNumber
                                    );
                                }

                                ?>

                            </span>


                            <span class="episode-name">

                                <?= e(
                                    $episode['title']
                                    ?: (
                                        'Episode '
                                        . $episode['episode_number']
                                    )
                                ) ?>

                            </span>


                            <?php if ($isCurrent): ?>

                                <span class="episode-playing">
                                    ▶
                                </span>

                            <?php endif; ?>

                        </a>

                    <?php endforeach; ?>

                </div>

            </aside>


            <!-- =================================================
                 PLAYER
            ================================================== -->

            <main class="watch-player-column">

                <div class="watch-player-heading">

                    <div>

                        <span class="eyebrow">
                            Now Watching
                        </span>

                        <h1>
                            <?= e($anime['title']) ?>
                        </h1>

                    </div>


                    <span class="watch-episode-badge">

                        Episode
                        <?= e(
                            (string)$currentEpisode['episode_number']
                        ) ?>

                    </span>

                </div>


                <?php

                require __DIR__
                    . '/includes/video_player.php';

                ?>


                <!-- =============================================
                     PREVIOUS / NEXT
                ============================================== -->

                <div class="watch-navigation">


                    <?php if ($previousEpisode): ?>

                        <a
                            class="button ghost"
                            href="/watch.php?anime=<?= (int)$anime['id'] ?>&ep=<?= e((string)$previousEpisode['episode_number']) ?>"
                        >
                            ← Previous
                        </a>

                    <?php else: ?>

                        <button
                            class="button ghost"
                            type="button"
                            disabled
                        >
                            ← Previous
                        </button>

                    <?php endif; ?>


                    <?php if ($nextEpisode): ?>

                        <a
                            class="button ghost"
                            href="/watch.php?anime=<?= (int)$anime['id'] ?>&ep=<?= e((string)$nextEpisode['episode_number']) ?>"
                        >
                            Next Episode →
                        </a>

                    <?php else: ?>

                        <button
                            class="button ghost"
                            type="button"
                            disabled
                        >
                            Next Episode →
                        </button>

                    <?php endif; ?>


                </div>


                <!-- =============================================
                     NATIVE AD
                ============================================== -->

                <div class="watch-ad">

                    <?php show_native_ad(); ?>

                </div>

            </main>


            <!-- =================================================
                 ANIME DETAILS
            ================================================== -->

            <aside class="watch-details">


                <?php if (!empty($anime['poster_url'])): ?>

                    <div class="watch-detail-poster">

                        <img
                            src="<?= e($anime['poster_url']) ?>"
                            alt="<?= e($anime['title']) ?>"
                            loading="lazy"
                        >

                    </div>

                <?php endif; ?>


                <div class="watch-detail-copy">

                    <span class="eyebrow">
                        Anime Details
                    </span>


                    <h2>
                        <?= e($anime['title']) ?>
                    </h2>


                    <div class="watch-detail-tags">


                        <?php if (!empty($anime['release_year'])): ?>

                            <span>
                                <?= (int)$anime['release_year'] ?>
                            </span>

                        <?php endif; ?>


                        <?php if (!empty($anime['status'])): ?>

                            <span>
                                <?= e($anime['status']) ?>
                            </span>

                        <?php endif; ?>


                        <?php if (!empty($videoSources['sub'])): ?>

                            <span>
                                SUB
                            </span>

                        <?php endif; ?>


                        <?php if (!empty($videoSources['dub'])): ?>

                            <span>
                                DUB
                            </span>

                        <?php endif; ?>


                    </div>


                    <?php if (!empty($anime['description'])): ?>

                        <p>
                            <?= nl2br(
                                e($anime['description'])
                            ) ?>
                        </p>

                    <?php endif; ?>


                    <div class="watch-detail-facts">


                        <?php if (!empty($anime['genres'])): ?>

                            <div>

                                <span>
                                    Genres
                                </span>

                                <strong>
                                    <?= e($anime['genres']) ?>
                                </strong>

                            </div>

                        <?php endif; ?>


                        <div>

                            <span>
                                Episodes
                            </span>

                            <strong>
                                <?= count($episodes) ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Available Audio
                            </span>

                            <strong>

                                <?php

                                $audio = [];

                                if (!empty($videoSources['sub'])) {
                                    $audio[] = 'SUB';
                                }

                                if (!empty($videoSources['dub'])) {
                                    $audio[] = 'DUB';
                                }

                                if (!empty($videoSources['raw'])) {
                                    $audio[] = 'RAW';
                                }

                                echo e(
                                    $audio
                                        ? implode(' · ', $audio)
                                        : 'Unavailable'
                                );

                                ?>

                            </strong>

                        </div>


                    </div>

                </div>

            </aside>


        </div>

    </div>

</section>


<?php require __DIR__ . '/includes/footer.php'; ?>
