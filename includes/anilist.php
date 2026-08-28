<?php

function anilist_request($query, $variables = [])
{
    $payload = json_encode([
        'query' => $query,
        'variables' => $variables
    ]);

    $maxAttempts = 7;
    $attempt = 0;

    while ($attempt < $maxAttempts) {

        $attempt++;

        $headers = [];

        $ch = curl_init('https://graphql.anilist.co');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) {

                $length = strlen($header);
                $parts = explode(':', $header, 2);

                if (count($parts) === 2) {
                    $headers[
                        strtolower(trim($parts[0]))
                    ] = trim($parts[1]);
                }

                return $length;
            },
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'AniScope/1.0'
        ]);

        $body = curl_exec($ch);

        $status = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $error = curl_error($ch);

        curl_close($ch);


        /*
         * Successful response
         */
        if (
            $body !== false &&
            $error === '' &&
            $status >= 200 &&
            $status < 300
        ) {

            $json = json_decode(
                $body,
                true
            );

            if (!is_array($json)) {

                if ($attempt < $maxAttempts) {
                    sleep(2);
                    continue;
                }

                throw new RuntimeException(
                    'Invalid response from AniList.'
                );
            }

            if (!empty($json['errors'])) {

                $message =
                    $json['errors'][0]['message']
                    ?? 'AniList API error.';

                /*
                 * Some GraphQL errors may be temporary.
                 */
                if ($attempt < $maxAttempts) {
                    sleep(2);
                    continue;
                }

                throw new RuntimeException(
                    $message
                );
            }

            return $json['data'] ?? [];
        }


        /*
         * Rate limit
         */
        if ($status === 429) {

            $retryAfter = isset(
                $headers['retry-after']
            )
                ? (int) $headers['retry-after']
                : 0;

            if ($retryAfter <= 0) {

                /*
                 * Exponential backoff:
                 * 3, 6, 12, 20, 30...
                 */
                $retryAfter = min(
                    30,
                    max(
                        3,
                        (int) pow(2, $attempt)
                    )
                );
            }

            /*
             * Small random jitter prevents repeatedly
             * hitting the same API window.
             */
            $retryAfter += random_int(1, 3);

            if ($attempt < $maxAttempts) {
                sleep($retryAfter);
                continue;
            }

            throw new RuntimeException(
                'AniList rate limit is still active. Please try again shortly.'
            );
        }


        /*
         * Temporary server errors
         */
        if (
            in_array(
                $status,
                [500, 502, 503, 504],
                true
            )
        ) {

            if ($attempt < $maxAttempts) {

                $delay = min(
                    20,
                    $attempt * 3
                );

                sleep($delay);
                continue;
            }

            throw new RuntimeException(
                'AniList is temporarily unavailable.'
            );
        }


        /*
         * Network / timeout errors
         */
        if (
            $body === false ||
            $error !== ''
        ) {

            if ($attempt < $maxAttempts) {

                $delay = min(
                    15,
                    $attempt * 2
                );

                sleep($delay);
                continue;
            }

            throw new RuntimeException(
                $error ?: 'AniList network request failed.'
            );
        }


        /*
         * Non-retryable HTTP errors
         */
        throw new RuntimeException(
            'AniList returned HTTP ' . $status
        );
    }

    throw new RuntimeException(
        'AniList request could not be completed.'
    );
}

function anilist_search_characters($name)
{
    $query = <<<'GRAPHQL'
query ($search: String) {
  Page(page: 1, perPage: 12) {
    characters(search: $search, sort: SEARCH_MATCH) {
      id
      name {
        full
        native
        alternative
      }
      image {
        large
        medium
      }
      description(asHtml: false)
      media(perPage: 5, sort: POPULARITY_DESC) {
        nodes {
          id
          title {
            romaji
            english
          }
        }
      }
    }
  }
}
GRAPHQL;

    $data = anilist_request($query, [
        'search' => trim($name)
    ]);

    return $data['Page']['characters'] ?? [];
}

