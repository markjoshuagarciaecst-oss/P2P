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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extraScripts)): ?>
        <?php echo $extraScripts; ?>
    <?php endif; ?>

    <!-- Toast container -->
    <div class="toast-container"></div>

    <?php if (isLoggedIn()): ?>
    <!-- Bottom navigation — visible on mobile only -->
    <nav class="bottom-nav">
        <a href="<?php echo APP_URL; ?>/index.php">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="<?php echo APP_URL; ?>/pages/skills.php">
            <i class="fas fa-search"></i>
            <span>Browse</span>
        </a>
        <a href="<?php echo APP_URL; ?>/pages/bookings.php">
            <i class="fas fa-calendar-alt"></i>
            <span>Bookings</span>
        </a>
        <a href="<?php echo APP_URL; ?>/pages/messages.php">
            <i class="fas fa-comments"></i>
            <span>Messages</span>
        </a>
        <a href="<?php echo APP_URL; ?>/pages/notifications.php" style="position:relative">
            <i class="fas fa-bell"></i>
            <?php
            // Show unread count on bell icon
            if (class_exists('Notification')) {
                $notifObj = new Notification();
                $uc = $notifObj->getUnreadCount(getUserId());
                if ($uc > 0) {
                    echo '<span class="nav-badge">' . ($uc > 9 ? '9+' : $uc) . '</span>';
                }
            }
            ?>
            <span>Alerts</span>
        </a>
        <a href="<?php echo APP_URL; ?>/pages/dashboard.php">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
        </a>
    </nav>
    <?php endif; ?>

</body>
</html>