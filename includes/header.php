<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Notification.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.min.css" />
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <script>
        window.APP_URL = '<?php echo APP_URL; ?>';
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/index.php">
                <i class="fas fa-clock"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/pages/skills.php">Browse Skills</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/pages/dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/admin/index.php">
                            <i class="fas fa-cog me-1"></i>Admin Panel
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/pages/chat.php">Chat</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <form class="d-flex ms-lg-3 my-2 my-lg-0" method="GET" action="<?php echo APP_URL; ?>/pages/search-users.php">
                    <input class="form-control form-control-sm" type="search" placeholder="Search users" aria-label="Search users" name="search">
                    <button class="btn btn-outline-light btn-sm ms-2" type="submit"><i class="fas fa-search"></i></button>
                </form>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): 
                        $notification = new Notification();
                        $unreadCount = $notification->getUnreadCount(getUserId());
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <?php 
                            $recentNotifications = $notification->getRecentNotifications(getUserId(), 5);
                            if (empty($recentNotifications)):
                            ?>
                            <li><span class="dropdown-item text-muted">No notifications</span></li>
                            <?php else: ?>
                                <?php foreach ($recentNotifications as $notif): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $notif['is_read'] ? '' : 'fw-bold'; ?>" href="<?php echo APP_URL; ?>/pages/notifications.php?read=<?php echo $notif['id']; ?>">
                                        <small><?php echo sanitize($notif['title']); ?></small><br>
                                        <small class="text-muted"><?php echo formatDate($notif['created_at']); ?></small>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/notifications.php">View All</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                            <span class="badge bg-warning text-dark"><?php echo $_SESSION['user_points']; ?> pts</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/profile.php?id=<?php echo getUserId(); ?>">My Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/my-skills.php">My Skills</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/bookings.php">My Bookings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/pages/logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/pages/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/pages/register.php">Register</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="py-4">