function anilist_character($id)
{
    $query = <<<'GRAPHQL'
query ($id: Int) {
  Character(id: $id) {
    id
    name {
      full
      native
      alternative
    }
    image {
      large
      medium
    }
    description(asHtml: false)
    media(perPage: 10, sort: POPULARITY_DESC) {
      nodes {
        id
        title {
          romaji
          english
        }
      }
    }
  }
}
GRAPHQL;

    $data = anilist_request($query, [
        'id' => (int) $id
    ]);

    return $data['Character'] ?? null;
}

function anilist_character_series($character)
{
    $nodes = $character['media']['nodes'] ?? [];

    if (!$nodes) {
        return 'Unknown Series';
    }

    $title = $nodes[0]['title'] ?? [];

    if (!empty($title['english'])) {
        return $title['english'];
    }

    if (!empty($title['romaji'])) {
        return $title['romaji'];
    }

    return 'Unknown Series';
}

function anilist_character_image($character)
{
    if (!empty($character['image']['large'])) {
        return $character['image']['large'];
    }

    if (!empty($character['image']['medium'])) {
        return $character['image']['medium'];
    }

    return '/assets/images/character-blue.svg';
}

function anilist_clean_text($text)
{
    $text = (string) $text;

    // AniList spoiler markers
    $text = str_replace(['~!', '!~'], '', $text);

    // Markdown links: [Naruto](https://...) -> Naruto
    $text = preg_replace(
        '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/i',
        '$1',
        $text
    );

    // Escaped / malformed AniList links
    $text = preg_replace(
        '/\[([^\]]+)\]\\?\((https?:\/\/[^)]+)\)/i',
        '$1',
        $text
    );

    // Markdown formatting
    $text = str_replace(
        ['__', '**', '###', '##', '#'],
        '',
        $text
    );

    // Remove remaining URLs
    $text = preg_replace(
        '~https?://\S+~i',
        '',
        $text
    );

    // Normalize newlines
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Remove excessive spaces
    $text = preg_replace('/[ \t]+/', ' ', $text);

    // Clean spaces before punctuation
    $text = preg_replace('/\s+([,.!?;:])/', '$1', $text);

    // Clean excessive blank lines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}


function anilist_character_bio($character)
{
    $bio = anilist_clean_text(
        $character['description'] ?? ''
    );

    if ($bio === '') {
        return 'Biography information is not available yet.';
    }

    return $bio;
}


function anilist_character_abilities($character)
{
    $bio = anilist_character_bio($character);

    if ($bio === '') {
        return 'Abilities information is not available yet.';
    }

    $abilities = [];

    $patterns = [
        'jinchuuriki' => 'Jinchūriki abilities',
        'jinchūriki' => 'Jinchūriki abilities',
        'tailed beast' => 'Tailed Beast power',
        'shukaku' => 'Power of Shukaku',
        'sharingan' => 'Sharingan',
        'mangekyou' => 'Mangekyō Sharingan',
        'mangekyō' => 'Mangekyō Sharingan',
        'byakugan' => 'Byakugan',
        'rasengan' => 'Rasengan',
        'chidori' => 'Chidori',
        'sage mode' => 'Sage Mode',
        'domain expansion' => 'Domain Expansion',
        'cursed energy' => 'Cursed Energy',
        'six eyes' => 'Six Eyes',
        'limitless' => 'Limitless',
        'one for all' => 'One For All',
        'devil fruit' => 'Devil Fruit abilities',
        'bankai' => 'Bankai',
        'zanpakuto' => 'Zanpakutō',
        'alchemy' => 'Alchemy',
        'magic' => 'Magic abilities',
        'chakra' => 'Chakra control',
        'swordsmanship' => 'Swordsmanship'
    ];

    $lower = strtolower($bio);

    foreach ($patterns as $needle => $label) {
        if (strpos($lower, $needle) !== false) {
            $abilities[] = $label;
        }
    }

    /*
     * Try to detect useful power-related sentences from the biography.
     */
    $sentences = preg_split(
        '/(?<=[.!?])\s+/',
        str_replace("\n", ' ', $bio)
    );

    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);

        if ($sentence === '') {
            continue;
        }

        $sentenceLower = strtolower($sentence);

        $keywords = [
            'ability',
            'power',
            'technique',
            'jutsu',
            'jinchuuriki',
            'jinchūriki',
            'tailed beast',
            'chakra',
            'magic',
            'curse',
            'weapon'
        ];

        foreach ($keywords as $keyword) {
            if (strpos($sentenceLower, $keyword) !== false) {

                if (strlen($sentence) <= 160) {
                    $abilities[] = $sentence;
                }

                break;
            }
        }

        if (count($abilities) >= 5) {
            break;
        }
    }

    $abilities = array_values(array_unique($abilities));

    if (!$abilities) {
        return 'Special combat abilities and techniques.';
    }

    return implode(', ', array_slice($abilities, 0, 5));
}


