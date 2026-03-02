<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class SchemaController extends PgBaseController
{
    public function index(string $db): void
    {
        $this->resolvePg($db);
        $this->render('schema/index.html.twig', [
            'active'    => 'databases',
            'db'        => $db,
            'schemas'   => $this->pg->listSchemas(),
            'server_id' => $this->serverId,
        ]);
    }
}
