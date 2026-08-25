/* =========================================================
   ANISCOPE VIDEO PLAYER
========================================================= */

document
    .querySelectorAll('[data-aniscope-player]')
    .forEach(player => {

        const video =
            player.querySelector('[data-aniscope-video]');

        if (!video) {
            return;
        }

        const languageButtons =
            player.querySelectorAll('[data-video-language]');

        /*
        |--------------------------------------------------------------------------
        | SUB / DUB / RAW SWITCH
        |--------------------------------------------------------------------------
        */

        languageButtons.forEach(button => {

            button.addEventListener('click', () => {

                const newUrl =
                    button.dataset.videoUrl;

                if (!newUrl) {
                    return;
                }

                const currentTime =
                    video.currentTime || 0;

                const wasPlaying =
                    !video.paused;

                video.src = newUrl;
                video.load();

                const restorePlayback = () => {

                    if (
                        currentTime > 0 &&
                        Number.isFinite(video.duration)
                    ) {
                        video.currentTime =
                            Math.min(
                                currentTime,
                                video.duration
                            );
                    }

                    if (wasPlaying) {
                        video.play().catch(() => {});
                    }

                    video.removeEventListener(
                        'loadedmetadata',
                        restorePlayback
                    );
                };

                video.addEventListener(
                    'loadedmetadata',
                    restorePlayback
                );

                languageButtons.forEach(item => {
                    item.classList.remove('active');
                });

                button.classList.add('active');
            });

        });


        /*
        |--------------------------------------------------------------------------
        | SUBTITLE SWITCH
        |--------------------------------------------------------------------------
        */

        const subtitleSelect =
            player.querySelector('[data-subtitle-select]');

        if (subtitleSelect) {

            subtitleSelect.addEventListener('change', () => {

                const selected =
                    Number(subtitleSelect.value);

                const tracks =
                    video.textTracks;

                for (
                    let i = 0;
                    i < tracks.length;
                    i++
                ) {
                    tracks[i].mode =
                        i === selected
                            ? 'showing'
                            : 'disabled';
                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | DEFAULT SUBTITLE STATE
        |--------------------------------------------------------------------------
        */

        video.addEventListener('loadedmetadata', () => {

            const tracks =
                video.textTracks;

            for (
                let i = 0;
                i < tracks.length;
                i++
            ) {
                tracks[i].mode = 'disabled';
            }

        });

    });


/* =========================================================
   EPISODE SEARCH
========================================================= */

document
    .querySelectorAll('[data-episode-search]')
    .forEach(search => {

        search.addEventListener('input', () => {

            const query =
                search.value
                    .trim()
                    .toLowerCase();

            const list =
                document.querySelector(
                    '[data-episode-list]'
                );

            if (!list) {
                return;
            }

            list
                .querySelectorAll(
                    '[data-episode-item]'
                )
                .forEach(item => {

                    const number =
                        (
                            item.dataset.episodeNumber
                            || ''
                        ).toLowerCase();

                    const text =
                        item.textContent
                            .toLowerCase();

                    item.hidden =
                        query !== ''
                        &&
                        !number.includes(query)
                        &&
                        !text.includes(query);

                });

        });

    });
