<?php

require_once __DIR__ . '/auth.php';
require_admin();

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/anilist.php';

$query = trim($_GET['q'] ?? '');
$results = [];
$error = '';

if ($query !== '') {
    try {
        $results = anilist_search_characters($query);
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $anilistId = (int) ($_POST['anilist_id'] ?? 0);

    if ($anilistId <= 0) {
        flash('error', 'Invalid character selected.');
        header('Location: /admin/import-character.php');
        exit;
    }

    try {
        $character = anilist_character($anilistId);

        if (!$character) {
            throw new RuntimeException('Character information was not found.');
        }

        $name = trim((string) ($character['name']['full'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Character name is missing.');
        }

        $payload = [
            'name' => $name,
            'anime_name' => anilist_character_series($character),
            'bio' => anilist_character_bio($character),
            'abilities' => anilist_character_abilities($character),
            'image_url' => anilist_character_image($character)
        ];

        $response = api_request(
            'POST',
            '/api/characters',
            $payload,
            admin_token()
        );

        if (!empty($response['ok'])) {
            flash(
                'success',
                $name . ' imported successfully. Review biography and abilities before publishing.'
            );

            header('Location: /admin/characters.php');
            exit;
        }

        throw new RuntimeException(
            api_message($response, 'Character import failed.')
        );

    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());

        header(
            'Location: /admin/import-character.php?q=' .
            rawurlencode($query)
        );
        exit;
    }
}

admin_header('Import Character', 'import-character');

?>

<div class="admin-list-heading">
    <div>
        <h2>Import Anime Character</h2>
        <p>Search AniList and import character information into AniScope.</p>
    </div>

    <a class="button" href="/admin/characters.php">
        ← Characters
    </a>
</div>

<section class="admin-card admin-form">

    <form method="get">

        <label>
            Character name

            <div class="form-row">

                <input
                    type="search"
                    name="q"
                    value="<?= e($query) ?>"
                    placeholder="Example: Satoru Gojo"
                    required
                >

                <button class="button primary" type="submit">
                    Search
                </button>

            </div>
        </label>

    </form>

</section>

<?php if ($error !== ''): ?>

    <div class="alert error">
        <?= e($error) ?>
    </div>

<?php endif; ?>

<?php if ($query !== '' && !$error): ?>

    <div class="admin-list-heading">

        <div>
            <h2>Search results</h2>

            <p>
                <?= count($results) ?> result(s) found for
                “<?= e($query) ?>”
            </p>
        </div>

    </div>

    <?php if (!$results): ?>

        <section class="admin-card">
            <p>No characters found.</p>
        </section>

    <?php else: ?>

        <div class="card-grid">

            <?php foreach ($results as $character): ?>

                <?php
                    $name = $character['name']['full'] ?? 'Unknown';
                    $native = $character['name']['native'] ?? '';
                    $image = anilist_character_image($character);
                    $series = anilist_character_series($character);

                    $about = trim(
                        strip_tags(
                            (string) ($character['description'] ?? '')
                        )
                    );

                    if (strlen($about) > 180) {
                        $about = substr($about, 0, 180) . '...';
                    }
                ?>

                <article class="admin-card">

                    <div style="
                        display:flex;
                        gap:18px;
                        align-items:flex-start;
                    ">

                        <img
                            src="<?= e($image) ?>"
                            alt="<?= e($name) ?>"
                            style="
                                width:110px;
                                height:150px;
                                object-fit:cover;
                                border-radius:12px;
                                flex-shrink:0;
                            "
                        >

                        <div style="flex:1">

                            <h3><?= e($name) ?></h3>

                            <?php if ($native !== ''): ?>
                                <p><?= e($native) ?></p>
                            <?php endif; ?>

                            <p>
                                <strong>Series:</strong>
                                <?= e($series) ?>
                            </p>

                            <?php if ($about !== ''): ?>
                                <p><?= e($about) ?></p>
                            <?php endif; ?>

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="anilist_id"
                                    value="<?= (int) ($character['id'] ?? 0) ?>"
                                >

                                <button
                                    class="button primary"
                                    type="submit"
                                >
                                    Import Character →
                                </button>

                            </form>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

<?php endif; ?>

<?php admin_footer(); ?>
