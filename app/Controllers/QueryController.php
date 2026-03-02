<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

class QueryController extends PgBaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->render('query/index.html.twig', [
            'active'    => 'query',
            'history'   => $this->getHistory(),
            'server_id' => $_SESSION['active_server_id'] ?? 0,
        ]);
    }

    public function run(): void
    {
        $this->resolvePg($_POST['db'] ?? '');
        $sql = trim($_POST['sql'] ?? '');

        if (empty($sql)) {
            $this->json(['error' => 'No SQL provided.'], 400);
            return;
        }

        try {
            $result = $this->pg->runQuery($sql);
            $this->saveToHistory($sql);
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function history(): void
    {
        $this->requireAuth();
        $this->json($this->getHistory());
    }

    public function save(): void
    {
        $this->requireAuth();
        $name = trim($_POST['name'] ?? '');
        $sql  = trim($_POST['sql'] ?? '');

        if (!$name || !$sql) {
            $this->json(['error' => 'Name and SQL required.'], 400);
            return;
        }

        $saved = $_SESSION['saved_queries'] ?? [];
        $saved[$name] = ['name' => $name, 'sql' => $sql, 'saved_at' => date('Y-m-d H:i:s')];
        $_SESSION['saved_queries'] = $saved;

        $this->json(['success' => true]);
    }

    private function saveToHistory(string $sql): void
    {
        $history   = $_SESSION['query_history'] ?? [];
        $history[] = ['sql' => $sql, 'run_at' => date('Y-m-d H:i:s')];
        // Keep last 50
        $_SESSION['query_history'] = array_slice($history, -50);
    }

    private function getHistory(): array
    {
        return array_reverse($_SESSION['query_history'] ?? []);
    }
}
