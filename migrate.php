<?php
// One-time migration script — delete after running
$host   = 'localhost';
$dbname = 'skillswap';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<pre>DB connection failed: " . $e->getMessage() . "</pre>");
}

function colExists($pdo, $table, $col) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (bool)$s->fetchColumn();
}

$migrations = [
    ['bookings',      'session_duration',  "ALTER TABLE bookings ADD COLUMN session_duration INT NOT NULL DEFAULT 1 AFTER scheduled_time"],
    ['bookings',      'teacher_confirmed', "ALTER TABLE bookings ADD COLUMN teacher_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER points_transferred"],
    ['bookings',      'learner_confirmed', "ALTER TABLE bookings ADD COLUMN learner_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER teacher_confirmed"],
    ['skills',        'max_session_hours', "ALTER TABLE skills ADD COLUMN max_session_hours INT NOT NULL DEFAULT 1 AFTER points_required"],
    ['notifications', 'booking_id',        "ALTER TABLE notifications ADD COLUMN booking_id INT DEFAULT NULL AFTER user_id"],
    ['notifications', 'type_update',       "ALTER TABLE notifications MODIFY COLUMN type ENUM('general','booking_request','booking_accepted','booking_rejected','session_completed','session_completion_requested','new_review','points') DEFAULT 'general'"],
    ['users',         'otp_code',          "ALTER TABLE users ADD COLUMN otp_code VARCHAR(6) DEFAULT NULL AFTER is_active"],
    ['users',         'otp_expires_at',    "ALTER TABLE users ADD COLUMN otp_expires_at DATETIME DEFAULT NULL AFTER otp_code"],
];

$results = [];
foreach ($migrations as [$table, $col, $sql]) {
    if ($col === 'type_update') {
        try { $pdo->exec($sql); $results[] = ['ok', "Updated ENUM on $table.type"]; }
        catch (PDOException $e) { $results[] = ['err', $e->getMessage()]; }
        continue;
    }
    if (colExists($pdo, $table, $col)) {
        $results[] = ['skip', "$table.$col already exists"];
        continue;
    }
    try { $pdo->exec($sql); $results[] = ['ok', "Added $table.$col"]; }
    catch (PDOException $e) { $results[] = ['err', "$table.$col — " . $e->getMessage()]; }
}

$allOk = !array_filter($results, fn($r) => $r[0] === 'err');
?>
<!DOCTYPE html><html><head><title>Migration</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head><body class="p-4"><div class="container" style="max-width:700px">
<h3 class="mb-4">Database Migration</h3>
<ul class="list-group mb-3">
<?php foreach ($results as [$s, $m]): ?>
<li class="list-group-item <?php echo $s==='ok'?'list-group-item-success':($s==='skip'?'list-group-item-secondary':'list-group-item-danger'); ?>">
    <?php echo $s==='ok'?'✅':($s==='skip'?'⏭':'❌'); ?> <?php echo htmlspecialchars($m); ?>
</li>
<?php endforeach; ?>
</ul>
<?php if ($allOk): ?>
<div class="alert alert-success">All done! Delete migrate.php from your server.</div>
<a href="pages/dashboard.php" class="btn btn-primary">Go to Dashboard →</a>
<?php else: ?>
<div class="alert alert-danger">Some migrations failed. Check errors above.</div>
<?php endif; ?>
</div></body></html>
