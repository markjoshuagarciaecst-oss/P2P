<?php
require_once '../config/database.php';
require_once '../classes/User.php';

$pageTitle = 'Login';
$error = '';
$success = '';

// Check if already logged in
if (isLoggedIn()) {
    redirect('../index.php');
}

$userObj = new User();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $user = $userObj->login($email, $password);
        if ($user) {
            $redirect = $_GET['redirect'] ?? 'index.php';
            redirect('../' . $redirect);
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card mt-5">
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
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p>Don't have an account? 
                            <a href="register.php" class="text-primary">Register here</a>
                        </p>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <p class="text-muted small">Demo Account</p>
                        <p class="small mb-1"><strong>Email:</strong> user@demo.com</p>
                        <p class="small"><strong>Password:</strong> demo123</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});
</script>

<?php include '../includes/footer.php'; ?>