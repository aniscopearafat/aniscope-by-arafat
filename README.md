# AniScope by Arafat

AniScope by Arafat is a PHP and MySQL anime/manga editorial website designed to run directly on shared hosting such as InfinityFree.

The old FastAPI/Python backend has been removed. All public pages, login/signup, admin actions, posts, characters, comments, likes, settings, and moderator management now run through PHP on the same domain.

## Features

- Public pages for Home, Anime, Manga, News, Characters, Article, and Character Profile
- PHP session login for members, moderators, and administrators
- Member signup restricted to Gmail, iCloud, Hotmail, and Yahoo email domains
- Member comments and likes
- Admin dashboard
- Create, edit, delete, draft, and publish posts
- Create, edit, and delete character profiles
- Administrator-only moderator management
- Administrator-only site settings and donation details
- Image uploads plus direct image URL / ImgBB image snippet support
- MySQL database with automatic table creation and seed content

## Requirements

- PHP 8.0+
- MySQL or MariaDB
- PDO MySQL extension

InfinityFree provides PHP and MySQL on free hosting, so this version is intended for that environment.

## Project Structure

```text
aniscope-by-arafat-main/
├── admin/
├── assets/
├── config/
│   └── database.php
├── includes/
│   ├── api.php
│   ├── cards.php
│   ├── footer.php
│   ├── header.php
│   ├── listing.php
│   ├── media.php
│   └── session.php
├── database.sql
├── index.php
├── anime.php
├── characters.php
├── manga.php
├── news.php
├── article.php
├── character.php
├── login.php
└── signup.php
```

## InfinityFree Setup

1. Create a MySQL database in InfinityFree/VistaPanel.
2. Open phpMyAdmin and import `database.sql`.
3. Copy `.env.example` to `.env`.
4. Fill in the InfinityFree database credentials:

```env
DB_HOST=sqlXXX.infinityfree.com
DB_PORT=3306
DB_NAME=if0_XXXXXXXX_aniscope
DB_USER=if0_XXXXXXXX
DB_PASS=your_database_password
DB_CHARSET=utf8mb4
```

5. Upload the project files into your domain's `htdocs` folder.
6. Visit your domain.

The site also creates missing tables and seed content automatically on first load if the database user has table creation permissions.

## Default Admin Login

```text
Username: admin
Password: admin123
```

Change this password after deployment by creating a new administrator password directly in the database or by adding a password-change screen later.

## Local Development

If you have PHP and MySQL installed locally:

```bash
cp .env.example .env
php -S localhost:8080
```

Then open:

```text
http://localhost:8080
```

## Uploads

The admin editors accept:

- JPG
- PNG
- WebP
- GIF

Maximum upload size is 5 MB. Uploaded files are stored in `assets/uploads/`.

SVG uploads are blocked. ImgBB HTML snippets are reduced to the contained image URL before storage.
