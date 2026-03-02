<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;
use PostgreManager\Models\ServerProfile;
use PostgreManager\Services\PgService;


abstract class PgBaseController extends BaseController
{
    protected PgService $pg;
    protected int $serverId;

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

        $profileModel = new ServerProfile($this->db());
        $this->pg     = new PgService($profileModel);
        $this->pg->connect($serverId, $dbName);
    }
}
