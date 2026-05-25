<?php
// API — fetch new messages after a given ID
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

$db        = Database::getInstance();
$userId    = getUserId();
$bookingId = (int)($_GET['b']    ?? 0);
$afterId   = (int)($_GET['after'] ?? 0);

if (!$bookingId) {
    echo json_encode([]);
    exit;
}

// Verify user belongs to this booking
$stmt = $db->prepare("SELECT id FROM bookings WHERE id = ? AND (learner_id = ? OR teacher_id = ?)");
$stmt->execute([$bookingId, $userId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode([]);
    exit;
}

$stmt = $db->prepare("
    SELECT cm.id, cm.sender_id, cm.message, cm.created_at,
           u.name AS sender_name, u.profile_picture
    FROM chat_messages cm
    JOIN users u ON u.id = cm.sender_id
    WHERE cm.booking_id = ? AND cm.id > ?
    ORDER BY cm.created_at ASC
");
$stmt->execute([$bookingId, $afterId]);
$rows = $stmt->fetchAll();

$out = [];
foreach ($rows as $m) {
    $out[] = [
        'id'          => (int)$m['id'],
        'sender_id'   => (int)$m['sender_id'],
        'sender_name' => $m['sender_name'],
        'mine'        => ($m['sender_id'] == $userId),
        'message'     => $m['message'],
        'time'        => date('M d, Y h:i A', strtotime($m['created_at'])),
        'avatar'      => APP_URL . '/' . ltrim($m['profile_picture'] ?? 'assets/images/default-avatar.png', '/'),
    ];
}

echo json_encode($out);
