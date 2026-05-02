<?php
// Review Class - handles reviews and ratings

class Review {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create review
    public function create($bookingId, $reviewerId, $revieweeId, $rating, $comment) {
        $stmt = $this->db->prepare("INSERT INTO reviews (booking_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$bookingId, $reviewerId, $revieweeId, $rating, $comment]);
            
            // Update user's average rating
            $this->updateUserRating($revieweeId);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Update user's average rating
    private function updateUserRating($userId) {
        $stmt = $this->db->prepare("
            UPDATE users u 
            SET u.average_rating = (
                SELECT AVG(rating) FROM reviews WHERE reviewee_id = ?
            ),
            u.total_reviews = (
                SELECT COUNT(*) FROM reviews WHERE reviewee_id = ?
            )
            WHERE u.id = ?
        ");
        $stmt->execute([$userId, $userId, $userId]);
    }
    
    // Get reviews for a user
    public function getUserReviews($userId, $limit = 10, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.name as reviewer_name, u.profile_picture as reviewer_picture, b.skill_id
            FROM reviews r
            INNER JOIN users u ON r.reviewer_id = u.id
            INNER JOIN bookings b ON r.booking_id = b.id
            WHERE r.reviewee_id = ?
            ORDER BY r.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    // Get review by booking
    public function getReviewByBooking($bookingId, $reviewerId) {
        $stmt = $this->db->prepare("SELECT * FROM reviews WHERE booking_id = ? AND reviewer_id = ?");
        $stmt->execute([$bookingId, $reviewerId]);
        return $stmt->fetch();
    }
    
    // Check if user can review
    public function canReview($bookingId, $reviewerId) {
        $stmt = $this->db->prepare("
            SELECT * FROM bookings 
            WHERE id = ? AND status = 'completed' 
            AND (learner_id = ? OR teacher_id = ?)
        ");
        $stmt->execute([$bookingId, $reviewerId, $reviewerId]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            return false;
        }
        
        // Check if already reviewed
        $existingReview = $this->getReviewByBooking($bookingId, $reviewerId);
        return !$existingReview;
    }
    
    // Get average rating
    public function getAverageRating($userId) {
        $stmt = $this->db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE reviewee_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    // Get rating distribution
    public function getRatingDistribution($userId) {
        $stmt = $this->db->prepare("
            SELECT rating, COUNT(*) as count 
            FROM reviews 
            WHERE reviewee_id = ? 
            GROUP BY rating 
            ORDER BY rating DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}