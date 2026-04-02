<?php

declare(strict_types=1);

return [
    'app' => [
        'env'          => $_ENV['APP_ENV']   ?? 'production',
        'debug'        => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'secret'       => $_ENV['APP_SECRET'] ?? 'changeme',
        'memory_limit' => trim((string) ($_ENV['APP_MEMORY_LIMIT'] ?? '256M')),
    ],
    // SQLite — app internal database (not the managed PG servers)
    'sqlite' => [
        'path' => dirname(__DIR__) . '/storage/database.sqlite',
    ],
    'twig' => [
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
    'postgresql' => [
        'client_binaries' => [
            'pg_dump' => trim((string) ($_ENV['PG_DUMP_BINARY'] ?? '')),
            'psql'    => trim((string) ($_ENV['PSQL_BINARY'] ?? '')),
        ],
    ],
];
