<?php

require_once __DIR__ . '/auth.php';
require_admin();

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/anilist.php';

$query = trim($_GET['q'] ?? '');
$results = [];
$error = '';
$bulkResults = [];

function normalize_character_name($name)
{
    $name = strtolower(trim((string) $name));
    $name = preg_replace('/\s+/', ' ', $name);

    return $name;
}

function existing_character_by_name($name, $characters)
{
    $target = normalize_character_name($name);

    foreach ($characters as $character) {
        $existingName = normalize_character_name(
            $character['name'] ?? ''
        );

        if ($existingName === $target) {
            return $character;
        }
    }

    return null;
}

function save_anilist_character($character, &$characters)
{
    $name = trim(
        (string) ($character['name']['full'] ?? '')
    );

    if ($name === '') {
        throw new RuntimeException(
            'Character name is missing.'
        );
    }

    $payload = [
        'name' => $name,
        'anime_name' => anilist_character_series($character),
        'bio' => anilist_character_bio($character),
        'abilities' => anilist_character_abilities($character),
        'image_url' => anilist_character_image($character)
    ];

    $existing = existing_character_by_name(
        $name,
        $characters
    );

    if ($existing) {

        $id = (int) ($existing['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException(
                'Existing character ID is invalid.'
            );
        }

        $response = api_request(
            'PUT',
            '/api/characters/' . $id,
            $payload,
            admin_token()
        );

        if (empty($response['ok'])) {
            throw new RuntimeException(
                api_message(
                    $response,
                    'Character update failed.'
                )
            );
        }

        /*
         * Keep local list updated in case same name
         * appears twice in one bulk request.
         */
        foreach ($characters as &$item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $item = array_merge(
                    $item,
                    $payload
                );
                break;
            }
        }
        unset($item);

        return [
            'status' => 'updated',
            'name' => $name,
            'id' => $id
        ];
    }

    $response = api_request(
        'POST',
        '/api/characters',
        $payload,
        admin_token()
    );

    if (empty($response['ok'])) {
        throw new RuntimeException(
            api_message(
                $response,
                'Character import failed.'
            )
        );
    }

    /*
     * Refresh local character list after insert.
     */
    $characters = api_data('/api/characters');

    return [
        'status' => 'created',
        'name' => $name,
        'id' => 0
    ];
}


/*
 * Normal search
 */
if ($query !== '') {

    try {

        $results = anilist_search_characters($query);

    } catch (RuntimeException $exception) {

        $error = $exception->getMessage();

    }
}


/*
 * Import handling
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? 'single';

    /*
     * Load current DB characters once.
     */
    $characters = api_data('/api/characters');

    if (!is_array($characters)) {
        $characters = [];
    }


    /*
     * BULK IMPORT
     */
    if ($action === 'bulk') {

        $bulkInput = trim(
            (string) ($_POST['bulk_names'] ?? '')
        );

        if ($bulkInput === '') {

            flash(
                'error',
                'Enter at least one character name.'
            );

            header(
                'Location: /admin/import-character.php'
            );
            exit;
        }

        /*
         * Split by comma or new line.
         */
        $names = preg_split(
            '/[\r\n,]+/',
            $bulkInput
        );

        $names = array_filter(
            array_map('trim', $names)
        );

        /*
         * Remove duplicate names from input.
         */
        $uniqueNames = [];
        $seen = [];

        foreach ($names as $name) {

            $key = normalize_character_name($name);

            if ($key === '') {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $uniqueNames[] = $name;
        }

        /*
         * Protect AniList from huge request batches.
         */
        if (count($uniqueNames) > 20) {
            $uniqueNames = array_slice(
                $uniqueNames,
                0,
                20
            );
        }

        foreach ($uniqueNames as $inputName) {

            try {

                /*
                 * Search AniList.
                 */
                $searchResults =
                    anilist_search_characters(
                        $inputName
                    );

                if (!$searchResults) {

                    $bulkResults[] = [
                        'status' => 'failed',
                        'input' => $inputName,
                        'name' => $inputName,
                        'message' => 'No AniList result found.'
                    ];

                    continue;
                }

                /*
                 * Best search match = first result.
                 */
                $match = anilist_best_character_match(
                    $inputName,
                    $searchResults
                );

                if (!$match) {

                    $bulkResults[] = [
                        'status' => 'failed',
                        'input' => $inputName,
                        'name' => $inputName,
                        'message' => 'No accurate character match found.'
                    ];

                    continue;
                }

                $anilistId = (int) (
                    $match['id'] ?? 0
                );

                if ($anilistId <= 0) {

                    $bulkResults[] = [
                        'status' => 'failed',
                        'input' => $inputName,
                        'name' => $inputName,
                        'message' => 'Invalid AniList character ID.'
                    ];

                    continue;
                }

                /*
                 * Fetch full character information.
                 */
                $character =
                    anilist_character($anilistId);

                if (!$character) {

                    $bulkResults[] = [
                        'status' => 'failed',
                        'input' => $inputName,
                        'name' => $inputName,
                        'message' => 'Character details not found.'
                    ];

                    continue;
                }

                $saved = save_anilist_character(
                    $character,
                    $characters
                );

                $bulkResults[] = [
                    'status' => $saved['status'],
                    'input' => $inputName,
                    'name' => $saved['name'],
                    'message' =>
                        $saved['status'] === 'updated'
                            ? 'Duplicate found — existing profile rewritten.'
                            : 'New character added.'
                ];

                /*
                 * Gentle delay between AniList requests.
                 */
                usleep(1250000);

            } catch (Throwable $exception) {

                $bulkResults[] = [
                    'status' => 'failed',
                    'input' => $inputName,
                    'name' => $inputName,
                    'message' => $exception->getMessage()
                ];
            }
        }

    }


    /*
     * SINGLE IMPORT
     */
    if ($action === 'single') {

        $anilistId = (int) (
            $_POST['anilist_id'] ?? 0
        );

        if ($anilistId <= 0) {

            flash(
                'error',
                'Invalid character selected.'
            );

            header(
                'Location: /admin/import-character.php'
            );
            exit;
        }

        try {

            $character =
                anilist_character($anilistId);

            if (!$character) {
                throw new RuntimeException(
                    'Character information was not found.'
                );
            }

            $saved = save_anilist_character(
                $character,
                $characters
            );

            if ($saved['status'] === 'updated') {

                flash(
                    'success',
                    $saved['name'] .
                    ' already existed, so AniScope rewrote the existing profile with fresh AniList data.'
                );

            } else {

                flash(
                    'success',
                    $saved['name'] .
                    ' imported successfully.'
                );
            }

            header(
                'Location: /admin/characters.php'
            );
            exit;

        } catch (Throwable $exception) {

            flash(
                'error',
                $exception->getMessage()
            );

            header(
                'Location: /admin/import-character.php?q=' .
                rawurlencode($query)
            );
            exit;
        }
    }
}


