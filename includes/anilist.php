<?php

function anilist_request($query, $variables = [])
{
    $payload = json_encode([
        'query' => $query,
        'variables' => $variables
    ]);

    $ch = curl_init('https://graphql.anilist.co');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'AniScope/1.0'
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException(
            $error ?: 'AniList request failed.'
        );
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException(
            'AniList returned HTTP ' . $status
        );
    }

    $json = json_decode($body, true);

    if (!is_array($json)) {
        throw new RuntimeException(
            'Invalid response from AniList.'
        );
    }

    if (!empty($json['errors'])) {
        throw new RuntimeException(
            $json['errors'][0]['message'] ?? 'AniList API error.'
        );
    }

    return $json['data'] ?? [];
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

function anilist_character_bio($character)
{
    $bio = trim((string) ($character['description'] ?? ''));

    if ($bio === '') {
        return 'Biography information is not available yet.';
    }

    return strip_tags($bio);
}
