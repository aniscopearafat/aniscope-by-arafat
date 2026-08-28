<?php

require_once __DIR__ . '/auth.php';
require_admin();

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/anilist.php';

$messages = [];
$results = [];

$stats = [
    'searched' => 0,
    'found' => 0,
    'added' => 0,
    'replaced' => 0,
    'not_found' => 0,
    'errors' => 0,
    'timeouts' => 0,
];

function anime_import_normalize_name($name)
{
    $name = strtolower(trim((string)$name));
    return preg_replace('/[^a-z0-9]+/i', '', $name);
}

function anime_import_existing_posts()
{
    $posts = api_data('/api/posts?category=Anime');
    return is_array($posts) ? $posts : [];
}

function anime_import_find_existing($anime, $posts)
{
    $anilistId = (int)($anime['id'] ?? 0);

    if ($anilistId > 0) {
        foreach ($posts as $post) {
            if ((int)($post['anilist_id'] ?? 0) === $anilistId) {
                return $post;
            }
        }
    }

    $titles = [];

    foreach (anilist_anime_titles($anime) as $title) {
        $normalized = anime_import_normalize_name($title);

        if ($normalized !== '') {
            $titles[] = $normalized;
        }
    }

    $titles = array_unique($titles);

    foreach ($posts as $post) {
        $existingTitle = anime_import_normalize_name($post['title'] ?? '');

        if ($existingTitle !== '' && in_array($existingTitle, $titles, true)) {
            return $post;
        }
    }

    return null;
}

function anime_import_is_timeout_error($message)
{
    $message = strtolower((string)$message);

    return strpos($message, 'timeout') !== false
        || strpos($message, 'timed out') !== false
        || strpos($message, 'operation timed') !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $status = ($_POST['status'] ?? 'draft') === 'published'
        ? 'published'
        : 'draft';

    $rawNames = trim((string)($_POST['anime_names'] ?? ''));

    if ($rawNames === '') {
        $messages[] = [
            'type' => 'error',
            'message' => 'Enter at least one anime title.',
        ];
    } else {
        $splitNames = preg_split('/[\r\n,]+/', $rawNames);

        $names = [];
        $seen = [];

        foreach ($splitNames ?: [] as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $normalized = anime_import_normalize_name($name);

            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $names[] = $name;
        }

        if (count($names) > 20) {
            $messages[] = [
                'type' => 'error',
                'message' => 'Maximum 20 anime titles per import batch.',
            ];
        } else {
            $existingPosts = anime_import_existing_posts();

            foreach ($names as $inputName) {
                $stats['searched']++;

                try {
                    $searchResults = anilist_search_anime($inputName);

                    $match = anilist_best_anime_match(
                        $inputName,
                        $searchResults
                    );

                    if (!$match) {
                        $stats['not_found']++;

                        $results[] = [
                            'input' => $inputName,
                            'status' => 'Not Found',
                            'detail' => 'No accurate AniList match found.',
                        ];

                        usleep(1250000);
                        continue;
                    }

                    $stats['found']++;

                    $animeId = (int)($match['id'] ?? 0);

                    if ($animeId <= 0) {
                        throw new RuntimeException(
                            'AniList returned an invalid anime ID.'
                        );
                    }

                    $anime = anilist_anime($animeId);

                    if (!$anime) {
                        $stats['not_found']++;

                        $results[] = [
                            'input' => $inputName,
                            'status' => 'Not Found',
                            'detail' => 'AniList details could not be loaded.',
                        ];

                        usleep(1250000);
                        continue;
                    }

                    $payload = anilist_anime_post_payload(
                        $anime,
                        $status
                    );

                    $existing = anime_import_find_existing(
                        $anime,
                        $existingPosts
                    );

                    if ($existing) {
                        // Preserve manually configured video/watch links.
                        $payload['youtube_url'] = $existing['youtube_url'] ?? '';

                        $payload['stream_anime_id'] =
                            !empty($existing['stream_anime_id'])
                                ? (int)$existing['stream_anime_id']
                                : 0;

                        $response = api_request(
                            'PUT',
                            '/api/posts/' . (int)$existing['id'],
                            $payload,
                            auth_token()
                        );

                        if (!$response['ok']) {
                            throw new RuntimeException(
                                api_message($response, 'Could not replace anime.')
                            );
                        }

                        $stats['replaced']++;

                        $saved = $response['data'] ?? [];

                        $results[] = [
                            'input' => $inputName,
                            'status' => 'Replaced',
                            'detail' =>
                                ($payload['title'] ?? $inputName)
                                . ' — AniList ID '
                                . $animeId,
                        ];
                    } else {
                        $response = api_request(
                            'POST',
                            '/api/posts',
                            $payload,
                            auth_token()
                        );

                        if (!$response['ok']) {
                            throw new RuntimeException(
                                api_message($response, 'Could not add anime.')
                            );
                        }

                        $stats['added']++;

                        $saved = $response['data'] ?? [];

                        $results[] = [
                            'input' => $inputName,
                            'status' => 'Added',
                            'detail' =>
                                ($payload['title'] ?? $inputName)
                                . ' — AniList ID '
                                . $animeId,
                        ];
                    }

                    // Keep duplicate detection updated during this same batch.
                    if (!empty($saved['id'])) {
                        $updated = false;

                        foreach ($existingPosts as $index => $post) {
                            if (
                                (int)($post['id'] ?? 0)
                                === (int)$saved['id']
                            ) {
                                $existingPosts[$index] = $saved;
                                $updated = true;
                                break;
                            }
                        }

                        if (!$updated) {
                            $existingPosts[] = $saved;
                        }
                    }
                } catch (Throwable $exception) {
                    $message = $exception->getMessage();

                    $stats['errors']++;

                    if (anime_import_is_timeout_error($message)) {
                        $stats['timeouts']++;
                    }

                    $results[] = [
                        'input' => $inputName,
                        'status' => 'Error',
                        'detail' => $message,
                    ];
                }

                usleep(1250000);
            }
        }
    }
}

