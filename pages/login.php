<?php
require_once '../config/database.php';
require_once '../classes/User.php';

$pageTitle = 'Login';
$error     = '';
$success   = '';

if (isLoggedIn()) {
    redirect('../index.php');
}

$userObj = new User();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Step 1: email + password ──────────────────────────────────────────────
    if (isset($_POST['email'])) {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $result = $userObj->login($email, $password);

            if ($result === false) {
                $error = 'Invalid email or password.';
            } elseif ($result === 'otp_sent') {
                // Redirect to OTP verification page
                redirect('verify-otp.php');
            } else {
                // OTP columns not migrated or mail failed — already logged in
                $redirect = $_GET['redirect'] ?? 'index.php';
                redirect('../' . $redirect);
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
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                        <h3 class="mt-3">Welcome Back</h3>
                        <p class="text-muted">Login to continue your learning journey</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
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
                            <a href="register.php" class="text-primary">Register here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon  = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
