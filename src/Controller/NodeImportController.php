<?php

namespace DouglasGreen\FreshWiki\Controller;

use PDO;

class NodeImportController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Import a node by path.
     * 
     * @param string $path The node path (e.g., "Path/To/Node")
     * @return array Result with 'success' bool and 'message' or 'errors'
     */
    public function importNode(string $path): array
    {
        // Split path into parts
        $parts = explode('/', $path);
        
        if (count($parts) < 1 || empty($path)) {
            return ['success' => false, 'errors' => ['Invalid path format.']];
        }
        
        $leafNodeName = array_pop($parts);
        
        if (empty($leafNodeName)) {
            return ['success' => false, 'errors' => ['Node name cannot be empty.']];
        }
        
        if (strlen($leafNodeName) > 255) {
            return ['success' => false, 'errors' => ['Node name must be 255 characters or less.']];
        }
        
        // Find parent node by traversing the path
        $parentId = null;
        foreach ($parts as $nodeName) {
            if (empty($nodeName)) {
                return ['success' => false, 'errors' => ['Path contains empty segment.']];
            }
            
            if ($parentId === null) {
                $stmt = $this->pdo->prepare(
                    'SELECT node_id FROM node WHERE name = ? AND parent_id IS NULL'
                );
                $stmt->execute([$nodeName]);
            } else {
                $stmt = $this->pdo->prepare(
                    'SELECT node_id FROM node WHERE name = ? AND parent_id = ?'
                );
                $stmt->execute([$nodeName, $parentId]);
            }
            
            $node = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$node) {
                return [
                    'success' => false, 
                    'errors' => ["Parent node '{$nodeName}' not found."]
                ];
            }
            
            $parentId = (int)$node['node_id'];
        }
        
        // Check if leaf node already exists
        if ($parentId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT node_id FROM node WHERE name = ? AND parent_id IS NULL'
            );
            $stmt->execute([$leafNodeName]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT node_id FROM node WHERE name = ? AND parent_id = ?'
            );
            $stmt->execute([$leafNodeName, $parentId]);
        }
        
        if ($stmt->fetch()) {
            return [
                'success' => false, 
                'errors' => ["Node '{$leafNodeName}' already exists at the specified path."]
            ];
        }
        
        // Check maximum children limit
        if ($parentId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM node WHERE parent_id = ?');
            $stmt->execute([$parentId]);
            $childCount = (int)$stmt->fetchColumn();
            if ($childCount >= 100) {
                return ['success' => false, 'errors' => ['Parent node has reached the maximum limit of 100 children.']];
            }
        }
        
        // Create the new node
        $stmt = $this->pdo->prepare(
            'INSERT INTO node (parent_id, name, created_by) VALUES (?, ?, NULL)'
        );
        $stmt->execute([$parentId, $leafNodeName]);
        
        return [
            'success' => true, 
            'message' => "Node '{$leafNodeName}' created successfully."
        ];
    }
}
