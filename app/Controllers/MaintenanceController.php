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
        $dbName = $_POST['db'] ?? '';
        if (!$dbName) { Flight::halt(400, 'Missing parameters'); return; }

        $this->resolvePg($dbName);
        $filename = $dbName . '_backup_' . date('Ymd_His') . '.sql';

        try {
            $dump = $this->pg->exportDatabase($dbName);
            header('Content-Type: application/sql');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($dump));
            echo $dump;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Backup failed: ' . $e->getMessage();
            $this->redirect('/maintenance?server_id=' . $this->serverId);
        }
    }

    public function logs(): void
    {
        $this->resolvePg();
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
