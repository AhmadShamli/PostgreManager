<?php

declare(strict_types=1);

namespace PostgreManager\Services;

use PDO;
use PDOException;
use PostgreManager\Models\ServerProfile;

class PgService
{
    private ?PDO $conn = null;
    private array $activeProfile = [];

    public function __construct(
        private ServerProfile $profileModel,
        private array $clientBinaries = []
    ) {}

    /**
     * Connect to a PostgreSQL server using a stored profile.
     */
    public function connect(int $profileId, string $dbName = ''): PDO
    {
        $profile = $this->profileModel->find($profileId);
        if (!$profile) {
            throw new \RuntimeException("Server profile #{$profileId} not found.");
        }

        $this->activeProfile = $profile;
        $db  = $dbName ?: $profile['db_name'];
        $this->conn = $this->createPdo($profile, $db);
        return $this->conn;
    }

    public function recreateDatabase(string $name): void
    {
        if (!$this->activeProfile) {
            throw new \RuntimeException('No active profile. Call connect() first.');
        }

        $maintenanceDb = $this->pickMaintenanceDatabase($name);
        $maintenance   = $this->createPdo($this->activeProfile, $maintenanceDb);

        $stmt = $maintenance->prepare(
            "SELECT pg_catalog.pg_get_userbyid(datdba) AS owner,
                    pg_encoding_to_char(encoding) AS encoding,
                    datcollate AS lc_collate,
                    datctype AS lc_ctype,
                    datconnlimit AS conn_limit
             FROM pg_catalog.pg_database
             WHERE datname = :name"
        );
        $stmt->execute([':name' => $name]);
        $database = $stmt->fetch();

        if (!$database) {
            throw new \RuntimeException("Database \"{$name}\" not found.");
        }

        $maintenance->prepare(
            "SELECT pg_terminate_backend(pid)
             FROM pg_stat_activity
             WHERE datname = :name AND pid <> pg_backend_pid()"
        )->execute([':name' => $name]);

        try {
            $maintenance->exec('DROP DATABASE IF EXISTS ' . $this->quoteName($name) . ' WITH (FORCE)');
        } catch (PDOException) {
            $maintenance->exec('DROP DATABASE IF EXISTS ' . $this->quoteName($name));
        }

        $sql = sprintf(
            'CREATE DATABASE %s WITH OWNER %s ENCODING %s LC_COLLATE %s LC_CTYPE %s TEMPLATE template0 CONNECTION LIMIT %d',
            $this->quoteName($name),
            $this->quoteName($database['owner']),
            $maintenance->quote($database['encoding']),
            $maintenance->quote($database['lc_collate']),
            $maintenance->quote($database['lc_ctype']),
            (int) $database['conn_limit']
        );

        $maintenance->exec($sql);
    }

    public function getConnection(): PDO
    {
        if (!$this->conn) {
            throw new \RuntimeException('No active connection. Call connect() first.');
        }
        return $this->conn;
    }

    public function exportDatabase(string $name): string
    {
        $result = $this->runClientCommand('pg_dump', [
            '--no-password',
            '-h', (string) ($this->activeProfile['host'] ?? ''),
            '-p', (string) ($this->activeProfile['port'] ?? 5432),
            '-U', (string) ($this->activeProfile['pg_username'] ?? ''),
            $name,
        ]);

        if ($result['stdout'] === '') {
            throw new \RuntimeException('pg_dump completed without producing any output.');
        }

        return $result['stdout'];
    }

