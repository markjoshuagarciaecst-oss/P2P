<?php
require_once '../config/database.php';
require_once '../classes/Notification.php';

$pageTitle = 'Notifications';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=notifications.php');
}

$notificationObj = new Notification();

// Mark notification as read
if (isset($_GET['read'])) {
    $notificationId = (int)$_GET['read'];
    $notificationObj->markAsRead($notificationId, getUserId());
    redirect('notifications.php');
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $notificationObj->markAllAsRead(getUserId());
    redirect('notifications.php');
}

// Get notifications
$notifications = $notificationObj->getUserNotifications(getUserId());
$unreadCount = $notificationObj->getUnreadCount(getUserId());
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
        <?php if ($unreadCount > 0): ?>
        <a href="?mark_all_read=1" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-check-double me-1"></i>Mark all as read
        </a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($notifications)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4>No notifications</h4>
                <p>You're all caught up!</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notif): ?>
                <a href="?read=<?php echo $notif['id']; ?>" class="list-group-item list-group-item-action <?php echo $notif['is_read'] ? '' : 'bg-light'; ?>">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="me-3">
                            <?php 
                            $icon = 'fa-bell';
                            $iconClass = 'text-primary';
                            switch ($notif['type']) {
                                case 'booking_request':
                                    $icon = 'fa-calendar-plus';
                                    $iconClass = 'text-info';
                                    break;
                                case 'booking_accepted':
                                    $icon = 'fa-check-circle';
                                    $iconClass = 'text-success';
                                    break;
                                case 'booking_rejected':
                                    $icon = 'fa-times-circle';
                                    $iconClass = 'text-danger';
                                    break;
                                case 'session_completed':
                                    $icon = 'fa-check-double';
                                    $iconClass = 'text-success';
                                    break;
                                case 'new_review':
                                    $icon = 'fa-star';
                                    $iconClass = 'text-warning';
                                    break;
                                case 'points':
                                    $icon = 'fa-coins';
                                    $iconClass = 'text-warning';
                                    break;
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?> fa-lg <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 <?php echo $notif['is_read'] ? 'text-muted' : ''; ?>"><?php echo sanitize($notif['title']); ?></h6>
                            <p class="mb-1 small <?php echo $notif['is_read'] ? 'text-muted' : ''; ?>"><?php echo sanitize($notif['message']); ?></p>
                            <small class="text-muted"><?php echo formatDate($notif['created_at']); ?></small>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                        <span class="badge bg-primary rounded-pill">New</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>