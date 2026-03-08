<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;

class DatabaseController extends PgBaseController
{
    public function index(): void
    {
        $this->resolvePg();
        $this->render('databases/index.html.twig', [
            'active'    => 'databases',
            'databases' => $this->pg->listDatabases(),
            'server_id' => $this->serverId,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->render('databases/create.html.twig', [
            'active'    => 'databases',
            'server_id' => $_SESSION['active_server_id'] ?? 0,
        ]);
    }

    public function store(): void
    {
        $this->resolvePg();
        $name  = trim($_POST['name'] ?? '');
        $owner = trim($_POST['owner'] ?? '');
        $limit = (int) ($_POST['conn_limit'] ?? -1);

        try {
            $this->pg->createDatabase($name, $owner, $limit);
            $_SESSION['flash_success'] = "Database \"{$name}\" created.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/databases?server_id=' . $this->serverId);
    }

    public function drop(string $name): void
    {
        $this->resolvePg();
        try {
            $this->pg->dropDatabase($name);
            $_SESSION['flash_success'] = "Database \"{$name}\" dropped.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/databases?server_id=' . $this->serverId);
    }

    public function truncate(string $name): void
    {
        $this->resolvePg($name);
        try {
            $this->pg->truncateDatabase();
            $_SESSION['flash_success'] = "Database \"{$name}\" truncated (all tables dropped).";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/databases?server_id=' . $this->serverId);
    }

    public function export(string $name): void
    {
        $this->requireAuth();
        $serverId = (int) ($_GET['server_id'] ?? $_SESSION['active_server_id'] ?? 0);
        // pg_dump must be available on the server
        $profile   = (new \PostgreManager\Models\ServerProfile($this->db()))->find($serverId);
        if (!$profile) { Flight::halt(404); return; }

        $password = (new \PostgreManager\Models\ServerProfile($this->db()))->decryptPassword($profile['pg_password_enc']);
        $filename = $name . '_' . date('Ymd_His') . '.sql';

        header('Content-Type: application/sql');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %d -U %s %s',
            escapeshellarg($password),
            escapeshellarg($profile['host']),
            (int) $profile['port'],
            escapeshellarg($profile['pg_username']),
            escapeshellarg($name)
        );
        passthru($cmd);
    }

    public function importForm(string $name): void
    {
        $this->requireAuth();
        $this->render('databases/import.html.twig', [
            'active'    => 'databases',
            'db_name'   => $name,
            'server_id' => $_SESSION['active_server_id'] ?? 0,
        ]);
    }

    public function import(string $name): void
    {
        $this->resolvePg($name);
        if (empty($_FILES['sql_file']['tmp_name'])) {
            $_SESSION['flash_error'] = 'No file uploaded.';
            $this->redirect("/databases/{$name}/import");
            return;
        }

        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        try {
            $this->pg->getConnection()->exec($sql);
            $_SESSION['flash_success'] = "SQL imported into \"{$name}\" successfully.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/databases?server_id=' . $this->serverId);
    }
}
