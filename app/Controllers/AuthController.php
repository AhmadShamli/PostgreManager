<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use PostgreManager\Models\User;
use PostgreManager\Models\Setting;
use PostgreManager\Services\AuthService;

class AuthController extends BaseController
{
    private AuthService $auth;
    private Setting $setting;

    public function __construct()
    {
        parent::__construct();
        $this->auth    = new AuthService(new User($this->db()));
        $this->setting = new Setting($this->db());
    }

    public function home(): void
    {
        if (!$this->setting->isSetupComplete()) {
            $this->redirect('/setup');
            return;
        }
        $this->isLoggedIn() ? $this->redirect('/dashboard') : $this->redirect('/login');
    }

    public function loginForm(): void
    {
        if (!$this->setting->isSetupComplete()) {
            $this->redirect('/setup');
            return;
        }
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
            return;
        }
        $this->render('auth/login.html.twig', [
            'csrf_token' => AuthService::generateCsrf(),
        ]);
    }

    public function login(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!AuthService::verifyCsrf($token)) {
            $this->render('auth/login.html.twig', [
                'error'      => 'Invalid request. Please try again.',
                'csrf_token' => AuthService::generateCsrf(),
            ]);
            return;
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $this->auth->attempt($email, $password);

        if (!$user) {
            $this->render('auth/login.html.twig', [
                'error'      => 'Invalid email or password.',
                'old_email'  => $email,
                'csrf_token' => AuthService::generateCsrf(),
            ]);
            return;
        }

        $this->auth->login($user);
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect('/login');
    }
}
