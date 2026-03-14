<?php
// Quick diagnostic – DELETE this file after confirming the system works
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h2>Comboni Library – Server Diagnostic</h2>';
echo '<p>PHP Version: <strong>' . phpversion() . '</strong></p>';

// Check PDO MySQL
if (extension_loaded('pdo_mysql')) {
    echo '<p style="color:green">✔ PDO MySQL extension loaded</p>';
} else {
    echo '<p style="color:red">✘ PDO MySQL extension NOT loaded – enable pdo_mysql in php.ini</p>';
}

// Test DB connection
require_once __DIR__ . '/config.php';
try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '<p style="color:green">✔ MySQL connection successful</p>';

    // Check if database exists
    $dbs = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'")->fetchAll();
    if ($dbs) {
        echo '<p style="color:green">✔ Database <strong>' . DB_NAME . '</strong> found</p>';
        $pdo->exec("USE " . DB_NAME);
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo '<p>Tables: <strong>' . implode(', ', $tables) . '</strong></p>';
        if (count($tables) < 6) {
            echo '<p style="color:orange">⚠ Expected 7 tables. Run schema.sql in phpMyAdmin.</p>';
        }
    } else {
        echo '<p style="color:red">✘ Database <strong>' . DB_NAME . '</strong> NOT found – run schema.sql first</p>';
    }
} catch (PDOException $e) {
    echo '<p style="color:red">✘ MySQL error: ' . $e->getMessage() . '</p>';
    echo '<p>Check DB_USER/DB_PASS in config.php</p>';
}
echo '<hr><p style="color:gray;font-size:12px">Delete test.php when done.</p>';
