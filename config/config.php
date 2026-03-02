<?php

declare(strict_types=1);

return [
    'app' => [
        'env'    => $_ENV['APP_ENV']   ?? 'production',
        'debug'  => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'secret' => $_ENV['APP_SECRET'] ?? 'changeme',
    ],
    // SQLite — app internal database (not the managed PG servers)
    'sqlite' => [
        'path' => dirname(__DIR__) . '/storage/database.sqlite',
    ],
    'twig' => [
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
];
