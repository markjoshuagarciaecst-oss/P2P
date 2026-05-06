<?php
require_once '../config/database.php';
$db = Database::getInstance();
$stmt = $db->query('SELECT id, name, email, role FROM users WHERE role = "admin"');
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'Current admin users:' . PHP_EOL;
if (empty($admins)) {
    echo 'No admin users found.' . PHP_EOL;
    echo PHP_EOL;
    echo 'To create an admin account, you can:' . PHP_EOL;
    echo '1. Register a normal user account at /pages/register.php' . PHP_EOL;
    echo '2. Then manually update their role in the database:' . PHP_EOL;
    echo '   UPDATE users SET role = "admin" WHERE email = "your-email@example.com";' . PHP_EOL;
    echo PHP_EOL;
    echo 'Or create an admin account directly:' . PHP_EOL;
    echo '   INSERT INTO users (name, email, password, role) VALUES ("Admin", "admin@example.com", "$2y$10$hashedpassword", "admin");' . PHP_EOL;
} else {
    foreach ($admins as $admin) {
        echo '- ID: ' . $admin['id'] . ', Name: ' . $admin['name'] . ', Email: ' . $admin['email'] . PHP_EOL;
    }
}
?>