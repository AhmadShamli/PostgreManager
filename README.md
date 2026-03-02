# PostgreManager

A web-based PostgreSQL manager built with PHP 8.4, FlightPHP, Twig, and AdminLTE.

## Requirements
- PHP 8.4 + php-pgsql + php-fpm
- PostgreSQL 12+
- Nginx
- Composer
- `pg_dump` on server PATH (for export/backup)

## Quick Start

```bash
# 1. Install dependencies
composer install

# 2. Copy env
cp .env.example .env
# Edit .env with your app DB credentials

# 3. Create app database & run schema
psql -U postgres -c "CREATE DATABASE postgre_manager;"
psql -U postgres -d postgre_manager -f database/schema.sql

# 4. Configure Nginx (see nginx/default.conf)
# Point root to /path/to/PostgreManager/public

# 5. Visit http://localhost → redirected to /setup
```

## Setup Wizard
On first visit, you'll be prompted to create your **Super Admin** account. The setup page is locked after completion.

## Folder Structure

```
app/
  Controllers/   ← FlightPHP controllers (PSR-4)
  Models/        ← Data models
  Services/      ← PgService (PostgreSQL), AuthService
config/          ← config.php
database/        ← schema.sql
nginx/           ← default.conf
public/          ← Web root (index.php, assets/)
resources/views/ ← Twig templates
routes/          ← web.php
bootstrap.php
```

## Stack
- **PHP 8.4** · PSR-4 · PSR-12
- **FlightPHP** (micro-framework)
- **Twig** (templating)
- **AdminLTE 3** (UI)
- **Nginx + PHP-FPM**
- **PostgreSQL** (app + managed databases)
