# AniScope by Arafat

A complete, GitHub-ready anime and manga editorial website built with a PHP frontend, FastAPI backend, and SQLite. It includes a responsive dark-neon public site, JWT-secured REST API, session-based admin panel, seeded content, and original SVG artwork.

No WordPress, Laravel, or Django is used. The included character and editorial artwork is original placeholder art—not copyrighted anime imagery.

## Features

- Home, Anime, Characters, Manga, News, article, and character profile pages
- Responsive 4/2/1 card grids for desktop, tablet, and mobile
- One role-aware login for members, moderators, and administrators
- Restricted-domain member signup (Gmail, iCloud, Hotmail, or Yahoo; no OTP)
- Member-only article comments and likes, plus link sharing
- Administrator-managed moderator accounts and site/donation information
- Create, edit, and delete posts and characters
- Draft/published post status and Anime/Manga/News categories
- Optional privacy-enhanced YouTube embeds on posts
- FastAPI REST API with JWT-protected write operations
- SQLite schema and automatic seed data on first backend start
- Hashed administrator password using bcrypt
- Environment-based API URL and JWT secret
- Original SVG/gradient placeholder artwork

## Project structure

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

## Requirements

- Python 3.10+
- PHP 8.0+ with `allow_url_fopen` enabled
- A terminal on macOS, Linux, or WSL (Windows commands differ slightly)

## Local setup

Clone or open the project, then create the environment file:

```bash
cp .env.example .env
```

Change `JWT_SECRET` in `.env` to a long random value before any shared deployment.

### Run the FastAPI backend

From the project root:

```bash
cd backend
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8000
```

The backend can also start directly with `python backend/main.py`. It automatically binds to `SERVER_PORT` or `PORT` when supplied by a hosting panel such as HidenCloud.

On first start, FastAPI creates `backend/database.db`, creates all tables, and inserts the sample administrator, posts, and characters.

- API root: <http://localhost:8000>
- Interactive API docs: <http://localhost:8000/docs>
- Health endpoint: <http://localhost:8000/api/health>

### Run the PHP frontend

Open a second terminal at the project root:

```bash
php -S localhost:8080
```

Then open:

- Website: <http://localhost:8080>
- Admin: <http://localhost:8080/admin/login.php>
- Login: <http://localhost:8080/login.php>
- Signup: <http://localhost:8080/signup.php>

## Administrator login

```text
Username: admin
Password: admin123
```

The password is stored only as a bcrypt hash. Change this starter credential before deploying publicly. The shared PHP login detects the API role: administrators and moderators enter the dashboard, while members return to the public site. JWTs are stored in the server-side PHP session and write actions use CSRF tokens.

## API endpoints

| Method | Endpoint | Access |
|---|---|---|
| POST | `/api/login` | Public |
| POST | `/api/signup` | Public |
| GET | `/api/settings` | Public |
| PUT | `/api/settings` | Administrator |
| GET/POST | `/api/moderators` | Administrator |
| DELETE | `/api/moderators/{id}` | Administrator |
| GET | `/api/posts` | Public |
| GET | `/api/posts/{id}` | Public |
| POST | `/api/posts` | Admin JWT |
| PUT | `/api/posts/{id}` | Admin JWT |
| DELETE | `/api/posts/{id}` | Admin JWT |
| GET | `/api/characters` | Public |
| GET | `/api/characters/{id}` | Public |
| POST | `/api/characters` | Admin JWT |
| PUT | `/api/characters/{id}` | Admin JWT |
| DELETE | `/api/characters/{id}` | Admin JWT |
| GET/POST | `/api/posts/{id}/comments` | Read public / write signed-in |
| DELETE | `/api/comments/{id}` | Owner or staff |
| GET | `/api/posts/{id}/likes` | Public |
| POST | `/api/posts/{id}/like` | Signed-in |

Filter posts with `?category=Anime` and/or `?status=published`.

## Change the site name or logo

- Site name: search for `AniScope by Arafat` in the PHP files and update the visible text and page titles.
- Header/footer brand markup: `includes/header.php` and `includes/footer.php`.
- Admin brand: `admin/layout.php` and `admin/login.php`.
- Logo: replace `assets/images/logo-mark.svg` while keeping the same filename, or update its paths in the files above.
- Theme colors: edit the CSS custom properties at the top of `assets/css/style.css`.
- Homepage background: sign in as administrator, open **Site settings**, and change the Homepage background image URL. Local paths and full image URLs are supported.

## Deployment later

For a small production deployment:

1. Serve PHP through Nginx or Apache with PHP-FPM.
2. Run FastAPI through Gunicorn/Uvicorn workers behind the same reverse proxy.
3. Set a strong `JWT_SECRET`, a production `API_URL`, and exact HTTPS `CORS_ORIGINS` in the server environment.
4. Keep `.env` and `backend/database.db` outside public web access and back up the database.
5. Enable HTTPS, secure cookies, request-rate limiting, server logging, and regular dependency updates.
6. For larger traffic, migrate SQLite to PostgreSQL and move uploaded images to object storage/CDN.

One convenient proxy layout is `/` → PHP and `/api/` → FastAPI. If both share one HTTPS hostname, set `API_URL` to that backend origin and update CORS accordingly.

## GitHub upload commands

Create an empty GitHub repository named `aniscope-by-arafat`, then run from this folder:

```bash
git init
git add .
git commit -m "Build AniScope by Arafat website"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/aniscope-by-arafat.git
git push -u origin main
```

The SQLite development database and `.env` are intentionally ignored. Anyone cloning the repository can recreate the seeded database by starting FastAPI.

## License and artwork note

This codebase is ready for you to license as desired. All bundled SVG artwork was created specifically as generic, original anime-inspired placeholder art and does not depict or reproduce named copyrighted anime characters.
