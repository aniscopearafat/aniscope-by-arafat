# AniScope by Arafat

**AniScope by Arafat** is a complete anime and manga editorial website built with a **PHP frontend**, **FastAPI backend**, and **SQLite database**.

It includes a responsive dark-neon public website, JWT-secured REST API, session-based admin panel, seeded demo content, member features, moderator/admin roles, and original SVG placeholder artwork.

No **WordPress**, **Laravel**, or **Django** is used.
All included character and editorial artwork is original placeholder art and does not use copyrighted anime imagery.

---

## ✨ Features

### Public Website

* Home, Anime, Characters, Manga, News, Article, and Character Profile pages
* Responsive card grids for desktop, tablet, and mobile
* Dark-neon anime-inspired UI design
* Article categories: Anime, Manga, and News
* Character profile pages with original placeholder artwork
* Optional privacy-enhanced YouTube embeds on posts
* Link sharing support for articles

### User System

* One role-aware login system for members, moderators, and administrators
* Restricted-domain member signup
  Supported email domains:

  * Gmail
  * iCloud
  * Hotmail
  * Yahoo
* Member-only article comments
* Member-only article likes
* Secure session-based PHP login flow

### Admin Panel

* Administrator dashboard
* Create, edit, and delete posts
* Create, edit, and delete characters
* Draft and published post status
* Manage Anime, Manga, and News content
* Administrator-managed moderator accounts
* Manage site and donation information

### Backend API

* FastAPI REST API
* JWT-protected write operations
* SQLite database with automatic setup
* Seed data created on first backend start
* bcrypt-hashed administrator password
* Environment-based API URL and JWT secret

---

## 📁 Project Structure

```text
aniscopewebsite/
├── admin/
│   ├── auth.php
│   ├── characters.php
│   ├── dashboard.php
│   ├── layout.php
│   ├── login.php
│   ├── logout.php
│   └── posts.php
├── assets/
│   ├── css/style.css
│   ├── images/*.svg
│   └── js/main.js
├── backend/
│   ├── main.py
│   └── requirements.txt
├── includes/
│   ├── api.php
│   ├── cards.php
│   ├── footer.php
│   ├── header.php
│   └── listing.php
├── index.php
├── anime.php
├── characters.php
├── manga.php
├── news.php
├── article.php
├── character.php
├── .env.example
└── README.md
```

---

## 🧰 Requirements

Before running the project locally, make sure you have:

* Python 3.10+
* PHP 8.0+
* PHP `allow_url_fopen` enabled
* A terminal on macOS, Linux, or WSL

> Windows commands may be slightly different depending on your setup.

---

## ⚙️ Local Setup

From the project root, create your environment file:

```bash
cp .env.example .env
```

Open the `.env` file and change `JWT_SECRET` to a long random value before using the project publicly.

---

## 🚀 Run the FastAPI Backend

From the project root:

```bash
cd backend
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8000
```

On the first start, FastAPI will automatically:

* Create `backend/database.db`
* Create all required tables
* Insert sample administrator data
* Insert sample posts
* Insert sample characters

Backend URLs:

```text
API Root:        http://localhost:8000
API Docs:        http://localhost:8000/docs
Health Check:    http://localhost:8000/api/health
```

---

## 🌐 Run the PHP Frontend

Open a second terminal at the project root:

```bash
php -S localhost:8080
```

Then open:

```text
Website:    http://localhost:8080
Admin:      http://localhost:8080/admin/login.php
Login:      http://localhost:8080/login.php
Signup:     http://localhost:8080/signup.php
```

---

## 🔐 Administrator Login

Default administrator account:

```text
Username: admin
Password: admin123
```

The password is stored as a bcrypt hash.

> Change this starter credential before deploying the website publicly.

The shared PHP login automatically detects the user role:

* Administrators and moderators are redirected to the dashboard
* Members are returned to the public website
* JWT tokens are stored in the server-side PHP session
* Write actions use CSRF tokens for extra protection

