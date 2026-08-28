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
