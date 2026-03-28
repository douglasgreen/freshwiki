<?php

require_once __DIR__ . '/vendor/autoload.php';

use DouglasGreen\FreshWiki\Controller\NodeController;
use DouglasGreen\FreshWiki\Controller\TagController;
use DouglasGreen\FreshWiki\Controller\UserController;

// Load configuration
$config = parse_ini_file('config.ini', true);

if ($config === false) {
    die("Error reading configuration file.");
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
    die("Connection failed: " . $e->getMessage());
}

// Initialize Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/resources/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
]);

// Initialize session
session_start();

// Create controllers
$controller = new UserController($pdo, $twig);
$nodeController = new NodeController($pdo, $twig);
$tagController = new TagController($pdo, $twig);

// Route requests
$action = $_GET['action'] ?? 'login';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'node_create') {
        $result = $nodeController->handleCreate(
            $_POST['name'] ?? '',
            isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null
        );
        if ($result['success']) {
            header('Location: index.php?action=node');
            exit;
        }
        echo $nodeController->showNodes($result['errors'] ?? []);
        exit;
    }

    if ($action === 'tag_create') {
        $result = $tagController->handleCreate(
            $_POST['name'] ?? ''
        );
        if ($result['success']) {
            header('Location: index.php?action=tag');
            exit;
        }
        echo $tagController->showTags($result['errors'] ?? []);
        exit;
    }

    if ($action === 'node_delete') {
        $result = $nodeController->handleDelete((int)($_POST['node_id'] ?? 0));
        if ($result['success']) {
            header('Location: index.php?action=node');
            exit;
        }
        echo $nodeController->showNodes($result['errors'] ?? []);
        exit;
    }

    if ($action === 'tag_delete') {
        $result = $tagController->handleDelete((int)($_POST['tag_id'] ?? 0));
        if ($result['success']) {
            header('Location: index.php?action=tag');
            exit;
        }
        echo $tagController->showTags($result['errors'] ?? []);
        exit;
    }

    if ($action === 'login') {
        $result = $controller->handleLogin(
            $_POST['username'] ?? '',
            $_POST['password'] ?? ''
        );
        if ($result['success']) {
            header('Location: index.php');
            exit;
        }
        echo $controller->showLogin($result['errors'] ?? []);
        exit;
    }
    
    if ($action === 'register') {
        $result = $controller->handleRegister(
            $_POST['username'] ?? '',
            $_POST['email'] ?? '',
            $_POST['password'] ?? ''
        );
        if ($result['success']) {
            if ($result['is_first_user'] ?? false) {
                // First user is admin - redirect to login with message
                echo $twig->render('login.html.twig', [
                    'errors' => ['Account created successfully! You are the first user and have been assigned admin privileges. Please log in.']
                ]);
            } else {
                echo $twig->render('login.html.twig', [
                    'errors' => ['Account created successfully! Please log in.']
                ]);
            }
            exit;
        }
        echo $controller->showRegister($result['errors'] ?? []);
        exit;
    }
}

// Handle GET requests
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($action === 'node') {
    // Require login for node management
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    echo $nodeController->showNodes();
    exit;
}

if ($action === 'tag') {
    // Require login for tag management
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    echo $tagController->showTags();
    exit;
}

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo $controller->showWelcome($_SESSION['username']);
    exit;
}

if ($action === 'register') {
    echo $controller->showRegister();
} else {
    // Default to login page
    echo $controller->showLogin();
}
