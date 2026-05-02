<?php
require_once '../config/database.php';
require_once '../classes/Booking.php';

$pageTitle = 'Chat';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=chat.php');
}

$bookingObj = new Booking();

// Get booking ID from URL
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$bookingId) {
    redirect('bookings.php');
}

// Get booking details
$booking = $bookingObj->getBookingById($bookingId);

if (!$booking) {
    redirect('bookings.php');
}

// Check if user is part of this booking
$isTeacher = $booking['teacher_id'] == getUserId();
$isLearner = $booking['learner_id'] == getUserId();

if (!$isTeacher && !$isLearner) {
    redirect('bookings.php');
}

// Only show chat if booking is accepted
if ($booking['status'] !== 'accepted') {
    redirect('bookings.php');
}

// Get chat messages
$db = Database::getInstance();

// Create chat messages table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
)");

// Get all messages for this booking
$stmt = $db->prepare("SELECT cm.*, u.name as sender_name, u.profile_picture FROM chat_messages cm 
                     JOIN users u ON cm.sender_id = u.id 
                     WHERE cm.booking_id = ? 
                     ORDER BY cm.created_at ASC");
$stmt->execute([$bookingId]);
$messages = $stmt->fetchAll();

// Handle new message
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize($_POST['message'] ?? '');
    
    if (!empty($message)) {
        $stmt = $db->prepare("INSERT INTO chat_messages (booking_id, sender_id, message) VALUES (?, ?, ?)");
        if ($stmt->execute([$bookingId, getUserId(), $message])) {
            $success = 'Message sent!';
            // Redirect to refresh
            header("Location: chat.php?booking_id=$bookingId");
            exit();
        } else {
            $error = 'Failed to send message';
        }
    }
}

$otherUserName = $isTeacher ? $booking['learner_name'] : $booking['teacher_name'];
$otherUserPicture = $isTeacher ? $booking['learner_picture'] : $booking['teacher_picture'];
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4" style="max-width: 800px;">
    <!-- Chat Header -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        Chat with <?php echo sanitize($otherUserName); ?>
                    </h5>
                    <small><?php echo sanitize($booking['skill_title']); ?></small>
                </div>
                <div class="col-auto">
                    <a href="bookings.php" class="btn btn-sm btn-light">
                        <i class="fas fa-times me-1"></i>Close
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Messages -->
    <div class="card" style="height: 500px; display: flex; flex-direction: column;">
        <div class="card-body" style="flex: 1; overflow-y: auto; background-color: #f8f9fa;">
            <?php if (empty($messages)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="mb-3 <?php echo $msg['sender_id'] == getUserId() ? 'text-end' : ''; ?>">
                        <div class="d-flex <?php echo $msg['sender_id'] == getUserId() ? 'justify-content-end' : ''; ?> align-items-end gap-2">
                            <?php if ($msg['sender_id'] != getUserId()): ?>
                            <img src="<?php echo asset($msg['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                                 alt="<?php echo sanitize($msg['sender_name']); ?>" 
                                 class="rounded-circle" style="width: 30px; height: 30px; object-fit: cover;">
                            <?php endif; ?>
                            <div style="max-width: 70%;">
                                <?php if ($msg['sender_id'] != getUserId()): ?>
                                <small class="text-muted d-block"><?php echo sanitize($msg['sender_name']); ?></small>
                                <?php endif; ?>
                                <div class="bg-<?php echo $msg['sender_id'] == getUserId() ? 'primary text-white' : 'white border'; ?> p-2 rounded">
                                    <p class="mb-0"><?php echo nl2br(sanitize($msg['message'])); ?></p>
                                </div>
                                <small class="text-muted d-block"><?php echo formatDateTime($msg['created_at']); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Message Input -->
        <div class="card-footer border-top">
            <form method="POST" action="">
                <div class="input-group">
                    <input type="text" class="form-control" name="message" placeholder="Type your message..." required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i>Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
