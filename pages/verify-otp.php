<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../config/mail.php';

$pageTitle = 'Verify Your Identity';
$error     = '';

if (isLoggedIn()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

// ── Recover user ID from session OR from signed URL token ────────────────────
$userId = 0;

if (!empty($_SESSION['otp_user_id'])) {
    $userId = (int)$_SESSION['otp_user_id'];
} elseif (!empty($_GET['uid']) && !empty($_GET['sig'])) {
    // Session was lost — verify the HMAC signature and restore from URL
    $uid = (int)$_GET['uid'];
    $sig = $_GET['sig'];
    $expected = hash_hmac('sha256', (string)$uid, DB_PASS);
    if (hash_equals($expected, $sig)) {
        $userId = $uid;
        $_SESSION['otp_user_id'] = $userId;
    }
}

if (!$userId) {
    redirect(APP_URL . '/pages/login.php');
}

// Build the token params to keep in links on this page
$urlToken = '';
if (!empty($_GET['uid']) && !empty($_GET['sig'])) {
    $urlToken = '&uid=' . (int)$_GET['uid'] . '&sig=' . htmlspecialchars($_GET['sig']);
}

$userObj      = new User();
$mailFailed   = !empty($_SESSION['otp_fallback_code']);
$fallbackCode = isset($_SESSION['otp_fallback_code']) ? $_SESSION['otp_fallback_code'] : null;

// ── Resend OTP ────────────────────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    $db   = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);
        try {
            $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
               ->execute([$otp, $expires, $userId]);
        } catch (Exception $e) {}

        $html = "<div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;border:1px solid #e0e0e0;border-radius:8px'>
            <h2 style='color:#0d6efd'>" . APP_NAME . "</h2>
            <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>, your new code:</p>
            <div style='font-size:2.5rem;font-weight:bold;letter-spacing:12px;text-align:center;background:#f0f4ff;padding:20px;border-radius:8px;margin:24px 0;color:#0d6efd'>$otp</div>
            <p style='color:#666'>Expires in <strong>10 minutes</strong>.</p>
        </div>";

        $result = sendMail($user['email'], $user['name'], APP_NAME . ' — New login code', $html);
        if ($result !== true) {
            $_SESSION['otp_fallback_code'] = $otp;
            $_SESSION['otp_mail_error']    = $result;
        } else {
            unset($_SESSION['otp_fallback_code'], $_SESSION['otp_mail_error']);
        }
    }
    redirect(APP_URL . '/pages/verify-otp.php?resent=1' . $urlToken);
}

// ── Verify submitted code ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim(isset($_POST['otp']) ? $_POST['otp'] : '');

    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = 'Please enter the 6-digit code.';
    } elseif ($userObj->verifyOtp($userId, $code)) {
        unset($_SESSION['otp_fallback_code'], $_SESSION['otp_mail_error']);
        $redir = isset($_SESSION['otp_redirect']) ? $_SESSION['otp_redirect'] : '';
        redirect($redir ? APP_URL . '/' . ltrim($redir, '/') : APP_URL . '/pages/dashboard.php');
    } else {
        $error = 'Invalid or expired code. Please try again or request a new one.';
    }
}

// Get masked email
$db   = Database::getInstance();
$stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row  = $stmt->fetch();
$maskedEmail = '';
if ($row) {
    $parts = explode('@', $row['email']);
    $local  = $parts[0];
    $domain = isset($parts[1]) ? $parts[1] : '';
    $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
}
?>
<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card mt-5 shadow-sm">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt fa-4x text-primary"></i>
                        <h3 class="mt-3">Verify Your Identity</h3>

                        <?php if ($mailFailed): ?>
                            <?php $mailErr = isset($_SESSION['otp_mail_error']) ? $_SESSION['otp_mail_error'] : ''; ?>
                            <?php if ($mailErr === 'not_configured'): ?>
                            <div class="alert alert-info text-start mt-3 mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Email not configured. Use the code below to log in.
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning text-start mt-3 mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Could not send email. Use the code below instead.
                            </div>
                            <?php endif; ?>
                            <div class="p-3 mb-3 rounded"
                                 style="background:#f0f4ff;font-size:2.2rem;font-weight:bold;
                                        letter-spacing:14px;color:#0d6efd;text-align:center">
                                <?php echo htmlspecialchars($fallbackCode); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mt-2">
                                We sent a 6-digit code to<br>
                                <strong><?php echo htmlspecialchars($maskedEmail); ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_GET['resent'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $mailFailed ? 'New code generated (shown above).' : 'A new code has been sent.'; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="otp" class="form-label fw-semibold">Enter Verification Code</label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold"
                                   id="otp" name="otp"
                                   maxlength="6"
                                   placeholder="000000"
                                   autocomplete="one-time-code"
                                   inputmode="numeric"
                                   autofocus required
                                   style="font-size:2rem;letter-spacing:10px">
                            <div class="form-text text-center mt-1">Code expires in 10 minutes.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-check-circle me-2"></i>Verify &amp; Login
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="text-muted small mb-2">Didn't receive the code?</p>
                        <a href="<?php echo APP_URL; ?>/pages/verify-otp.php?resend=1<?php echo $urlToken; ?>"
                           class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-redo me-1"></i>Resend Code
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/login.php" class="btn btn-link btn-sm text-muted">
                            ← Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('otp').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        this.closest('form').submit();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
