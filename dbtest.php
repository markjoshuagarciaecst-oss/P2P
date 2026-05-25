<?php
// DB connection test — DELETE THIS FILE after confirming it works
$host = 'sql209.infinityfree.com';   // same as DB_HOST in database.php
$name = 'if0_42008218_skillswap';   // same as DB_NAME
$user = 'if0_42008218';   // same as DB_USER
$pass = 'Hackyu33';     // same as DB_PASS

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    echo '<h2 style="color:green">✅ Database connected successfully!</h2>';
    echo '<p>Host: ' . htmlspecialchars($host) . '</p>';
    echo '<p>Database: ' . htmlspecialchars($name) . '</p>';

    // Check tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo '<p style="color:orange">⚠️ Connected but no tables found. You need to import database.sql via phpMyAdmin.</p>';
    } else {
        echo '<p>Tables found: <strong>' . implode(', ', $tables) . '</strong></p>';
        echo '<p style="color:green">✅ Database is fully set up!</p>';
    }
} catch (PDOException $e) {
    echo '<h2 style="color:red">❌ Connection failed</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Check your DB_HOST, DB_NAME, DB_USER, DB_PASS values.</p>';
}
echo '<p><strong>Remember to delete this file after testing!</strong></p>';
