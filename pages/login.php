<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../config/mail.php';

$pageTitle = 'Login';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$db    = Database::getInstance();
$error = '';
$step  = 'credentials'; // credentials | otp

// ── STEP 2: Verify OTP ────────────────────────────────────────────────────────
if (isset($_POST['step']) && $_POST['step'] === 'otp') {
    $uid  = (int)($_POST['uid'] ?? 0);
    $sig  = $_POST['sig'] ?? '';
    $code = trim($_POST['otp'] ?? '');

    // Verify signature
    $expected = hash_hmac('sha256', (string)$uid, DB_PASS);
    if (!$uid || !hash_equals($expected, $sig)) {
        $error = 'Invalid session. Please log in again.';
        $step  = 'credentials';
    } elseif (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = 'Please enter the 6-digit code.';
        $step  = 'otp';
    } else {
        // Check OTP in DB first, fall back to session if columns missing
        $user = null;
        try {
            $stmt = $db->prepare(
                "SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > NOW() AND is_active = 1"
            );
            $stmt->execute([$uid, $code]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            // otp_code column doesn't exist — check session fallback
            if (!empty($_SESSION['otp_code_' . $uid]) && $_SESSION['otp_code_' . $uid] === $code) {
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
            }
        }

        if ($user) {
            // Clear OTP
            try {
                $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
                   ->execute([$uid]);
            } catch (Exception $e) {}
            unset($_SESSION['otp_code_' . $uid]);

            // Create session
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['name'];
            $_SESSION['user_email']  = $user['email'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['user_points'] = $user['points'];

            header('Location: ' . APP_URL . '/pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid or expired code. Please try again.';
            $step  = 'otp';
        }
    }

    // Keep OTP form data for re-display
    $otpUid = $uid;
    $otpSig = $sig;
}

// ── STEP 1: Email + Password ──────────────────────────────────────────────────
if (isset($_POST['step']) && $_POST['step'] === 'credentials') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
        $step  = 'credentials';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
            $step  = 'credentials';
        } else {
            // Generate OTP
            $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 600);

            // Save OTP to DB
            try {
                $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
                   ->execute([$otp, $expires, $user['id']]);
                $otpSaved = true;
            } catch (Exception $e) {
                // Column missing — still proceed, show code on screen
                $otpSaved = false;
            }

            // Always store in session as fallback
            $_SESSION['otp_code_' . $user['id']] = $otp;

            // Send email
            $html = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;
                        border:1px solid #e0e0e0;border-radius:8px;text-align:center'>
                <h2 style='color:#0d6efd'>Time for Skill</h2>
                <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                <p>Your login verification code is:</p>
                <div style='font-size:3rem;font-weight:bold;letter-spacing:14px;
                            background:#f0f4ff;padding:24px;border-radius:8px;
                            margin:24px 0;color:#0d6efd'>$otp</div>
                <p style='color:#666'>Expires in <strong>10 minutes</strong>.</p>
            </div>";

            $mailResult = sendMail($user['email'], $user['name'],
                                   'Time for Skill — Login Code', $html);

            // Build signed token (works even if session drops)
            $sig = hash_hmac('sha256', (string)$user['id'], DB_PASS);

            if ($mailResult === true) {
                $step   = 'otp';
                $otpUid = $user['id'];
                $otpSig = $sig;
                $maskedEmail = substr($user['email'], 0, 2)
                             . str_repeat('*', max(0, strpos($user['email'], '@') - 2))
                             . substr($user['email'], strpos($user['email'], '@'));
            } else {
                // Mail failed — always show OTP on screen, never skip to dashboard
                $step        = 'otp';
                $otpUid      = $user['id'];
                $otpSig      = $sig;
                $showOtpCode = $otp;
                $maskedEmail = '';
            }
        }
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card mt-5 shadow-sm">
                <div class="card-body p-5">

                    <?php if ($step === 'credentials'): ?>
                    <!-- ── Step 1: Login form ── -->
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                        <h3 class="mt-3">Welcome Back</h3>
                        <p class="text-muted">Login to continue your learning journey</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="step" value="credentials">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePw">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Continue
                        </button>
                    </form>
                    <div class="text-center mt-4">
                        <p class="mb-0">Don't have an account?
                            <a href="register.php">Register here</a>
                        </p>
                    </div>

                    <?php else: ?>
                    <!-- ── Step 2: OTP form ── -->
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt fa-4x text-primary"></i>
                        <h3 class="mt-3">Verify Your Identity</h3>
                        <?php if (!empty($showOtpCode)): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Email could not be sent. Your code is:
                        </div>
                        <div style="font-size:2.5rem;font-weight:bold;letter-spacing:14px;
                                    background:#f0f4ff;padding:20px;border-radius:8px;
                                    color:#0d6efd;text-align:center;margin-bottom:16px">
                            <?php echo htmlspecialchars($showOtpCode); ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mt-2">
                            We sent a 6-digit code to<br>
                            <strong><?php echo htmlspecialchars($maskedEmail ?? ''); ?></strong>
                        </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="step" value="otp">
                        <input type="hidden" name="uid"  value="<?php echo (int)($otpUid ?? 0); ?>">
                        <input type="hidden" name="sig"  value="<?php echo htmlspecialchars($otpSig ?? ''); ?>">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Enter Verification Code</label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold"
                                   id="otpInput" name="otp"
                                   maxlength="6" placeholder="000000"
                                   inputmode="numeric" autocomplete="one-time-code"
                                   autofocus required
                                   style="font-size:2rem;letter-spacing:10px">
                            <div class="form-text text-center mt-1">Code expires in 10 minutes.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-check-circle me-2"></i>Verify &amp; Login
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-link btn-sm text-muted">
                            ← Back to Login
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
var toggleBtn = document.getElementById('togglePw');
if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
        var pw = document.querySelector('input[name="password"]');
        var ic = this.querySelector('i');
        if (pw.type === 'password') {
            pw.type = 'text';
            ic.className = 'fas fa-eye-slash';
        } else {
            pw.type = 'password';
            ic.className = 'fas fa-eye';
        }
    });
}

var otpInput = document.getElementById('otpInput');
if (otpInput) {
    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        if (this.value.length === 6) {
            this.closest('form').submit();
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
