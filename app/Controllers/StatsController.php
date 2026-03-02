<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class StatsController extends PgBaseController
{
    public function index(): void
    {
        $this->resolvePg();
        $p = $this->activeProfile;
        $this->render('stats/index.html.twig', [
            'active'         => 'stats',
            'server_id'      => $this->serverId,
            'server_label'   => $p['label'] ?? 'Unknown',
            'server_host'    => ($p['host'] ?? '') . ':' . ($p['port'] ?? 5432),
            'server_db'      => $p['db_name'] ?? '',
            'cache_hit'      => $this->pg->cacheHitRatio(),
            'active_queries' => $this->pg->activeQueries(),
            'table_sizes'    => $this->pg->tableSizeStats(),
            'index_usage'    => $this->pg->indexUsageStats(),
            'locks'          => $this->pg->locks(),
        ]);
    }
}
