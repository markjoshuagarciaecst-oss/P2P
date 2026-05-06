<?php
require_once '../config/database.php';
require_once '../classes/Booking.php';
require_once '../classes/User.php';

$pageTitle = 'Chat';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=chat.php');
}

$bookingObj = new Booking();

// Get booking ID or user ID from URL
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$userId = getUserId();

// Get chat database and create table if needed
$db = Database::getInstance();
$db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
)");

$columnCheck = $db->query("SHOW COLUMNS FROM chat_messages LIKE 'receiver_id'");
if (!$columnCheck->fetch()) {
    $db->exec("ALTER TABLE chat_messages ADD COLUMN receiver_id INT NULL AFTER sender_id");
}
$db->exec("ALTER TABLE chat_messages MODIFY booking_id INT NULL");

// Build chat conversation list
$conversationStmt = $db->prepare("SELECT b.id AS booking_id,
       b.skill_id,
       s.title AS skill_title,
       b.teacher_id,
       b.learner_id,
       l.name AS learner_name,
       l.profile_picture AS learner_picture,
       t.name AS teacher_name,
       t.profile_picture AS teacher_picture,
       (SELECT cm.message FROM chat_messages cm WHERE cm.booking_id = b.id ORDER BY cm.created_at DESC LIMIT 1) AS last_message,
       (SELECT MAX(cm.created_at) FROM chat_messages cm WHERE cm.booking_id = b.id) AS last_activity
    FROM bookings b
    JOIN skills s ON s.id = b.skill_id
    JOIN users l ON l.id = b.learner_id
    JOIN users t ON t.id = b.teacher_id
    WHERE (b.teacher_id = ? OR b.learner_id = ?) AND b.status IN ('accepted', 'completed')
    ORDER BY last_activity DESC, b.created_at DESC");
$conversationStmt->execute([$userId, $userId]);
$chatConversations = $conversationStmt->fetchAll();

$booking = null;
$isTeacher = false;
$isLearner = false;
$messages = [];
$pendingChatUser = null;

if ($bookingId) {
    $booking = $bookingObj->getBookingById($bookingId);

    if ($booking && ($booking['teacher_id'] == $userId || $booking['learner_id'] == $userId) && in_array($booking['status'], ['accepted', 'completed'])) {
        $isTeacher = $booking['teacher_id'] == $userId;
        $isLearner = $booking['learner_id'] == $userId;
    } else {
        $booking = null;
    }
}

if (!$booking && $targetUserId) {
    $stmt = $db->prepare("SELECT id FROM bookings WHERE ((teacher_id = ? AND learner_id = ?) OR (teacher_id = ? AND learner_id = ?)) AND status IN ('accepted','completed') ORDER BY updated_at DESC, created_at DESC LIMIT 1");
    $stmt->execute([$userId, $targetUserId, $targetUserId, $userId]);
    $existingBooking = $stmt->fetch();
    if ($existingBooking) {
        $bookingId = $existingBooking['id'];
        $booking = $bookingObj->getBookingById($bookingId);
        if ($booking) {
            $isTeacher = $booking['teacher_id'] == $userId;
            $isLearner = $booking['learner_id'] == $userId;
        }
    } else {
        $userObj = new User();
        $pendingChatUser = $userObj->getUserById($targetUserId);
    }
}

// Default to the most recent conversation when no booking is selected
if (!$booking && !$pendingChatUser && !empty($chatConversations)) {
    $bookingId = $chatConversations[0]['booking_id'];
    $booking = $bookingObj->getBookingById($bookingId);
    if ($booking && ($booking['teacher_id'] == $userId || $booking['learner_id'] == $userId)) {
        $isTeacher = $booking['teacher_id'] == $userId;
        $isLearner = $booking['learner_id'] == $userId;
    }
}

if ($booking) {
    $stmt = $db->prepare("SELECT cm.*, u.name as sender_name, u.profile_picture FROM chat_messages cm 
                         JOIN users u ON cm.sender_id = u.id 
                         WHERE cm.booking_id = ? 
                         ORDER BY cm.created_at ASC");
    $stmt->execute([$bookingId]);
    $messages = $stmt->fetchAll();
} elseif ($pendingChatUser) {
    $stmt = $db->prepare("SELECT cm.*, u.name as sender_name, u.profile_picture FROM chat_messages cm 
                         JOIN users u ON cm.sender_id = u.id 
                         WHERE cm.booking_id IS NULL AND ((cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?)) 
                         ORDER BY cm.created_at ASC");
    $stmt->execute([$userId, $pendingChatUser['id'], $pendingChatUser['id'], $userId]);
    $messages = $stmt->fetchAll();
}

// Handle new message
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = sanitize($_POST['message'] ?? '');
    
    if (!empty($message)) {
        $receiverId = null;
        if ($booking) {
            $receiverId = $booking['teacher_id'] == $userId ? $booking['learner_id'] : $booking['teacher_id'];
        } elseif ($pendingChatUser) {
            $receiverId = $pendingChatUser['id'];
        }

        $stmt = $db->prepare("INSERT INTO chat_messages (booking_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
        $chatBookingId = $booking ? $bookingId : null;
        if ($stmt->execute([$chatBookingId, getUserId(), $receiverId, $message])) {
            $success = 'Message sent!';
            if ($booking) {
                header("Location: chat.php?booking_id=$bookingId");
            } elseif ($pendingChatUser) {
                header("Location: chat.php?user_id={$pendingChatUser['id']}");
            }
            exit();
        } else {
            $error = 'Failed to send message';
        }
    }
}

$otherUserName = '';
$otherUserPicture = '';
if ($booking) {
    $otherUserName = $isTeacher ? $booking['learner_name'] : $booking['teacher_name'];
    $otherUserPicture = $isTeacher ? $booking['learner_picture'] : $booking['teacher_picture'];
} elseif ($pendingChatUser) {
    $otherUserName = $pendingChatUser['name'];
    $otherUserPicture = $pendingChatUser['profile_picture'];
}
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Chats</h5>
                    <a href="<?php echo APP_URL; ?>/pages/bookings.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-plus me-1"></i>Bookings
                    </a>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($chatConversations)): ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p class="mb-0">No chat conversations yet.</p>
                            <small>Start a session from your bookings.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chatConversations as $conv): ?>
                            <?php $otherName = $conv['teacher_id'] == $userId ? $conv['learner_name'] : $conv['teacher_name']; ?>
                            <?php $activeClass = $conv['booking_id'] == $bookingId ? 'active' : ''; ?>
                            <a href="<?php echo APP_URL; ?>/pages/chat.php?booking_id=<?php echo $conv['booking_id']; ?>" class="list-group-item list-group-item-action <?php echo $activeClass; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 mb-0"><?php echo sanitize($otherName); ?></h6>
                                    <small class="text-muted"><?php echo $conv['last_activity'] ? formatDate($conv['last_activity']) : ''; ?></small>
                                </div>
                                <p class="mb-1 text-truncate text-muted" style="max-width: 100%;">
                                    <?php echo sanitize($conv['last_message'] ?: 'Session: ' . $conv['skill_title']); ?>
                                </p>
                                <small class="text-muted"><?php echo sanitize($conv['skill_title']); ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($booking): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Chat with <?php echo sanitize($otherUserName); ?></h5>
                            <small><?php echo sanitize($booking['skill_title']); ?></small>
                        </div>
                        <button id="startVideoCall" class="btn btn-success btn-sm">
                            <i class="fas fa-video me-1"></i>Video Call
                        </button>
                    </div>
                </div>
                <div class="card shadow-sm" style="height: 560px; display: flex; flex-direction: column;">
                    <div class="card-body" style="flex: 1; overflow-y: auto; background-color: #f8f9fa;">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <?php if ($pendingChatUser): ?>
                                    <h5>No active chat with <?php echo sanitize($pendingChatUser['name']); ?> yet.</h5>
                                    <p>Start a session with them first, then your chat will appear here.</p>
                                <?php else: ?>
                                    <p>No messages yet. Start the conversation!</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="mb-3 <?php echo $msg['sender_id'] == getUserId() ? 'text-end' : ''; ?>">
                                    <div class="d-flex <?php echo $msg['sender_id'] == getUserId() ? 'justify-content-end' : ''; ?> align-items-end gap-2">
                                        <?php if ($msg['sender_id'] != getUserId()): ?>
                                            <img src="<?php echo asset($msg['profile_picture'] ?? 'assets/images/default-avatar.png'); ?>" 
                                                 alt="<?php echo sanitize($msg['sender_name']); ?>" 
                                                 class="rounded-circle" style="width: 34px; height: 34px; object-fit: cover;">
                                        <?php endif; ?>
                                        <div style="max-width: 75%;">
                                            <?php if ($msg['sender_id'] != getUserId()): ?>
                                                <small class="text-muted d-block"><?php echo sanitize($msg['sender_name']); ?></small>
                                            <?php endif; ?>
                                            <div class="p-3 rounded shadow-sm <?php echo $msg['sender_id'] == getUserId() ? 'bg-primary text-white ms-auto' : 'bg-white text-dark'; ?>">
                                                <p class="mb-0"><?php echo nl2br(sanitize($msg['message'])); ?></p>
                                            </div>
                                            <small class="text-muted d-block mt-1"><?php echo formatDateTime($msg['created_at']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer border-top">
                        <form method="POST" action="<?php echo APP_URL; ?>/pages/chat.php?<?php echo $booking ? 'booking_id=' . $bookingId : ($pendingChatUser ? 'user_id=' . $pendingChatUser['id'] : ''); ?>">
                            <div class="input-group">
                                <input type="text" class="form-control" name="message" placeholder="Type your message..." required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                        <h4 class="mb-2">Select a conversation</h4>
                        <p class="text-muted">Your chat list appears on the left. Click a contact to open the conversation.</p>
                        <a href="<?php echo APP_URL; ?>/pages/bookings.php" class="btn btn-primary btn-sm">Start a session</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($booking): ?>
<!-- Video Call Section -->
<div id="videoCallSection" class="container py-4" style="max-width: 800px; display: none;">
    <div class="card">
        <div class="card-header bg-success text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">
                        <i class="fas fa-video me-2"></i>
                        Video Call with <?php echo sanitize($otherUserName); ?>
                    </h5>
                </div>
                <div class="col-auto">
                    <button id="endVideoCall" class="btn btn-danger btn-sm">
                        <i class="fas fa-phone-slash me-1"></i>End Call
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Your Video</h6>
                    <video id="localVideo" autoplay muted class="w-100 border rounded"></video>
                </div>
                <div class="col-md-6">
                    <h6><?php echo sanitize($otherUserName); ?>'s Video</h6>
                    <video id="remoteVideo" autoplay class="w-100 border rounded"></video>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startVideoCallBtn = document.getElementById('startVideoCall');
    const endVideoCallBtn = document.getElementById('endVideoCall');
    const videoCallSection = document.getElementById('videoCallSection');
    const localVideo = document.getElementById('localVideo');
    const remoteVideo = document.getElementById('remoteVideo');

    let localStream;
    let peer;
    let currentCall;

    // Initialize PeerJS
    const userId = <?php echo getUserId(); ?>;
    const otherUserId = <?php echo $isTeacher ? $booking['learner_id'] : $booking['teacher_id']; ?>;
    const peerId = `user_${userId}_booking_${<?php echo $bookingId; ?>}`;

    peer = new Peer(peerId);

    peer.on('open', function(id) {
        console.log('My peer ID is: ' + id);
    });

    peer.on('call', function(call) {
        // Answer the call
        navigator.mediaDevices.getUserMedia({video: true, audio: true})
            .then(function(stream) {
                localStream = stream;
                localVideo.srcObject = stream;
                call.answer(stream);
                currentCall = call;
                videoCallSection.style.display = 'block';
                startVideoCallBtn.style.display = 'none';
            })
            .catch(function(err) {
                console.error('Failed to get local stream', err);
                alert('Could not access camera/microphone');
            });

        call.on('stream', function(remoteStream) {
            remoteVideo.srcObject = remoteStream;
        });
    });

    startVideoCallBtn.addEventListener('click', function() {
        navigator.mediaDevices.getUserMedia({video: true, audio: true})
            .then(function(stream) {
                localStream = stream;
                localVideo.srcObject = stream;
                videoCallSection.style.display = 'block';
                startVideoCallBtn.style.display = 'none';

                // Call the other user
                const otherPeerId = `user_${otherUserId}_booking_${<?php echo $bookingId; ?>}`;
                const call = peer.call(otherPeerId, stream);
                currentCall = call;

                call.on('stream', function(remoteStream) {
                    remoteVideo.srcObject = remoteStream;
                });

                call.on('close', function() {
                    endCall();
                });
            })
            .catch(function(err) {
                console.error('Failed to get local stream', err);
                alert('Could not access camera/microphone. Please allow access and try again.');
            });
    });

    endVideoCallBtn.addEventListener('click', function() {
        endCall();
    });

    function endCall() {
        if (currentCall) {
            currentCall.close();
        }
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
        }
        localVideo.srcObject = null;
        remoteVideo.srcObject = null;
        videoCallSection.style.display = 'none';
        startVideoCallBtn.style.display = 'inline-block';
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
