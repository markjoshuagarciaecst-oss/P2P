<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h2>PHP Info</h2>';
echo '<p>PHP version: <strong>' . phpversion() . '</strong></p>';
echo '<p>Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . '</p>';

echo '<h3>Extensions</h3><ul>';
foreach (['pdo', 'pdo_mysql', 'curl', 'mbstring', 'openssl', 'json'] as $ext) {
    $ok = extension_loaded($ext);
    echo '<li style="color:' . ($ok ? 'green' : 'red') . '">' . ($ok ? '✅' : '❌') . ' ' . $ext . '</li>';
}
echo '</ul>';

echo '<h3>File existence check</h3><ul>';
$files = [
    'config/database.php',
    'classes/User.php',
    'classes/Skill.php',
    'classes/Booking.php',
    'classes/Notification.php',
    'classes/Transaction.php',
    'classes/Review.php',
    'includes/header.php',
    'includes/footer.php',
    'index.php',
];
foreach ($files as $f) {
    $exists = file_exists(__DIR__ . '/' . $f);
    echo '<li style="color:' . ($exists ? 'green' : 'red') . '">' . ($exists ? '✅' : '❌ MISSING') . ' ' . $f . '</li>';
}
echo '</ul>';

echo '<h3>Try loading config</h3>';
try {
    require_once __DIR__ . '/config/database.php';
    echo '<p style="color:green">✅ config/database.php loaded OK</p>';
    $db = Database::getInstance();
    echo '<p style="color:green">✅ Database connected</p>';
} catch (Throwable $e) {
    echo '<p style="color:red">❌ Error: ' . htmlspecialchars($e->getMessage()) . ' in ' . htmlspecialchars($e->getFile()) . ' line ' . $e->getLine() . '</p>';
}

echo '<hr><p><strong>Delete phptest.php after use.</strong></p>';
