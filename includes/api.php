<?php
declare(strict_types=1);

function env_value(string $key, string $default = ''): string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$name, $value] = array_map('trim', explode('=', $line, 2));
                $values[$name] = trim($value, "\"'");
            }
        }
    }
    return $_ENV[$key] ?? getenv($key) ?: ($values[$key] ?? $default);
}

function db_config(): array
{
    $configFile = dirname(__DIR__) . '/config/database.php';
    $config = is_file($configFile) ? require $configFile : [];
    return [
        'host' => $config['host'] ?? env_value('DB_HOST', 'localhost'),
        'port' => $config['port'] ?? env_value('DB_PORT', '3306'),
        'name' => $config['name'] ?? env_value('DB_NAME', ''),
        'user' => $config['user'] ?? env_value('DB_USER', ''),
        'pass' => $config['pass'] ?? env_value('DB_PASS', ''),
        'charset' => $config['charset'] ?? env_value('DB_CHARSET', 'utf8mb4'),
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $config = db_config();
    if ($config['name'] === '' || $config['user'] === '') {
        throw new RuntimeException('Database is not configured. Set DB_HOST, DB_NAME, DB_USER, and DB_PASS in .env or config/database.php.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']);
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    initialize_database($pdo);
    return $pdo;
}

function now_iso(): string
{
    return gmdate('c');
}

function slugify(string $value): string
{
    $value = preg_replace('/[^a-zA-Z0-9\s-]/', '', trim($value));
    $value = strtolower((string)$value);
    return preg_replace('/[-\s]+/', '-', $value) ?: 'untitled';
}

function unique_slug(PDO $pdo, string $table, string $title, ?int $itemId = null): string
{
    if (!in_array($table, ['posts', 'characters'], true)) throw new InvalidArgumentException('Invalid table.');
    $base = slugify($title);
    $candidate = $base;
    $suffix = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$candidate];
        if ($itemId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $itemId;
        }
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        if (!$statement->fetch()) return $candidate;
        $candidate = $base . '-' . $suffix++;
    }
}

