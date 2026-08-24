CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(200) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','moderator','user') NOT NULL DEFAULT 'admin',
    created_at VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS characters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    anime_name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    bio MEDIUMTEXT NOT NULL,
    abilities VARCHAR(1000) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_comments_post (post_id),
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    UNIQUE KEY idx_likes_unique (post_id, user_id),
    INDEX idx_likes_post (post_id),
    CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL,
    updated_at VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (username, email, password_hash, role, created_at) VALUES
('admin', NULL, '$2b$12$I1sfEyuj6bo7M3Km/NOdSO4gAY8GvPWgWMgv1kJ5eE.RNQ9FcLlYi', 'admin', '2026-08-10T00:00:00+00:00');

INSERT IGNORE INTO settings (`key`, `value`, updated_at) VALUES
('site_name', 'AniScope by Arafat', '2026-08-10T00:00:00+00:00'),
('site_tagline', 'Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News', '2026-08-10T00:00:00+00:00'),
('donation_label', 'Support AniScope', '2026-08-10T00:00:00+00:00'),
('donation_number', '', '2026-08-10T00:00:00+00:00'),
('home_background_url', '/assets/images/hero-original.svg', '2026-08-10T00:00:00+00:00'),
('anime_cover_url', '', '2026-08-10T00:00:00+00:00'),
('manga_cover_url', '', '2026-08-10T00:00:00+00:00'),
('news_cover_url', '', '2026-08-10T00:00:00+00:00'),
('characters_cover_url', '/assets/images/characters-banner.svg', '2026-08-10T00:00:00+00:00'),
('login_cover_url', '/assets/images/login-original.svg', '2026-08-10T00:00:00+00:00'),
('theme_ink', '#070711', '2026-08-10T00:00:00+00:00'),
('theme_panel', '#11111f', '2026-08-10T00:00:00+00:00'),
('theme_panel2', '#17172a', '2026-08-10T00:00:00+00:00'),
('theme_text', '#f5f3ff', '2026-08-10T00:00:00+00:00'),
('theme_muted', '#a5a3b8', '2026-08-10T00:00:00+00:00'),
('theme_primary', '#8b5cf6', '2026-08-10T00:00:00+00:00'),
('theme_secondary', '#6d28d9', '2026-08-10T00:00:00+00:00'),
('theme_accent', '#38bdf8', '2026-08-10T00:00:00+00:00'),
('copyright_text', '© 2026 AniScope by Arafat. All rights reserved.', '2026-08-10T00:00:00+00:00');

INSERT IGNORE INTO posts (title, slug, category, excerpt, content, image_url, youtube_url, status, created_at, updated_at) VALUES
('Demon Slayer Season 1 Explained', 'demon-slayer-season-1-explained', 'Anime', 'A spoiler-aware guide to a young swordsman''s first trials and the bonds that shape his journey.', 'We explore the season''s structure, emotional themes, training arcs, and the choices that turn grief into purpose. This editorial uses no copyrighted imagery and is provided as sample content for AniScope.', '/assets/images/post-fire.svg', '', 'published', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Jujutsu Kaisen Beginner Guide', 'jujutsu-kaisen-beginner-guide', 'Anime', 'A friendly introduction to cursed energy, rival schools, and the rules behind supernatural combat.', 'New to modern supernatural battle stories? This guide breaks down the key ideas, power systems, and character dynamics in plain language, while keeping major surprises safely out of view.', '/assets/images/post-sorcery.svg', '', 'published', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Solo Leveling Story Explained', 'solo-leveling-story-explained', 'Manga', 'From overlooked hunter to mysterious powerhouse: the core progression fantasy explained.', 'This sample feature maps the hero''s progression, the appeal of game-like systems, and the tension between personal growth and a changing world.', '/assets/images/post-shadow.svg', '', 'published', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('One Piece Anime Overview', 'one-piece-anime-overview', 'News', 'A broad, beginner-friendly look at a vast sea adventure and its enduring sense of discovery.', 'This overview discusses long-form storytelling, found family, imaginative islands, and why viewers connect with sprawling adventure narratives.', '/assets/images/post-ocean.svg', '', 'published', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Five Manga Chapters Worth Watching', 'five-manga-chapters-worth-watching', 'Manga', 'Fresh story beats, sharp cliffhangers, and what readers are discussing this week.', 'Our fictional weekly manga desk highlights pacing, visual storytelling, and memorable reveals across the latest sample updates.', '/assets/images/post-manga.svg', '', 'published', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Summer Anime Radar', 'summer-anime-radar', 'News', 'Original recommendations and production trends to keep on your seasonal watchlist.', 'AniScope''s sample newsroom looks at fantasy adventures, character dramas, and creative original series without relying on copyrighted promotional art.', '/assets/images/post-news.svg', '', 'published', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00');

INSERT IGNORE INTO characters (name, anime_name, slug, bio, abilities, image_url, created_at, updated_at) VALUES
('Original Fire Swordsman', 'Ember Oath', 'original-fire-swordsman', 'A disciplined guardian who carries a blade forged from meteor glass and protects mountain villages from spectral storms.', 'Ember breathing, heat resistance, meteor-glass swordsmanship', '/assets/images/character-fire.svg', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Original Shadow Ninja', 'Moonveil Chronicles', 'original-shadow-ninja', 'A quiet scout from the floating city of Kagehara who turns darkness into paths between rooftops.', 'Shadow step, echo clones, silent movement', '/assets/images/character-shadow.svg', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Original Blue-Eyed Sorcerer', 'Azure Paradox', 'original-blue-eyed-sorcerer', 'A playful academy mentor who studies spatial magic and hides deep responsibility behind an easy grin.', 'Spatial folding, azure barrier, arcane perception', '/assets/images/character-blue.svg', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00'),
('Original Pirate Hero', 'Starwake Seas', 'original-pirate-hero', 'An optimistic sky-pirate captain searching for the legendary compass that points toward forgotten dreams.', 'Wind sails, fearless leadership, constellation compass', '/assets/images/character-pirate.svg', '2026-08-10T00:00:00+00:00', '2026-08-10T00:00:00+00:00');