    public function importSqlFile(string $name, string $filePath): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('Uploaded SQL file could not be read.');
        }

        if (filesize($filePath) === 0) {
            throw new \RuntimeException('The uploaded SQL file is empty.');
        }

        $this->runClientCommand('psql', [
            '--no-password',
            '-v', 'ON_ERROR_STOP=1',
            '-h', (string) ($this->activeProfile['host'] ?? ''),
            '-p', (string) ($this->activeProfile['port'] ?? 5432),
            '-U', (string) ($this->activeProfile['pg_username'] ?? ''),
            '-d', $name,
            '-f', $filePath,
        ]);
    }

    // ── Server Info ────────────────────────────────────────────────────────
    public function serverVersion(): string
    {
        return $this->conn->query('SELECT version()')->fetchColumn();
    }

    public function uptime(): string
    {
        return $this->conn->query("SELECT date_trunc('second', now() - pg_postmaster_start_time())")->fetchColumn();
    }

    public function activeConnections(): int
    {
        return (int) $this->conn->query("SELECT COUNT(*) FROM pg_stat_activity WHERE state = 'active'")->fetchColumn();
    }

    // ── Databases ─────────────────────────────────────────────────────────
    public function listDatabases(): array
    {
        return $this->conn->query(
            "SELECT d.datname AS name,
                    pg_catalog.pg_get_userbyid(d.datdba) AS owner,
                    pg_size_pretty(pg_database_size(d.datname)) AS size,
                    pg_database_size(d.datname) AS size_bytes,
                    d.datconnlimit AS conn_limit,
                    d.datcollate AS collation
             FROM pg_catalog.pg_database d
             WHERE d.datistemplate = FALSE
             ORDER BY d.datname"
        )->fetchAll();
    }

    public function createDatabase(string $name, string $owner = '', int $connLimit = -1): void
    {
        $name = $this->quoteName($name);
        $sql  = "CREATE DATABASE {$name}";
        if ($owner) $sql .= " OWNER " . $this->quoteName($owner);
        if ($connLimit >= 0) $sql .= " CONNECTION LIMIT {$connLimit}";
        $this->conn->exec($sql);
    }

    public function dropDatabase(string $name): void
    {
        $this->conn->exec("DROP DATABASE IF EXISTS " . $this->quoteName($name));
    }

    /**
     * Remove user-created objects so the database is close to a fresh create.
     * Keeps the default public schema in place.
     */
    public function truncateDatabase(): void
    {
        $this->conn->beginTransaction();

        try {
            $schemas = $this->conn->query(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN ('pg_catalog', 'information_schema', 'pg_toast', 'public')
                 ORDER BY schema_name"
            )->fetchAll();

            foreach ($schemas as $schema) {
                $this->conn->exec('DROP SCHEMA IF EXISTS ' . $this->quoteName($schema['schema_name']) . ' CASCADE');
            }

            $objects = $this->conn->query(
                "SELECT c.relname AS name, c.relkind
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                 AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
                 ORDER BY c.relname"
            )->fetchAll();

            foreach ($objects as $object) {
                $qualifiedName = $this->quoteName('public') . '.' . $this->quoteName($object['name']);
                $command = match ($object['relkind']) {
                    'r', 'p' => 'DROP TABLE IF EXISTS ',
                    'v'      => 'DROP VIEW IF EXISTS ',
                    'm'      => 'DROP MATERIALIZED VIEW IF EXISTS ',
                    'S'      => 'DROP SEQUENCE IF EXISTS ',
                    'f'      => 'DROP FOREIGN TABLE IF EXISTS ',
                    default  => null,
                };

                if ($command !== null) {
                    $this->conn->exec($command . $qualifiedName . ' CASCADE');
                }
            }

            $routines = $this->conn->query(
                "SELECT p.proname AS name,
                        pg_get_function_identity_arguments(p.oid) AS identity_args,
                        p.prokind
                 FROM pg_proc p
                 JOIN pg_namespace n ON n.oid = p.pronamespace
                 WHERE n.nspname = 'public'
                 ORDER BY p.proname"
            )->fetchAll();

            foreach ($routines as $routine) {
                $kind = $routine['prokind'] === 'p' ? 'PROCEDURE' : 'FUNCTION';
                $signature = $this->quoteName('public') . '.' . $this->quoteName($routine['name']) . '(' . $routine['identity_args'] . ')';
                $this->conn->exec("DROP {$kind} IF EXISTS {$signature} CASCADE");
            }

            $types = $this->conn->query(
                "SELECT t.typname AS name
                 FROM pg_type t
                 JOIN pg_namespace n ON n.oid = t.typnamespace
                 WHERE n.nspname = 'public'
                 AND t.typtype IN ('d', 'e')
                 ORDER BY t.typname"
            )->fetchAll();

            foreach ($types as $type) {
                $this->conn->exec('DROP TYPE IF EXISTS ' . $this->quoteName('public') . '.' . $this->quoteName($type['name']) . ' CASCADE');
            }

            $this->conn->exec('CREATE SCHEMA IF NOT EXISTS public');
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function totalDatabaseSize(): string
    {
        return $this->conn->query(
            "SELECT pg_size_pretty(SUM(pg_database_size(datname))) FROM pg_database WHERE datistemplate = FALSE"
        )->fetchColumn();
    }

    // ── PG Users & Roles ─────────────────────────────────────────────────
    public function listRoles(): array
    {
        return $this->conn->query(
            "SELECT rolname AS name, rolsuper, rolcreatedb, rolcreaterole,
                    rolcanlogin, rolreplication, rolconnlimit AS conn_limit,
                    pg_catalog.shobj_description(oid, 'pg_authid') AS comment
             FROM pg_catalog.pg_roles
             ORDER BY rolname"
        )->fetchAll();
    }

    public function createRole(string $name, array $opts = []): void
    {
        $attrs = [];
        if (!empty($opts['login']))       $attrs[] = 'LOGIN';
        if (!empty($opts['superuser']))   $attrs[] = 'SUPERUSER';
        if (!empty($opts['createdb']))    $attrs[] = 'CREATEDB';
        if (!empty($opts['createrole']))  $attrs[] = 'CREATEROLE';
        if (!empty($opts['replication'])) $attrs[] = 'REPLICATION';
        if (isset($opts['password']) && $opts['password'] !== '') {
            $quoted   = $this->conn->quote($opts['password']);
            $attrs[] = "PASSWORD {$quoted}";
        }
        $attrStr = $attrs ? ' WITH ' . implode(' ', $attrs) : '';
        $this->conn->exec("CREATE ROLE " . $this->quoteName($name) . $attrStr);
    }

    public function dropRole(string $name): void
    {
        $this->conn->exec("DROP ROLE IF EXISTS " . $this->quoteName($name));
    }

    public function changeRolePassword(string $name, string $password): void
    {
        $quoted = $this->conn->quote($password);
        $this->conn->exec("ALTER ROLE " . $this->quoteName($name) . " WITH PASSWORD {$quoted}");
    }

    public function grantPrivilege(string $privilege, string $dbName, string $role): void
    {
        $this->conn->exec("GRANT {$privilege} ON DATABASE " . $this->quoteName($dbName) . " TO " . $this->quoteName($role));
    }

    public function revokePrivilege(string $privilege, string $dbName, string $role): void
    {
        $this->conn->exec("REVOKE {$privilege} ON DATABASE " . $this->quoteName($dbName) . " FROM " . $this->quoteName($role));
    }

    // ── Schemas ───────────────────────────────────────────────────────────
    public function listSchemas(): array
    {
        return $this->conn->query(
            "SELECT schema_name AS name
             FROM information_schema.schemata
             WHERE schema_name NOT IN ('information_schema','pg_catalog','pg_toast')
             ORDER BY schema_name"
        )->fetchAll();
    }

    // ── Tables ────────────────────────────────────────────────────────────
    public function listTables(string $schema = 'public'): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.table_name AS name,
                    pg_size_pretty(pg_total_relation_size(quote_ident(t.table_schema)||'.'||quote_ident(t.table_name))) AS size,
                    c.reltuples::BIGINT AS approx_rows
             FROM information_schema.tables t
             JOIN pg_class c ON c.relname = t.table_name
             WHERE t.table_schema = :schema AND t.table_type = 'BASE TABLE'
             ORDER BY t.table_name"
        );
        $stmt->execute([':schema' => $schema]);
        return $stmt->fetchAll();
    }

    public function tableColumns(string $schema, string $table): array
    {
        $stmt = $this->conn->prepare(
            "SELECT column_name AS name, data_type, character_maximum_length,
                    is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table
             ORDER BY ordinal_position"
        );
        $stmt->execute([':schema' => $schema, ':table' => $table]);
        return $stmt->fetchAll();
    }

    public function tableIndexes(string $schema, string $table): array
    {
        $stmt = $this->conn->prepare(
            "SELECT indexname AS name, indexdef AS definition
             FROM pg_indexes
             WHERE schemaname = :schema AND tablename = :table"
        );
        $stmt->execute([':schema' => $schema, ':table' => $table]);
        return $stmt->fetchAll();
    }

    public function dropTable(string $schema, string $table, bool $cascade = false): void
    {
        $cascade = $cascade ? ' CASCADE' : '';
        $this->conn->exec("DROP TABLE IF EXISTS " . $this->quoteName($schema) . '.' . $this->quoteName($table) . $cascade);
    }

    // ── Data ─────────────────────────────────────────────────────────────
    public function tableData(string $schema, string $table, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $q      = "SELECT * FROM " . $this->quoteName($schema) . '.' . $this->quoteName($table);
        $total  = (int) $this->conn->query("SELECT COUNT(*) FROM " . $this->quoteName($schema) . '.' . $this->quoteName($table))->fetchColumn();
        $rows   = $this->conn->query($q . " LIMIT {$perPage} OFFSET {$offset}")->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    // ── Query Editor ─────────────────────────────────────────────────────
    public function runQuery(string $sql): array
    {
        $start = microtime(true);
        $stmt  = $this->conn->query($sql);
        $time  = round((microtime(true) - $start) * 1000, 2);

        $rows    = $stmt->fetchAll();
        $columns = $rows ? array_keys($rows[0]) : [];

        return [
            'columns'       => $columns,
            'rows'          => $rows,
            'row_count'     => count($rows),
            'execution_ms'  => $time,
        ];
    }

    // ── Statistics ───────────────────────────────────────────────────────
    public function activeQueries(): array
    {
        return $this->conn->query(
            "SELECT pid, usename, application_name, state,
                    EXTRACT(EPOCH FROM (now() - query_start))::INT AS duration_s,
                    left(query, 200) AS query
             FROM pg_stat_activity
             WHERE state != 'idle' AND pid <> pg_backend_pid()
             ORDER BY duration_s DESC NULLS LAST"
        )->fetchAll();
    }

    public function cacheHitRatio(): float
    {
        $row = $this->conn->query(
            "SELECT ROUND(blks_hit * 100.0 / NULLIF(blks_hit + blks_read, 0), 2) AS ratio
             FROM pg_stat_database WHERE datname = current_database()"
        )->fetch();
        return (float) ($row['ratio'] ?? 0);
    }

    public function indexUsageStats(): array
    {
        return $this->conn->query(
            "SELECT relname AS table, indexrelname AS index,
                    idx_scan, idx_tup_read, idx_tup_fetch
             FROM pg_stat_user_indexes
             ORDER BY idx_scan DESC LIMIT 20"
        )->fetchAll();
    }

    public function tableSizeStats(): array
    {
        return $this->conn->query(
            "SELECT relname AS table,
                    pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
                    pg_size_pretty(pg_relation_size(relid)) AS table_size,
                    pg_size_pretty(pg_total_relation_size(relid) - pg_relation_size(relid)) AS index_size,
                    n_live_tup AS rows, n_dead_tup AS dead_rows
             FROM pg_stat_user_tables
             ORDER BY pg_total_relation_size(relid) DESC LIMIT 20"
        )->fetchAll();
    }

    public function locks(): array
    {
        return $this->conn->query(
            "SELECT l.pid, l.mode, l.granted,
                    c.relname AS relation, a.query, a.state
             FROM pg_locks l
             LEFT JOIN pg_class c ON c.oid = l.relation
             LEFT JOIN pg_stat_activity a ON a.pid = l.pid
             WHERE NOT l.granted OR l.mode LIKE '%ExclusiveLock'
             ORDER BY l.pid"
        )->fetchAll();
    }

    // ── Maintenance ──────────────────────────────────────────────────────
    public function vacuum(string $schema, string $table, bool $full = false, bool $analyze = true): void
    {
        $cmd = $full ? 'VACUUM FULL' : 'VACUUM';
        if ($analyze) $cmd .= ' ANALYZE';
        $this->conn->exec("{$cmd} " . $this->quoteName($schema) . '.' . $this->quoteName($table));
    }

    public function reindex(string $table): void
    {
        $this->conn->exec("REINDEX TABLE " . $this->conn->quote($table));
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function quoteName(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private function createPdo(array $profile, string $dbName): PDO
    {
        $dsn = "pgsql:host={$profile['host']};port={$profile['port']};dbname={$dbName}";

        return new PDO(
            $dsn,
            $profile['pg_username'],
            $this->profileModel->decryptPassword($profile['pg_password_enc']),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10,
            ]
        );
    }

    private function pickMaintenanceDatabase(string $targetDb): string
    {
        foreach (['postgres', 'template1', $this->activeProfile['db_name'] ?? ''] as $candidate) {
            if ($candidate === '' || $candidate === $targetDb) {
                continue;
            }

            try {
                $pdo = $this->createPdo($this->activeProfile, $candidate);
                $pdo = null;
                return $candidate;
            } catch (PDOException) {
                continue;
            }
        }

        throw new \RuntimeException('Unable to connect to a maintenance database for recreate.');
    }

    private function runClientCommand(string $binary, array $args): array
    {
        if (!$this->activeProfile) {
            throw new \RuntimeException('No active profile. Call connect() first.');
        }

        $resolvedBinary = $this->resolveClientBinary($binary);
        $command = escapeshellarg($resolvedBinary);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg((string) $arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = ['PGPASSWORD' => $this->profileModel->decryptPassword($this->activeProfile['pg_password_enc'])];
        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to start {$binary}.");
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $message = trim($stderr) !== '' ? trim($stderr) : "{$binary} exited with code {$exitCode}.";
            throw new \RuntimeException($message);
        }

        return [
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }

    private function resolveClientBinary(string $binary): string
    {
        $configured = trim((string) ($this->clientBinaries[$binary] ?? ''));
        if ($configured !== '') {
            if ($this->isExecutablePath($configured)) {
                return $configured;
            }

            throw new \RuntimeException(sprintf(
                '%s was configured as "%s" but that file was not found or is not executable.',
                $binary,
                $configured
            ));
        }

        $found = $this->findExecutableInPath($binary);
        if ($found !== null) {
            return $found;
        }

        $envVar = $binary === 'pg_dump' ? 'PG_DUMP_BINARY' : 'PSQL_BINARY';

        throw new \RuntimeException(sprintf(
            '%s was not found on the server. Install PostgreSQL client tools or set %s in .env to the full executable path.',
            $binary,
            $envVar
        ));
    }

    private function findExecutableInPath(string $binary): ?string
    {
        $path = getenv('PATH') ?: '';
        if ($path === '') {
            return null;
        }

        $extensions = [''];
        if (DIRECTORY_SEPARATOR === '\\') {
            $pathext = getenv('PATHEXT') ?: '.EXE;.BAT;.CMD;.COM';
            $extensions = array_values(array_unique(array_filter(array_map('trim', explode(';', $pathext)))));
            array_unshift($extensions, '');
        }

        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            $dir = trim($dir, " \t\n\r\0\x0B\"'");
            if ($dir === '') {
                continue;
            }

            foreach ($extensions as $extension) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $binary . $extension;
                if ($this->isExecutablePath($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function isExecutablePath(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        return DIRECTORY_SEPARATOR === '\\' || is_executable($path);
    }
}