function anilist_character_aliases($character)
{
    $aliases = $character['name']['alternative'] ?? [];

    $aliases = array_filter(
        array_map('trim', $aliases)
    );

    return array_values(array_unique($aliases));
}

function anilist_normalize_name($name)
{
    $name = strtolower(
        trim((string) $name)
    );

    $name = preg_replace(
        '/[^a-z0-9]+/i',
        '',
        $name
    );

    return $name;
}


function anilist_best_character_match($searchName, $results)
{
    if (!$results) {
        return null;
    }

    $wanted = anilist_normalize_name(
        $searchName
    );

    /*
     * First: exact full/native/alternative match.
     */
    foreach ($results as $character) {

        $candidateNames = [];

        if (!empty(
            $character['name']['full']
        )) {
            $candidateNames[] =
                $character['name']['full'];
        }

        if (!empty(
            $character['name']['native']
        )) {
            $candidateNames[] =
                $character['name']['native'];
        }

        foreach (
            $character['name']['alternative']
                ?? []
            as $alternative
        ) {
            $candidateNames[] =
                $alternative;
        }

        foreach (
            $candidateNames
            as $candidate
        ) {

            if (
                anilist_normalize_name(
                    $candidate
                ) === $wanted
            ) {
                return $character;
            }
        }
    }


    /*
     * Second: similarity scoring.
     */
    $best = null;
    $bestScore = 0;

    foreach ($results as $character) {

        $fullName =
            $character['name']['full']
            ?? '';

        $normalized =
            anilist_normalize_name(
                $fullName
            );

        if (
            $wanted === '' ||
            $normalized === ''
        ) {
            continue;
        }

        similar_text(
            $wanted,
            $normalized,
            $score
        );

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $character;
        }
    }


    /*
     * Avoid importing a very unrelated result.
     */
    if ($bestScore < 55) {
        return null;
    }

    return $best;
}


/* ============================================================
   ANILIST ANIME / MEDIA IMPORT
   ============================================================ */

/**
 * Search AniList anime by title.
 */
function anilist_search_anime($name)
{
    $query = <<<'GRAPHQL'
query ($search: String) {
  Page(page: 1, perPage: 12) {
    media(
      search: $search
      type: ANIME
      sort: SEARCH_MATCH
    ) {
      id

      title {
        romaji
        english
        native
      }

      synonyms

      description(asHtml: false)

      coverImage {
        extraLarge
        large
        medium
      }

      bannerImage

      format
      status
      episodes
      duration
      season
      seasonYear
      source
      averageScore
      popularity
      favourites

      genres

      startDate {
        year
        month
        day
      }

      endDate {
        year
        month
        day
      }

      studios(isMain: true) {
        nodes {
          id
          name
          isAnimationStudio
        }
      }

      siteUrl
    }
  }
}
GRAPHQL;

    $data = anilist_request($query, [
        'search' => trim((string)$name)
    ]);

    return $data['Page']['media'] ?? [];
}


