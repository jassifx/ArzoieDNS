<?php
declare(strict_types=1);
namespace Arzoie\Controllers;

use Arzoie\Core\{Audit, Database, Router, View};

final class AuthController
{
    public function form(): void { View::render('login', ['title' => 'Sign in']); }

    public function login(): void
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ok = Database::transaction(function () use ($email, $password): bool {
            $user = Database::run('SELECT * FROM users WHERE email = ? FOR UPDATE', [$email])->fetch();
            $dummy = '$2y$12$Yg6oT2rbgH8iyNmxdt59bO73LllwfwX.Ue3ln0Tp5tfZ8I9G1ifj2';
            $valid = password_verify($password, $user['password_hash'] ?? $dummy);
            if (!$user) { return false; }
            if ($user['locked_until'] !== null && strtotime($user['locked_until'] . ' UTC') > time()) { return false; }
            if (!$valid) {
                $attempts = (int) $user['failed_attempts'] + 1;
                Database::run('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?', [$attempts, $attempts >= 5 ? gmdate('Y-m-d H:i:s', time() + 900) : null, $user['id']]);
                Audit::log('auth.failed', $email);
                return false;
            }
            Database::run('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?', [$user['id']]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id']; $_SESSION['user_email'] = $email;
            $_SESSION['_token'] = bin2hex(random_bytes(32));
            Audit::log('auth.login', $email);
            return true;
        });
        Router::redirect($ok ? '/' : '/login', $ok ? '' : 'Sign-in failed or account temporarily locked.');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_token'] = bin2hex(random_bytes(32));
        Router::redirect('/login');
    }
}
