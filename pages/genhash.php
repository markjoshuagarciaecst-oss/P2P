<?php
// Password hash generator — DELETE after use
require_once '../config/database.php';

$password = $_POST['pw'] ?? '';
$hash     = $password ? password_hash($password, PASSWORD_DEFAULT) : '';
$email    = $_POST['email'] ?? '';

if ($hash && $email) {
    $db   = Database::getInstance();
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    echo '<p style="color:green">✅ Password updated for ' . htmlspecialchars($email) . '</p>';
}
?>
<!DOCTYPE html>
<html>
<head><title>Set Password</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container" style="max-width:500px">
    <h3>Set Admin Password</h3>
    <p class="text-danger"><strong>Delete this file immediately after use.</strong></p>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Admin Email</label>
            <input type="email" name="email" class="form-control"
                   value="adminskillswap08@gmail.com" required>
        </div>
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="pw" class="form-control"
                   placeholder="Enter new password" required>
        </div>
        <button type="submit" class="btn btn-primary">Set Password</button>
    </form>
</div>
</body>
</html>