---

## 🔌 API Endpoints

| Method   | Endpoint                   | Access                        |
| -------- | -------------------------- | ----------------------------- |
| POST     | `/api/login`               | Public                        |
| POST     | `/api/signup`              | Public                        |
| GET      | `/api/settings`            | Public                        |
| PUT      | `/api/settings`            | Administrator                 |
| GET/POST | `/api/moderators`          | Administrator                 |
| DELETE   | `/api/moderators/{id}`     | Administrator                 |
| GET      | `/api/posts`               | Public                        |
| GET      | `/api/posts/{id}`          | Public                        |
| POST     | `/api/posts`               | Admin JWT                     |
| PUT      | `/api/posts/{id}`          | Admin JWT                     |
| DELETE   | `/api/posts/{id}`          | Admin JWT                     |
| GET      | `/api/characters`          | Public                        |
| GET      | `/api/characters/{id}`     | Public                        |
| POST     | `/api/characters`          | Admin JWT                     |
| PUT      | `/api/characters/{id}`     | Admin JWT                     |
| DELETE   | `/api/characters/{id}`     | Admin JWT                     |
| GET/POST | `/api/posts/{id}/comments` | Read public / write signed-in |
| DELETE   | `/api/comments/{id}`       | Owner or staff                |
| GET      | `/api/posts/{id}/likes`    | Public                        |
| POST     | `/api/posts/{id}/like`     | Signed-in                     |

Filter posts using query parameters:

```text
/api/posts?category=Anime
/api/posts?category=Manga
/api/posts?category=News
/api/posts?status=published
/api/posts?category=Anime&status=published
```

---

## 🎨 Customization Guide

### Change Site Name

Search for:

```text
AniScope by Arafat
```

Then update the visible text and page titles inside the PHP files.

### Change Header or Footer Brand

Edit:

```text
includes/header.php
includes/footer.php
```

### Change Admin Brand

Edit:

```text
admin/layout.php
admin/login.php
```

### Change Logo

Replace this file:

```text
assets/images/logo-mark.svg
```

You can keep the same filename, or update the logo path inside the related PHP files.

### Change Theme Colors

Edit the CSS custom properties at the top of:

```text
assets/css/style.css
```

### Change Homepage Background

Sign in as administrator, open **Site Settings**, and update the homepage background image URL.

Supported background sources:

* Local image paths
* Full image URLs

---

## 🛡️ Security Notes

Before deploying publicly:

* Change the default administrator password
* Set a strong `JWT_SECRET`
* Use HTTPS
* Use secure cookies
* Keep `.env` private
* Keep `backend/database.db` outside public web access
* Enable request-rate limiting
* Enable server logging
* Regularly update dependencies
* Back up the database

---

## 🚀 Deployment Notes

For a small production deployment:

1. Serve PHP using Nginx or Apache with PHP-FPM.
2. Run FastAPI through Gunicorn/Uvicorn workers.
3. Place both behind the same reverse proxy.
4. Set a production `API_URL`.
5. Set exact HTTPS `CORS_ORIGINS`.
6. Keep `.env` and `backend/database.db` outside public access.
7. Enable HTTPS, secure cookies, logging, and backups.

A convenient production proxy layout:

```text
/       → PHP frontend
/api/   → FastAPI backend
```

If both share one HTTPS hostname, set `API_URL` to that backend origin and update CORS settings accordingly.

For larger traffic:

* Migrate SQLite to PostgreSQL
* Move uploaded images to object storage
* Use a CDN for static files
* Add caching for public pages
* Add background workers for heavy tasks

---

## 📄 License and Artwork Note

This codebase is ready for you to license as desired.

All bundled SVG artwork was created as generic, original anime-inspired placeholder art. It does not depict or reproduce named copyrighted anime characters.

---

## 💜 Project Credit

Created for **AniScope by Arafat** — an anime and manga editorial platform focused on reviews, characters, stories, and community discussion.
