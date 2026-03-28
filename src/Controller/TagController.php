<?php

namespace DouglasGreen\FreshWiki\Controller;

use PDO;
use Twig\Environment;

class TagController
{
    private PDO $pdo;
    private Environment $twig;

    public function __construct(PDO $pdo, Environment $twig)
    {
        $this->pdo = $pdo;
        $this->twig = $twig;
    }

    public function showTags(array $errors = []): string
    {
        $tags = $this->getAllTags();
        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

        return $this->twig->render('tag.html.twig', [
            'tags' => $tags,
            'is_admin' => $isAdmin,
            'errors' => $errors
        ]);
    }

    public function handleCreate(string $name): array
    {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return ['success' => false, 'errors' => ['Unauthorized access.']];
        }

        $errors = [];

        // Normalize name: lowercase, hyphens only
        $name = strtolower(trim($name));

        if (empty($name)) {
            $errors[] = 'Tag name is required.';
        } elseif (strlen($name) > 63) {
            $errors[] = 'Tag name must be 63 characters or less.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $name)) {
            $errors[] = 'Tag name must contain only lowercase letters, numbers, and hyphens.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if tag already exists
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tag WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Tag already exists.']];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tag (name, created_by) VALUES (?, ?)'
        );
        $stmt->execute([$name, $_SESSION['user_id'] ?? null]);

        return ['success' => true];
    }

    public function handleDelete(int $tagId): array
    {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return ['success' => false, 'errors' => ['Unauthorized access.']];
        }

        $stmt = $this->pdo->prepare('DELETE FROM tag WHERE tag_id = ?');
        $stmt->execute([$tagId]);

        return ['success' => true];
    }

    private function getAllTags(): array
    {
        $stmt = $this->pdo->query('SELECT t.*, COUNT(pt.page_id) as page_count FROM tag t LEFT JOIN page_tag pt ON t.tag_id = pt.tag_id GROUP BY t.tag_id ORDER BY t.name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
