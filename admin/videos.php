<?php

require_once __DIR__ . '/auth.php';
require_admin();

require_once dirname(__DIR__) . '/includes/streaming.php';
require_once __DIR__ . '/layout.php';


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function save_episode_media_from_form(int $episodeId): void
{
    replace_stream_source(
        $episodeId,
        'sub',
        (string)($_POST['sub_url'] ?? '')
    );

    replace_stream_source(
        $episodeId,
        'dub',
        (string)($_POST['dub_url'] ?? '')
    );

    replace_stream_source(
        $episodeId,
        'raw',
        (string)($_POST['raw_url'] ?? '')
    );


    replace_stream_subtitle(
        $episodeId,
        'English',
        'en',
        (string)($_POST['subtitle_en'] ?? '')
    );

    replace_stream_subtitle(
        $episodeId,
        'Bangla',
        'bn',
        (string)($_POST['subtitle_bn'] ?? '')
    );

    replace_stream_subtitle(
        $episodeId,
        'Hindi',
        'hi',
        (string)($_POST['subtitle_hi'] ?? '')
    );
}


/*
|--------------------------------------------------------------------------
| Request State
|--------------------------------------------------------------------------
*/

$mode = $_GET['mode'] ?? '';

$animeId =
    (int)($_GET['anime'] ?? 0);

$episodeId =
    (int)($_GET['episode'] ?? 0);


$editingAnime =
    $animeId
        ? stream_anime($animeId)
        : [];


