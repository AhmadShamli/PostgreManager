<?php

declare(strict_types=1);

namespace PostgreManager\Services;

use PDO;
use PDOException;
use PostgreManager\Models\ServerProfile;

class PgService
{
    private ?PDO $conn = null;

    public function __construct(private ServerProfile $profileModel) {}

    /**
     * Connect to a PostgreSQL server using a stored profile.
     */
    public function connect(int $profileId, string $dbName = ''): PDO
    {
        $profile = $this->profileModel->find($profileId);
        if (!$profile) {
            throw new \RuntimeException("Server profile #{$profileId} not found.");
        }

        $db  = $dbName ?: $profile['db_name'];
        $dsn = "pgsql:host={$profile['host']};port={$profile['port']};dbname={$db}";

        $this->conn = new PDO(
            $dsn,
            $profile['pg_username'],
            $this->profileModel->decryptPassword($profile['pg_password_enc']),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10,
            ]
        );
        return $this->conn;
    }

    public function getConnection(): PDO
    {
        if (!$this->conn) {
            throw new \RuntimeException('No active connection. Call connect() first.');
        }
        return $this->conn;
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
}
