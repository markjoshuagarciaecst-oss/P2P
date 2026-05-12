<?php
require_once '../config/database.php';
require_once '../classes/Notification.php';
require_once '../classes/Booking.php';

$pageTitle = 'Notifications';

if (!isLoggedIn()) {
    redirect('login.php?redirect=notifications.php');
}

$notifObj   = new Notification();
$bookingObj = new Booking();
$db         = Database::getInstance();
$userId     = getUserId();

$error   = '';
$success = '';

// ── Handle learner confirming session complete ────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'confirm_complete') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $notifId   = (int)($_POST['notif_id']   ?? 0);

    if ($bookingId > 0) {
        $result = $bookingObj->learnerConfirm($bookingId, $userId);
        if ($result) {
            if ($notifId > 0) $notifObj->markAsRead($notifId, $userId);
            $b = $bookingObj->getBookingById($bookingId);
            $success = $b && $b['status'] === 'completed'
                ? 'Session confirmed! Points have been transferred.'
                : 'You confirmed the session. Waiting for the teacher to also confirm.';
        } else {
            $error = 'Could not confirm. Make sure you have enough points and the session is still active.';
        }
    }
}

// ── Mark single notification as read ─────────────────────────────────────────
if (isset($_GET['read'])) {
    $notifObj->markAsRead((int)$_GET['read'], $userId);
    header('Location: notifications.php');
    exit;
}

// ── Mark all as read ──────────────────────────────────────────────────────────
if (isset($_GET['mark_all_read'])) {
    $notifObj->markAllAsRead($userId);
    header('Location: notifications.php');
    exit;
}

// ── Load notifications ────────────────────────────────────────────────────────
$notifications = $notifObj->getUserNotifications($userId);
$unreadCount   = $notifObj->getUnreadCount($userId);

// ── Pre-load all accepted bookings where this user is the learner ─────────────
// Used to attach a confirm button to completion-request notifications even when
// booking_id is NULL in the notification row.
$pendingConfirmBookings = [];
$stmt = $db->prepare(
    "SELECT b.id, b.teacher_confirmed, b.learner_confirmed, s.title as skill_title,
            t.name as teacher_name
     FROM bookings b
     JOIN skills s ON s.id = b.skill_id
     JOIN users  t ON t.id = b.teacher_id
     WHERE b.learner_id = ? AND b.status = 'accepted' AND b.learner_confirmed = 0"
);
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $row) {
    $pendingConfirmBookings[$row['id']] = $row;
}
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

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php
    // ── Show a prominent action card for every booking awaiting learner confirm ──
    if (!empty($pendingConfirmBookings)):
    ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <h5 class="mb-3"><i class="fas fa-hourglass-half me-2"></i>Sessions awaiting your confirmation</h5>
        <?php foreach ($pendingConfirmBookings as $b): ?>
        <div class="d-flex align-items-center justify-content-between mb-2 p-2 bg-white rounded border">
            <div>
                <strong><?php echo htmlspecialchars($b['skill_title']); ?></strong>
                with <?php echo htmlspecialchars($b['teacher_name']); ?>
                <?php if ($b['teacher_confirmed']): ?>
                <span class="badge bg-warning text-dark ms-2">Teacher confirmed ✓ — waiting for you</span>
                <?php else: ?>
                <span class="badge bg-secondary ms-2">Teacher hasn't confirmed yet</span>
                <?php endif; ?>
            </div>
            <form method="POST" class="ms-3 mb-0">
                <input type="hidden" name="action"     value="confirm_complete">
                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('Confirm this session is complete? Points will be deducted from your balance.')">
                    <i class="fas fa-check me-1"></i>Confirm Complete
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <h4>No notifications</h4>
            <p class="text-muted">You're all caught up!</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notif):
                $type = $notif['type'] ?? 'general';
                $bid  = isset($notif['booking_id']) ? (int)$notif['booking_id'] : 0;

                // Icon per type
                $icon = 'fa-bell'; $ic = 'text-primary';
                switch ($type) {
                    case 'booking_request':              $icon='fa-calendar-plus';  $ic='text-info';    break;
                    case 'booking_accepted':             $icon='fa-check-circle';   $ic='text-success'; break;
                    case 'booking_rejected':             $icon='fa-times-circle';   $ic='text-danger';  break;
                    case 'session_completion_requested': $icon='fa-hourglass-half'; $ic='text-warning'; break;
                    case 'session_completed':            $icon='fa-check-double';   $ic='text-success'; break;
                    case 'new_review':                   $icon='fa-star';           $ic='text-warning'; break;
                    case 'points':                       $icon='fa-coins';          $ic='text-warning'; break;
                }

                $isCompletionReq = $type === 'session_completion_requested'
                    || strpos($notif['title'], 'Session Completion') !== false
                    || strpos($notif['title'], 'Completion Requested') !== false;
            ?>
            <div class="list-group-item <?php echo $notif['is_read'] ? '' : 'bg-light'; ?>">
                <div class="d-flex align-items-start gap-3">
                    <div class="pt-1 fs-5">
                        <i class="fas <?php echo $icon; ?> <?php echo $ic; ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <strong class="<?php echo $notif['is_read'] ? 'text-muted fw-normal' : ''; ?>">
                                <?php echo htmlspecialchars($notif['title']); ?>
                            </strong>
                            <small class="text-muted ms-3 text-nowrap"><?php echo formatDate($notif['created_at']); ?></small>
                        </div>
                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars($notif['message']); ?></p>

                        <?php
                        // Show confirm button on the notification itself if we have a booking_id
                        // AND the booking is still awaiting learner confirmation
                        $showConfirmBtn = false;
                        $confirmBookingId = 0;
                        if ($isCompletionReq) {
                            if ($bid > 0 && isset($pendingConfirmBookings[$bid])) {
                                $showConfirmBtn   = true;
                                $confirmBookingId = $bid;
                            } elseif ($bid === 0 && !empty($pendingConfirmBookings)) {
                                // booking_id not stored — attach to first pending booking
                                $first = reset($pendingConfirmBookings);
                                $showConfirmBtn   = true;
                                $confirmBookingId = $first['id'];
                            }
                        }
                        ?>
                        <?php if ($showConfirmBtn): ?>
                        <form method="POST" class="mt-2 d-inline">
                            <input type="hidden" name="action"     value="confirm_complete">
                            <input type="hidden" name="booking_id" value="<?php echo $confirmBookingId; ?>">
                            <input type="hidden" name="notif_id"   value="<?php echo $notif['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm"
                                    onclick="return confirm('Confirm this session is complete? Points will be deducted from your balance.')">
                                <i class="fas fa-check me-1"></i>Yes, Confirm Complete
                            </button>
                            <a href="?read=<?php echo $notif['id']; ?>" class="btn btn-outline-secondary btn-sm ms-1">Dismiss</a>
                        </form>
                        <?php elseif (!$notif['is_read']): ?>
                        <a href="?read=<?php echo $notif['id']; ?>" class="btn btn-outline-secondary btn-sm mt-1">
                            Mark as read
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
