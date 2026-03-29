<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DouglasGreen\FreshWiki\Controller\NodeImportController;

// Check arguments
if ($argc < 2) {
    echo "Usage: php bin/import_node.php <path>\n";
    echo "Example: php bin/import_node.php Path/To/Node\n";
    exit(1);
}

$path = $argv[1];

// Load configuration
$config = parse_ini_file(__DIR__ . '/../config.ini', true);

if ($config === false) {
    echo "Error reading configuration file.\n";
    exit(1);
}

$db = $config['db'];

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8mb4",
        $db['username'],
        $db['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Import the node
$controller = new NodeImportController($pdo);
$result = $controller->importNode($path);

if ($result['success']) {
    echo $result['message'] . "\n";
    exit(0);
} else {
    foreach ($result['errors'] as $error) {
        echo "Error: $error\n";
    }
    exit(1);
}
