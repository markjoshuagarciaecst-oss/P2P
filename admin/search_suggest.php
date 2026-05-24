<?php
// Autocomplete suggestions for admin content search
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$db   = Database::getInstance();
$like = "%$q%";
$suggestions = [];

// Pull matching words from skill titles, descriptions, user names/bios
$sources = [
    "SELECT title       FROM skills WHERE title       LIKE ? LIMIT 10",
    "SELECT description FROM skills WHERE description LIKE ? LIMIT 10",
    "SELECT name        FROM users  WHERE name        LIKE ? LIMIT 10",
    "SELECT bio         FROM users  WHERE bio         LIKE ? AND bio IS NOT NULL LIMIT 10",
];

$wordSet = [];
foreach ($sources as $sql) {
    $stmt = $db->prepare($sql);
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $text) {
        // Extract individual words that match the query
        $words = preg_split('/\s+/', $text ?? '');
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:"\'()');
            if (strlen($word) >= 2 && stripos($word, $q) !== false) {
                $wordSet[strtolower($word)] = $word;
            }
        }
    }
}

// Also include the full phrase if it matches a skill title
$stmt = $db->prepare("SELECT DISTINCT title FROM skills WHERE title LIKE ? LIMIT 5");
$stmt->execute([$like]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $title) {
    $wordSet[strtolower($title)] = $title;
}

// Sort and limit
$results = array_values($wordSet);
usort($results, fn($a, $b) => strcasecmp($a, $b));
echo json_encode(array_slice($results, 0, 12));
