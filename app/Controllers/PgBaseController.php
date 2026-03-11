<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;
use PostgreManager\Models\DatabaseLock;
use PostgreManager\Models\ServerProfile;
use PostgreManager\Services\PgService;


abstract class PgBaseController extends BaseController
{
    protected PgService $pg;
    protected DatabaseLock $databaseLock;
    protected int $serverId;
    protected array $activeProfile = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Resolve the active server from session or POST and connect.
     */
    protected function resolvePg(string $dbName = ''): void
    {
        $this->requireAuth();
        $serverId = (int) ($_POST['server_id'] ?? $_GET['server_id'] ?? $_SESSION['active_server_id'] ?? 0);

        if (!$serverId) {
            $_SESSION['flash_error'] = 'No server selected.';
            Flight::redirect('/servers');
            exit;
        }

        $_SESSION['active_server_id'] = $serverId;
        $this->serverId = $serverId;

        $profileModel        = new ServerProfile($this->db());
        $this->databaseLock  = new DatabaseLock($this->db());
        $profile             = $profileModel->find($serverId);
        $this->activeProfile = $profile ?? [];
        $this->pg            = new PgService($profileModel);
        $this->pg->connect($serverId, $dbName);
    }

    protected function isDatabaseLocked(string $dbName): bool
    {
        return $this->databaseLock->isLocked($this->serverId, $dbName);
    }

    protected function requireDatabaseConfirmation(string $dbName, string $expectedWord): void
    {
        if (!$this->isDatabaseLocked($dbName)) {
            return;
        }

        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));
        if ($confirmation !== $expectedWord) {
            throw new \RuntimeException("Database \"{$dbName}\" is locked. Type {$expectedWord} to continue.");
        }
    }
}
