<?php
// Notification Class

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a notification.
     * Always tries to store booking_id; silently falls back if column missing.
     */
    public function create($userId, $title, $message, $type = 'general', $bookingId = null) {
        $userId    = (int)$userId;
        $bookingId = ($bookingId && (int)$bookingId > 0) ? (int)$bookingId : null;

        // Try with booking_id first
        try {
            $this->db->prepare(
                "INSERT INTO notifications (user_id, booking_id, title, message, type)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$userId, $bookingId, $title, $message, $type]);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            // Fall back without booking_id (column may not exist)
            try {
                $this->db->prepare(
                    "INSERT INTO notifications (user_id, title, message, type)
                     VALUES (?, ?, ?, ?)"
                )->execute([$userId, $title, $message, $type]);
                return (int)$this->db->lastInsertId();
            } catch (PDOException $e2) {
                error_log("Notification::create failed: " . $e2->getMessage());
                return false;
            }
        }
    }

    public function getUserNotifications($userId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([(int)$userId, (int)$limit, (int)$offset]);
        return $stmt->fetchAll();
    }

    public function getUnreadCount($userId) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0"
        );
        $stmt->execute([(int)$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsRead($notificationId, $userId) {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([(int)$notificationId, (int)$userId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllAsRead($userId) {
        $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
                 ->execute([(int)$userId]);
        return true;
    }

    public function delete($notificationId, $userId) {
        $stmt = $this->db->prepare(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([(int)$notificationId, (int)$userId]);
        return $stmt->rowCount() > 0;
    }

    public function getRecentNotifications($userId, $limit = 5) {
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([(int)$userId, (int)$limit]);
        return $stmt->fetchAll();
    }
}
