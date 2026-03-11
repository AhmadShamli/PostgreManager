<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;

class DatabaseController extends PgBaseController
{
    public function index(): void
    {
        $this->resolvePg();
        $databases = $this->pg->listDatabases();
        $locks = $this->databaseLock->getByServer($this->serverId);

        foreach ($databases as &$database) {
            $database['is_locked'] = $locks[$database['name']] ?? false;
        }
        unset($database);

        $this->render('databases/index.html.twig', [
            'active'    => 'databases',
            'databases' => $databases,
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
            $this->requireDatabaseConfirmation($name, 'DROP');
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
            $this->requireDatabaseConfirmation($name, 'TRUNCATE');
            $this->pg->truncateDatabase();
            $_SESSION['flash_success'] = "Database \"{$name}\" reset to an empty default state.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/databases?server_id=' . $this->serverId);
    }

    public function recreate(string $name): void
    {
        $this->resolvePg();
        try {
            $this->requireDatabaseConfirmation($name, 'RECREATE');
            $this->pg->recreateDatabase($name);
            $_SESSION['flash_success'] = "Database \"{$name}\" recreated with its previous owner preserved.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/databases?server_id=' . $this->serverId);
    }

    public function lock(string $name): void
    {
        $this->resolvePg();
        $this->databaseLock->setLocked($this->serverId, $name, true);
        $_SESSION['flash_success'] = "Database \"{$name}\" locked.";
        $this->redirect('/databases?server_id=' . $this->serverId);
    }

    public function unlock(string $name): void
    {
        $this->resolvePg();
        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));
        if ($confirmation !== 'UNLOCK') {
            $_SESSION['flash_error'] = "Database \"{$name}\" is locked. Type UNLOCK to remove protection.";
            $this->redirect('/databases?server_id=' . $this->serverId);
            return;
        }

        $this->databaseLock->setLocked($this->serverId, $name, false);
        $_SESSION['flash_success'] = "Database \"{$name}\" unlocked.";
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
