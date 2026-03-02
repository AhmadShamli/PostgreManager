<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use PostgreManager\Models\User;
use PostgreManager\Models\Setting;
use PostgreManager\Services\AuthService;

class SetupController extends BaseController
{
    private User $userModel;
    private Setting $setting;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User($this->db());
        $this->setting   = new Setting($this->db());
    }

    /** Required PHP extensions: [name => critical] */
    private const REQUIRED_EXT = [
        'pdo'        => true,
        'pdo_sqlite' => true,
        'pdo_pgsql'  => true,
        'openssl'    => true,
        'mbstring'   => true,
        'json'       => true,
        'session'    => false,
        'fileinfo'   => false,
    ];

    private function checkExtensions(): array
    {
        $results = [];
        foreach (self::REQUIRED_EXT as $ext => $critical) {
            $results[] = [
                'name'     => $ext,
                'loaded'   => extension_loaded($ext),
                'critical' => $critical,
            ];
        }
        return $results;
    }

    public function index(): void
    {
        if ($this->setting->isSetupComplete()) {
            $this->redirect('/login');
            return;
        }

        $extensions   = $this->checkExtensions();
        $missingCritical = array_filter($extensions, fn($e) => $e['critical'] && !$e['loaded']);

        $this->render('setup/index.html.twig', [
            'csrf_token'      => AuthService::generateCsrf(),
            'extensions'      => $extensions,
            'has_missing'     => !empty($missingCritical),
        ]);
    }

    public function store(): void
    {
        if ($this->setting->isSetupComplete()) {
            $this->redirect('/login');
            return;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!AuthService::verifyCsrf($token)) {
            $this->render('setup/index.html.twig', [
                'error'      => 'Invalid request. Please try again.',
                'csrf_token' => AuthService::generateCsrf(),
                'old'        => $_POST,
            ]);
            return;
        }

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        $errors = [];
        if (!$name) $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if ($errors) {
            $this->render('setup/index.html.twig', [
                'errors'     => $errors,
                'csrf_token' => AuthService::generateCsrf(),
                'old'        => $_POST,
            ]);
            return;
        }

        $this->userModel->create($name, $email, $password, 'super_admin');
        $this->setting->markSetupComplete();

        $_SESSION['flash_success'] = 'Setup complete! Please sign in.';
        $this->redirect('/login');
    }
}