admin_header(
    'Import Character',
    'import-character'
);

?>

<div class="admin-list-heading">

    <div>
        <h2>Import Anime Characters</h2>

        <p>
            Search individually or bulk import characters
            from AniList.
        </p>
    </div>

    <a
        class="button"
        href="/admin/characters.php"
    >
        ← Characters
    </a>

</div>


<!-- BULK IMPORT -->

<section class="admin-card admin-form">

    <div class="admin-list-heading">

        <div>
            <h2>Bulk Import</h2>

            <p>
                Enter character names separated by commas
                or new lines. Maximum 20 at once.
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
            value="bulk"
        >

        <label>

            Character names

            <textarea
                name="bulk_names"
                rows="5"
                placeholder="Naruto Uzumaki, Sasuke Uchiha, Gaara, Kakashi Hatake"
                required
            ><?= e($_POST['bulk_names'] ?? '') ?></textarea>

        </label>

        <p class="form-hint">
            Duplicate characters are automatically detected.
            Existing profiles will be rewritten with the latest
            imported information.
        </p>

        <button
            class="button primary"
            type="submit"
        >
            Import / Rewrite All →
        </button>

    </form>

</section>


<?php if ($bulkResults): ?>

    <section
        class="admin-card"
        style="margin-top:20px;"
    >

        <h2>Bulk Import Results</h2>

        <div
            style="
                display:grid;
                gap:10px;
                margin-top:16px;
            "
        >

            <?php foreach ($bulkResults as $item): ?>

                <?php
                $status = $item['status'];

                if ($status === 'created') {
                    $icon = '✓';
                    $label = 'Added';
                } elseif ($status === 'updated') {
                    $icon = '↻';
                    $label = 'Rewritten';
                } else {
                    $icon = '✕';
                    $label = 'Failed';
                }
                ?>

                <div
                    style="
                        padding:13px 15px;
                        border:1px solid rgba(255,255,255,.08);
                        border-radius:12px;
                        background:rgba(255,255,255,.025);
                    "
                >

                    <strong>
                        <?= e($icon . ' ' . $item['name']) ?>
                    </strong>

                    <span>
                        — <?= e($label) ?>
                    </span>

                    <?php if (!empty($item['message'])): ?>

                        <div
                            style="
                                margin-top:5px;
                                opacity:.75;
                                font-size:.9rem;
                            "
                        >
                            <?= e($item['message']) ?>
                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

<?php endif; ?>


<!-- SINGLE SEARCH -->

<div
    class="admin-list-heading"
    style="margin-top:30px;"
>

    <div>

        <h2>Single Character Search</h2>

        <p>
            Search AniList and review the result before importing.
        </p>

    </div>

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

                <button
                    class="button primary"
                    type="submit"
                >
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
                <?= count($results) ?>
                result(s) found for
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

                $name =
                    $character['name']['full']
                    ?? 'Unknown';

                $native =
                    $character['name']['native']
                    ?? '';

                $image =
                    anilist_character_image(
                        $character
                    );

                $series =
                    anilist_character_series(
                        $character
                    );

                $about =
                    anilist_clean_text(
                        $character['description']
                        ?? ''
                    );

                if (strlen($about) > 180) {

                    $about =
                        substr(
                            $about,
                            0,
                            180
                        ) . '...';
                }

                ?>

                <article class="admin-card">

                    <div
                        style="
                            display:flex;
                            gap:18px;
                            align-items:flex-start;
                        "
                    >

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

                            <h3>
                                <?= e($name) ?>
                            </h3>

                            <?php if ($native !== ''): ?>

                                <p>
                                    <?= e($native) ?>
                                </p>

                            <?php endif; ?>

                            <p>
                                <strong>Series:</strong>
                                <?= e($series) ?>
                            </p>

                            <?php if ($about !== ''): ?>

                                <p>
                                    <?= e($about) ?>
                                </p>

                            <?php endif; ?>

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="single"
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
                                    Import / Rewrite →
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
