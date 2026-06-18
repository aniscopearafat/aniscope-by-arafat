import os
import re
import secrets
import sqlite3
from contextlib import asynccontextmanager
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Literal

import bcrypt
import jwt
from dotenv import load_dotenv
from fastapi import Depends, FastAPI, HTTPException, Query, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from pydantic import BaseModel, Field

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR.parent / ".env")

database_setting = Path(os.getenv("DATABASE_PATH", "backend/database.db"))
DB_PATH = database_setting if database_setting.is_absolute() else BASE_DIR.parent / database_setting
JWT_SECRET = os.getenv("JWT_SECRET") or secrets.token_urlsafe(48)
JWT_ALGORITHM = "HS256"
JWT_EXPIRE_MINUTES = int(os.getenv("JWT_EXPIRE_MINUTES", "120"))
security = HTTPBearer(auto_error=False)


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def slugify(value: str) -> str:
    value = re.sub(r"[^a-zA-Z0-9\s-]", "", value).strip().lower()
    return re.sub(r"[-\s]+", "-", value) or "untitled"


def db_connection() -> sqlite3.Connection:
    connection = sqlite3.connect(DB_PATH)
    connection.row_factory = sqlite3.Row
    connection.execute("PRAGMA foreign_keys = ON")
    return connection


def ensure_column(connection: sqlite3.Connection, table: str, column: str, definition: str) -> None:
    columns = {row["name"] for row in connection.execute(f"PRAGMA table_info({table})")}
    if column not in columns:
        connection.execute(f"ALTER TABLE {table} ADD COLUMN {column} {definition}")


def unique_slug(connection: sqlite3.Connection, table: str, title: str, item_id: int | None = None) -> str:
    base = slugify(title)
    candidate = base
    suffix = 2
    while True:
        query = f"SELECT id FROM {table} WHERE slug = ?"
        params: list[object] = [candidate]
        if item_id is not None:
            query += " AND id != ?"
            params.append(item_id)
        if connection.execute(query, params).fetchone() is None:
            return candidate
        candidate = f"{base}-{suffix}"
        suffix += 1


