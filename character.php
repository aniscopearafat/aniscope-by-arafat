<?php

require_once __DIR__ . '/includes/api.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$character = $id
    ? api_data('/api/characters/' . $id)
    : [];

if (!$character) {

    http_response_code(404);

    $pageTitle = 'Character Not Found — AniScope';

    require __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/cards.php';

    echo '<section class="section not-found">
            <div class="container">';

    empty_state(
        'That character profile could not be found.'
    );

    echo '</div></section>';

    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle =
    $character['name'] .
    ' — AniScope by Arafat';

$activePage = 'characters';

require __DIR__ . '/includes/header.php';


$bio = trim(
    (string) ($character['bio'] ?? '')
);

$bio = str_replace(
    ["\r\n", "\r"],
    "\n",
    $bio
);

$paragraphs = preg_split(
    "/\n\s*\n/",
    $bio
);

if (!$paragraphs) {
    $paragraphs = [$bio];
}


$abilities = preg_split(
    '/,\s*/',
    (string) ($character['abilities'] ?? '')
);

$abilities = array_filter(
    array_map('trim', $abilities)
);

?>

<section class="profile-page">

    <div class="profile-glow"></div>

    <div class="container profile-grid">

        <div class="profile-image reveal">

            <img
                src="<?= e(image_url($character['image_url'])) ?>"
                alt="<?= e($character['name']) ?>"
            >

            <div class="profile-image-caption">
                <span>Character</span>

                <strong>
                    <?= e($character['anime_name']) ?>
                </strong>
            </div>

        </div>


        <div class="profile-copy reveal">

            <span class="eyebrow">
                <?= e($character['anime_name']) ?>
            </span>

            <h1>
                <?= e($character['name']) ?>
            </h1>


            <section class="character-story">

                <div class="character-section-heading">
                    <span>Profile</span>
                    <h2>Biography</h2>
                </div>

                <div class="character-bio">

                    <?php foreach ($paragraphs as $paragraph): ?>

                        <?php
                        $paragraph = trim($paragraph);

                        if ($paragraph === '') {
                            continue;
                        }
                        ?>

                        <p>
                            <?= nl2br(e($paragraph)) ?>
                        </p>

                    <?php endforeach; ?>

                </div>

            </section>


            <?php if ($abilities): ?>

                <section class="character-abilities">

                    <div class="character-section-heading">
                        <span>Combat Profile</span>
                        <h2>Signature Abilities</h2>
                    </div>

                    <div class="ability-tags">

                        <?php foreach ($abilities as $ability): ?>

                            <span class="ability-tag">
                                <?= e($ability) ?>
                            </span>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>


            <div class="character-meta">

                <div>
                    <span>Series</span>
                    <strong>
                        <?= e($character['anime_name']) ?>
                    </strong>
                </div>

                <div>
                    <span>Profile</span>
                    <strong>AniScope Character Database</strong>
                </div>

            </div>


            <a
                class="button ghost"
                href="/characters.php"
            >
                ← All characters
            </a>

        </div>

    </div>

</section>

<?php
require __DIR__ . '/includes/footer.php';
?>
