<?php
// scratch/check_mysql.php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDB();
    echo "Driver: " . DB_DRIVER . "\n";
    
    // Check tables
    if (DB_DRIVER === 'mysql') {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables in MySQL database '" . DB_NAME . "':\n";
        print_r($tables);
    } else {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables in SQLite database:\n";
        print_r($tables);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