def initialize_database() -> None:
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    with db_connection() as db:
        db.executescript(
            """
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                category TEXT NOT NULL CHECK(category IN ('Anime', 'Manga', 'News')),
                excerpt TEXT NOT NULL,
                content TEXT NOT NULL,
                image_url TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'published' CHECK(status IN ('draft', 'published')),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS characters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                anime_name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                bio TEXT NOT NULL,
                abilities TEXT NOT NULL,
                image_url TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS likes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE(post_id, user_id),
                FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category);
            CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);
            CREATE INDEX IF NOT EXISTS idx_comments_post ON comments(post_id);
            CREATE INDEX IF NOT EXISTS idx_likes_post ON likes(post_id);
            """
        )
        ensure_column(db, "users", "email", "TEXT")
        ensure_column(db, "posts", "youtube_url", "TEXT")
        db.execute("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(email) WHERE email IS NOT NULL")
        if db.execute("SELECT COUNT(*) FROM users").fetchone()[0] == 0:
            password_hash = bcrypt.hashpw(b"admin123", bcrypt.gensalt()).decode()
            db.execute(
                "INSERT INTO users (username, password_hash, role, created_at, email) VALUES (?, ?, ?, ?, ?)",
                ("admin", password_hash, "admin", now_iso(), None),
            )

        settings = {
            "site_name": "AniScope by Arafat",
            "site_tagline": "Bangla Anime Reviews, Character Analysis, Manga Updates & Anime News",
            "donation_label": "Support AniScope",
            "donation_number": "",
            "home_background_url": "/assets/images/hero-original.svg",
            "copyright_text": f"© {datetime.now().year} AniScope by Arafat. All rights reserved.",
        }
        timestamp = now_iso()
        db.executemany(
            "INSERT OR IGNORE INTO settings (key, value, updated_at) VALUES (?, ?, ?)",
            [(key, value, timestamp) for key, value in settings.items()],
        )

        if db.execute("SELECT COUNT(*) FROM posts").fetchone()[0] == 0:
            posts = [
                ("Demon Slayer Season 1 Explained", "Anime", "A spoiler-aware guide to a young swordsman's first trials and the bonds that shape his journey.", "We explore the season's structure, emotional themes, training arcs, and the choices that turn grief into purpose. This editorial uses no copyrighted imagery and is provided as sample content for AniScope.", "/assets/images/post-fire.svg"),
                ("Jujutsu Kaisen Beginner Guide", "Anime", "A friendly introduction to cursed energy, rival schools, and the rules behind supernatural combat.", "New to modern supernatural battle stories? This guide breaks down the key ideas, power systems, and character dynamics in plain language, while keeping major surprises safely out of view.", "/assets/images/post-sorcery.svg"),
                ("Solo Leveling Story Explained", "Manga", "From overlooked hunter to mysterious powerhouse: the core progression fantasy explained.", "This sample feature maps the hero's progression, the appeal of game-like systems, and the tension between personal growth and a changing world.", "/assets/images/post-shadow.svg"),
                ("One Piece Anime Overview", "News", "A broad, beginner-friendly look at a vast sea adventure and its enduring sense of discovery.", "This overview discusses long-form storytelling, found family, imaginative islands, and why viewers connect with sprawling adventure narratives.", "/assets/images/post-ocean.svg"),
                ("Five Manga Chapters Worth Watching", "Manga", "Fresh story beats, sharp cliffhangers, and what readers are discussing this week.", "Our fictional weekly manga desk highlights pacing, visual storytelling, and memorable reveals across the latest sample updates.", "/assets/images/post-manga.svg"),
                ("Summer Anime Radar", "News", "Original recommendations and production trends to keep on your seasonal watchlist.", "AniScope's sample newsroom looks at fantasy adventures, character dramas, and creative original series without relying on copyrighted promotional art.", "/assets/images/post-news.svg"),
            ]
            timestamp = now_iso()
            db.executemany(
                "INSERT INTO posts (title, slug, category, excerpt, content, image_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'published', ?, ?)",
                [(title, slugify(title), category, excerpt, content, image, timestamp, timestamp) for title, category, excerpt, content, image in posts],
            )

        if db.execute("SELECT COUNT(*) FROM characters").fetchone()[0] == 0:
            characters = [
                ("Original Fire Swordsman", "Ember Oath", "A disciplined guardian who carries a blade forged from meteor glass and protects mountain villages from spectral storms.", "Ember breathing, heat resistance, meteor-glass swordsmanship", "/assets/images/character-fire.svg"),
                ("Original Shadow Ninja", "Moonveil Chronicles", "A quiet scout from the floating city of Kagehara who turns darkness into paths between rooftops.", "Shadow step, echo clones, silent movement", "/assets/images/character-shadow.svg"),
                ("Original Blue-Eyed Sorcerer", "Azure Paradox", "A playful academy mentor who studies spatial magic and hides deep responsibility behind an easy grin.", "Spatial folding, azure barrier, arcane perception", "/assets/images/character-blue.svg"),
                ("Original Pirate Hero", "Starwake Seas", "An optimistic sky-pirate captain searching for the legendary compass that points toward forgotten dreams.", "Wind sails, fearless leadership, constellation compass", "/assets/images/character-pirate.svg"),
            ]
            timestamp = now_iso()
            db.executemany(
                "INSERT INTO characters (name, anime_name, slug, bio, abilities, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [(name, anime, slugify(name), bio, abilities, image, timestamp, timestamp) for name, anime, bio, abilities, image in characters],
            )


@asynccontextmanager
async def lifespan(_: FastAPI):
    initialize_database()
    yield


app = FastAPI(title="AniScope by Arafat API", version="1.0.0", lifespan=lifespan)
allowed_origins = [origin.strip() for origin in os.getenv("CORS_ORIGINS", "http://localhost:8080,http://127.0.0.1:8080").split(",")]
app.add_middleware(CORSMiddleware, allow_origins=allowed_origins, allow_credentials=True, allow_methods=["*"], allow_headers=["*"])


class LoginRequest(BaseModel):
    username: str = Field(min_length=1, max_length=80)
    password: str = Field(min_length=1, max_length=200)


class SignupRequest(BaseModel):
    username: str = Field(min_length=3, max_length=40, pattern=r"^[A-Za-z0-9_]+$")
    email: str = Field(min_length=6, max_length=200)
    password: str = Field(min_length=6, max_length=200)


class ModeratorPayload(BaseModel):
    username: str = Field(min_length=3, max_length=40, pattern=r"^[A-Za-z0-9_]+$")
    password: str = Field(min_length=6, max_length=200)


class CommentPayload(BaseModel):
    content: str = Field(min_length=1, max_length=2000)


class SettingsPayload(BaseModel):
    site_name: str = Field(min_length=2, max_length=100)
    site_tagline: str = Field(min_length=2, max_length=300)
    donation_label: str = Field(max_length=100)
    donation_number: str = Field(max_length=100)
    home_background_url: str = Field(default="", max_length=500)
    copyright_text: str = Field(min_length=2, max_length=300)


