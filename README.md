# PostgreManager

A web-based PostgreSQL manager. Manage multiple external PostgreSQL servers from a single UI.
App data (users, settings, server profiles) is stored in a local **SQLite** database — losing a managed server never locks you out.

## Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.4 |
| Framework | FlightPHP (micro-framework) |
| Templating | Twig |
| UI | AdminLTE 3 (via cdnjs) |
| App DB | SQLite (auto-created on first boot) |
| Managed DBs | PostgreSQL (any external server) |
| Web Server | Nginx + PHP-FPM |

## PHP Extensions Required

| Extension | Purpose | Required |
|---|---|---|
| `pdo` | Database abstraction | ✅ |
| `pdo_sqlite` | App database | ✅ |
| `pdo_pgsql` | Managed PostgreSQL servers | ✅ |
| `openssl` | Password encryption | ✅ |
| `mbstring` | String handling | ✅ |
| `json` | API responses | ✅ |
| `session` | User sessions | ⚠️ optional |
| `fileinfo` | File uploads | ⚠️ optional |

Missing extensions are reported on the setup page automatically.

## Quick Start (Docker)

```bash
cp .env.example .env        # set APP_SECRET
docker compose up -d
# visit http://localhost:8080 → /setup
```

First visit redirects to `/setup` where you create the Super Admin account.

## Quick Start (Manual)

```bash
# 1. Install PHP dependencies
composer install

# 2. Configure environment
cp .env.example .env
# Edit APP_SECRET at minimum

# 3. Configure Nginx to serve public/ as web root
#    See nginx/default.conf for reference

# 4. Visit http://yourdomain/setup
```

The SQLite database is auto-created at `storage/database.sqlite` on first boot. No manual schema migration needed.

## Folder Structure

```
app/
  Controllers/     ← Route handlers (PSR-4, OOP)
  Models/          ← SQLite data access (User, Setting, ServerProfile)
  Services/        ← PgService (managed PG), AuthService
config/            ← config.php
database/          ← schema.sql (SQLite)
nginx/             ← default.conf
public/            ← Web root (index.php, assets/css, assets/js)
resources/views/   ← Twig templates (AdminLTE-based)
routes/            ← web.php
storage/           ← database.sqlite, twig_cache (auto-created, gitignored)
bootstrap.php      ← App bootstrap (SQLite init, Twig, Flight registration)
docker-compose.yml ← app (PHP-FPM) + nginx only
```

## Features

- **Initial Setup Wizard** — extension check + super admin creation on first run
- **App User Management** — roles: `super_admin`, `admin`, `viewer`
- **Server Profiles** — save multiple PostgreSQL server connections (passwords AES-256 encrypted)
- **Database Management** — list, create, drop, export (pg_dump), import SQL
- **Schema & Table Browser** — navigate schemas → tables → columns/indexes
- **Data Browser** — paginated rows, delete, export CSV/JSON
- **SQL Query Editor** — CodeMirror editor, async execution, query history
- **PostgreSQL User/Role Management** — create, edit, drop roles and attributes
- **Statistics & Monitoring** — cache hit ratio, active queries, table sizes, index usage, locks
- **Maintenance** — VACUUM ANALYZE, backup (pg_dump download), activity log
- **Settings** — UI theme, query timeout, row limit; profile management

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `production` | `development` or `production` |
| `APP_DEBUG` | `false` | Enable Twig debug mode |
| `APP_SECRET` | — | **Required.** Used for CSRF and encryption key |
