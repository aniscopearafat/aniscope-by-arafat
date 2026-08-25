<?php

/*
|--------------------------------------------------------------------------
| AniScope Reusable Video Player
|--------------------------------------------------------------------------
|
| Example:
|
| $videoTitle = 'Episode 1';
|
| $videoSources = [
|     'sub' => 'https://.../episode-01-sub.mp4',
|     'dub' => 'https://.../episode-01-dub.mp4'
| ];
|
| $subtitleTracks = [
|     [
|         'label' => 'English',
|         'lang'  => 'en',
|         'url'   => 'https://.../english.vtt'
|     ]
| ];
|
*/

$videoTitle = $videoTitle ?? 'AniScope Video';

$videoPoster = $videoPoster ?? '';

$videoSources = is_array($videoSources ?? null)
    ? $videoSources
    : [];

$subtitleTracks = is_array($subtitleTracks ?? null)
    ? $subtitleTracks
    : [];


/*
|--------------------------------------------------------------------------
| Find default video source
|--------------------------------------------------------------------------
*/

$defaultLanguage = '';

foreach (['sub', 'dub', 'raw'] as $language) {

    if (!empty($videoSources[$language])) {

        $defaultLanguage = $language;

        break;
    }
}


$defaultSource = $defaultLanguage
    ? $videoSources[$defaultLanguage]
    : '';

?>

<div
    class="aniscope-player"
    data-aniscope-player
>

    <div class="aniscope-video-wrap">

        <?php if ($defaultSource): ?>

            <video
                class="aniscope-video"
                data-aniscope-video
                controls
                playsinline
                preload="metadata"
                <?php if ($videoPoster): ?>
                    poster="<?= e($videoPoster) ?>"
                <?php endif; ?>
            >

                <source
                    src="<?= e($defaultSource) ?>"
                    type="video/mp4"
                >


                <?php foreach ($subtitleTracks as $track): ?>

                    <?php

                    $trackUrl = trim(
                        (string) ($track['url'] ?? '')
                    );

                    if ($trackUrl === '') {
                        continue;
                    }

                    ?>

                    <track
                        kind="subtitles"
                        src="<?= e($trackUrl) ?>"
                        srclang="<?= e($track['lang'] ?? 'en') ?>"
                        label="<?= e($track['label'] ?? 'Subtitle') ?>"
                    >

                <?php endforeach; ?>


                Your browser does not support HTML5 video.

            </video>

        <?php else: ?>

            <div class="aniscope-player-empty">

                <div class="aniscope-player-empty-icon">
                    ▶
                </div>

                <h3>
                    Video unavailable
                </h3>

                <p>
                    No video source has been added for this episode.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <?php if ($defaultSource): ?>

        <div class="aniscope-player-bar">

            <div class="aniscope-player-info">

                <span>
                    NOW PLAYING
                </span>

                <strong>
                    <?= e($videoTitle) ?>
                </strong>

            </div>


            <div class="aniscope-player-options">


                <?php
                $availableLanguages = array_filter(
                    ['sub', 'dub', 'raw'],
                    fn($language) =>
                        !empty($videoSources[$language])
                );
                ?>


                <?php if ($availableLanguages): ?>

                    <div class="aniscope-language-switch">

                        <span class="player-option-label">
                            AUDIO
                        </span>


                        <?php foreach (
                            [
                                'sub' => 'SUB',
                                'dub' => 'DUB',
                                'raw' => 'RAW'
                            ]
                            as $language => $label
                        ): ?>

                            <?php if (!empty($videoSources[$language])): ?>

                                <button
                                    type="button"
                                    class="video-language-button <?= $defaultLanguage === $language ? 'active' : '' ?>"
                                    data-video-language="<?= e($language) ?>"
                                    data-video-url="<?= e($videoSources[$language]) ?>"
                                >
                                    <?= e($label) ?>
                                </button>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <?php if ($subtitleTracks): ?>

                    <label class="aniscope-subtitle-control">

                        <span class="player-option-label">
                            SUBTITLE
                        </span>

                        <select
                            data-subtitle-select
                            aria-label="Subtitle language"
                        >

                            <option value="-1">
                                Off
                            </option>


                            <?php foreach (
                                $subtitleTracks as $index => $track
                            ): ?>

                                <?php if (!empty($track['url'])): ?>

                                    <option value="<?= (int) $index ?>">
                                        <?= e($track['label'] ?? 'Subtitle') ?>
                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </select>

                    </label>

                <?php endif; ?>


            </div>

        </div>

    <?php endif; ?>

</div>