function initialize_database(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) return;

    $schema = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            email VARCHAR(200) NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','moderator','user') NOT NULL DEFAULT 'admin',
            created_at VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            category ENUM('Anime','Manga','News') NOT NULL,
            excerpt VARCHAR(500) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            youtube_url VARCHAR(500) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            INDEX idx_posts_category (category),
            INDEX idx_posts_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS characters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            anime_name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            bio MEDIUMTEXT NOT NULL,
            abilities VARCHAR(1000) NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            INDEX idx_comments_post (post_id),
            CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS likes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            UNIQUE KEY idx_likes_unique (post_id, user_id),
            INDEX idx_likes_post (post_id),
            CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` TEXT NOT NULL,
            updated_at VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($schema as $statement) $pdo->exec($statement);

    seed_database($pdo);
    $initialized = true;
}

function seed_database(PDO $pdo): void
{
    $timestamp = now_iso();
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $statement = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, NULL, ?, ?, ?)');
        $statement->execute(['admin', password_hash('admin123', PASSWORD_BCRYPT), 'admin', $timestamp]);
    }

    $settings = [
        'site_name' => 'AniScope by Arafat',
        'site_tagline' => 'Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News',
        'donation_label' => 'Support AniScope',
        'donation_number' => '',
        'home_background_url' => '/assets/images/hero-original.svg',
        'anime_cover_url' => '',
        'manga_cover_url' => '',
        'news_cover_url' => '',
        'characters_cover_url' => '/assets/images/characters-banner.svg',
        'login_cover_url' => '/assets/images/login-original.svg',
        'theme_ink' => '#070711',
        'theme_panel' => '#11111f',
        'theme_panel2' => '#17172a',
        'theme_text' => '#f5f3ff',
        'theme_muted' => '#a5a3b8',
        'theme_primary' => '#8b5cf6',
        'theme_secondary' => '#6d28d9',
        'theme_accent' => '#38bdf8',
        'copyright_text' => '© ' . gmdate('Y') . ' AniScope by Arafat. All rights reserved.',
    ];
    $statement = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`, updated_at) VALUES (?, ?, ?)');
    foreach ($settings as $key => $value) $statement->execute([$key, $value, $timestamp]);

    if ((int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn() === 0) {
        $posts = [
            ['Demon Slayer Season 1 Explained', 'Anime', "A spoiler-aware guide to a young swordsman's first trials and the bonds that shape his journey.", "We explore the season's structure, emotional themes, training arcs, and the choices that turn grief into purpose. This editorial uses no copyrighted imagery and is provided as sample content for AniScope.", '/assets/images/post-fire.svg'],
            ['Jujutsu Kaisen Beginner Guide', 'Anime', 'A friendly introduction to cursed energy, rival schools, and the rules behind supernatural combat.', 'New to modern supernatural battle stories? This guide breaks down the key ideas, power systems, and character dynamics in plain language, while keeping major surprises safely out of view.', '/assets/images/post-sorcery.svg'],
            ['Solo Leveling Story Explained', 'Manga', 'From overlooked hunter to mysterious powerhouse: the core progression fantasy explained.', "This sample feature maps the hero's progression, the appeal of game-like systems, and the tension between personal growth and a changing world.", '/assets/images/post-shadow.svg'],
            ['One Piece Anime Overview', 'News', 'A broad, beginner-friendly look at a vast sea adventure and its enduring sense of discovery.', 'This overview discusses long-form storytelling, found family, imaginative islands, and why viewers connect with sprawling adventure narratives.', '/assets/images/post-ocean.svg'],
            ['Five Manga Chapters Worth Watching', 'Manga', 'Fresh story beats, sharp cliffhangers, and what readers are discussing this week.', 'Our fictional weekly manga desk highlights pacing, visual storytelling, and memorable reveals across the latest sample updates.', '/assets/images/post-manga.svg'],
            ['Summer Anime Radar', 'News', 'Original recommendations and production trends to keep on your seasonal watchlist.', "AniScope's sample newsroom looks at fantasy adventures, character dramas, and creative original series without relying on copyrighted promotional art.", '/assets/images/post-news.svg'],
        ];
        $statement = $pdo->prepare('INSERT INTO posts (title, slug, category, excerpt, content, image_url, youtube_url, stream_anime_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($posts as [$title, $category, $excerpt, $content, $image]) {
            $statement->execute([$title, slugify($title), $category, $excerpt, $content, $image, '', 'published', $timestamp, $timestamp]);
        }
    }

    if ((int)$pdo->query('SELECT COUNT(*) FROM characters')->fetchColumn() === 0) {
        $characters = [
            ['Original Fire Swordsman', 'Ember Oath', 'A disciplined guardian who carries a blade forged from meteor glass and protects mountain villages from spectral storms.', 'Ember breathing, heat resistance, meteor-glass swordsmanship', '/assets/images/character-fire.svg'],
            ['Original Shadow Ninja', 'Moonveil Chronicles', 'A quiet scout from the floating city of Kagehara who turns darkness into paths between rooftops.', 'Shadow step, echo clones, silent movement', '/assets/images/character-shadow.svg'],
            ['Original Blue-Eyed Sorcerer', 'Azure Paradox', 'A playful academy mentor who studies spatial magic and hides deep responsibility behind an easy grin.', 'Spatial folding, azure barrier, arcane perception', '/assets/images/character-blue.svg'],
            ['Original Pirate Hero', 'Starwake Seas', 'An optimistic sky-pirate captain searching for the legendary compass that points toward forgotten dreams.', 'Wind sails, fearless leadership, constellation compass', '/assets/images/character-pirate.svg'],
        ];
        $statement = $pdo->prepare('INSERT INTO characters (name, anime_name, slug, bio, abilities, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($characters as [$name, $anime, $bio, $abilities, $image]) {
            $statement->execute([$name, $anime, slugify($name), $bio, $abilities, $image, $timestamp, $timestamp]);
        }
    }
}

