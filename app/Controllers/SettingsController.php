<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use PostgreManager\Models\Setting;
use PostgreManager\Models\User;

class SettingsController extends BaseController
{
    private Setting $setting;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->setting   = new Setting($this->db());
        $this->userModel = new User($this->db());
    }

    public function index(): void
    {
        $this->requireRole('super_admin', 'admin');
        $this->render('settings/index.html.twig', [
            'active'   => 'settings',
            'theme'    => $this->setting->get('ui_theme', 'dark'),
            'timeout'  => $this->setting->get('query_timeout', '30'),
            'row_limit'=> $this->setting->get('row_limit', '50'),
        ]);
    }

    public function update(): void
    {
        $this->requireRole('super_admin', 'admin');
        $this->setting->set('ui_theme',      $_POST['ui_theme']    ?? 'dark');
        $this->setting->set('query_timeout', $_POST['query_timeout'] ?? '30');
        $this->setting->set('row_limit',     $_POST['row_limit']   ?? '50');
        $_SESSION['flash_success'] = 'Settings saved.';
        $this->redirect('/settings');
    }

    public function profile(): void
    {
        $this->requireAuth();
        $this->render('settings/profile.html.twig', [
            'active' => 'settings',
            'user'   => $this->currentUser(),
        ]);
    }

    public function updateProfile(): void
    {
        $this->requireAuth();
        $current = $this->currentUser();

        $this->userModel->update($current['id'], [
            'name'  => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
        ]);

        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 8) {
                $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
                $this->redirect('/profile');
                return;
            }
            $this->userModel->changePassword($current['id'], $_POST['password']);
        }

        // Refresh session
        $updated = $this->userModel->findById($current['id']);
        $_SESSION['user'] = [
            'id'    => $updated['id'],
            'name'  => $updated['name'],
            'email' => $updated['email'],
            'role'  => $updated['role'],
        ];

        $_SESSION['flash_success'] = 'Profile updated.';
        $this->redirect('/profile');
    }
}
