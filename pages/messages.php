<?php
require_once '../config/database.php';
require_once '../classes/Booking.php';
require_once '../classes/User.php';

$pageTitle = 'Messages';

if (!isLoggedIn()) {
    redirect(APP_URL . '/pages/login.php');
}

$db     = Database::getInstance();
$userId = getUserId();

// Ensure chat_messages table exists
try {
    $db->query("SELECT 1 FROM chat_messages LIMIT 1");
} catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL
    )");
}

// ── Handle AJAX send ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    $bid  = (int)($_POST['b'] ?? 0);
    $msg  = trim($_POST['message'] ?? '');

    if (!$bid || !$msg) {
        echo json_encode(['ok' => false]);
        exit;
    }

    // Verify user belongs to this booking
    $s = $db->prepare("SELECT * FROM bookings WHERE id = ? AND (learner_id = ? OR teacher_id = ?)");
    $s->execute([$bid, $userId, $userId]);
    $bk = $s->fetch();

    if (!$bk) {
        echo json_encode(['ok' => false]);
        exit;
    }

    $receiverId = ($bk['teacher_id'] == $userId) ? $bk['learner_id'] : $bk['teacher_id'];
    $ins = $db->prepare("INSERT INTO chat_messages (booking_id, sender_id, receiver_id, message) VALUES (?,?,?,?)");
    $ins->execute([$bid, $userId, $receiverId, htmlspecialchars(trim($msg), ENT_QUOTES, 'UTF-8')]);
    $newId = $db->lastInsertId();

    echo json_encode(['ok' => true, 'id' => (int)$newId]);
    exit;
}

$bookingObj = new Booking();

$selectedBooking = isset($_GET['b']) ? (int)$_GET['b'] : 0;

