<?php

declare(strict_types=1);

require_once __DIR__ . '/api.php';


function stream_anime_list(bool $publishedOnly = false): array
{
    $sql = "
        SELECT *
        FROM stream_anime
    ";

    if ($publishedOnly) {
        $sql .= " WHERE is_published = 1 ";
    }

    $sql .= " ORDER BY id DESC ";

    return db()->query($sql)->fetchAll() ?: [];
}


function stream_anime(int $id): array
{
    $q = db()->prepare("
        SELECT *
        FROM stream_anime
        WHERE id = ?
        LIMIT 1
    ");

    $q->execute([$id]);

    $row = $q->fetch();

    return is_array($row) ? $row : [];
}


function stream_episode_list(int $animeId, bool $publishedOnly = false): array
{
    $sql = "
        SELECT *
        FROM stream_episodes
        WHERE anime_id = ?
    ";

    if ($publishedOnly) {
        $sql .= " AND is_published = 1 ";
    }

    $sql .= " ORDER BY episode_number ASC ";

    $q = db()->prepare($sql);
    $q->execute([$animeId]);

    return $q->fetchAll() ?: [];
}


function stream_episode(int $id): array
{
    $q = db()->prepare("
        SELECT *
        FROM stream_episodes
        WHERE id = ?
        LIMIT 1
    ");

    $q->execute([$id]);

    $row = $q->fetch();

    return is_array($row) ? $row : [];
}


function stream_episode_sources(int $episodeId): array
{
    $q = db()->prepare("
        SELECT *
        FROM stream_sources
        WHERE episode_id = ?
        AND is_active = 1
        ORDER BY is_default DESC, id ASC
    ");

    $q->execute([$episodeId]);

    return $q->fetchAll() ?: [];
}


function stream_player_sources(int $episodeId): array
{
    $rows = stream_episode_sources($episodeId);
    $sources = [];

    foreach ($rows as $row) {
        $language = strtolower((string)($row['language'] ?? ''));

        if (
            in_array($language, ['sub','dub','raw'], true)
            && empty($sources[$language])
        ) {
            $sources[$language] = $row['source_url'];
        }
    }

    return $sources;
}


function stream_episode_subtitles(int $episodeId): array
{
    $q = db()->prepare("
        SELECT *
        FROM stream_subtitles
        WHERE episode_id = ?
        AND is_active = 1
        ORDER BY is_default DESC, id ASC
    ");

    $q->execute([$episodeId]);

    $rows = $q->fetchAll() ?: [];

    return array_map(
        fn($row) => [
            'label' => $row['label'],
            'lang'  => $row['language_code'],
            'url'   => $row['subtitle_url']
        ],
        $rows
    );
}


function save_stream_anime(array $data, int $id = 0): int
{
    $title = trim((string)($data['title'] ?? ''));

    if ($title === '') {
        throw new InvalidArgumentException('Anime title is required.');
    }

    $slug = slugify($title);
    $now = now_iso();

    if ($id > 0) {
        $q = db()->prepare("
            UPDATE stream_anime
            SET
                title = ?,
                slug = ?,
                description = ?,
                poster_url = ?,
                banner_url = ?,
                release_year = ?,
                status = ?,
                genres = ?,
                is_published = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $q->execute([
            $title,
            $slug,
            trim((string)($data['description'] ?? '')),
            trim((string)($data['poster_url'] ?? '')),
            trim((string)($data['banner_url'] ?? '')),
            ($data['release_year'] ?? '') !== '' ? (int)$data['release_year'] : null,
            trim((string)($data['status'] ?? '')),
            trim((string)($data['genres'] ?? '')),
            !empty($data['is_published']) ? 1 : 0,
            $now,
            $id
        ]);

        return $id;
    }

    $q = db()->prepare("
        INSERT INTO stream_anime (
            title, slug, description, poster_url, banner_url,
            release_year, status, genres, is_published,
            created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $q->execute([
        $title,
        $slug,
        trim((string)($data['description'] ?? '')),
        trim((string)($data['poster_url'] ?? '')),
        trim((string)($data['banner_url'] ?? '')),
        ($data['release_year'] ?? '') !== '' ? (int)$data['release_year'] : null,
        trim((string)($data['status'] ?? '')),
        trim((string)($data['genres'] ?? '')),
        !empty($data['is_published']) ? 1 : 0,
        $now,
        $now
    ]);

    return (int)db()->lastInsertId();
}


function delete_stream_anime(int $id): void
{
    $q = db()->prepare("DELETE FROM stream_anime WHERE id = ?");
    $q->execute([$id]);
}


function save_stream_episode(array $data, int $id = 0): int
{
    $animeId = (int)($data['anime_id'] ?? 0);
    $episodeNumber = (float)($data['episode_number'] ?? 0);

    if ($animeId <= 0 || $episodeNumber <= 0) {
        throw new InvalidArgumentException('Anime and episode number are required.');
    }

    $now = now_iso();

    if ($id > 0) {
        $q = db()->prepare("
            UPDATE stream_episodes
            SET
                anime_id = ?,
                episode_number = ?,
                title = ?,
                description = ?,
                thumbnail_url = ?,
                is_published = ?,
                updated_at = ?
            WHERE id = ?
        ");

        $q->execute([
            $animeId,
            $episodeNumber,
            trim((string)($data['title'] ?? '')),
            trim((string)($data['description'] ?? '')),
            trim((string)($data['thumbnail_url'] ?? '')),
            !empty($data['is_published']) ? 1 : 0,
            $now,
            $id
        ]);

        return $id;
    }

    $q = db()->prepare("
        INSERT INTO stream_episodes (
            anime_id, episode_number, title, description,
            thumbnail_url, is_published, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $q->execute([
        $animeId,
        $episodeNumber,
        trim((string)($data['title'] ?? '')),
        trim((string)($data['description'] ?? '')),
        trim((string)($data['thumbnail_url'] ?? '')),
        !empty($data['is_published']) ? 1 : 0,
        $now,
        $now
    ]);

    return (int)db()->lastInsertId();
}


function delete_stream_episode(int $id): void
{
    $q = db()->prepare("DELETE FROM stream_episodes WHERE id = ?");
    $q->execute([$id]);
}


function replace_stream_source(
    int $episodeId,
    string $language,
    string $url
): void {
    $language = strtolower($language);
    $url = trim($url);

    db()->prepare("
        DELETE FROM stream_sources
        WHERE episode_id = ?
        AND language = ?
    ")->execute([$episodeId, $language]);

    if ($url === '') {
        return;
    }

    $now = now_iso();

    $q = db()->prepare("
        INSERT INTO stream_sources (
            episode_id,
            language,
            source_type,
            server_name,
            source_url,
            is_default,
            is_active,
            created_at,
            updated_at
        )
        VALUES (?, ?, 'mp4', 'Cloudflare R2', ?, 1, 1, ?, ?)
    ");

    $q->execute([
        $episodeId,
        $language,
        $url,
        $now,
        $now
    ]);
}


function replace_stream_subtitle(
    int $episodeId,
    string $label,
    string $languageCode,
    string $url
): void {
    $url = trim($url);

    db()->prepare("
        DELETE FROM stream_subtitles
        WHERE episode_id = ?
        AND language_code = ?
    ")->execute([$episodeId, $languageCode]);

    if ($url === '') {
        return;
    }

    $now = now_iso();

    $q = db()->prepare("
        INSERT INTO stream_subtitles (
            episode_id,
            label,
            language_code,
            subtitle_url,
            is_default,
            is_active,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, 0, 1, ?, ?)
    ");

    $q->execute([
        $episodeId,
        $label,
        $languageCode,
        $url,
        $now,
        $now
    ]);
}