class PostPayload(BaseModel):
    title: str = Field(min_length=2, max_length=200)
    category: Literal["Anime", "Manga", "News"]
    excerpt: str = Field(min_length=2, max_length=500)
    content: str = Field(min_length=2)
    image_url: str = Field(min_length=1, max_length=500)
    youtube_url: str | None = Field(default=None, max_length=500)
    status: Literal["draft", "published"] = "published"


class CharacterPayload(BaseModel):
    name: str = Field(min_length=2, max_length=160)
    anime_name: str = Field(min_length=2, max_length=160)
    bio: str = Field(min_length=2)
    abilities: str = Field(min_length=2, max_length=1000)
    image_url: str = Field(min_length=1, max_length=500)


def authenticate(credentials: HTTPAuthorizationCredentials | None = Depends(security)) -> dict:
    if credentials is None:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Authentication required")
    try:
        payload = jwt.decode(credentials.credentials, JWT_SECRET, algorithms=[JWT_ALGORITHM])
    except jwt.PyJWTError as exc:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid or expired token") from exc
    return payload


def require_staff(user: dict = Depends(authenticate)) -> dict:
    if user.get("role") not in {"admin", "moderator"}:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Staff access required")
    return user


def require_admin(user: dict = Depends(authenticate)) -> dict:
    if user.get("role") != "admin":
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Admin access required")
    return user


def token_response(user: sqlite3.Row) -> dict:
    expires = datetime.now(timezone.utc) + timedelta(minutes=JWT_EXPIRE_MINUTES)
    token = jwt.encode({"sub": str(user["id"]), "username": user["username"], "role": user["role"], "exp": expires}, JWT_SECRET, algorithm=JWT_ALGORITHM)
    return {"access_token": token, "token_type": "bearer", "expires_in": JWT_EXPIRE_MINUTES * 60, "user": {"id": user["id"], "username": user["username"], "email": user["email"], "role": user["role"]}}


@app.get("/")
def root():
    return {"name": "AniScope by Arafat API", "status": "online", "docs": "/docs"}


@app.get("/api/health")
def health():
    return {"status": "ok"}


@app.post("/api/login")
def login(payload: LoginRequest):
    with db_connection() as db:
        user = db.execute("SELECT * FROM users WHERE lower(username) = lower(?) OR lower(email) = lower(?)", (payload.username, payload.username)).fetchone()
    if user is None or not bcrypt.checkpw(payload.password.encode(), user["password_hash"].encode()):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid username or password")
    return token_response(user)


@app.post("/api/signup", status_code=201)
def signup(payload: SignupRequest):
    email = payload.email.strip().lower()
    domain = email.rsplit("@", 1)[-1] if "@" in email else ""
    if domain not in {"gmail.com", "icloud.com", "hotmail.com", "yahoo.com"}:
        raise HTTPException(status_code=422, detail="Use a gmail.com, icloud.com, hotmail.com, or yahoo.com email address")
    password_hash = bcrypt.hashpw(payload.password.encode(), bcrypt.gensalt()).decode()
    try:
        with db_connection() as db:
            cursor = db.execute("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, 'user', ?)", (payload.username, email, password_hash, now_iso()))
            user = db.execute("SELECT * FROM users WHERE id = ?", (cursor.lastrowid,)).fetchone()
    except sqlite3.IntegrityError as exc:
        raise HTTPException(status_code=409, detail="That username or email is already registered") from exc
    return token_response(user)


@app.get("/api/settings")
def get_settings():
    with db_connection() as db:
        return {row["key"]: row["value"] for row in db.execute("SELECT key, value FROM settings")}


@app.put("/api/settings")
def update_settings(payload: SettingsPayload, _: dict = Depends(require_admin)):
    values = payload.model_dump()
    with db_connection() as db:
        db.executemany("INSERT INTO settings (key, value, updated_at) VALUES (?, ?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at", [(key, value.strip(), now_iso()) for key, value in values.items()])
    return values


@app.get("/api/moderators")
def list_moderators(_: dict = Depends(require_admin)):
    with db_connection() as db:
        return [dict(row) for row in db.execute("SELECT id, username, email, role, created_at FROM users WHERE role = 'moderator' ORDER BY id DESC")]


