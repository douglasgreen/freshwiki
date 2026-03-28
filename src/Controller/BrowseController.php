<?php

namespace DouglasGreen\FreshWiki\Controller;

use PDO;
use Twig\Environment;

class BrowseController
{
    private PDO $pdo;
    private Environment $twig;

    public function __construct(PDO $pdo, Environment $twig)
    {
        $this->pdo = $pdo;
        $this->twig = $twig;
    }

    public function showBrowse(?int $nodeId): string
    {
        $browseData = $this->getNodeAndChildren($nodeId);
        return $this->twig->render('browse.html.twig', [
            'username' => $_SESSION['username'] ?? 'Guest',
            'browse_data' => $browseData
        ]);
    }

    public function getNodeAndChildren(?int $nodeId): array
    {
        $currentNode = null;
        $childNodes = [];
        $pages = [];
        $files = [];

        if ($nodeId !== null) {
            // Get current node
            $stmt = $this->pdo->prepare('SELECT * FROM node WHERE node_id = ?');
            $stmt->execute([$nodeId]);
            $currentNode = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentNode) {
                return ['current_node' => null, 'child_nodes' => [], 'pages' => [], 'files' => []];
            }

            // Get child nodes
            $stmt = $this->pdo->prepare('SELECT * FROM node WHERE parent_id = ? ORDER BY name');
            $stmt->execute([$nodeId]);
            $childNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get pages in this node
            $stmt = $this->pdo->prepare('SELECT page_id, title, summary FROM page WHERE node_id = ? AND is_archived = FALSE ORDER BY updated_at DESC');
            $stmt->execute([$nodeId]);
            $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get files in this node
            $stmt = $this->pdo->prepare('SELECT file_id, filename, content_type, file_size_bytes FROM file WHERE node_id = ? AND is_archived = FALSE ORDER BY updated_at DESC');
            $stmt->execute([$nodeId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get root nodes (no parent)
            $stmt = $this->pdo->query('SELECT * FROM node WHERE parent_id IS NULL ORDER BY name');
            $childNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'current_node' => $currentNode,
            'child_nodes' => $childNodes,
            'pages' => $pages,
            'files' => $files
        ];
    }
}
