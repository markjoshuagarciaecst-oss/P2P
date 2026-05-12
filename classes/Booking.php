<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Transaction.php';
require_once __DIR__ . '/Notification.php';

// Booking Class - handles session bookings and scheduling

class Booking {
    private $db;

    // Track which new columns actually exist so queries never crash
    private $hasDuration  = false;
    private $hasConfirmed = false;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->detectColumns();
    }

    // Check once whether the new columns have been migrated yet
    private function detectColumns() {
        try {
            $s = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'bookings'
                   AND COLUMN_NAME  = 'session_duration'"
            );
            $s->execute();
            $this->hasDuration = (bool)$s->fetchColumn();

            $s = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'bookings'
                   AND COLUMN_NAME  = 'teacher_confirmed'"
            );
            $s->execute();
            $this->hasConfirmed = (bool)$s->fetchColumn();
        } catch (PDOException $e) {
            // If information_schema is unavailable just leave flags false
        }
    }

    // Build the duration / confirmed fragment for SELECT queries
    private function durationSelect() {
        if ($this->hasDuration) {
            return "b.session_duration,
                   (s.points_required * b.session_duration) as total_points";
        }
        return "1 as session_duration,
                   s.points_required as total_points";
    }

    private function confirmedSelect() {
        if ($this->hasConfirmed) {
            return "b.teacher_confirmed, b.learner_confirmed";
        }
        return "0 as teacher_confirmed, 0 as learner_confirmed";
    }

    // -------------------------------------------------------------------------
    // Create booking request
    // -------------------------------------------------------------------------
    public function create($learnerId, $teacherId, $skillId, $scheduledDate, $scheduledTime, $sessionDuration = 1) {
        $sessionDuration = max(1, (int)$sessionDuration);

        // Enforce teacher's max session hours if column exists
        try {
            $s = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'skills'
                   AND COLUMN_NAME  = 'max_session_hours'"
            );
            $s->execute();
            if ($s->fetchColumn()) {
                $s2 = $this->db->prepare("SELECT max_session_hours FROM skills WHERE id = ?");
                $s2->execute([$skillId]);
                $row = $s2->fetch();
                if ($row) {
                    $sessionDuration = min($sessionDuration, max(1, (int)$row['max_session_hours']));
                }
            }
        } catch (PDOException $e) { /* ignore */ }

        if ($this->hasDuration) {
            $stmt = $this->db->prepare("
                INSERT INTO bookings (learner_id, teacher_id, skill_id, scheduled_date, scheduled_time, session_duration)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            try {
                $stmt->execute([$learnerId, $teacherId, $skillId, $scheduledDate, $scheduledTime, $sessionDuration]);
            } catch (PDOException $e) {
                return false;
            }
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO bookings (learner_id, teacher_id, skill_id, scheduled_date, scheduled_time)
                VALUES (?, ?, ?, ?, ?)
            ");
            try {
                $stmt->execute([$learnerId, $teacherId, $skillId, $scheduledDate, $scheduledTime]);
            } catch (PDOException $e) {
                return false;
            }
        }

        $bookingId = $this->db->lastInsertId();

        // Notify teacher of the new booking request
        $booking = $this->getBookingById($bookingId);
        if ($booking) {
            $notification = new Notification();
            $notification->create(
                $teacherId,
                'New Booking Request',
                "{$booking['learner_name']} has requested a session for '{$booking['skill_title']}' on " . date('M d, Y', strtotime($booking['scheduled_date'])) . ".",
                'booking_request'
            );
        }

        return $bookingId;
    }

    // -------------------------------------------------------------------------
    // Get booking by ID
    // -------------------------------------------------------------------------
    public function getBookingById($id) {
        $dur  = $this->durationSelect();
        $conf = $this->confirmedSelect();
        $stmt = $this->db->prepare("
            SELECT b.*,
                   s.title as skill_title, s.points_required,
                   $dur,
                   $conf,
                   l.name as learner_name, l.profile_picture as learner_picture,
                   t.name as teacher_name, t.profile_picture as teacher_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users l ON b.learner_id = l.id
            INNER JOIN users t ON b.teacher_id = t.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // -------------------------------------------------------------------------
    // Get user's bookings (as learner)
    // -------------------------------------------------------------------------
    public function getLearnerBookings($userId, $status = null) {
        $dur  = $this->durationSelect();
        $conf = $this->confirmedSelect();
        $sql = "
            SELECT b.*, s.title as skill_title, s.points_required,
                   $dur,
                   $conf,
                   t.name as teacher_name, t.profile_picture as teacher_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users t ON b.teacher_id = t.id
            WHERE b.learner_id = ?
        ";
        $params = [$userId];
        if ($status) { $sql .= " AND b.status = ?"; $params[] = $status; }
        $sql .= " ORDER BY b.scheduled_date DESC, b.scheduled_time DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Get user's bookings (as teacher)
    // -------------------------------------------------------------------------
    public function getTeacherBookings($userId, $status = null) {
        $dur  = $this->durationSelect();
        $conf = $this->confirmedSelect();
        $sql = "
            SELECT b.*, s.title as skill_title, s.points_required,
                   $dur,
                   $conf,
                   l.name as learner_name, l.profile_picture as learner_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users l ON b.learner_id = l.id
            WHERE b.teacher_id = ?
        ";
        $params = [$userId];
        if ($status) { $sql .= " AND b.status = ?"; $params[] = $status; }
        $sql .= " ORDER BY b.scheduled_date DESC, b.scheduled_time DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Get all bookings for a user (both as learner and teacher)
    // -------------------------------------------------------------------------
    public function getAllUserBookings($userId) {
        $stmt = $this->db->prepare("
            SELECT b.*, s.title as skill_title,
                   CASE WHEN b.learner_id = ? THEN t.name  ELSE l.name  END as other_party_name,
                   CASE WHEN b.learner_id = ? THEN t.profile_picture ELSE l.profile_picture END as other_party_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users l ON b.learner_id = l.id
            INNER JOIN users t ON b.teacher_id = t.id
            WHERE b.learner_id = ? OR b.teacher_id = ?
            ORDER BY b.scheduled_date DESC, b.scheduled_time DESC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Accept booking
    // -------------------------------------------------------------------------
    public function accept($bookingId, $teacherId) {
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'accepted' WHERE id = ? AND teacher_id = ? AND status = 'pending'");
        $stmt->execute([$bookingId, $teacherId]);
        if ($stmt->rowCount() > 0) {
            $booking = $this->getBookingById($bookingId);
            $notification = new Notification();
            $notification->create(
                $booking['learner_id'],
                'Booking Accepted',
                "{$booking['teacher_name']} accepted your session request for '{$booking['skill_title']}' on " . date('M d, Y', strtotime($booking['scheduled_date'])) . ". You can now chat with your teacher.",
                'booking_accepted'
            );
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Reject booking
    // -------------------------------------------------------------------------
    public function reject($bookingId, $teacherId) {
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ? AND teacher_id = ? AND status = 'pending'");
        $stmt->execute([$bookingId, $teacherId]);
        if ($stmt->rowCount() > 0) {
            $booking = $this->getBookingById($bookingId);
            $notification = new Notification();
            $notification->create(
                $booking['learner_id'],
                'Booking Rejected',
                "{$booking['teacher_name']} declined your session request for '{$booking['skill_title']}'. You can browse other teachers for this skill.",
                'booking_rejected'
            );
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Teacher marks session as complete — sends notification to learner to confirm
    // Points do NOT transfer here. Transfer only happens when learner confirms.
    // -------------------------------------------------------------------------
    public function teacherConfirm($bookingId, $teacherId) {
        $booking = $this->getBookingById($bookingId);
        if (!$booking || $booking['teacher_id'] != $teacherId || $booking['status'] != 'accepted') {
            return false;
        }

        // Record teacher's confirmation flag if column exists
        if ($this->hasConfirmed) {
            $this->db->prepare("UPDATE bookings SET teacher_confirmed = 1 WHERE id = ?")
                     ->execute([$bookingId]);
        }

        // Always notify the learner — they are the final decision-maker
        $notification = new Notification();
        $notification->create(
            $booking['learner_id'],
            'Session Completion Requested',
            "{$booking['teacher_name']} has marked your '{$booking['skill_title']}' session as complete. Please confirm to transfer points.",
            'session_completion_requested',
            $bookingId
        );

        return true;
    }

    // -------------------------------------------------------------------------
    // Learner confirms session complete — THIS is the final trigger for points transfer
    // -------------------------------------------------------------------------
    public function learnerConfirm($bookingId, $learnerId) {
        $booking = $this->getBookingById($bookingId);
        if (!$booking || $booking['learner_id'] != $learnerId || $booking['status'] != 'accepted') {
            return false;
        }

        // Record learner's confirmation flag if column exists
        if ($this->hasConfirmed) {
            $this->db->prepare("UPDATE bookings SET learner_confirmed = 1 WHERE id = ?")
                     ->execute([$bookingId]);
        }

        // Learner confirming is always the final step — transfer points now
        return $this->complete($bookingId, $booking['teacher_id']);
    }

    // -------------------------------------------------------------------------
    // Complete booking and transfer points
    // Called only when both parties have confirmed (or migration not run)
    // -------------------------------------------------------------------------
    public function complete($bookingId, $teacherId) {
        $booking = $this->getBookingById($bookingId);

        if (!$booking) { error_log("Booking not found: $bookingId"); return false; }
        if ($booking['teacher_id'] != $teacherId) { error_log("Teacher ID mismatch"); return false; }
        if ($booking['status'] != 'accepted') { error_log("Booking not accepted: {$booking['status']}"); return false; }

        $points    = (int)$booking['points_required'] * max(1, (int)($booking['session_duration'] ?? 1));
        $learnerId = $booking['learner_id'];

        $user    = new User();
        $learner = $user->getUserById($learnerId);
        if (!$learner) { error_log("Learner not found: $learnerId"); return false; }
        if ($learner['points'] < $points) {
            error_log("Learner {$learnerId} has {$learner['points']} points, needs $points");
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE users SET points = points - ? WHERE id = ?")->execute([$points, $learnerId]);
            $this->db->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points, $teacherId]);
            $this->db->prepare("UPDATE bookings SET status = 'completed', points_transferred = 1 WHERE id = ?")->execute([$bookingId]);

            $transaction = new Transaction();
            $transaction->create($learnerId, $bookingId, $points, 'spent',  "Learning: {$booking['skill_title']}");
            $transaction->create($teacherId, $bookingId, $points, 'earned', "Teaching: {$booking['skill_title']}");

            $notification = new Notification();
            $notification->create(
                $learnerId,
                'Session Completed',
                "Your '{$booking['skill_title']}' session with {$booking['teacher_name']} is complete. {$points} points have been deducted from your balance.",
                'session_completed'
            );
            $notification->create(
                $teacherId,
                'Session Completed',
                "Your '{$booking['skill_title']}' session with {$booking['learner_name']} is complete. You earned {$points} points!",
                'session_completed'
            );

            // Refresh session points for whoever is currently logged in
            $currentUserId = getUserId();
            if ($currentUserId == $learnerId || $currentUserId == $teacherId) {
                $updated = $user->getUserById($currentUserId);
                if ($updated) $_SESSION['user_points'] = $updated['points'];
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Transaction failed: " . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Cancel booking
    // -------------------------------------------------------------------------
    public function cancel($bookingId, $userId) {
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND (learner_id = ? OR teacher_id = ?) AND status IN ('pending', 'accepted')");
        $stmt->execute([$bookingId, $userId, $userId]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------
    // Get upcoming sessions
    // -------------------------------------------------------------------------
    public function getUpcomingSessions($userId, $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT b.*, s.title as skill_title,
                   CASE WHEN b.learner_id = ? THEN t.name ELSE l.name END as other_party_name
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users l ON b.learner_id = l.id
            INNER JOIN users t ON b.teacher_id = t.id
            WHERE (b.learner_id = ? OR b.teacher_id = ?)
              AND b.status IN ('pending', 'accepted')
              AND (b.scheduled_date > CURDATE() OR (b.scheduled_date = CURDATE() AND b.scheduled_time > CURTIME()))
            ORDER BY b.scheduled_date ASC, b.scheduled_time ASC LIMIT ?
        ");
        $stmt->execute([$userId, $userId, $userId, $limit]);
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // Get pending requests (for teachers)
    // -------------------------------------------------------------------------
    public function getPendingRequests($teacherId) {
        $dur = $this->durationSelect();
        $stmt = $this->db->prepare("
            SELECT b.*, s.title as skill_title, s.points_required,
                   $dur,
                   l.name as learner_name, l.profile_picture as learner_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users l ON b.learner_id = l.id
            WHERE b.teacher_id = ? AND b.status = 'pending'
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }
}