@app.post("/api/moderators", status_code=201)
def create_moderator(payload: ModeratorPayload, _: dict = Depends(require_admin)):
    password_hash = bcrypt.hashpw(payload.password.encode(), bcrypt.gensalt()).decode()
    try:
        with db_connection() as db:
            cursor = db.execute("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, NULL, ?, 'moderator', ?)", (payload.username, password_hash, now_iso()))
            row = db.execute("SELECT id, username, email, role, created_at FROM users WHERE id = ?", (cursor.lastrowid,)).fetchone()
    except sqlite3.IntegrityError as exc:
        raise HTTPException(status_code=409, detail="That username is already in use") from exc
    return dict(row)


@app.delete("/api/moderators/{moderator_id}")
def delete_moderator(moderator_id: int, _: dict = Depends(require_admin)):
    with db_connection() as db:
        cursor = db.execute("DELETE FROM users WHERE id = ? AND role = 'moderator'", (moderator_id,))
    if cursor.rowcount == 0:
        raise HTTPException(status_code=404, detail="Moderator not found")
    return {"message": "Moderator removed"}


@app.get("/api/posts")
def list_posts(category: str | None = Query(default=None), status_filter: str | None = Query(default=None, alias="status")):
    query = "SELECT * FROM posts WHERE 1=1"
    params: list[str] = []
    if category:
        query += " AND category = ?"
        params.append(category.title())
    if status_filter:
        query += " AND status = ?"
        params.append(status_filter)
    query += " ORDER BY created_at DESC, id DESC"
    with db_connection() as db:
        return [dict(row) for row in db.execute(query, params).fetchall()]


@app.get("/api/posts/{post_id}")
def get_post(post_id: int):
    with db_connection() as db:
        row = db.execute("SELECT * FROM posts WHERE id = ?", (post_id,)).fetchone()
    if row is None:
        raise HTTPException(status_code=404, detail="Post not found")
    return dict(row)


@app.post("/api/posts", status_code=201)
def create_post(payload: PostPayload, _: dict = Depends(require_staff)):
    timestamp = now_iso()
    with db_connection() as db:
        slug = unique_slug(db, "posts", payload.title)
        cursor = db.execute("INSERT INTO posts (title, slug, category, excerpt, content, image_url, youtube_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", (payload.title, slug, payload.category, payload.excerpt, payload.content, payload.image_url, payload.youtube_url or "", payload.status, timestamp, timestamp))
        row = db.execute("SELECT * FROM posts WHERE id = ?", (cursor.lastrowid,)).fetchone()
    return dict(row)


@app.put("/api/posts/{post_id}")
def update_post(post_id: int, payload: PostPayload, _: dict = Depends(require_staff)):
    with db_connection() as db:
        if db.execute("SELECT id FROM posts WHERE id = ?", (post_id,)).fetchone() is None:
            raise HTTPException(status_code=404, detail="Post not found")
        slug = unique_slug(db, "posts", payload.title, post_id)
        db.execute("UPDATE posts SET title=?, slug=?, category=?, excerpt=?, content=?, image_url=?, youtube_url=?, status=?, updated_at=? WHERE id=?", (payload.title, slug, payload.category, payload.excerpt, payload.content, payload.image_url, payload.youtube_url or "", payload.status, now_iso(), post_id))
        row = db.execute("SELECT * FROM posts WHERE id = ?", (post_id,)).fetchone()
    return dict(row)


@app.delete("/api/posts/{post_id}")
def delete_post(post_id: int, _: dict = Depends(require_staff)):
    with db_connection() as db:
        cursor = db.execute("DELETE FROM posts WHERE id = ?", (post_id,))
    if cursor.rowcount == 0:
        raise HTTPException(status_code=404, detail="Post not found")
    return {"message": "Post deleted"}


@app.get("/api/characters")
def list_characters():
    with db_connection() as db:
        return [dict(row) for row in db.execute("SELECT * FROM characters ORDER BY created_at DESC, id DESC").fetchall()]


@app.get("/api/characters/{character_id}")
def get_character(character_id: int):
    with db_connection() as db:
        row = db.execute("SELECT * FROM characters WHERE id = ?", (character_id,)).fetchone()
    if row is None:
        raise HTTPException(status_code=404, detail="Character not found")
    return dict(row)


@app.post("/api/characters", status_code=201)
def create_character(payload: CharacterPayload, _: dict = Depends(require_staff)):
    timestamp = now_iso()
    with db_connection() as db:
        slug = unique_slug(db, "characters", payload.name)
        cursor = db.execute("INSERT INTO characters (name, anime_name, slug, bio, abilities, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", (payload.name, payload.anime_name, slug, payload.bio, payload.abilities, payload.image_url, timestamp, timestamp))
        row = db.execute("SELECT * FROM characters WHERE id = ?", (cursor.lastrowid,)).fetchone()
    return dict(row)


