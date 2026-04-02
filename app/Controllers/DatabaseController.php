<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

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
        $this->resolvePg($name);
        $filename = $name . '_' . date('Ymd_His') . '.sql';

        try {
            $dump = $this->pg->exportDatabase($name);
            header('Content-Type: application/sql');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . strlen($dump));
            echo $dump;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Export failed: ' . $e->getMessage();
            $this->redirect('/databases?server_id=' . $this->serverId);
        }
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
        $file = $_FILES['sql_file'] ?? null;
        $resetBeforeImport = isset($_POST['reset_before_import']);

        if (!$file || !isset($file['error'])) {
            $_SESSION['flash_error'] = 'No SQL file was received.';
            $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
            return;
        }

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['flash_error'] = 'Please choose a SQL file to import.';
            $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = $this->uploadErrorMessage((int) $file['error']);
            $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
            return;
        }

        $tmpFile = (string) ($file['tmp_name'] ?? '');
        if ($tmpFile === '' || !is_uploaded_file($tmpFile)) {
            $_SESSION['flash_error'] = 'The uploaded SQL file is invalid.';
            $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
            return;
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'sql') {
            $_SESSION['flash_error'] = 'Only .sql files can be imported.';
            $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
            return;
        }

        if (filesize($tmpFile) === 0) {
            $_SESSION['flash_error'] = 'The uploaded SQL file is empty.';
            $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
            return;
        }

        try {
            if ($resetBeforeImport) {
                $this->pg->truncateDatabase();
            }

            $this->pg->importSqlFile($name, $tmpFile);
            $_SESSION['flash_success'] = $resetBeforeImport
                ? "Database \"{$name}\" was reset and the SQL import completed successfully."
                : "SQL imported into \"{$name}\" successfully.";
            $this->redirect('/databases?server_id=' . $this->serverId);
            return;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (
                !$resetBeforeImport
                && stripos($message, 'relation "_sqlx_migrations" already exists') !== false
            ) {
                $message .= ' Try again with "Reset existing objects before import" enabled, or import into an empty database.';
            }

            $_SESSION['flash_error'] = 'Import failed: ' . $message;
        }
        $this->redirect("/databases/{$name}/import?server_id={$this->serverId}");
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded SQL file is too large.',
            UPLOAD_ERR_PARTIAL => 'The SQL file upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Upload failed because the server is missing a temporary directory.',
            UPLOAD_ERR_CANT_WRITE => 'Upload failed because the server could not write the temporary file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by a server extension.',
            default => 'Upload failed. Please try again.',
        };
    }
}
