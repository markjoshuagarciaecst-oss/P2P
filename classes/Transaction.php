<?php
// Transaction Class - handles point transactions

class Transaction {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create transaction record
    public function create($userId, $bookingId, $amount, $type, $description) {
        $stmt = $this->db->prepare("INSERT INTO point_transactions (user_id, booking_id, amount, type, description) VALUES (?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$userId, $bookingId, $amount, $type, $description]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get user's transaction history
    public function getUserTransactions($userId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT * FROM point_transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    // Get transaction by ID
    public function getTransactionById($id) {
        $stmt = $this->db->prepare("SELECT * FROM point_transactions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Get transaction by booking
    public function getTransactionByBooking($bookingId, $userId) {
        $stmt = $this->db->prepare("SELECT * FROM point_transactions WHERE booking_id = ? AND user_id = ?");
        $stmt->execute([$bookingId, $userId]);
        return $stmt->fetchAll();
    }
    
    // Get total earned
    public function getTotalEarned($userId) {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM point_transactions WHERE user_id = ? AND type = 'earned'");
        $stmt->execute([$userId]);
        return $stmt->fetch()['total'];
    }
    
    // Get total spent
    public function getTotalSpent($userId) {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM point_transactions WHERE user_id = ? AND type = 'spent'");
        $stmt->execute([$userId]);
        return $stmt->fetch()['total'];
    }
    
    // Get recent transactions
    public function getRecentTransactions($userId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM point_transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}