function api_response(bool $ok, int $status, array $data = [], ?string $error = null): array
{
    return ['ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $error];
}

function require_role(array $roles): ?array
{
    if (!function_exists('logged_in') || !logged_in()) return null;
    $user = current_user();
    return in_array($user['role'] ?? '', $roles, true) ? $user : null;
}

function validate_text(string $value, int $min, int $max, string $label): string
{
    $value = trim($value);
    $length = strlen($value);
    if ($length < $min || $length > $max) throw new RuntimeException($label . ' is invalid.');
    return $value;
}

function api_request(string $method, string $path, ?array $payload = null, ?string $token = null): array
{
    try {
        $pdo = db();
        $parts = parse_url($path);
        $route = $parts['path'] ?? $path;
        parse_str($parts['query'] ?? '', $query);
        $payload ??= [];

        if ($method === 'POST' && $route === '/api/login') {
            $username = validate_text((string)($payload['username'] ?? ''), 1, 200, 'Username');
            $statement = $pdo->prepare('SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1');
            $statement->execute([$username, $username]);
            $user = $statement->fetch();
            if (!$user || !password_verify((string)($payload['password'] ?? ''), $user['password_hash'])) {
                return api_response(false, 401, ['detail' => 'Invalid username or password']);
            }
            unset($user['password_hash']);
            return api_response(true, 200, ['access_token' => bin2hex(random_bytes(32)), 'token_type' => 'session', 'expires_in' => 0, 'user' => $user]);
        }

        if ($method === 'POST' && $route === '/api/signup') {
            $username = validate_text((string)($payload['username'] ?? ''), 3, 40, 'Username');
            if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) return api_response(false, 422, ['detail' => 'Use only letters, numbers, and underscores in your username.']);
            $email = strtolower(validate_text((string)($payload['email'] ?? ''), 6, 200, 'Email'));
            $domain = str_contains($email, '@') ? substr(strrchr($email, '@'), 1) : '';
            if (!in_array($domain, ['gmail.com', 'icloud.com', 'hotmail.com', 'yahoo.com'], true)) {
                return api_response(false, 422, ['detail' => 'Use a gmail.com, icloud.com, hotmail.com, or yahoo.com email address']);
            }
            validate_text((string)($payload['password'] ?? ''), 6, 200, 'Password');
            $statement = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)');
            try {
                $statement->execute([$username, $email, password_hash((string)$payload['password'], PASSWORD_BCRYPT), 'user', now_iso()]);
            } catch (PDOException $exception) {
                return api_response(false, 409, ['detail' => 'That username or email is already registered']);
            }
            $user = $pdo->query('SELECT id, username, email, role, created_at FROM users WHERE id = ' . (int)$pdo->lastInsertId())->fetch();
            return api_response(true, 201, ['access_token' => bin2hex(random_bytes(32)), 'token_type' => 'session', 'expires_in' => 0, 'user' => $user ?: []]);
        }

        if ($method === 'GET' && $route === '/api/settings') {
            $rows = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll();
            return api_response(true, 200, array_column($rows, 'value', 'key'));
        }

        if ($method === 'PUT' && $route === '/api/settings') {
            if (!require_role(['admin'])) return api_response(false, 403, ['detail' => 'Admin access required']);
            $fields = ['site_name', 'site_tagline', 'donation_label', 'donation_number', 'home_background_url', 'anime_cover_url', 'manga_cover_url', 'news_cover_url', 'characters_cover_url', 'login_cover_url', 'theme_ink', 'theme_panel', 'theme_panel2', 'theme_text', 'theme_muted', 'theme_primary', 'theme_secondary', 'theme_accent', 'copyright_text'];
            $statement = $pdo->prepare('INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');
            foreach ($fields as $field) $statement->execute([$field, trim((string)($payload[$field] ?? '')), now_iso()]);
            return api_response(true, 200, $payload);
        }

        if ($method === 'GET' && $route === '/api/moderators') {
            if (!require_role(['admin'])) return api_response(false, 403, ['detail' => 'Admin access required']);
            return api_response(true, 200, $pdo->query("SELECT id, username, email, role, created_at FROM users WHERE role = 'moderator' ORDER BY id DESC")->fetchAll());
        }

        if ($method === 'POST' && $route === '/api/moderators') {
            if (!require_role(['admin'])) return api_response(false, 403, ['detail' => 'Admin access required']);
            $username = validate_text((string)($payload['username'] ?? ''), 3, 40, 'Username');
            if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) return api_response(false, 422, ['detail' => 'Use only letters, numbers, and underscores in the username.']);
            validate_text((string)($payload['password'] ?? ''), 6, 200, 'Password');
            try {
                $statement = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, NULL, ?, ?, ?)');
                $statement->execute([$username, password_hash((string)$payload['password'], PASSWORD_BCRYPT), 'moderator', now_iso()]);
            } catch (PDOException $exception) {
                return api_response(false, 409, ['detail' => 'That username is already in use']);
            }
            $statement = $pdo->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = ?');
            $statement->execute([(int)$pdo->lastInsertId()]);
            return api_response(true, 201, $statement->fetch() ?: []);
        }

        if ($method === 'DELETE' && preg_match('#^/api/moderators/(\d+)$#', $route, $match)) {
            if (!require_role(['admin'])) return api_response(false, 403, ['detail' => 'Admin access required']);
            $statement = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'moderator'");
            $statement->execute([(int)$match[1]]);
            return $statement->rowCount() ? api_response(true, 200, ['message' => 'Moderator removed']) : api_response(false, 404, ['detail' => 'Moderator not found']);
        }

        if ($method === 'GET' && $route === '/api/posts') {
            $sql = 'SELECT * FROM posts WHERE 1=1';
            $params = [];
            if (!empty($query['category'])) {
                $sql .= ' AND category = ?';
                $params[] = ucfirst(strtolower((string)$query['category']));
            }
            if (!empty($query['status'])) {
                $sql .= ' AND status = ?';
                $params[] = (string)$query['status'];
            }
            $sql .= ' ORDER BY created_at DESC, id DESC';
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            return api_response(true, 200, $statement->fetchAll());
        }

        if ($method === 'GET' && preg_match('#^/api/posts/(\d+)$#', $route, $match)) {
            $statement = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $statement->execute([(int)$match[1]]);
            $row = $statement->fetch();
            return $row ? api_response(true, 200, $row) : api_response(false, 404, ['detail' => 'Post not found']);
        }

        if (($method === 'POST' && $route === '/api/posts') || ($method === 'PUT' && preg_match('#^/api/posts/(\d+)$#', $route, $match))) {
            if (!require_role(['admin', 'moderator'])) return api_response(false, 403, ['detail' => 'Staff access required']);
            $postId = isset($match[1]) ? (int)$match[1] : null;
            $title = validate_text((string)($payload['title'] ?? ''), 2, 200, 'Title');
            $category = (string)($payload['category'] ?? 'Anime');
            if (!in_array($category, ['Anime', 'Manga', 'News'], true)) return api_response(false, 422, ['detail' => 'Invalid category']);
            $status = (string)($payload['status'] ?? 'published');
            if (!in_array($status, ['draft', 'published'], true)) return api_response(false, 422, ['detail' => 'Invalid status']);
            $values = [
                $title,
                unique_slug($pdo, 'posts', $title, $postId),
                $category,
                validate_text((string)($payload['excerpt'] ?? ''), 2, 500, 'Excerpt'),
                validate_text((string)($payload['content'] ?? ''), 2, 1000000, 'Content'),
                validate_text((string)($payload['image_url'] ?? ''), 1, 500, 'Image'),
                trim((string)($payload['youtube_url'] ?? '')),
                !empty($payload['stream_anime_id'])
                    ? (int)$payload['stream_anime_id']
                    : null,
                $status,
                now_iso(),
            ];
            if ($postId) {
                $exists = $pdo->prepare('SELECT id FROM posts WHERE id = ?');
                $exists->execute([$postId]);
                if (!$exists->fetch()) return api_response(false, 404, ['detail' => 'Post not found']);
                $statement = $pdo->prepare('UPDATE posts SET title=?, slug=?, category=?, excerpt=?, content=?, image_url=?, youtube_url=?, stream_anime_id=?, status=?, updated_at=? WHERE id=?');
                $statement->execute([...$values, $postId]);
            } else {
                $statement = $pdo->prepare('INSERT INTO posts (title, slug, category, excerpt, content, image_url, youtube_url, stream_anime_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([...$values, $values[9]]);
                $postId = (int)$pdo->lastInsertId();
            }
            $statement = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $statement->execute([$postId]);
            return api_response(true, $method === 'POST' ? 201 : 200, $statement->fetch() ?: []);
        }

        if ($method === 'DELETE' && preg_match('#^/api/posts/(\d+)$#', $route, $match)) {
            if (!require_role(['admin', 'moderator'])) return api_response(false, 403, ['detail' => 'Staff access required']);
            $statement = $pdo->prepare('DELETE FROM posts WHERE id = ?');
            $statement->execute([(int)$match[1]]);
            return $statement->rowCount() ? api_response(true, 200, ['message' => 'Post deleted']) : api_response(false, 404, ['detail' => 'Post not found']);
        }

        if ($method === 'GET' && $route === '/api/characters') {
            return api_response(true, 200, $pdo->query('SELECT * FROM characters ORDER BY created_at DESC, id DESC')->fetchAll());
        }

        if ($method === 'GET' && preg_match('#^/api/characters/(\d+)$#', $route, $match)) {
            $statement = $pdo->prepare('SELECT * FROM characters WHERE id = ?');
            $statement->execute([(int)$match[1]]);
            $row = $statement->fetch();
            return $row ? api_response(true, 200, $row) : api_response(false, 404, ['detail' => 'Character not found']);
        }

        if (($method === 'POST' && $route === '/api/characters') || ($method === 'PUT' && preg_match('#^/api/characters/(\d+)$#', $route, $match))) {
            if (!require_role(['admin', 'moderator'])) return api_response(false, 403, ['detail' => 'Staff access required']);
            $characterId = isset($match[1]) ? (int)$match[1] : null;
            $name = validate_text((string)($payload['name'] ?? ''), 2, 160, 'Name');
            $values = [
                $name,
                validate_text((string)($payload['anime_name'] ?? ''), 2, 160, 'Original series'),
                unique_slug($pdo, 'characters', $name, $characterId),
                validate_text((string)($payload['bio'] ?? ''), 2, 1000000, 'Biography'),
                validate_text((string)($payload['abilities'] ?? ''), 2, 1000, 'Abilities'),
                validate_text((string)($payload['image_url'] ?? ''), 1, 500, 'Image'),
                now_iso(),
            ];
            if ($characterId) {
                $exists = $pdo->prepare('SELECT id FROM characters WHERE id = ?');
                $exists->execute([$characterId]);
                if (!$exists->fetch()) return api_response(false, 404, ['detail' => 'Character not found']);
                $statement = $pdo->prepare('UPDATE characters SET name=?, anime_name=?, slug=?, bio=?, abilities=?, image_url=?, updated_at=? WHERE id=?');
                $statement->execute([...$values, $characterId]);
            } else {
                $statement = $pdo->prepare('INSERT INTO characters (name, anime_name, slug, bio, abilities, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $statement->execute([...$values, $values[6]]);
                $characterId = (int)$pdo->lastInsertId();
            }
            $statement = $pdo->prepare('SELECT * FROM characters WHERE id = ?');
            $statement->execute([$characterId]);
            return api_response(true, $method === 'POST' ? 201 : 200, $statement->fetch() ?: []);
        }

        if ($method === 'DELETE' && preg_match('#^/api/characters/(\d+)$#', $route, $match)) {
            if (!require_role(['admin', 'moderator'])) return api_response(false, 403, ['detail' => 'Staff access required']);
            $statement = $pdo->prepare('DELETE FROM characters WHERE id = ?');
            $statement->execute([(int)$match[1]]);
            return $statement->rowCount() ? api_response(true, 200, ['message' => 'Character deleted']) : api_response(false, 404, ['detail' => 'Character not found']);
        }

        if ($method === 'GET' && preg_match('#^/api/posts/(\d+)/comments$#', $route, $match)) {
            $statement = $pdo->prepare('SELECT comments.id, comments.user_id, comments.content, comments.created_at, users.username FROM comments JOIN users ON users.id = comments.user_id WHERE comments.post_id = ? ORDER BY comments.id DESC');
            $statement->execute([(int)$match[1]]);
            return api_response(true, 200, $statement->fetchAll());
        }

        if ($method === 'POST' && preg_match('#^/api/posts/(\d+)/comments$#', $route, $match)) {
            $user = require_role(['admin', 'moderator', 'user']);
            if (!$user) return api_response(false, 401, ['detail' => 'Authentication required']);
            $postId = (int)$match[1];
            $exists = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
            $exists->execute([$postId]);
            if (!$exists->fetch()) return api_response(false, 404, ['detail' => 'Post not found']);
            $statement = $pdo->prepare('INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, ?)');
            $statement->execute([$postId, (int)$user['id'], validate_text((string)($payload['content'] ?? ''), 1, 2000, 'Comment'), now_iso()]);
            $statement = $pdo->prepare('SELECT comments.id, comments.user_id, comments.content, comments.created_at, users.username FROM comments JOIN users ON users.id = comments.user_id WHERE comments.id = ?');
            $statement->execute([(int)$pdo->lastInsertId()]);
            return api_response(true, 201, $statement->fetch() ?: []);
        }

        if ($method === 'DELETE' && preg_match('#^/api/comments/(\d+)$#', $route, $match)) {
            $user = require_role(['admin', 'moderator', 'user']);
            if (!$user) return api_response(false, 401, ['detail' => 'Authentication required']);
            if (in_array($user['role'], ['admin', 'moderator'], true)) {
                $statement = $pdo->prepare('DELETE FROM comments WHERE id = ?');
                $statement->execute([(int)$match[1]]);
            } else {
                $statement = $pdo->prepare('DELETE FROM comments WHERE id = ? AND user_id = ?');
                $statement->execute([(int)$match[1], (int)$user['id']]);
            }
            return $statement->rowCount() ? api_response(true, 200, ['message' => 'Comment deleted']) : api_response(false, 404, ['detail' => 'Comment not found or not owned by you']);
        }

        if ($method === 'GET' && preg_match('#^/api/posts/(\d+)/likes$#', $route, $match)) {
            $postId = (int)$match[1];
            $statement = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
            $statement->execute([$postId]);
            $count = (int)$statement->fetchColumn();
            $liked = false;
            if (!empty($query['user_id'])) {
                $statement = $pdo->prepare('SELECT id FROM likes WHERE post_id = ? AND user_id = ?');
                $statement->execute([$postId, (int)$query['user_id']]);
                $liked = (bool)$statement->fetch();
            }
            return api_response(true, 200, ['count' => $count, 'liked' => $liked]);
        }

        if ($method === 'POST' && preg_match('#^/api/posts/(\d+)/like$#', $route, $match)) {
            $user = require_role(['admin', 'moderator', 'user']);
            if (!$user) return api_response(false, 401, ['detail' => 'Authentication required']);
            $postId = (int)$match[1];
            $userId = (int)$user['id'];
            $statement = $pdo->prepare('SELECT id FROM likes WHERE post_id = ? AND user_id = ?');
            $statement->execute([$postId, $userId]);
            $existing = $statement->fetch();
            if ($existing) {
                $statement = $pdo->prepare('DELETE FROM likes WHERE id = ?');
                $statement->execute([(int)$existing['id']]);
                $liked = false;
            } else {
                $exists = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
                $exists->execute([$postId]);
                if (!$exists->fetch()) return api_response(false, 404, ['detail' => 'Post not found']);
                $statement = $pdo->prepare('INSERT INTO likes (post_id, user_id, created_at) VALUES (?, ?, ?)');
                $statement->execute([$postId, $userId, now_iso()]);
                $liked = true;
            }
            $statement = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
            $statement->execute([$postId]);
            return api_response(true, 200, ['count' => (int)$statement->fetchColumn(), 'liked' => $liked]);
        }

        return api_response(false, 404, ['detail' => 'Route not found']);
    } catch (Throwable $exception) {
        return api_response(false, 500, ['detail' => $exception->getMessage()], $exception->getMessage());
    }
}

