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

Flight::register('db', PDO::class, [], fn() => $pdo);
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

// ── Routes ────────────────────────────────────────────────────────────────
require __DIR__ . '/routes/web.php';
