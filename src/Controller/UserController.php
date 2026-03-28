<?php

namespace DouglasGreen\FreshWiki\Controller;

use PDO;
use Twig\Environment;

class UserController
{
    private PDO $pdo;
    private Environment $twig;

    public function __construct(PDO $pdo, Environment $twig)
    {
        $this->pdo = $pdo;
        $this->twig = $twig;
    }

    public function showLogin(array $errors = []): string
    {
        return $this->twig->render('login.html.twig', ['errors' => $errors]);
    }

    public function showRegister(array $errors = []): string
    {
        return $this->twig->render('register.html.twig', ['errors' => $errors]);
    }

    public function handleLogin(string $email, string $password): array
    {
        $errors = [];

        if (empty($email)) {
            $errors[] = 'Email is required.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM user WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'errors' => ['Invalid email or password.']];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Invalid email or password.']];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'errors' => ['Your account is inactive.']];
        }

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        return ['success' => true];
    }

    public function handleRegister(string $username, string $email, string $password): array
    {
        $errors = [];

        if (empty($username)) {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) > 63) {
            $errors[] = 'Username must be 63 characters or less.';
        }

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } elseif (strlen($email) > 255) {
            $errors[] = 'Email must be 255 characters or less.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if email already exists
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Email already registered.']];
        }

        // Check if username already exists
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Username already taken.']];
        }

        // Determine role: first user is admin
        $role = $this->isFirstUser() ? 'admin' : 'user';

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $this->pdo->prepare(
            'INSERT INTO user (username, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, $role]);

        return ['success' => true, 'is_first_user' => $role === 'admin'];
    }

    private function isFirstUser(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM user');
        return $stmt->fetchColumn() == 0;
    }
}