/**
 * Get one anime directly by AniList ID.
 */
function anilist_anime($id)
{
    $query = <<<'GRAPHQL'
query ($id: Int) {
  Media(id: $id, type: ANIME) {

    id

    title {
      romaji
      english
      native
    }

    synonyms

    description(asHtml: false)

    coverImage {
      extraLarge
      large
      medium
    }

    bannerImage

    format
    status
    episodes
    duration
    season
    seasonYear
    source
    averageScore
    popularity
    favourites

    genres

    startDate {
      year
      month
      day
    }

    endDate {
      year
      month
      day
    }

    studios(isMain: true) {
      nodes {
        id
        name
        isAnimationStudio
      }
    }

    siteUrl
  }
}
GRAPHQL;

    $data = anilist_request($query, [
        'id' => (int)$id
    ]);

    return $data['Media'] ?? [];
}


/**
 * Normalize a title for safer comparisons.
 */
function anilist_normalize_anime_title($title)
{
    $title = strtolower(
        trim((string)$title)
    );

    return preg_replace(
        '/[^a-z0-9]+/i',
        '',
        $title
    );
}


/**
 * Return every useful title/alias for an AniList anime.
 */
function anilist_anime_titles($anime)
{
    $titles = [];

    foreach ([
        $anime['title']['english'] ?? '',
        $anime['title']['romaji'] ?? '',
        $anime['title']['native'] ?? ''
    ] as $title) {

        $title = trim((string)$title);

        if ($title !== '') {
            $titles[] = $title;
        }
    }

    foreach (($anime['synonyms'] ?? []) as $synonym) {

        $synonym = trim((string)$synonym);

        if ($synonym !== '') {
            $titles[] = $synonym;
        }
    }

    return array_values(
        array_unique($titles)
    );
}


/**
 * Pick the best title for AniScope.
 *
 * English first, then Romaji, then native title.
 */
function anilist_anime_title($anime)
{
    foreach ([
        $anime['title']['english'] ?? '',
        $anime['title']['romaji'] ?? '',
        $anime['title']['native'] ?? ''
    ] as $title) {

        $title = trim((string)$title);

        if ($title !== '') {
            return $title;
        }
    }

    return 'Untitled Anime';
}


/**
 * Choose the most accurate anime from AniList search results.
 *
 * Exact title/synonym matches are preferred.
 * Fuzzy matching is only used as a fallback.
 */
function anilist_best_anime_match($searchName, $results)
{
    if (!is_array($results) || !$results) {
        return null;
    }

    $wanted = anilist_normalize_anime_title(
        $searchName
    );

    if ($wanted === '') {
        return null;
    }


    /*
     * Exact match first.
     */
    foreach ($results as $anime) {

        if (!is_array($anime)) {
            continue;
        }

        foreach (anilist_anime_titles($anime) as $title) {

            if (
                anilist_normalize_anime_title($title)
                === $wanted
            ) {
                return $anime;
            }
        }
    }


    /*
     * Fuzzy fallback.
     */
    $best = null;
    $bestScore = 0;

    foreach ($results as $anime) {

        if (!is_array($anime)) {
            continue;
        }

        foreach (anilist_anime_titles($anime) as $title) {

            $candidate =
                anilist_normalize_anime_title($title);

            if ($candidate === '') {
                continue;
            }

            similar_text(
                $wanted,
                $candidate,
                $score
            );

            if ($score > $bestScore) {

                $bestScore = $score;
                $best = $anime;
            }
        }
    }


    /*
     * Avoid importing a completely unrelated search result.
     */
    if ($bestScore < 55) {
        return null;
    }

    return $best;
}


/**
 * Clean AniList descriptions for AniScope.
 */
