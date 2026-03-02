<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use PostgreManager\Models\User;

class AppUserController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User($this->db());
    }

    public function index(): void
    {
        $this->requireRole('super_admin', 'admin');
        $this->render('users/index.html.twig', [
            'active' => 'app-users',
            'users'  => $this->userModel->all(),
        ]);
    }

    public function create(): void
    {
        $this->requireRole('super_admin', 'admin');
        $this->render('users/form.html.twig', [
            'active' => 'app-users',
            'user'   => null,
        ]);
    }

    public function store(): void
    {
        $this->requireRole('super_admin', 'admin');

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'viewer';

        $errors = $this->validate($name, $email, $password);
        if ($errors) {
            $this->render('users/form.html.twig', [
                'active' => 'app-users',
                'errors' => $errors,
                'old'    => $_POST,
                'user'   => null,
            ]);
            return;
        }

        $this->userModel->create($name, $email, $password, $role);
        $_SESSION['flash_success'] = "User {$name} created successfully.";
        $this->redirect('/app-users');
    }

    public function edit(string $id): void
    {
        $this->requireRole('super_admin', 'admin');
        $user = $this->userModel->findById((int) $id);
        if (!$user) { Flight::halt(404, 'User not found'); return; }

        $this->render('users/form.html.twig', [
            'active' => 'app-users',
            'user'   => $user,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireRole('super_admin', 'admin');
        $user = $this->userModel->findById((int) $id);
        if (!$user) { Flight::halt(404); return; }

        $this->userModel->update((int) $id, [
            'name'      => trim($_POST['name'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'role'      => $_POST['role'] ?? 'viewer',
            'is_active' => isset($_POST['is_active']) ? 'true' : 'false',
        ]);

        if (!empty($_POST['password'])) {
            $this->userModel->changePassword((int) $id, $_POST['password']);
        }

        $_SESSION['flash_success'] = 'User updated.';
        $this->redirect('/app-users');
    }

    public function destroy(string $id): void
    {
        $this->requireRole('super_admin');
        $current = $this->currentUser();
        if ((int) $id === (int) $current['id']) {
            $_SESSION['flash_error'] = 'Cannot delete your own account.';
            $this->redirect('/app-users');
            return;
        }
        $this->userModel->delete((int) $id);
        $_SESSION['flash_success'] = 'User deleted.';
        $this->redirect('/app-users');
    }

    private function validate(string $name, string $email, string $password): array
    {
        $errors = [];
        if (empty($name))     $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        return $errors;
    }
}