function api_data(string $path): array
{
    $response = api_request('GET', $path);
    return $response['ok'] ? $response['data'] : [];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function image_url(string $url): string
{
    if (preg_match('#^https?://#', $url)) return $url;
    return '/' . ltrim($url, '/');
}

function format_date(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp ? date('M j, Y', $timestamp) : '';
}

function site_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = api_data('/api/settings') ?: [
            'site_name' => 'AniScope by Arafat',
            'site_tagline' => 'Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News',
            'donation_label' => 'Support AniScope',
            'donation_number' => '',
            'home_background_url' => '/assets/images/hero-original.svg',
            'anime_cover_url' => '',
            'manga_cover_url' => '',
            'news_cover_url' => '',
            'characters_cover_url' => '/assets/images/characters-banner.svg',
            'login_cover_url' => '/assets/images/login-original.svg',
            'theme_ink' => '#070711',
            'theme_panel' => '#11111f',
            'theme_panel2' => '#17172a',
            'theme_text' => '#f5f3ff',
            'theme_muted' => '#a5a3b8',
            'theme_primary' => '#8b5cf6',
            'theme_secondary' => '#6d28d9',
            'theme_accent' => '#38bdf8',
            'copyright_text' => '© ' . date('Y') . ' AniScope by Arafat. All rights reserved.',
        ];
    }
    return $settings;
}