$editingEpisode =
    $episodeId
        ? stream_episode($episodeId)
        : [];


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action =
        $_POST['action']
        ?? '';

    try {


        /*
        |--------------------------------------------------------------------------
        | CREATE NEW ANIME + FIRST EPISODE
        |--------------------------------------------------------------------------
        */

        if ($action === 'create_anime_video') {

            $title =
                trim(
                    (string)($_POST['title'] ?? '')
                );

            if ($title === '') {
                throw new RuntimeException(
                    'Anime title is required.'
                );
            }


            if (
                trim(
                    (string)($_POST['sub_url'] ?? '')
                ) === ''
                &&
                trim(
                    (string)($_POST['dub_url'] ?? '')
                ) === ''
                &&
                trim(
                    (string)($_POST['raw_url'] ?? '')
                ) === ''
            ) {

                throw new RuntimeException(
                    'Please add at least one video URL.'
                );
            }


            /*
             * Create anime.
             */

            $newAnimeId =
                save_stream_anime(
                    [
                        'title' =>
                            $title,

                        'description' =>
                            trim(
                                (string)(
                                    $_POST['anime_description']
                                    ?? ''
                                )
                            ),

                        'poster_url' =>
                            trim(
                                (string)(
                                    $_POST['poster_url']
                                    ?? ''
                                )
                            ),

                        'banner_url' =>
                            trim(
                                (string)(
                                    $_POST['banner_url']
                                    ?? ''
                                )
                            ),

                        'release_year' =>
                            $_POST['release_year']
                            ?? '',

                        'status' =>
                            trim(
                                (string)(
                                    $_POST['anime_status']
                                    ?? ''
                                )
                            ),

                        'genres' =>
                            trim(
                                (string)(
                                    $_POST['genres']
                                    ?? ''
                                )
                            ),

                        'is_published' =>
                            1
                    ]
                );


            /*
             * Create first episode.
             */

            $newEpisodeId =
                save_stream_episode(
                    [
                        'anime_id' =>
                            $newAnimeId,

                        'episode_number' =>
                            $_POST['episode_number']
                            ?? 1,

                        'title' =>
                            trim(
                                (string)(
                                    $_POST['episode_title']
                                    ?? ''
                                )
                            ),

                        'description' =>
                            trim(
                                (string)(
                                    $_POST['episode_description']
                                    ?? ''
                                )
                            ),

                        'thumbnail_url' =>
                            trim(
                                (string)(
                                    $_POST['thumbnail_url']
                                    ?? ''
                                )
                            ),

                        'is_published' =>
                            1
                    ]
                );


            save_episode_media_from_form(
                $newEpisodeId
            );


            flash(
                'success',
                'Anime and first episode added successfully.'
            );


            header(
                'Location: /admin/videos.php?anime='
                . $newAnimeId
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | ADD EPISODE TO EXISTING ANIME
        |--------------------------------------------------------------------------
        */

        if ($action === 'add_existing_episode') {

            $existingAnimeId =
                (int)(
                    $_POST['anime_id']
                    ?? 0
                );


            if ($existingAnimeId <= 0) {

                throw new RuntimeException(
                    'Please select an anime.'
                );
            }


            if (
                trim(
                    (string)($_POST['sub_url'] ?? '')
                ) === ''
                &&
                trim(
                    (string)($_POST['dub_url'] ?? '')
                ) === ''
                &&
                trim(
                    (string)($_POST['raw_url'] ?? '')
                ) === ''
            ) {

                throw new RuntimeException(
                    'Please add at least one video URL.'
                );
            }


            $newEpisodeId =
                save_stream_episode(
                    [
                        'anime_id' =>
                            $existingAnimeId,

                        'episode_number' =>
                            $_POST['episode_number']
                            ?? '',

                        'title' =>
                            trim(
                                (string)(
                                    $_POST['episode_title']
                                    ?? ''
                                )
                            ),

                        'description' =>
                            trim(
                                (string)(
                                    $_POST['episode_description']
                                    ?? ''
                                )
                            ),

                        'thumbnail_url' =>
                            trim(
                                (string)(
                                    $_POST['thumbnail_url']
                                    ?? ''
                                )
                            ),

                        'is_published' =>
                            1
                    ]
                );


            save_episode_media_from_form(
                $newEpisodeId
            );


            flash(
                'success',
                'New episode added successfully.'
            );


            header(
                'Location: /admin/videos.php?anime='
                . $existingAnimeId
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | EDIT ANIME
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_anime') {

            $id =
                save_stream_anime(
                    $_POST,
                    (int)(
                        $_POST['id']
                        ?? 0
                    )
                );


            flash(
                'success',
                'Anime updated successfully.'
            );


            header(
                'Location: /admin/videos.php?anime='
                . $id
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | EDIT EPISODE
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_episode') {

            $id =
                save_stream_episode(
                    $_POST,
                    (int)(
                        $_POST['id']
                        ?? 0
                    )
                );


            save_episode_media_from_form(
                $id
            );


            flash(
                'success',
                'Episode updated successfully.'
            );


            header(
                'Location: /admin/videos.php?anime='
                . (int)(
                    $_POST['anime_id']
                    ?? 0
                )
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE EPISODE
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_episode') {

            $episode =
                stream_episode(
                    (int)(
                        $_POST['id']
                        ?? 0
                    )
                );


            delete_stream_episode(
                (int)(
                    $_POST['id']
                    ?? 0
                )
            );


            flash(
                'success',
                'Episode deleted.'
            );


            header(
                'Location: /admin/videos.php?anime='
                . (int)(
                    $episode['anime_id']
                    ?? 0
                )
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE ANIME
        |--------------------------------------------------------------------------
        */

        if ($action === 'delete_anime') {

            delete_stream_anime(
                (int)(
                    $_POST['id']
                    ?? 0
                )
            );


            flash(
                'success',
                'Anime and its episodes deleted.'
            );


            header(
                'Location: /admin/videos.php'
            );

            exit;
        }


    } catch (Throwable $e) {

        flash(
            'error',
            $e->getMessage()
        );


        header(
            'Location: /admin/videos.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

$animeList =
    stream_anime_list();


$episodes =
    $animeId
        ? stream_episode_list(
            $animeId
        )
        : [];


$sources =
    $episodeId
        ? stream_episode_sources(
            $episodeId
        )
        : [];


$subtitles =
    $episodeId
        ? stream_episode_subtitles(
            $episodeId
        )
        : [];


$sourceMap = [];

foreach ($sources as $source) {

    $sourceMap[
        $source['language']
    ] =
        $source['source_url'];
}


$subtitleMap = [];

foreach ($subtitles as $track) {

    $subtitleMap[
        $track['lang']
    ] =
        $track['url'];
}


admin_header(
    'Videos',
    'videos'
);

?>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="admin-list-heading">

    <div>

        <h2>
            Anime Streaming
        </h2>

        <p>
            Upload videos to Cloudflare R2,
            then paste the video URL here.
        </p>

    </div>

</div>


<!-- =========================================================
     TWO MAIN OPTIONS
========================================================= -->

<?php if (!$animeId && !$episodeId): ?>

<div class="stream-admin-choice-grid">


    <!-- =====================================================
         OPTION 1 — NEW ANIME + FIRST EPISODE
    ====================================================== -->

    <section class="admin-card admin-form stream-admin-section">

        <div class="stream-admin-section-head">

            <span class="stream-step">
                01
            </span>

            <div>

                <h2>
                    Add New Anime Video
                </h2>

                <p>
                    Use this when the anime does not exist yet.
                    Anime details and its first episode will be created together.
                </p>

            </div>

        </div>


        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="create_anime_video"
            >


            <h3>
                Anime Information
            </h3>


            <label>

                Anime Title

                <input
                    name="title"
                    required
                    placeholder="Jujutsu Kaisen"
                >

            </label>


            <label>

                Anime Description

                <textarea
                    name="anime_description"
                    rows="4"
                    placeholder="Short anime description..."
                ></textarea>

            </label>


            <div class="form-row">

                <label>

                    Poster URL

                    <input
                        type="url"
                        name="poster_url"
                        placeholder="https://..."
                    >

                </label>


                <label>

                    Banner URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="banner_url"
                        placeholder="https://..."
                    >

                </label>

            </div>


            <div class="form-row">

                <label>

                    Release Year

                    <input
                        type="number"
                        name="release_year"
                        min="1900"
                        max="2100"
                        placeholder="2024"
                    >

                </label>


                <label>

                    Anime Status

                    <input
                        name="anime_status"
                        placeholder="Airing / Finished"
                    >

                </label>

            </div>


            <label>

                Genres

                <input
                    name="genres"
                    placeholder="Action, Supernatural, Fantasy"
                >

            </label>


            <h3>
                First Episode
            </h3>


            <div class="form-row">

                <label>

                    Episode Number

                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="episode_number"
                        value="1"
                        required
                    >

                </label>


                <label>

                    Episode Title

                    <input
                        name="episode_title"
                        placeholder="Episode 1"
                    >

                </label>

            </div>


            <label>

                Episode Description

                <span class="optional-label">
                    Optional
                </span>

                <textarea
                    name="episode_description"
                    rows="3"
                ></textarea>

            </label>


            <label>

                Episode Thumbnail URL

                <span class="optional-label">
                    Optional
                </span>

                <input
                    type="url"
                    name="thumbnail_url"
                >

            </label>


            <h3>
                Video URLs
            </h3>


            <div class="stream-video-important">

                <strong>
                    🎬 Paste your Cloudflare R2 video URL here
                </strong>

                <p>
                    At least one video source is required.
                </p>

            </div>


            <label class="stream-primary-field">

                SUB Video URL

                <input
                    type="url"
                    name="sub_url"
                    placeholder="https://pub-xxxx.r2.dev/episode-01-sub.mp4"
                >

            </label>


            <label>

                DUB Video URL

                <span class="optional-label">
                    Optional
                </span>

                <input
                    type="url"
                    name="dub_url"
                    placeholder="https://pub-xxxx.r2.dev/episode-01-dub.mp4"
                >

            </label>


            <label>

                RAW Video URL

                <span class="optional-label">
                    Optional
                </span>

                <input
                    type="url"
                    name="raw_url"
                >

            </label>


            <h3>
                Subtitle Files
            </h3>


            <label>

                English .VTT URL

                <span class="optional-label">
                    Optional
                </span>

                <input
                    type="url"
                    name="subtitle_en"
                >

            </label>


            <label>

                Bangla .VTT URL

                <span class="optional-label">
                    Optional
                </span>

                <input
                    type="url"
                    name="subtitle_bn"
                >

            </label>


            <label>

                Hindi .VTT URL

                <span class="optional-label">
                    Optional
                </span>

                <input
                    type="url"
                    name="subtitle_hi"
                >

            </label>


            <button
                class="button primary full"
                type="submit"
            >

                Create Anime + Episode →

            </button>

        </form>

    </section>


    <!-- =====================================================
         OPTION 2 — EXISTING ANIME
    ====================================================== -->

    <section class="admin-card admin-form stream-admin-section">

        <div class="stream-admin-section-head">

            <span class="stream-step">
                02
            </span>

            <div>

                <h2>
                    Add Existing Anime Video
                </h2>

                <p>
                    Already added Episode 1?
                    Select the anime here and add Episode 2, 3, 4 and beyond.
                </p>

            </div>

        </div>


        <?php if (!$animeList): ?>

            <div class="empty-state">

                <h3>
                    No anime available yet
                </h3>

                <p>
                    Create your first anime using the section on the left.
                </p>

            </div>

        <?php else: ?>

            <form method="post">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="add_existing_episode"
                >


                <label>

                    Select Existing Anime

                    <select
                        name="anime_id"
                        required
                    >

                        <option value="">
                            -- Select anime --
                        </option>

                        <?php foreach ($animeList as $anime): ?>

                            <option
                                value="<?= (int)$anime['id'] ?>"
                            >
                                <?= e($anime['title']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <h3>
                    New Episode
                </h3>


                <div class="form-row">

                    <label>

                        Episode Number

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="episode_number"
                            required
                            placeholder="2"
                        >

                    </label>


                    <label>

                        Episode Title

                        <input
                            name="episode_title"
                            placeholder="Episode 2"
                        >

                    </label>

                </div>


                <label>

                    Episode Description

                    <span class="optional-label">
                        Optional
                    </span>

                    <textarea
                        name="episode_description"
                        rows="3"
                    ></textarea>

                </label>


                <label>

                    Thumbnail URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="thumbnail_url"
                    >

                </label>


                <h3>
                    Video URLs
                </h3>


                <div class="stream-video-important">

                    <strong>
                        🎬 Paste the next episode R2 URL
                    </strong>

                    <p>
                        Example: Episode 2 SUB video.
                    </p>

                </div>


                <label class="stream-primary-field">

                    SUB Video URL

                    <input
                        type="url"
                        name="sub_url"
                        placeholder="https://pub-xxxx.r2.dev/episode-02-sub.mp4"
                    >

                </label>


                <label>

                    DUB Video URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="dub_url"
                    >

                </label>


                <label>

                    RAW Video URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="raw_url"
                    >

                </label>


                <h3>
                    Subtitle Files
                </h3>


                <label>

                    English .VTT URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="subtitle_en"
                    >

                </label>


                <label>

                    Bangla .VTT URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="subtitle_bn"
                    >

                </label>


                <label>

                    Hindi .VTT URL

                    <span class="optional-label">
                        Optional
                    </span>

                    <input
                        type="url"
                        name="subtitle_hi"
                    >

                </label>


                <button
                    class="button primary full"
                    type="submit"
                >

                    Add Episode →

                </button>

            </form>

        <?php endif; ?>

    </section>

</div>


<!-- =========================================================
     CURRENT ANIME LIBRARY
========================================================= -->

<section class="admin-card stream-library">

    <div class="admin-list-heading">

        <div>

            <h2>
                Current Anime Library
            </h2>

            <p>
                Manage existing anime and their episodes.
            </p>

        </div>

    </div>


    <div class="admin-table-wrap">

        <table class="admin-table">

            <thead>

                <tr>
                    <th>Anime</th>
                    <th>Year</th>
                    <th>Status</th>
                    <th>Episodes</th>
                    <th></th>
                </tr>

            </thead>


            <tbody>

            <?php foreach ($animeList as $anime): ?>

                <?php

                $animeEpisodes =
                    stream_episode_list(
                        (int)$anime['id']
                    );

                ?>

                <tr>

                    <td>

                        <strong>
                            <?= e($anime['title']) ?>
                        </strong>

                    </td>


                    <td>
                        <?= e(
                            (string)(
                                $anime['release_year']
                                ?? ''
                            )
                        ) ?>
                    </td>


                    <td>

                        <?= !empty(
                            $anime['is_published']
                        )
                            ? 'Published'
                            : 'Draft'
                        ?>

                    </td>


                    <td>

                        <?= count($animeEpisodes) ?>

                    </td>


                    <td class="actions">

                        <a
                            href="/admin/videos.php?anime=<?= (int)$anime['id'] ?>"
                        >
                            Manage
                        </a>


                        <form
                            method="post"
                            data-confirm="Delete this anime and every episode permanently?"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="delete_anime"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$anime['id'] ?>"
                            >

                            <button type="submit">
                                Delete
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>

<?php endif; ?>


<!-- =========================================================
     MANAGE EXISTING ANIME
========================================================= -->

<?php if ($animeId && $editingAnime): ?>


<div class="editor-heading">

    <div>

        <a href="/admin/videos.php">
            ← Back to videos
        </a>

        <h2>
            <?= e($editingAnime['title']) ?>
        </h2>

        <p>
            Manage episodes and existing video sources.
        </p>

    </div>

</div>


<section class="admin-card">

    <div class="admin-list-heading">

        <div>

            <h2>
                Episodes
            </h2>

            <p>
                <?= count($episodes) ?>
                episode<?= count($episodes) === 1 ? '' : 's' ?>
            </p>

        </div>

        <a
            class="button primary"
            href="/admin/videos.php?anime=<?= $animeId ?>&mode=new-episode"
        >
            + Add Episode
        </a>

    </div>


    <div class="admin-table-wrap">

        <table class="admin-table">

            <thead>

                <tr>
                    <th>Episode</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th></th>
                </tr>

            </thead>


            <tbody>

            <?php foreach ($episodes as $episode): ?>

                <tr>

                    <td>
                        <?= e(
                            (string)$episode['episode_number']
                        ) ?>
                    </td>


                    <td>

                        <strong>

                            <?= e(
                                $episode['title']
                                ?: (
                                    'Episode '
                                    . $episode['episode_number']
                                )
                            ) ?>

                        </strong>

                    </td>


                    <td>

                        <?= !empty(
                            $episode['is_published']
                        )
                            ? 'Published'
                            : 'Draft'
                        ?>

                    </td>


                    <td class="actions">

                        <a
                            href="/admin/videos.php?anime=<?= $animeId ?>&episode=<?= (int)$episode['id'] ?>&mode=edit-episode"
                        >
                            Edit
                        </a>


                        <form
                            method="post"
                            data-confirm="Delete this episode permanently?"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="delete_episode"
                            >

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int)$episode['id'] ?>"
                            >

                            <button type="submit">
                                Delete
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>


<?php endif; ?>


<!-- =========================================================
     ADD / EDIT EPISODE FROM MANAGE PAGE
========================================================= -->

<?php if (
    $animeId
    &&
    (
        $mode === 'new-episode'
        ||
        $editingEpisode
    )
): ?>


<section class="admin-card admin-form">

    <h2>

        <?= $editingEpisode
            ? 'Edit Episode'
            : 'Add New Episode'
        ?>

    </h2>


    <p class="form-hint">

        Anime:
        <strong>
            <?= e(
                $editingAnime['title']
                ?? ''
            ) ?>
        </strong>

    </p>


    <form method="post">


        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="<?= $editingEpisode ? 'save_episode' : 'add_existing_episode' ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int)(
                $editingEpisode['id']
                ?? 0
            ) ?>"
        >

        <input
            type="hidden"
            name="anime_id"
            value="<?= $animeId ?>"
        >


        <div class="form-row">

            <label>

                Episode Number

                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="episode_number"
                    required
                    value="<?= e(
                        (string)(
                            $editingEpisode['episode_number']
                            ?? ''
                        )
                    ) ?>"
                >

            </label>


            <label>

                Episode Title

                <input
                    name="title"
                    value="<?= e(
                        $editingEpisode['title']
                        ?? ''
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="episode_title"
                    value="<?= e(
                        $editingEpisode['title']
                        ?? ''
                    ) ?>"
                >

            </label>

        </div>


        <label>

            Episode Description

            <textarea
                name="description"
                rows="3"
            ><?= e(
                $editingEpisode['description']
                ?? ''
            ) ?></textarea>

        </label>


        <label>

            Thumbnail URL

            <input
                type="url"
                name="thumbnail_url"
                value="<?= e(
                    $editingEpisode['thumbnail_url']
                    ?? ''
                ) ?>"
            >

        </label>


        <h3>
            Video Sources
        </h3>


        <label class="stream-primary-field">

            SUB R2 Video URL

            <input
                type="url"
                name="sub_url"
                value="<?= e(
                    $sourceMap['sub']
                    ?? ''
                ) ?>"
            >

        </label>


        <label>

            DUB R2 Video URL

            <span class="optional-label">
                Optional
            </span>

            <input
                type="url"
                name="dub_url"
                value="<?= e(
                    $sourceMap['dub']
                    ?? ''
                ) ?>"
            >

        </label>


        <label>

            RAW Video URL

            <span class="optional-label">
                Optional
            </span>

            <input
                type="url"
                name="raw_url"
                value="<?= e(
                    $sourceMap['raw']
                    ?? ''
                ) ?>"
            >

        </label>


        <h3>
            Subtitles
        </h3>


        <label>

            English VTT URL

            <input
                type="url"
                name="subtitle_en"
                value="<?= e(
                    $subtitleMap['en']
                    ?? ''
                ) ?>"
            >

        </label>


        <label>

            Bangla VTT URL

            <input
                type="url"
                name="subtitle_bn"
                value="<?= e(
                    $subtitleMap['bn']
                    ?? ''
                ) ?>"
            >

        </label>


        <label>

            Hindi VTT URL

            <input
                type="url"
                name="subtitle_hi"
                value="<?= e(
                    $subtitleMap['hi']
                    ?? ''
                ) ?>"
            >

        </label>


        <input
            type="hidden"
            name="is_published"
            value="1"
        >


        <button
            class="button primary"
            type="submit"
        >

            <?= $editingEpisode
                ? 'Save Episode'
                : 'Add Episode'
            ?>

            →

        </button>

    </form>

</section>


<?php endif; ?>


<?php admin_footer(); ?>
