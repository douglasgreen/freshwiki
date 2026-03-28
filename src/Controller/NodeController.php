<?php

namespace DouglasGreen\FreshWiki\Controller;

use PDO;
use Twig\Environment;

class NodeController
{
    private PDO $pdo;
    private Environment $twig;

    public function __construct(PDO $pdo, Environment $twig)
    {
        $this->pdo = $pdo;
        $this->twig = $twig;
    }

    public function showNodes(array $errors = []): string
    {
        $nodes = $this->getNodeTree();
        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

        return $this->twig->render('node.html.twig', [
            'nodes' => $nodes,
            'is_admin' => $isAdmin,
            'errors' => $errors
        ]);
    }

    public function handleCreate(string $name, ?int $parentId): array
    {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return ['success' => false, 'errors' => ['Unauthorized access.']];
        }

        $errors = [];

        if (empty($name)) {
            $errors[] = 'Node name is required.';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Node name must be 255 characters or less.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check if parent node has reached maximum children limit
        if ($parentId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM node WHERE parent_id = ?');
            $stmt->execute([$parentId]);
            $childCount = (int)$stmt->fetchColumn();
            if ($childCount >= 100) {
                return ['success' => false, 'errors' => ['Parent node has reached the maximum limit of 100 children.']];
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO node (parent_id, name, created_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $parentId ?: null,
            $name,
            $_SESSION['user_id'] ?? null
        ]);

        return ['success' => true];
    }

    public function handleDelete(int $nodeId): array
    {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return ['success' => false, 'errors' => ['Unauthorized access.']];
        }

        // Check if node has children
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM node WHERE parent_id = ?');
        $stmt->execute([$nodeId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Cannot delete node with children.']];
        }

        // Check if node has pages
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM page WHERE node_id = ?');
        $stmt->execute([$nodeId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Cannot delete node with pages.']];
        }

        $stmt = $this->pdo->prepare('DELETE FROM node WHERE node_id = ?');
        $stmt->execute([$nodeId]);

        return ['success' => true];
    }

    public function getNodeAndChildren(?int $nodeId): array
    {
        $currentNode = null;
        $childNodes = [];
        $pages = [];

        if ($nodeId !== null) {
            // Get current node
            $stmt = $this->pdo->prepare('SELECT * FROM node WHERE node_id = ?');
            $stmt->execute([$nodeId]);
            $currentNode = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentNode) {
                return ['current_node' => null, 'child_nodes' => [], 'pages' => []];
            }

            // Get child nodes
            $stmt = $this->pdo->prepare('SELECT * FROM node WHERE parent_id = ? ORDER BY name');
            $stmt->execute([$nodeId]);
            $childNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get pages in this node
            $stmt = $this->pdo->prepare('SELECT page_id, title, summary FROM page WHERE node_id = ? AND is_archived = FALSE ORDER BY updated_at DESC');
            $stmt->execute([$nodeId]);
            $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get root nodes (no parent)
            $stmt = $this->pdo->query('SELECT * FROM node WHERE parent_id IS NULL ORDER BY name');
            $childNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'current_node' => $currentNode,
            'child_nodes' => $childNodes,
            'pages' => $pages
        ];
    }

    private function getNodeTree(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM node ORDER BY name');
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->buildTree($nodes, null);
    }

    private function buildTree(array $nodes, ?int $parentId): array
    {
        $tree = [];
        foreach ($nodes as $node) {
            if ($node['parent_id'] == $parentId) {
                $node['children'] = $this->buildTree($nodes, $node['node_id']);
                $tree[] = $node;
            }
        }
        return $tree;
    }
}