function setting_value(array $settings, string $key, string $default = ''): string
{
    $value = trim((string)($settings[$key] ?? ''));
    return $value !== '' ? $value : $default;
}

function theme_color(array $settings, string $key, string $default): string
{
    $value = setting_value($settings, $key, $default);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
}

function hex_to_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function rgba_from_hex(string $hex, float $alpha): string
{
    [$red, $green, $blue] = hex_to_rgb($hex);
    return sprintf('rgba(%d,%d,%d,%.2f)', $red, $green, $blue, $alpha);
}

function theme_css(array $settings): string
{
    $ink = theme_color($settings, 'theme_ink', '#070711');
    $panel = theme_color($settings, 'theme_panel', '#11111f');
    $panel2 = theme_color($settings, 'theme_panel2', '#17172a');
    $text = theme_color($settings, 'theme_text', '#f5f3ff');
    $muted = theme_color($settings, 'theme_muted', '#a5a3b8');
    $primary = theme_color($settings, 'theme_primary', '#8b5cf6');
    $secondary = theme_color($settings, 'theme_secondary', '#6d28d9');
    $accent = theme_color($settings, 'theme_accent', '#38bdf8');
    return ':root{' .
        '--ink:' . $ink . ';' .
        '--panel:' . $panel . ';' .
        '--panel2:' . $panel2 . ';' .
        '--text:' . $text . ';' .
        '--muted:' . $muted . ';' .
        '--purple:' . $primary . ';' .
        '--violet:' . $secondary . ';' .
        '--blue:' . $accent . ';' .
        '--line:' . rgba_from_hex($text, 0.10) . ';' .
        '--glow:0 0 40px ' . rgba_from_hex($primary, 0.25) . ';' .
    '}';
}

function youtube_embed_url(?string $url): string
{
    if (!$url) return '';
    $id = '';
    $parts = parse_url(trim($url));
    $host = strtolower($parts['host'] ?? '');
    if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) $id = trim($parts['path'] ?? '', '/');
    if (str_contains($host, 'youtube.com')) {
        parse_str($parts['query'] ?? '', $query);
        $id = $query['v'] ?? '';
        if (!$id && preg_match('#/(?:embed|shorts)/([A-Za-z0-9_-]{6,})#', $parts['path'] ?? '', $match)) $id = $match[1];
    }
    return preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . $id : '';
}
