# PostgreManager

A web-based PostgreSQL manager. Manage multiple external PostgreSQL servers from a single UI.
App data (users, settings, server profiles, database locks) is stored in a local **SQLite** database, so losing a managed server never locks you out.

## Stack

| Layer | Technology |
|---|---|
| Runtime | PHP 8.4 |
| Framework | FlightPHP (micro-framework) |
| Templating | Twig |
| UI | AdminLTE 3 (via cdnjs) |
| App DB | SQLite (auto-created on first boot) |
| Managed DBs | PostgreSQL (any external server) |
| Web Server | Apache + mod_php |

## PHP Extensions Required

| Extension | Purpose | Required |
|---|---|---|
| `pdo` | Database abstraction | Yes |
| `pdo_sqlite` | App database | Yes |
| `pdo_pgsql` | Managed PostgreSQL servers | Yes |
| `openssl` | Password encryption | Yes |
| `mbstring` | String handling | Yes |
| `json` | API responses | Yes |
| `session` | User sessions | Optional |
| `fileinfo` | File uploads | Optional |

Missing extensions are reported on the setup page automatically.

## Quick Start (Docker)

```bash
cp .env.example .env
docker compose up -d
# visit http://localhost:8080 -> /setup
```

First visit redirects to `/setup` where you create the Super Admin account.

## Quick Start (Manual)

```bash
# 1. Install PHP dependencies
composer install

# 2. Configure environment
cp .env.example .env
# Edit APP_SECRET at minimum

# 3. Configure your web server to serve public/ as web root
#    The Docker setup uses Apache

# 4. Visit http://yourdomain/setup
```

The SQLite database is auto-created at `storage/database.sqlite` on first boot. No manual schema migration is needed.

For backup/import features, the host running PHP must also have PostgreSQL client tools available:

```bash
# optional: set explicit client paths if pg_dump / psql are not on PATH
PG_DUMP_BINARY=
PSQL_BINARY=
```

Windows example:

```bash
PG_DUMP_BINARY="C:\Program Files\PostgreSQL\17\bin\pg_dump.exe"
PSQL_BINARY="C:\Program Files\PostgreSQL\17\bin\psql.exe"
```

## Folder Structure

```text
app/
  Controllers/     <- Route handlers (PSR-4, OOP)
  Models/          <- SQLite data access (User, Setting, ServerProfile, DatabaseLock)
  Services/        <- PgService (managed PG), AuthService
config/            <- config.php
database/          <- schema.sql (SQLite)
public/            <- Web root (index.php, assets/css, assets/js)
resources/views/   <- Twig templates (AdminLTE-based)
routes/            <- web.php
storage/           <- database.sqlite, twig_cache (auto-created, gitignored)
bootstrap.php      <- App bootstrap (SQLite init, Twig, Flight registration)
docker-compose.yml <- single Apache + PHP container
```

## Features

- **Initial Setup Wizard** - extension check and super admin creation on first run
- **App User Management** - roles: `super_admin`, `admin`, `viewer`
- **Server Profiles** - save multiple PostgreSQL server connections, with passwords AES-256 encrypted
- **Database Management** - list, create, drop, owner-preserving recreate, object reset, export (`pg_dump`), import SQL
- **Schema and Table Browser** - navigate schemas -> tables -> columns and indexes
- **Data Browser** - paginated rows, delete, export CSV/JSON
- **SQL Query Editor** - CodeMirror editor, async execution, query history
- **PostgreSQL User/Role Management** - create, edit, drop roles and attributes
- **Statistics and Monitoring** - cache hit ratio, active queries, table sizes, index usage, locks
- **Maintenance** - `VACUUM ANALYZE`, backup (`pg_dump` download), activity log
- **Database Safety Lock** - lock a database so destructive actions require exact typed confirmation words
- **Settings** - UI theme, query timeout, row limit, profile management

## Destructive Actions

- `Reset database objects` removes user-created tables, views, routines, types, sequences, and extra schemas while keeping the default `public` schema.
- `Recreate database` drops and creates the database again from a maintenance connection, preserving the previous database owner.
- Locked databases require exact typed confirmation on both the UI and server side before actions such as `DROP`, `TRUNCATE`, `RECREATE`, `DELETE`, or `UNLOCK` are allowed.

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `production` | `development` or `production` |
| `APP_DEBUG` | `false` | Enable Twig debug mode |
| `APP_SECRET` | none | Required. Used for CSRF and encryption key |
| `PG_DUMP_BINARY` | empty | Optional full path to `pg_dump` if it is not on the server PATH |
| `PSQL_BINARY` | empty | Optional full path to `psql` if it is not on the server PATH |