admin_header(
    'Import Anime',
    'import-anime'
);
?>

<section class="admin-section">

    <div class="admin-section-head">
        <div>
            <span class="eyebrow">AniList Automation</span>
            <h1>Import Anime</h1>
            <p>
                Import AniList anime directly into the existing
                AniScope Anime section.
            </p>
        </div>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="alert <?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>
    <?php endforeach; ?>

    <form method="post" class="admin-form">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <label>
            <span>Anime Titles</span>

            <textarea
                name="anime_names"
                rows="10"
                required
                placeholder="Jujutsu Kaisen
Naruto
Bleach
Death Note"
            ><?= e($_POST['anime_names'] ?? '') ?></textarea>

            <small>
                One anime per line or separated by commas.
                Maximum 20 anime per batch.
            </small>
        </label>

        <label>
            <span>Import Status</span>

            <select name="status">
                <option
                    value="draft"
                    <?= ($_POST['status'] ?? 'draft') === 'draft'
                        ? 'selected'
                        : '' ?>
                >
                    Draft
                </option>

                <option
                    value="published"
                    <?= ($_POST['status'] ?? '') === 'published'
                        ? 'selected'
                        : '' ?>
                >
                    Published
                </option>
            </select>

            <small>
                Draft is recommended for reviewing imported data first.
            </small>
        </label>

        <button class="button primary" type="submit">
            Import Anime
        </button>

    </form>

    <?php if ($stats['searched'] > 0): ?>

        <?php
        $statLabels = [
            'searched' => 'Searched',
            'found' => 'Found',
            'added' => 'Added',
            'replaced' => 'Replaced',
            'not_found' => 'Not Found',
            'errors' => 'Errors',
            'timeouts' => 'Timeouts',
        ];
        ?>

        <div
            class="import-stat-grid"
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(120px,1fr));
                gap:12px;
                margin-top:28px;
            "
        >
            <?php foreach ($statLabels as $key => $label): ?>

                <div class="panel" style="padding:18px">

                    <strong
                        style="
                            display:block;
                            font-size:1.6rem;
                            margin-bottom:5px;
                        "
                    >
                        <?= (int)$stats[$key] ?>
                    </strong>

                    <span><?= e($label) ?></span>

                </div>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <?php if ($results): ?>

        <div
            class="panel"
            style="
                margin-top:24px;
                overflow:auto;
            "
        >

            <table
                style="
                    width:100%;
                    border-collapse:collapse;
                "
            >
                <thead>
                    <tr>
                        <th style="text-align:left;padding:12px">Input</th>
                        <th style="text-align:left;padding:12px">Result</th>
                        <th style="text-align:left;padding:12px">Details</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($results as $result): ?>

                        <tr>
                            <td style="padding:12px">
                                <?= e($result['input']) ?>
                            </td>

                            <td style="padding:12px">
                                <strong>
                                    <?= e($result['status']) ?>
                                </strong>
                            </td>

                            <td style="padding:12px">
                                <?= e($result['detail']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

</section>

<?php admin_footer(); ?>
