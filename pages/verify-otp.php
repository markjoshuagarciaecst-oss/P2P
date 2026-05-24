<?php
require_once '../config/database.php';
require_once '../classes/User.php';

$pageTitle = 'Verify Your Identity';
$error     = '';

// Must have a pending OTP session
if (isLoggedIn()) {
    redirect('../index.php');
}

if (empty($_SESSION['otp_user_id'])) {
    redirect('login.php');
}

$userObj = new User();
$userId  = (int)$_SESSION['otp_user_id'];

// Was email sending configured / successful?
$mailFailed   = !empty($_SESSION['otp_fallback_code']);
$fallbackCode = $_SESSION['otp_fallback_code'] ?? null;

// ── Resend OTP ────────────────────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    $db   = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user) {
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);
        $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
           ->execute([$otp, $expires, $userId]);

        require_once '../config/mail.php';
        $html = "
        <div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;
                    border:1px solid #e0e0e0;border-radius:8px'>
            <h2 style='color:#0d6efd'>" . APP_NAME . "</h2>
            <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>, here is your new code:</p>
            <div style='font-size:2.5rem;font-weight:bold;letter-spacing:12px;text-align:center;
                        background:#f0f4ff;padding:20px;border-radius:8px;margin:24px 0;color:#0d6efd'>
                $otp
            </div>
            <p style='color:#666'>Expires in <strong>10 minutes</strong>.</p>
        </div>";

        $result = sendMail($user['email'], $user['name'], APP_NAME . ' — New login code', $html);

        if ($result !== true) {
            $_SESSION['otp_fallback_code'] = $otp;
            $_SESSION['otp_mail_error']    = $result;
        } else {
            unset($_SESSION['otp_fallback_code'], $_SESSION['otp_mail_error']);
        }    }
    redirect('verify-otp.php?resent=1');
}

// ── Verify submitted code ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp'] ?? '');

    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = 'Please enter the 6-digit code.';
    } elseif ($userObj->verifyOtp($userId, $code)) {
        unset($_SESSION['otp_fallback_code'], $_SESSION['otp_mail_error']);
        $redirect = $_SESSION['otp_redirect'] ?? '';
        redirect('../' . ($redirect ?: 'index.php'));
    } else {
        $error = 'Invalid or expired code. Please try again or request a new one.';
    }
}

// Get masked email for display
$db   = Database::getInstance();
$stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row  = $stmt->fetch();
$maskedEmail = '';
if ($row) {
    [$local, $domain] = explode('@', $row['email']);
    $maskedEmail = substr($local, 0, 2)
                 . str_repeat('*', max(0, strlen($local) - 2))
                 . '@' . $domain;
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
                        <?php if (($_SESSION['otp_mail_error'] ?? '') === 'not_configured'): ?>
                        <!-- Credentials not set up yet — show code on screen -->
                        <div class="alert alert-info text-start mt-3 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Email sending is not configured yet. Your code is shown below — enter it to log in.
                        </div>
                        <?php else: ?>
                        <!-- Email configured but failed to send -->
                        <div class="alert alert-warning text-start mt-3 mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Could not send email. Your code is shown below instead.
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
                        <?php echo $mailFailed ? 'New code generated (shown above).' : 'A new code has been sent to your email.'; ?>
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
                        <a href="?resend=1" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-redo me-1"></i>Resend Code
                        </a>
                        <a href="login.php" class="btn btn-link btn-sm text-muted">
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
