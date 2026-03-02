-- PostgreManager App Schema (SQLite)
-- Auto-created on first run via bootstrap.php

CREATE TABLE IF NOT EXISTS pm_settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

INSERT OR IGNORE INTO pm_settings (key, value) VALUES ('setup_complete', 'false');

CREATE TABLE IF NOT EXISTS pm_users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'viewer',
    is_active     INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT DEFAULT (datetime('now')),
    updated_at    TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS pm_server_profiles (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER REFERENCES pm_users(id) ON DELETE CASCADE,
    label           TEXT NOT NULL,
    host            TEXT NOT NULL,
    port            INTEGER NOT NULL DEFAULT 5432,
    db_name         TEXT NOT NULL DEFAULT 'postgres',
    pg_username     TEXT NOT NULL,
    pg_password_enc TEXT NOT NULL,
    is_shared       INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT DEFAULT (datetime('now'))
);
