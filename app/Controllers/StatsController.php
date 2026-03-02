<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class StatsController extends PgBaseController
{
    public function index(): void
    {
        $this->resolvePg();
        $this->render('stats/index.html.twig', [
            'active'       => 'stats',
            'server_id'    => $this->serverId,
            'cache_hit'    => $this->pg->cacheHitRatio(),
            'active_queries' => $this->pg->activeQueries(),
            'table_sizes'  => $this->pg->tableSizeStats(),
            'index_usage'  => $this->pg->indexUsageStats(),
            'locks'        => $this->pg->locks(),
        ]);
    }
}
