<?php

require_once __DIR__ . '/auth.php';
require_admin();

require_once dirname(__DIR__) . '/includes/streaming.php';
require_once __DIR__ . '/layout.php';

$mode = $_GET['mode'] ?? '';
$animeId = (int)($_GET['anime'] ?? 0);
$episodeId = (int)($_GET['episode'] ?? 0);

$editingAnime = $animeId ? stream_anime($animeId) : [];
$editingEpisode = $episodeId ? stream_episode($episodeId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    try {

        if ($action === 'save_anime') {

            $id = save_stream_anime(
                $_POST,
                (int)($_POST['id'] ?? 0)
            );

            flash('success', 'Anime saved.');
            header('Location: /admin/videos.php?anime=' . $id);
            exit;
        }

        if ($action === 'delete_anime') {

            delete_stream_anime(
                (int)($_POST['id'] ?? 0)
            );

            flash('success', 'Anime deleted.');
            header('Location: /admin/videos.php');
            exit;
        }

        if ($action === 'save_episode') {

            $id = save_stream_episode(
                $_POST,
                (int)($_POST['id'] ?? 0)
            );

            replace_stream_source(
                $id,
                'sub',
                (string)($_POST['sub_url'] ?? '')
            );

            replace_stream_source(
                $id,
                'dub',
                (string)($_POST['dub_url'] ?? '')
            );

            replace_stream_source(
                $id,
                'raw',
                (string)($_POST['raw_url'] ?? '')
            );

            replace_stream_subtitle(
                $id,
                'English',
                'en',
                (string)($_POST['subtitle_en'] ?? '')
            );

            replace_stream_subtitle(
                $id,
                'Bangla',
                'bn',
                (string)($_POST['subtitle_bn'] ?? '')
            );

            replace_stream_subtitle(
                $id,
                'Hindi',
                'hi',
                (string)($_POST['subtitle_hi'] ?? '')
            );

            flash('success', 'Episode saved.');

            header(
                'Location: /admin/videos.php?anime='
                . (int)($_POST['anime_id'] ?? 0)
            );

            exit;
        }

        if ($action === 'delete_episode') {

            $episode = stream_episode(
                (int)($_POST['id'] ?? 0)
            );

            delete_stream_episode(
                (int)($_POST['id'] ?? 0)
            );

            flash('success', 'Episode deleted.');

            header(
                'Location: /admin/videos.php?anime='
                . (int)($episode['anime_id'] ?? 0)
            );

            exit;
        }

    } catch (Throwable $e) {

        flash('error', $e->getMessage());

        header('/admin/videos.php');
        exit;
    }
}

$animeList = stream_anime_list();

$episodes = $animeId
    ? stream_episode_list($animeId)
    : [];

$sources = $episodeId
    ? stream_episode_sources($episodeId)
    : [];

$subs = $episodeId
    ? stream_episode_subtitles($episodeId)
    : [];

$sourceMap = [];

foreach ($sources as $source) {
    $sourceMap[$source['language']] = $source['source_url'];
}

$subtitleMap = [];

foreach ($subs as $track) {
    $subtitleMap[$track['lang']] = $track['url'];
}

admin_header('Videos', 'videos');

?>

<div class="admin-list-heading">
    <div>
        <h2>Anime Videos</h2>
        <p>Manage anime, episodes, Cloudflare R2 video links, audio versions and subtitles.</p>
    </div>

    <a class="button primary" href="/admin/videos.php?mode=new-anime">
        + New anime
    </a>
</div>


<?php if ($mode === 'new-anime' || $editingAnime): ?>

<section class="admin-card admin-form">

    <h3><?= $editingAnime ? 'Edit anime' : 'Add anime' ?></h3>

    <form method="post">

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_anime">
        <input type="hidden" name="id" value="<?= (int)($editingAnime['id'] ?? 0) ?>">

        <label>
            Title
            <input name="title" required value="<?= e($editingAnime['title'] ?? '') ?>">
        </label>

        <label>
            Description
            <textarea name="description" rows="6"><?= e($editingAnime['description'] ?? '') ?></textarea>
        </label>

        <div class="form-row">

            <label>
                Poster URL
                <input name="poster_url" value="<?= e($editingAnime['poster_url'] ?? '') ?>">
            </label>

            <label>
                Banner URL
                <input name="banner_url" value="<?= e($editingAnime['banner_url'] ?? '') ?>">
            </label>

        </div>

        <div class="form-row">

            <label>
                Release year
                <input type="number" name="release_year" value="<?= e((string)($editingAnime['release_year'] ?? '')) ?>">
            </label>

            <label>
                Status
                <input name="status" placeholder="Finished / Airing" value="<?= e($editingAnime['status'] ?? '') ?>">
            </label>

        </div>

        <label>
            Genres
            <input name="genres" placeholder="Action, Supernatural, Fantasy" value="<?= e($editingAnime['genres'] ?? '') ?>">
        </label>

        <label>
            <input
                type="checkbox"
                name="is_published"
                value="1"
                <?= !isset($editingAnime['is_published']) || !empty($editingAnime['is_published']) ? 'checked' : '' ?>
            >
            Published
        </label>

        <button class="button primary" type="submit">
            Save anime
        </button>

    </form>

