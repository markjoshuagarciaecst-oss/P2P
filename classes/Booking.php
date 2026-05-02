<?php
// Booking Class - handles session bookings and scheduling

class Booking {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create booking request
    public function create($learnerId, $teacherId, $skillId, $scheduledDate, $scheduledTime) {
        $stmt = $this->db->prepare("
            INSERT INTO bookings (learner_id, teacher_id, skill_id, scheduled_date, scheduled_time) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([$learnerId, $teacherId, $skillId, $scheduledDate, $scheduledTime]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get booking by ID
    public function getBookingById($id) {
        $stmt = $this->db->prepare("
            SELECT b.*, 
                   s.title as skill_title, s.points_required,
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
    
    // Get user's bookings (as learner)
    public function getLearnerBookings($userId, $status = null) {
        $sql = "
            SELECT b.*, s.title as skill_title, s.points_required,
                   t.name as teacher_name, t.profile_picture as teacher_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users t ON b.teacher_id = t.id
            WHERE b.learner_id = ?
        ";
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY b.scheduled_date DESC, b.scheduled_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Get user's bookings (as teacher)
    public function getTeacherBookings($userId, $status = null) {
        $sql = "
            SELECT b.*, s.title as skill_title, s.points_required,
                   l.name as learner_name, l.profile_picture as learner_picture
            FROM bookings b
            INNER JOIN skills s ON b.skill_id = s.id
            INNER JOIN users l ON b.learner_id = l.id
            WHERE b.teacher_id = ?
        ";
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY b.scheduled_date DESC, b.scheduled_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Get all bookings for a user (both as learner and teacher)
    public function getAllUserBookings($userId) {
        $stmt = $this->db->prepare("
            SELECT b.*, s.title as skill_title,
                   CASE 
                       WHEN b.learner_id = ? THEN t.name
                       ELSE l.name
                   END as other_party_name,
                   CASE 
                       WHEN b.learner_id = ? THEN t.profile_picture
                       ELSE l.profile_picture
                   END as other_party_picture
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
    
    // Accept booking
    public function accept($bookingId, $teacherId) {
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'accepted' WHERE id = ? AND teacher_id = ? AND status = 'pending'");
        $stmt->execute([$bookingId, $teacherId]);
        
        if ($stmt->rowCount() > 0) {
            // Create notification
            $booking = $this->getBookingById($bookingId);
            $notification = new Notification();
            $notification->create($booking['learner_id'], 'Booking Accepted', "Your session request for '{$booking['skill_title']}' has been accepted!", 'booking_accepted');
            return true;
        }
        return false;
    }
    
    // Reject booking
    public function reject($bookingId, $teacherId) {
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ? AND teacher_id = ? AND status = 'pending'");
        $stmt->execute([$bookingId, $teacherId]);
        
        if ($stmt->rowCount() > 0) {
            $booking = $this->getBookingById($bookingId);
            $notification = new Notification();
            $notification->create($booking['learner_id'], 'Booking Rejected', "Your session request for '{$booking['skill_title']}' has been rejected.", 'booking_rejected');
            return true;
        }
        return false;
    }
    
    // Complete booking and transfer points
    public function complete($bookingId, $teacherId) {
        $booking = $this->getBookingById($bookingId);
        
        if (!$booking || $booking['teacher_id'] != $teacherId || $booking['status'] != 'accepted') {
            return false;
        }
        
        $points = $booking['points_required'];
        $learnerId = $booking['learner_id'];
        
        // Check if learner has enough points
        $user = new User();
        $learner = $user->getUserById($learnerId);
        
        if ($learner['points'] < $points) {
            return false;
        }
        
        // Transfer points
        $this->db->beginTransaction();
        
        try {
            // Deduct from learner
            $stmt = $this->db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->execute([$points, $learnerId]);
            
            // Add to teacher
            $stmt = $this->db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->execute([$points, $teacherId]);
            
            // Update booking status
            $stmt = $this->db->prepare("UPDATE bookings SET status = 'completed', points_transferred = 1 WHERE id = ?");
            $stmt->execute([$bookingId]);
            
            // Record transactions
            $transaction = new Transaction();
            $transaction->create($learnerId, $bookingId, $points, 'spent', "Learning: {$booking['skill_title']}");
            $transaction->create($teacherId, $bookingId, $points, 'earned', "Teaching: {$booking['skill_title']}");
            
            // Create notifications
            $notification = new Notification();
            $notification->create($learnerId, 'Session Completed', "Your session for '{$booking['skill_title']}' has been completed. {$points} points have been transferred.", 'session_completed');
            $notification->create($teacherId, 'Session Completed', "Your session for '{$booking['skill_title']}' has been completed. You earned {$points} points!", 'session_completed');
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    // Cancel booking
    public function cancel($bookingId, $userId) {
        $stmt = $this->db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND (learner_id = ? OR teacher_id = ?) AND status IN ('pending', 'accepted')");
        $stmt->execute([$bookingId, $userId, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    // Get upcoming sessions
    public function getUpcomingSessions($userId, $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT b.*, s.title as skill_title,
                   CASE 
                       WHEN b.learner_id = ? THEN t.name
                       ELSE l.name
                   END as other_party_name
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
    
    // Get pending requests (for teachers)
    public function getPendingRequests($teacherId) {
        $stmt = $this->db->prepare("
            SELECT b.*, s.title as skill_title, s.points_required,
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