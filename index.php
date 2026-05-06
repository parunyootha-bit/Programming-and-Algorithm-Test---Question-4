<?php
require_once 'RestApi.php';

try {
    // Connect to the SQLite file you just created
    $dbPath = __DIR__ . '/database.db';
    $pdo = new PDO("sqlite:" . $dbPath);
    
    // Set error mode to exceptions so you can see if queries fail
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initialize and run the API
    $api = new RestApi($pdo);
    $api->handleRequest();

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
}