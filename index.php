<?php

$config = parse_ini_file('config.ini', true);

if ($config === false) {
    die("Error reading configuration file.");
}

$db = $config['db'];

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8mb4",
        $db['username'],
        $db['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to the database successfully.";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