</section>

<?php endif; ?>


<?php if ($animeId): ?>

<section class="admin-card">

    <div class="admin-list-heading">

        <div>
            <h2><?= e($editingAnime['title'] ?? 'Episodes') ?></h2>
            <p>Episodes and streaming sources.</p>
        </div>

        <a
            class="button primary"
            href="/admin/videos.php?anime=<?= $animeId ?>&mode=new-episode"
        >
            + Add episode
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

                    <td><?= e((string)$episode['episode_number']) ?></td>

                    <td>
                        <strong>
                            <?= e($episode['title'] ?: ('Episode '.$episode['episode_number'])) ?>
                        </strong>
                    </td>

                    <td>
                        <?= !empty($episode['is_published']) ? 'Published' : 'Draft' ?>
                    </td>

                    <td class="actions">

                        <a
                            href="/admin/videos.php?anime=<?= $animeId ?>&episode=<?= (int)$episode['id'] ?>&mode=edit-episode"
                        >
                            Edit
                        </a>

                        <form method="post" data-confirm="Delete this episode permanently?">

                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_episode">
                            <input type="hidden" name="id" value="<?= (int)$episode['id'] ?>">

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


<?php if (
    $animeId &&
    ($mode === 'new-episode' || $editingEpisode)
): ?>

<section class="admin-card admin-form">

    <h3>
        <?= $editingEpisode ? 'Edit episode' : 'Add episode' ?>
    </h3>

    <form method="post">

        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_episode">
        <input type="hidden" name="id" value="<?= (int)($editingEpisode['id'] ?? 0) ?>">
        <input type="hidden" name="anime_id" value="<?= $animeId ?>">

        <div class="form-row">

            <label>
                Episode number
                <input
                    type="number"
                    step="0.01"
                    name="episode_number"
                    required
                    value="<?= e((string)($editingEpisode['episode_number'] ?? '')) ?>"
                >
            </label>

            <label>
                Episode title
                <input
                    name="title"
                    value="<?= e($editingEpisode['title'] ?? '') ?>"
                >
            </label>

        </div>

        <label>
            Description
            <textarea name="description" rows="3"><?= e($editingEpisode['description'] ?? '') ?></textarea>
        </label>

        <label>
            Thumbnail URL
            <input name="thumbnail_url" value="<?= e($editingEpisode['thumbnail_url'] ?? '') ?>">
        </label>

        <h3>Video Sources</h3>

        <label>
            SUB R2 Video URL
            <input
                type="url"
                name="sub_url"
                value="<?= e($sourceMap['sub'] ?? '') ?>"
                placeholder="https://pub-xxxx.r2.dev/episode-sub.mp4"
            >
        </label>

        <label>
            DUB R2 Video URL
            <span class="optional-label">Optional</span>
            <input
                type="url"
                name="dub_url"
                value="<?= e($sourceMap['dub'] ?? '') ?>"
            >
        </label>

        <label>
            RAW Video URL
            <span class="optional-label">Optional</span>
            <input
                type="url"
                name="raw_url"
                value="<?= e($sourceMap['raw'] ?? '') ?>"
            >
        </label>

        <h3>Subtitles</h3>

        <label>
            English VTT URL
            <input type="url" name="subtitle_en" value="<?= e($subtitleMap['en'] ?? '') ?>">
        </label>

        <label>
            Bangla VTT URL
            <input type="url" name="subtitle_bn" value="<?= e($subtitleMap['bn'] ?? '') ?>">
        </label>

        <label>
            Hindi VTT URL
            <input type="url" name="subtitle_hi" value="<?= e($subtitleMap['hi'] ?? '') ?>">
        </label>

        <label>
            <input
                type="checkbox"
                name="is_published"
                value="1"
                <?= !isset($editingEpisode['is_published']) || !empty($editingEpisode['is_published']) ? 'checked' : '' ?>
            >
            Published
        </label>

        <button class="button primary" type="submit">
            Save episode
        </button>

    </form>

</section>

<?php endif; ?>


<?php if (!$animeId && $mode !== 'new-anime'): ?>

<section class="admin-card">

    <div class="admin-table-wrap">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>Anime</th>
                    <th>Year</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($animeList as $anime): ?>

                <tr>

                    <td>
                        <strong>
                            <?= e($anime['title']) ?>
                        </strong>
                    </td>

                    <td>
                        <?= e((string)($anime['release_year'] ?? '')) ?>
                    </td>

                    <td>
                        <?= !empty($anime['is_published']) ? 'Published' : 'Draft' ?>
                    </td>

                    <td class="actions">

                        <a href="/admin/videos.php?anime=<?= (int)$anime['id'] ?>">
                            Episodes
                        </a>

                        <a href="/admin/videos.php?anime=<?= (int)$anime['id'] ?>&mode=edit-anime">
                            Edit
                        </a>

                        <form method="post" data-confirm="Delete this anime and every episode permanently?">

                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_anime">
                            <input type="hidden" name="id" value="<?= (int)$anime['id'] ?>">

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


<?php admin_footer(); ?>
