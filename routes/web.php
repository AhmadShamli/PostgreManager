<?php

declare(strict_types=1);

use PostgreManager\Controllers\AuthController;
use PostgreManager\Controllers\SetupController;
use PostgreManager\Controllers\DashboardController;
use PostgreManager\Controllers\DatabaseController;
use PostgreManager\Controllers\PgUserController;
use PostgreManager\Controllers\SchemaController;
use PostgreManager\Controllers\TableController;
use PostgreManager\Controllers\DataController;
use PostgreManager\Controllers\QueryController;
use PostgreManager\Controllers\StatsController;
use PostgreManager\Controllers\MaintenanceController;
use PostgreManager\Controllers\SettingsController;
use PostgreManager\Controllers\AppUserController;
use PostgreManager\Controllers\ServerProfileController;

// ── Setup ────────────────────────────────────────────────────────────────────
Flight::route('GET /setup',          [SetupController::class, 'index']);
Flight::route('POST /setup',         [SetupController::class, 'store']);
Flight::route('POST /setup/test-db', [SetupController::class, 'testDb']);


// ── Auth ─────────────────────────────────────────────────────────────────────
Flight::route('GET /',               [AuthController::class, 'home']);
Flight::route('GET /login',          [AuthController::class, 'loginForm']);
Flight::route('POST /login',         [AuthController::class, 'login']);
Flight::route('GET /logout',         [AuthController::class, 'logout']);

// ── Dashboard ─────────────────────────────────────────────────────────────────
Flight::route('GET /dashboard',      [DashboardController::class, 'index']);

// ── Server Profiles ───────────────────────────────────────────────────────────
Flight::route('GET /servers',             [ServerProfileController::class, 'index']);
Flight::route('GET /servers/create',      [ServerProfileController::class, 'create']);
Flight::route('POST /servers',            [ServerProfileController::class, 'store']);
Flight::route('POST /servers/test',       [ServerProfileController::class, 'test']);
Flight::route('GET /servers/@id/edit',    [ServerProfileController::class, 'edit']);
Flight::route('POST /servers/@id',        [ServerProfileController::class, 'update']);
Flight::route('POST /servers/@id/delete', [ServerProfileController::class, 'destroy']);

// ── Databases ─────────────────────────────────────────────────────────────────
Flight::route('GET /databases',               [DatabaseController::class, 'index']);
Flight::route('GET /databases/create',        [DatabaseController::class, 'create']);
Flight::route('POST /databases',              [DatabaseController::class, 'store']);
Flight::route('POST /databases/@name/drop',   [DatabaseController::class, 'drop']);
Flight::route('POST /databases/@name/truncate', [DatabaseController::class, 'truncate']);
Flight::route('POST /databases/@name/recreate', [DatabaseController::class, 'recreate']);
Flight::route('POST /databases/@name/lock', [DatabaseController::class, 'lock']);
Flight::route('POST /databases/@name/unlock', [DatabaseController::class, 'unlock']);
Flight::route('GET /databases/@name/export',  [DatabaseController::class, 'export']);
Flight::route('GET /databases/@name/import',  [DatabaseController::class, 'importForm']);
Flight::route('POST /databases/@name/import', [DatabaseController::class, 'import']);

// ── PG Users & Roles ─────────────────────────────────────────────────────────
Flight::route('GET /pg-users',              [PgUserController::class, 'index']);
Flight::route('GET /pg-users/create',       [PgUserController::class, 'create']);
Flight::route('POST /pg-users',             [PgUserController::class, 'store']);
Flight::route('GET /pg-users/@name/edit',   [PgUserController::class, 'edit']);
Flight::route('POST /pg-users/@name',       [PgUserController::class, 'update']);
Flight::route('POST /pg-users/@name/drop',  [PgUserController::class, 'drop']);

// ── Schema & Tables ───────────────────────────────────────────────────────────
Flight::route('GET /databases/@db/schemas',                      [SchemaController::class, 'index']);
Flight::route('GET /databases/@db/schemas/@schema/tables',       [TableController::class, 'index']);
Flight::route('GET /databases/@db/schemas/@schema/tables/@table',[TableController::class, 'show']);
Flight::route('POST /databases/@db/schemas/@schema/tables/@table/drop', [TableController::class, 'drop']);

// ── Data Browser ─────────────────────────────────────────────────────────────
Flight::route('GET /databases/@db/schemas/@schema/tables/@table/data',    [DataController::class, 'index']);
Flight::route('POST /databases/@db/schemas/@schema/tables/@table/data',   [DataController::class, 'insert']);
Flight::route('POST /databases/@db/schemas/@schema/tables/@table/data/@rowid/update', [DataController::class, 'update']);
Flight::route('POST /databases/@db/schemas/@schema/tables/@table/data/@rowid/delete', [DataController::class, 'delete']);
Flight::route('GET /databases/@db/schemas/@schema/tables/@table/export',  [DataController::class, 'export']);

// ── Query Editor ─────────────────────────────────────────────────────────────
Flight::route('GET /query',          [QueryController::class, 'index']);
Flight::route('POST /query/run',     [QueryController::class, 'run']);
Flight::route('GET /query/history',  [QueryController::class, 'history']);
Flight::route('POST /query/save',    [QueryController::class, 'save']);

// ── Stats & Monitoring ───────────────────────────────────────────────────────
Flight::route('GET /stats',          [StatsController::class, 'index']);

// ── Maintenance ──────────────────────────────────────────────────────────────
Flight::route('GET /maintenance',         [MaintenanceController::class, 'index']);
Flight::route('POST /maintenance/vacuum', [MaintenanceController::class, 'vacuum']);
Flight::route('POST /maintenance/backup', [MaintenanceController::class, 'backup']);
Flight::route('GET /maintenance/logs',    [MaintenanceController::class, 'logs']);

// ── App Users (PM users, Super Admin only) ───────────────────────────────────
Flight::route('GET /app-users',              [AppUserController::class, 'index']);
Flight::route('GET /app-users/create',       [AppUserController::class, 'create']);
Flight::route('POST /app-users',             [AppUserController::class, 'store']);
Flight::route('GET /app-users/@id/edit',     [AppUserController::class, 'edit']);
Flight::route('POST /app-users/@id',         [AppUserController::class, 'update']);
Flight::route('POST /app-users/@id/delete',  [AppUserController::class, 'destroy']);

// ── Settings ─────────────────────────────────────────────────────────────────
Flight::route('GET /settings',       [SettingsController::class, 'index']);
Flight::route('POST /settings',      [SettingsController::class, 'update']);
Flight::route('GET /profile',        [SettingsController::class, 'profile']);
Flight::route('POST /profile',       [SettingsController::class, 'updateProfile']);

// ── Theme toggle ──────────────────────────────────────────────────────────────
Flight::route('POST /theme/toggle', function () {
    $current = $_SESSION['theme'] ?? 'dark';
    $next    = $current === 'dark' ? 'light' : 'dark';
    $_SESSION['theme'] = $next;
    // Persist to DB
    Flight::db()->prepare('INSERT OR REPLACE INTO pm_settings (key, value) VALUES (?, ?)')
        ->execute(['ui_theme', $next]);
    Flight::json(['theme' => $next]);
});

// ── Flash message clear ───────────────────────────────────────────────────────
Flight::route('POST /flash/clear', function () {
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    Flight::json(['ok' => true]);
});
