<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Config
$config = require __DIR__ . '/config/config.php';

// ── SQLite — app internal DB ───────────────────────────────────────────────
$sqlitePath = $config['sqlite']['path'];
$storageDir = dirname($sqlitePath);

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$isNewDb = !file_exists($sqlitePath);

$pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('PRAGMA journal_mode=WAL;');
$pdo->exec('PRAGMA foreign_keys=ON;');

// Auto-run schema on first boot
if ($isNewDb) {
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($schema);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS pm_database_locks (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        server_id     INTEGER NOT NULL REFERENCES pm_server_profiles(id) ON DELETE CASCADE,
        database_name TEXT NOT NULL,
        is_locked     INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT DEFAULT (datetime('now')),
        updated_at    TEXT DEFAULT (datetime('now')),
        UNIQUE(server_id, database_name)
    )"
);

Flight::map('db', fn() => $pdo);
Flight::set('db_ready', true);


// ── Twig ──────────────────────────────────────────────────────────────────
$loader = new FilesystemLoader(__DIR__ . '/resources/views');
$twig   = new Environment($loader, [
    'cache'       => $config['twig']['debug'] ? false : $storageDir . '/twig_cache',
    'debug'       => $config['twig']['debug'],
    'auto_reload' => true,
]);

if ($config['twig']['debug']) {
    $twig->addExtension(new \Twig\Extension\DebugExtension());
}

Flight::set('twig', $twig);
Flight::set('config', $config);

// ── Session ───────────────────────────────────────────────────────────────
session_start();

// ── Global Twig vars ──────────────────────────────────────────────────────
$twig->addGlobal('session', $_SESSION);
$twig->addGlobal('app_name', 'PostgreManager');

// ── Load app settings from DB into globals ────────────────────────────────
try {
    $theme          = $pdo->query("SELECT value FROM pm_settings WHERE key='ui_theme'")->fetchColumn() ?: 'dark';
    $sessionTimeout = (int) ($pdo->query("SELECT value FROM pm_settings WHERE key='session_timeout'")->fetchColumn() ?: 60);
} catch (\Throwable) {
    $theme = 'dark';
    $sessionTimeout = 60;
}
$_SESSION['theme'] = $theme;
Flight::set('session_timeout', $sessionTimeout);
// Re-add globals so theme is available in Twig
$twig->addGlobal('session', $_SESSION);

// ── Routes ────────────────────────────────────────────────────────────────
require __DIR__ . '/routes/web.php';
