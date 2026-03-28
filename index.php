<?php

require_once __DIR__ . '/vendor/autoload.php';

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

// Create controller
$controller = new UserController($pdo, $twig);

// Route requests
$action = $_GET['action'] ?? 'login';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
if ($action === 'register') {
    echo $controller->showRegister();
} else {
    // Default to login page
    echo $controller->showLogin();
}
