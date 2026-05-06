<?php
// 1. Connection to SQLite file
$dbFile = 'database.db';
$pdo = new PDO("sqlite:" . __DIR__ . "/" . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. Simple logic to import CSV if the table is empty
$check = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
if ($check == 0) {
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER, email TEXT, avatarUrl TEXT)");
    
    if (($handle = fopen("MOCK_DATA.csv", "r")) !== FALSE) {
        fgetcsv($handle); // Skip header
        $stmt = $pdo->prepare("INSERT INTO users (id, name, age, email, avatarUrl) VALUES (?, ?, ?, ?, ?)");
        $pdo->beginTransaction();
        while (($data = fgetcsv($handle)) !== FALSE) {
            $stmt->execute($data);
        }
        $pdo->commit();
        fclose($handle);
    }
}

// 3. Start API
$api = new RestApi($pdo);
$api->handleRequest();