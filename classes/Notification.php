<?php
// Notification Class - handles user notifications

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create notification
    public function create($userId, $title, $message, $type = 'general') {
        $stmt = $this->db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        
        try {
            $stmt->execute([$userId, $title, $message, $type]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get user's notifications
    public function getUserNotifications($userId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    // Get unread notifications count
    public function getUnreadCount($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return $stmt->fetch()['count'];
    }
    
    // Mark notification as read
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    // Mark all as read
    public function markAllAsRead($userId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        return true;
    }
    
    // Delete notification
    public function delete($notificationId, $userId) {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }
    
    // Get recent notifications (for dropdown)
    public function getRecentNotifications($userId, $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}