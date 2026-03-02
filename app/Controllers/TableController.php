<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class TableController extends PgBaseController
{
    public function index(string $db, string $schema): void
    {
        $this->resolvePg($db);
        $this->render('tables/index.html.twig', [
            'active'    => 'databases',
            'db'        => $db,
            'schema'    => $schema,
            'tables'    => $this->pg->listTables($schema),
            'server_id' => $this->serverId,
        ]);
    }

    public function show(string $db, string $schema, string $table): void
    {
        $this->resolvePg($db);
        $this->render('tables/show.html.twig', [
            'active'    => 'databases',
            'db'        => $db,
            'schema'    => $schema,
            'table'     => $table,
            'columns'   => $this->pg->tableColumns($schema, $table),
            'indexes'   => $this->pg->tableIndexes($schema, $table),
            'server_id' => $this->serverId,
        ]);
    }

    public function drop(string $db, string $schema, string $table): void
    {
        $this->resolvePg($db);
        $cascade = isset($_POST['cascade']);
        try {
            $this->pg->dropTable($schema, $table, $cascade);
            $_SESSION['flash_success'] = "Table \"{$schema}.{$table}\" dropped.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect("/databases/{$db}/schemas/{$schema}/tables?server_id=" . $this->serverId);
    }
}