// Load conversations
$convStmt = $db->prepare("
    SELECT b.id AS bid, s.title AS skill_title,
           b.teacher_id, b.learner_id,
           l.name AS learner_name, t.name AS teacher_name,
           (SELECT cm.message FROM chat_messages cm WHERE cm.booking_id = b.id ORDER BY cm.created_at DESC LIMIT 1) AS last_msg,
           (SELECT MAX(cm.created_at) FROM chat_messages cm WHERE cm.booking_id = b.id) AS last_at
    FROM bookings b
    JOIN skills s ON s.id = b.skill_id
    JOIN users  l ON l.id = b.learner_id
    JOIN users  t ON t.id = b.teacher_id
    WHERE (b.teacher_id = ? OR b.learner_id = ?)
      AND b.status IN ('accepted','completed')
    ORDER BY last_at DESC, b.created_at DESC
");
$convStmt->execute([$userId, $userId]);
$conversations = $convStmt->fetchAll();

if (!$selectedBooking && !empty($conversations)) {
    $selectedBooking = $conversations[0]['bid'];
}

$booking   = null;
$messages  = [];
$otherName = '';
$lastMsgId = 0;

if ($selectedBooking) {
    $booking = $bookingObj->getBookingById($selectedBooking);
    if ($booking && ($booking['teacher_id'] == $userId || $booking['learner_id'] == $userId)) {
        $otherName = ($booking['teacher_id'] == $userId) ? $booking['learner_name'] : $booking['teacher_name'];

        $msgStmt = $db->prepare("
            SELECT cm.*, u.name AS sender_name, u.profile_picture
            FROM chat_messages cm
            JOIN users u ON u.id = cm.sender_id
            WHERE cm.booking_id = ?
            ORDER BY cm.created_at ASC
        ");
        $msgStmt->execute([$selectedBooking]);
        $messages  = $msgStmt->fetchAll();
        $lastMsgId = !empty($messages) ? (int)end($messages)['id'] : 0;
    } else {
        $booking = null;
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="row g-4">

        <!-- Conversation list -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Messages</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($conversations)): ?>
                    <div class="list-group-item text-center text-muted py-4">
                        <i class="fas fa-comments fa-2x mb-2 d-block"></i>
                        No conversations yet.<br>
                        <small>Accept a booking to start chatting.</small>
                    </div>
                    <?php else: ?>
                    <?php foreach ($conversations as $c): ?>
                    <?php $name = ($c['teacher_id'] == $userId) ? $c['learner_name'] : $c['teacher_name']; ?>
                    <a href="<?php echo APP_URL; ?>/pages/messages.php?b=<?php echo $c['bid']; ?>"
                       class="list-group-item list-group-item-action <?php echo $c['bid'] == $selectedBooking ? 'active' : ''; ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo htmlspecialchars($name); ?></strong>
                            <small><?php echo $c['last_at'] ? formatDate($c['last_at']) : ''; ?></small>
                        </div>
                        <small class="text-truncate d-block">
                            <?php echo htmlspecialchars($c['last_msg'] ?: $c['skill_title']); ?>
                        </small>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chat window -->
        <div class="col-lg-8">
            <?php if ($booking): ?>
            <div class="card shadow-sm d-flex flex-column" style="height:580px">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        <?php echo htmlspecialchars($otherName); ?>
                    </h5>
                    <small><?php echo htmlspecialchars($booking['skill_title']); ?></small>
                </div>

                <!-- Messages box -->
                <div class="card-body overflow-auto flex-grow-1 bg-light" id="msgBox">
                    <?php if (empty($messages)): ?>
                    <div class="text-center text-muted py-5" id="emptyNote">
                        <i class="fas fa-comments fa-3x mb-3 d-block"></i>
                        No messages yet. Say hello!
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                    <?php $mine = ($m['sender_id'] == $userId); ?>
                    <div class="mb-3 <?php echo $mine ? 'text-end' : ''; ?>">
                        <?php if (!$mine): ?>
                        <small class="text-muted d-block"><?php echo htmlspecialchars($m['sender_name']); ?></small>
                        <?php endif; ?>
                        <div class="d-inline-block p-2 px-3 rounded shadow-sm <?php echo $mine ? 'bg-primary text-white' : 'bg-white'; ?>"
                             style="max-width:75%">
                            <?php echo nl2br(htmlspecialchars($m['message'])); ?>
                        </div>
                        <small class="text-muted d-block mt-1"><?php echo formatDateTime($m['created_at']); ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Send form -->
                <div class="card-footer border-top">
                    <form id="sendForm">
                        <div class="input-group">
                            <input type="text" class="form-control" id="msgInput"
                                   placeholder="Type a message…" autocomplete="off" required>
                            <button class="btn btn-primary" type="submit" id="sendBtn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            var APP_URL      = '<?php echo APP_URL; ?>';
            var bookingId    = <?php echo $selectedBooking; ?>;
            var currentUserId= <?php echo $userId; ?>;
            var lastId       = <?php echo $lastMsgId; ?>;
            var pollTimer;

            var box = document.getElementById('msgBox');

            function scrollBottom() {
                if (box) box.scrollTop = box.scrollHeight;
            }
            scrollBottom();

            // Build a message bubble HTML string
            function buildBubble(m) {
                var wrap = document.createElement('div');
                wrap.className = 'mb-3' + (m.mine ? ' text-end' : '');

                var nameHtml = m.mine ? '' :
                    '<small class="text-muted d-block">' + escHtml(m.sender_name) + '</small>';

                var bubbleClass = m.mine
                    ? 'bg-primary text-white'
                    : 'bg-white';

                wrap.innerHTML = nameHtml +
                    '<div class="d-inline-block p-2 px-3 rounded shadow-sm ' + bubbleClass + '" style="max-width:75%">' +
                        escHtml(m.message).replace(/\n/g, '<br>') +
                    '</div>' +
                    '<small class="text-muted d-block mt-1">' + escHtml(m.time) + '</small>';

                return wrap;
            }

            function escHtml(str) {
                return String(str)
                    .replace(/&/g,'&amp;')
                    .replace(/</g,'&lt;')
                    .replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;');
            }

            // Append new messages from API
            function appendMessages(msgs) {
                if (!msgs || !msgs.length) return;
                var empty = document.getElementById('emptyNote');
                if (empty) empty.remove();

                msgs.forEach(function(m) {
                    box.appendChild(buildBubble(m));
                    lastId = m.id;
                });
                scrollBottom();
            }

            // Poll for new messages every 3 seconds
            function poll() {
                fetch(APP_URL + '/api/messages.php?b=' + bookingId + '&after=' + lastId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) { appendMessages(data); })
                    .catch(function() {}) // silently ignore network errors
                    .finally(function() {
                        pollTimer = setTimeout(poll, 3000);
                    });
            }
            pollTimer = setTimeout(poll, 3000);

            // Send message via AJAX
            document.getElementById('sendForm').addEventListener('submit', function(e) {
                e.preventDefault();
                var input = document.getElementById('msgInput');
                var msg   = input.value.trim();
                if (!msg) return;

                var btn = document.getElementById('sendBtn');
                btn.disabled = true;
                input.value  = '';

                var fd = new FormData();
                fd.append('ajax_send', '1');
                fd.append('b',         bookingId);
                fd.append('message',   msg);

                fetch(APP_URL + '/pages/messages.php', {
                    method: 'POST',
                    body:   fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        // Immediately show own message without waiting for poll
                        var now = new Date();
                        var timeStr = now.toLocaleString('en-US', {
                            month:'short', day:'2-digit', year:'numeric',
                            hour:'2-digit', minute:'2-digit'
                        });
                        appendMessages([{
                            id:          data.id,
                            sender_id:   currentUserId,
                            sender_name: 'You',
                            mine:        true,
                            message:     msg,
                            time:        timeStr
                        }]);
                    } else {
                        // Put message back if send failed
                        input.value = msg;
                    }
                })
                .catch(function() { input.value = msg; })
                .finally(function() { btn.disabled = false; input.focus(); });
            });
            </script>

            <?php else: ?>
            <div class="card shadow-sm text-center py-5">
                <div class="card-body">
                    <i class="fas fa-comments fa-3x text-muted mb-3 d-block"></i>
                    <h5>Select a conversation</h5>
                    <p class="text-muted">Pick a chat from the left to open it.</p>
                    <a href="<?php echo APP_URL; ?>/pages/bookings.php" class="btn btn-primary btn-sm">
                        View Bookings
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