@app.put("/api/characters/{character_id}")
def update_character(character_id: int, payload: CharacterPayload, _: dict = Depends(require_staff)):
    with db_connection() as db:
        if db.execute("SELECT id FROM characters WHERE id = ?", (character_id,)).fetchone() is None:
            raise HTTPException(status_code=404, detail="Character not found")
        slug = unique_slug(db, "characters", payload.name, character_id)
        db.execute("UPDATE characters SET name=?, anime_name=?, slug=?, bio=?, abilities=?, image_url=?, updated_at=? WHERE id=?", (payload.name, payload.anime_name, slug, payload.bio, payload.abilities, payload.image_url, now_iso(), character_id))
        row = db.execute("SELECT * FROM characters WHERE id = ?", (character_id,)).fetchone()
    return dict(row)


@app.delete("/api/characters/{character_id}")
def delete_character(character_id: int, _: dict = Depends(require_staff)):
    with db_connection() as db:
        cursor = db.execute("DELETE FROM characters WHERE id = ?", (character_id,))
    if cursor.rowcount == 0:
        raise HTTPException(status_code=404, detail="Character not found")
    return {"message": "Character deleted"}


@app.get("/api/posts/{post_id}/comments")
def list_comments(post_id: int):
    with db_connection() as db:
        rows = db.execute("SELECT comments.id, comments.content, comments.created_at, users.username FROM comments JOIN users ON users.id = comments.user_id WHERE comments.post_id = ? ORDER BY comments.id DESC", (post_id,)).fetchall()
    return [dict(row) for row in rows]


@app.post("/api/posts/{post_id}/comments", status_code=201)
def create_comment(post_id: int, payload: CommentPayload, user: dict = Depends(authenticate)):
    with db_connection() as db:
        if db.execute("SELECT id FROM posts WHERE id = ? AND status = 'published'", (post_id,)).fetchone() is None:
            raise HTTPException(status_code=404, detail="Post not found")
        cursor = db.execute("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, ?)", (post_id, int(user["sub"]), payload.content.strip(), now_iso()))
        row = db.execute("SELECT comments.id, comments.content, comments.created_at, users.username FROM comments JOIN users ON users.id = comments.user_id WHERE comments.id = ?", (cursor.lastrowid,)).fetchone()
    return dict(row)


@app.delete("/api/comments/{comment_id}")
def delete_comment(comment_id: int, user: dict = Depends(authenticate)):
    with db_connection() as db:
        if user["role"] in {"admin", "moderator"}:
            cursor = db.execute("DELETE FROM comments WHERE id = ?", (comment_id,))
        else:
            cursor = db.execute("DELETE FROM comments WHERE id = ? AND user_id = ?", (comment_id, int(user["sub"])))
    if cursor.rowcount == 0:
        raise HTTPException(status_code=404, detail="Comment not found or not owned by you")
    return {"message": "Comment deleted"}


@app.get("/api/posts/{post_id}/likes")
def get_likes(post_id: int, user_id: int | None = None):
    with db_connection() as db:
        count = db.execute("SELECT COUNT(*) FROM likes WHERE post_id = ?", (post_id,)).fetchone()[0]
        liked = bool(user_id and db.execute("SELECT id FROM likes WHERE post_id = ? AND user_id = ?", (post_id, user_id)).fetchone())
    return {"count": count, "liked": liked}


@app.post("/api/posts/{post_id}/like")
def toggle_like(post_id: int, user: dict = Depends(authenticate)):
    user_id = int(user["sub"])
    with db_connection() as db:
        existing = db.execute("SELECT id FROM likes WHERE post_id = ? AND user_id = ?", (post_id, user_id)).fetchone()
        if existing:
            db.execute("DELETE FROM likes WHERE id = ?", (existing["id"],))
            liked = False
        else:
            if db.execute("SELECT id FROM posts WHERE id = ? AND status = 'published'", (post_id,)).fetchone() is None:
                raise HTTPException(status_code=404, detail="Post not found")
            db.execute("INSERT INTO likes (post_id, user_id, created_at) VALUES (?, ?, ?)", (post_id, user_id, now_iso()))
            liked = True
        count = db.execute("SELECT COUNT(*) FROM likes WHERE post_id = ?", (post_id,)).fetchone()[0]
    return {"count": count, "liked": liked}
