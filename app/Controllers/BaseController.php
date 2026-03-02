<?php

declare(strict_types=1);

namespace PostgreManager\Controllers;

use Flight;
use Twig\Environment;


abstract class BaseController
{
    protected Environment $twig;
    protected array $config;

    public function __construct()
    {
        $this->twig   = Flight::get('twig');
        $this->config = Flight::get('config');
    }

    protected function render(string $template, array $data = []): void
    {
        echo $this->twig->render($template, $data);
    }

    protected function redirect(string $url): void
    {
        Flight::redirect($url);
    }

    protected function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    protected function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    protected function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
            exit;
        }

        // Session timeout enforcement
        $timeoutMins = (int) \Flight::get('session_timeout');
        if ($timeoutMins > 0 && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeoutMins * 60) {
                session_destroy();
                header('Location: /login?reason=timeout');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        if (!in_array($user['role'] ?? '', $roles, true)) {
            Flight::halt(403, 'Forbidden');
            exit;
        }
    }

    protected function json(mixed $data, int $status = 200): void
    {
        Flight::json($data, $status);
    }

    protected function db(): \PDO
    {
        return Flight::db();
    }
}
