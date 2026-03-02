<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;

class MaintenanceController extends PgBaseController
{
    public function index(): void
    {
        $this->resolvePg();
        $this->render('maintenance/index.html.twig', [
            'active'    => 'maintenance',
            'server_id' => $this->serverId,
            'databases' => $this->pg->listDatabases(),
        ]);
    }

    public function vacuum(): void
    {
        $this->resolvePg($_POST['db'] ?? '');
        $schema = $_POST['schema'] ?? 'public';
        $table  = $_POST['table'] ?? '';
        $full   = isset($_POST['full']);

        try {
            $this->pg->vacuum($schema, $table, $full, true);
            $_SESSION['flash_success'] = "VACUUM" . ($full ? " FULL" : "") . " ANALYZE completed on \"{$schema}.{$table}\".";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/maintenance?server_id=' . $this->serverId);
    }

    public function backup(): void
    {
        $this->requireAuth();
        $serverId = (int) ($_POST['server_id'] ?? $_SESSION['active_server_id'] ?? 0);
        $dbName   = $_POST['db'] ?? '';
        $profile  = (new \PostgreManager\Models\ServerProfile($this->db()))->find($serverId);

        if (!$profile || !$dbName) { Flight::halt(400, 'Missing parameters'); return; }

        $password = (new \PostgreManager\Models\ServerProfile($this->db()))->decryptPassword($profile['pg_password_enc']);
        $filename = $dbName . '_backup_' . date('Ymd_His') . '.sql';

        header('Content-Type: application/sql');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %d -U %s %s',
            escapeshellarg($password),
            escapeshellarg($profile['host']),
            (int) $profile['port'],
            escapeshellarg($profile['pg_username']),
            escapeshellarg($dbName)
        );
        passthru($cmd);
    }

    public function logs(): void
    {
        $this->resolvePg();
        $logs = $this->pg->getConnection()->query(
            "SELECT log_time, user_name, database_name, message FROM pg_catalog.pg_reading_file('pg_log/postgresql.log') LIMIT 100"
        );
        // If log function unavailable, show recent activity
        $activity = $this->pg->getConnection()->query(
            "SELECT pid, usename, application_name, state, left(query,300) AS query, query_start
             FROM pg_stat_activity WHERE query_start IS NOT NULL
             ORDER BY query_start DESC LIMIT 50"
        )->fetchAll();

        $this->render('maintenance/logs.html.twig', [
            'active'    => 'maintenance',
            'server_id' => $this->serverId,
            'activity'  => $activity,
        ]);
    }
}
