    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p class="text-muted">Exchange skills and knowledge with peers. Learn something new, teach what you know.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo APP_URL; ?>/pages/skills.php" class="text-muted">Browse Skills</a></li>
                        <?php if (!isLoggedIn()): ?>
                        <li><a href="<?php echo APP_URL; ?>/pages/register.php" class="text-muted">Join Now</a></li>
                        <?php endif; ?>
                        <li><a href="#" class="text-muted">How It Works</a></li>
                        <li><a href="#" class="text-muted">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <ul class="list-unstyled text-muted">
                        <li><i class="fas fa-envelope me-2"></i> support@skillswap.com</li>
                        <li><i class="fas fa-phone me-2"></i> +1 234 567 890</li>
                    </ul>
                    <div class="mt-3">
                        <a href="#" class="text-muted me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-muted me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-muted me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-muted"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center text-muted">
                <small>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extraScripts)): ?>
        <?php echo $extraScripts; ?>
    <?php endif; ?>
</body>
</html>