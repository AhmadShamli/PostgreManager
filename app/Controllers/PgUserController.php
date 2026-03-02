<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;

class PgUserController extends PgBaseController
{
    public function index(): void
    {
        $this->resolvePg();
        $this->render('pg-users/index.html.twig', [
            'active'    => 'pg-users',
            'roles'     => $this->pg->listRoles(),
            'server_id' => $this->serverId,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->render('pg-users/form.html.twig', [
            'active'    => 'pg-users',
            'role'      => null,
            'server_id' => $_SESSION['active_server_id'] ?? 0,
        ]);
    }

    public function store(): void
    {
        $this->resolvePg();
        $name = trim($_POST['name'] ?? '');
        try {
            $this->pg->createRole($name, [
                'login'       => !empty($_POST['login']),
                'superuser'   => !empty($_POST['superuser']),
                'createdb'    => !empty($_POST['createdb']),
                'createrole'  => !empty($_POST['createrole']),
                'replication' => !empty($_POST['replication']),
                'password'    => $_POST['password'] ?? '',
            ]);
            $_SESSION['flash_success'] = "Role \"{$name}\" created.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/pg-users?server_id=' . $this->serverId);
    }

    public function edit(string $name): void
    {
        $this->resolvePg();
        $roles = $this->pg->listRoles();
        $role  = array_values(array_filter($roles, fn($r) => $r['name'] === $name))[0] ?? null;
        if (!$role) { Flight::halt(404); return; }

        $this->render('pg-users/form.html.twig', [
            'active'    => 'pg-users',
            'role'      => $role,
            'server_id' => $this->serverId,
        ]);
    }

    public function update(string $name): void
    {
        $this->resolvePg();
        try {
            if (!empty($_POST['password'])) {
                $this->pg->changeRolePassword($name, $_POST['password']);
            }
            // Rebuild role attributes via ALTER ROLE
            $attrs = [];
            $map   = ['superuser' => 'SUPERUSER', 'createdb' => 'CREATEDB', 'createrole' => 'CREATEROLE', 'replication' => 'REPLICATION', 'login' => 'LOGIN'];
            foreach ($map as $post => $sql) {
                $attrs[] = (isset($_POST[$post]) ? '' : 'NO') . $sql;
            }
            $this->pg->getConnection()->exec('ALTER ROLE "' . $name . '" WITH ' . implode(' ', $attrs));
            $_SESSION['flash_success'] = "Role \"{$name}\" updated.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/pg-users?server_id=' . $this->serverId);
    }

    public function drop(string $name): void
    {
        $this->resolvePg();
        try {
            $this->pg->dropRole($name);
            $_SESSION['flash_success'] = "Role \"{$name}\" dropped.";
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect('/pg-users?server_id=' . $this->serverId);
    }
}
