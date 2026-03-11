<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class DataController extends PgBaseController
{
    public function index(string $db, string $schema, string $table): void
    {
        $this->resolvePg($db);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = $this->pg->tableData($schema, $table, $page);

        $this->render('data/index.html.twig', [
            'active'    => 'databases',
            'db'        => $db,
            'schema'    => $schema,
            'table'     => $table,
            'data'      => $data,
            'is_locked' => $this->isDatabaseLocked($db),
            'server_id' => $this->serverId,
        ]);
    }

    public function insert(string $db, string $schema, string $table): void
    {
        $this->resolvePg($db);
        $columns = $this->pg->tableColumns($schema, $table);
        $values  = [];
        $cols    = [];

        foreach ($columns as $col) {
            $key = $col['name'];
            if (!isset($_POST[$key])) continue;
            $cols[]   = '"' . $key . '"';
            $values[] = $_POST[$key];
        }

        if ($cols) {
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO "' . $schema . '"."' . $table . '" (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')';
            $this->pg->getConnection()->prepare($sql)->execute($values);
        }

        $_SESSION['flash_success'] = 'Row inserted.';
        $this->redirect("/databases/{$db}/schemas/{$schema}/tables/{$table}/data?server_id=" . $this->serverId);
    }

    public function update(string $db, string $schema, string $table, string $rowid): void
    {
        $this->resolvePg($db);
        // Generic update using ctid (physical row id) — simple approach
        $cols   = [];
        $vals   = [];
        foreach ($_POST as $k => $v) {
            if (in_array($k, ['server_id', 'csrf_token'], true)) continue;
            $cols[] = '"' . $k . '" = ?';
            $vals[] = $v;
        }
        $vals[] = $rowid;
        if ($cols) {
            $sql = 'UPDATE "' . $schema . '"."' . $table . '" SET ' . implode(', ', $cols) . ' WHERE ctid = ?::tid';
            $this->pg->getConnection()->prepare($sql)->execute($vals);
        }
        $_SESSION['flash_success'] = 'Row updated.';
        $this->redirect("/databases/{$db}/schemas/{$schema}/tables/{$table}/data?server_id=" . $this->serverId);
    }

    public function delete(string $db, string $schema, string $table, string $rowid): void
    {
        $this->resolvePg($db);
        try {
            $this->requireDatabaseConfirmation($db, 'DELETE');
            $this->pg->getConnection()->prepare('DELETE FROM "' . $schema . '"."' . $table . '" WHERE ctid = ?::tid')->execute([$rowid]);
            $_SESSION['flash_success'] = 'Row deleted.';
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect("/databases/{$db}/schemas/{$schema}/tables/{$table}/data?server_id=" . $this->serverId);
    }

    public function export(string $db, string $schema, string $table): void
    {
        $this->resolvePg($db);
        $format = $_GET['format'] ?? 'csv';
        $rows   = $this->pg->getConnection()->query('SELECT * FROM "' . $schema . '"."' . $table . '"')->fetchAll();

        if ($format === 'json') {
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"{$table}.json\"");
            echo json_encode($rows, JSON_PRETTY_PRINT);
        } else {
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$table}.csv\"");
            if ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) fputcsv($out, $row);
                fclose($out);
            }
        }
    }
}