function anilist_clean_anime_text($text)
{
    $text = (string)$text;

    /*
     * AniList spoiler syntax.
     */
    $text = preg_replace(
        '/~!|!~/',
        '',
        $text
    );

    /*
     * Markdown links: [text](url) -> text
     */
    $text = preg_replace(
        '/\[([^\]]+)\]\([^)]+\)/',
        '$1',
        $text
    );

    /*
     * Basic markdown formatting.
     */
    $text = str_replace(
        ['**', '__', '*', '~~'],
        '',
        $text
    );

    /*
     * Remove remaining HTML.
     */
    $text = strip_tags($text);

    /*
     * Normalize line endings.
     */
    $text = str_replace(
        ["\r\n", "\r"],
        "\n",
        $text
    );

    /*
     * Remove excessive spaces.
     */
    $text = preg_replace(
        '/[ \t]+/',
        ' ',
        $text
    );

    /*
     * Keep paragraph breaks but remove huge gaps.
     */
    $text = preg_replace(
        "/\n{3,}/",
        "\n\n",
        $text
    );

    return trim($text);
}


/**
 * Short description suitable for posts.excerpt.
 *
 * posts.excerpt has a 500 character limit.
 */
function anilist_anime_excerpt($anime)
{
    $description = anilist_clean_anime_text(
        $anime['description'] ?? ''
    );

    if ($description === '') {
        return 'Explore this anime, its story, release information, genres, and production details.';
    }

    if (strlen($description) <= 480) {
        return $description;
    }

    $excerpt = substr(
        $description,
        0,
        477
    );

    $lastSpace = strrpos(
        $excerpt,
        ' '
    );

    if ($lastSpace !== false && $lastSpace > 300) {
        $excerpt = substr(
            $excerpt,
            0,
            $lastSpace
        );
    }

    return rtrim(
        $excerpt,
        " \t\n\r\0\x0B.,;:-"
    ) . '...';
}


/**
 * Best AniList poster.
 */
function anilist_anime_image($anime)
{
    foreach ([
        $anime['coverImage']['extraLarge'] ?? '',
        $anime['coverImage']['large'] ?? '',
        $anime['coverImage']['medium'] ?? ''
    ] as $image) {

        $image = trim((string)$image);

        if ($image !== '') {
            return $image;
        }
    }

    return '/assets/images/post-fire.svg';
}


/**
 * Human-readable enum formatting.
 *
 * TV_SHORT -> TV Short
 * FINISHED -> Finished
 */
function anilist_anime_label($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $value = strtolower(
        str_replace('_', ' ', $value)
    );

    return ucwords($value);
}


/**
 * Format AniList date.
 */
function anilist_anime_date($date)
{
    if (!is_array($date)) {
        return '';
    }

    $year = (int)($date['year'] ?? 0);
    $month = (int)($date['month'] ?? 0);
    $day = (int)($date['day'] ?? 0);

    if (!$year) {
        return '';
    }

    if (!$month) {
        return (string)$year;
    }

    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];

    $result = $months[$month] . ' ';

    if ($day) {
        $result .= $day . ', ';
    }

    $result .= $year;

    return $result;
}


/**
 * Main animation studios.
 */
function anilist_anime_studios($anime)
{
    $studios = [];

    $nodes =
        $anime['studios']['nodes']
        ?? [];

    foreach ($nodes as $studio) {

        $name = trim(
            (string)($studio['name'] ?? '')
        );

        if ($name !== '') {
            $studios[] = $name;
        }
    }

    return array_values(
        array_unique($studios)
    );
}


/**
 * Build readable article content for the existing posts system.
 */
