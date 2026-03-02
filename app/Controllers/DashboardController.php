<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use PostgreManager\Models\ServerProfile;
use PostgreManager\Services\PgService;

class DashboardController extends PgBaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        // Load server profiles for the sidebar switcher
        $profileModel = new ServerProfile($this->db());
        $profiles     = $profileModel->allForUser($user['id']);

        $stats = null;
        $error = null;

        $activeServerId = (int) ($_GET['server_id'] ?? $_SESSION['active_server_id'] ?? 0);

        if ($activeServerId) {
            try {
                $_SESSION['active_server_id'] = $activeServerId;
                $this->pg = new PgService($profileModel);
                $this->pg->connect($activeServerId);

                $stats = [
                    'version'     => $this->pg->serverVersion(),
                    'uptime'      => $this->pg->uptime(),
                    'connections' => $this->pg->activeConnections(),
                    'db_count'    => count($this->pg->listDatabases()),
                    'total_size'  => $this->pg->totalDatabaseSize(),
                    'cache_hit'   => $this->pg->cacheHitRatio(),
                    'active_queries' => $this->pg->activeQueries(),
                ];
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $this->render('dashboard/index.html.twig', [
            'active'          => 'dashboard',
            'profiles'        => $profiles,
            'active_server'   => $activeServerId,
            'stats'           => $stats,
            'error'           => $error,
        ]);
    }
}
