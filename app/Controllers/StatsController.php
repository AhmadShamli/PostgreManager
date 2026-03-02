<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class StatsController extends PgBaseController
{
    public function index(): void
    {
        $selectedDb = $_GET['db'] ?? '';
        $this->resolvePg($selectedDb);
        $p            = $this->activeProfile;
        $profileModel = new \PostgreManager\Models\ServerProfile($this->db());
        $user         = $this->currentUser();
        $databases    = $this->pg->listDatabases();
        // If no db specified, use the profile default
        if (!$selectedDb) {
            $selectedDb = $p['db_name'] ?? '';
        }
        $this->render('stats/index.html.twig', [
            'active'         => 'stats',
            'server_id'      => $this->serverId,
            'server_label'   => $p['label'] ?? 'Unknown',
            'server_db'      => $selectedDb,
            'all_servers'    => $profileModel->allForUser($user['id']),
            'databases'      => $databases,
            'selected_db'    => $selectedDb,
            'cache_hit'      => $this->pg->cacheHitRatio(),
            'active_queries' => $this->pg->activeQueries(),
            'table_sizes'    => $this->pg->tableSizeStats(),
            'index_usage'    => $this->pg->indexUsageStats(),
            'locks'          => $this->pg->locks(),
        ]);
    }
}
