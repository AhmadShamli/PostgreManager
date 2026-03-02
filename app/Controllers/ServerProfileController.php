<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;
use PostgreManager\Models\ServerProfile;
use PostgreManager\Services\PgService;

class ServerProfileController extends BaseController
{
    private ServerProfile $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ServerProfile($this->db());
    }

    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->render('servers/index.html.twig', [
            'active'   => 'servers',
            'profiles' => $this->model->allForUser($user['id']),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->render('servers/form.html.twig', [
            'active'  => 'servers',
            'profile' => null,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        $this->model->create($user['id'], $_POST);
        $_SESSION['flash_success'] = 'Server profile saved.';
        $this->redirect('/servers');
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $profile = $this->model->find((int) $id);
        if (!$profile) { Flight::halt(404); return; }
        $this->render('servers/form.html.twig', [
            'active'  => 'servers',
            'profile' => $profile,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireAuth();
        $this->model->update((int) $id, $_POST);
        $_SESSION['flash_success'] = 'Server profile updated.';
        $this->redirect('/servers');
    }

    public function destroy(string $id): void
    {
        $this->requireAuth();
        $this->model->delete((int) $id);
        $_SESSION['flash_success'] = 'Server profile deleted.';
        $this->redirect('/servers');
    }

    public function test(): void
    {
        $this->requireAuth();
        $host     = $_POST['host']        ?? '';
        $port     = (int) ($_POST['port'] ?? 5432);
        $dbName   = $_POST['db_name']     ?? 'postgres';
        $pgUser   = $_POST['pg_username'] ?? '';
        $pgPass   = $_POST['pg_password'] ?? '';

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
            new \PDO($dsn, $pgUser, $pgPass, [\PDO::ATTR_TIMEOUT => 5]);
            $this->json(['success' => true, 'message' => 'Connection successful!']);
        } catch (\PDOException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