function anilist_anime_content($anime)
{
    $title =
        anilist_anime_title($anime);

    $description =
        anilist_clean_anime_text(
            $anime['description'] ?? ''
        );

    $lines = [];

    $lines[] = $title;

    if ($description !== '') {

        $lines[] = '';
        $lines[] = 'Overview';
        $lines[] = '';
        $lines[] = $description;
    }

    $lines[] = '';
    $lines[] = 'Anime Information';
    $lines[] = '';


    $information = [];


    $format =
        anilist_anime_label(
            $anime['format'] ?? ''
        );

    if ($format !== '') {
        $information[] =
            'Format: ' . $format;
    }


    $episodes =
        (int)($anime['episodes'] ?? 0);

    if ($episodes > 0) {
        $information[] =
            'Episodes: ' . $episodes;
    }


    $duration =
        (int)($anime['duration'] ?? 0);

    if ($duration > 0) {
        $information[] =
            'Episode Duration: ' .
            $duration .
            ' minutes';
    }


    $status =
        anilist_anime_label(
            $anime['status'] ?? ''
        );

    if ($status !== '') {
        $information[] =
            'Status: ' . $status;
    }


    $season =
        anilist_anime_label(
            $anime['season'] ?? ''
        );

    $seasonYear =
        (int)($anime['seasonYear'] ?? 0);

    if ($season !== '') {

        $information[] =
            'Season: ' .
            $season .
            ($seasonYear
                ? ' ' . $seasonYear
                : '');

    } elseif ($seasonYear) {

        $information[] =
            'Release Year: ' .
            $seasonYear;
    }


    $startDate =
        anilist_anime_date(
            $anime['startDate'] ?? []
        );

    if ($startDate !== '') {
        $information[] =
            'Start Date: ' .
            $startDate;
    }


    $endDate =
        anilist_anime_date(
            $anime['endDate'] ?? []
        );

    if ($endDate !== '') {
        $information[] =
            'End Date: ' .
            $endDate;
    }


    $source =
        anilist_anime_label(
            $anime['source'] ?? ''
        );

    if ($source !== '') {
        $information[] =
            'Source: ' .
            $source;
    }


    $genres =
        $anime['genres'] ?? [];

    if (is_array($genres) && $genres) {

        $information[] =
            'Genres: ' .
            implode(
                ', ',
                array_filter($genres)
            );
    }


    $studios =
        anilist_anime_studios($anime);

    if ($studios) {

        $information[] =
            'Studio: ' .
            implode(', ', $studios);
    }


    $score =
        (int)($anime['averageScore'] ?? 0);

    if ($score > 0) {

        $information[] =
            'Average Score: ' .
            $score .
            '%';
    }


    foreach ($information as $line) {
        $lines[] = $line;
    }


    /*
     * Alternative titles.
     */
    $titles =
        anilist_anime_titles($anime);

    $mainTitle =
        anilist_normalize_anime_title(
            $title
        );

    $alternativeTitles = [];

    foreach ($titles as $alternativeTitle) {

        if (
            anilist_normalize_anime_title(
                $alternativeTitle
            ) === $mainTitle
        ) {
            continue;
        }

        $alternativeTitles[] =
            $alternativeTitle;
    }

    if ($alternativeTitles) {

        $lines[] = '';
        $lines[] = 'Alternative Titles';
        $lines[] = '';
        $lines[] = implode(
            ', ',
            array_slice(
                array_unique($alternativeTitles),
                0,
                8
            )
        );
    }


    return trim(
        implode("\n", $lines)
    );
}


/**
 * Convert AniList anime into the existing /api/posts payload.
 */
function anilist_anime_post_payload($anime, $status = 'draft')
{
    $status =
        $status === 'published'
            ? 'published'
            : 'draft';

    return [
        'anilist_id' =>
            (int)($anime['id'] ?? 0),

        'title' =>
            anilist_anime_title($anime),

        'category' =>
            'Anime',

        'excerpt' =>
            anilist_anime_excerpt($anime),

        'content' =>
            anilist_anime_content($anime),

        'image_url' =>
            anilist_anime_image($anime),

        'youtube_url' =>
            '',

        /*
         * AniList metadata does NOT mean
         * AniScope has a playable video.
         */
        'stream_anime_id' =>
            0,

        'status' =>
            $status
    ];
}
