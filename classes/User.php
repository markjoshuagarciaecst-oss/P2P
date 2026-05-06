<?php
// User Class - handles user authentication and profile management

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Register new user
    public function register($name, $email, $password) {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt->execute([$name, $email, $hashedPassword]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Login user
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_points'] = $user['points'];
            return $user;
        }
        return false;
    }
    
    // Get user by ID
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Update user profile
    public function updateProfile($userId, $name, $bio, $profilePicture = null) {
        if ($profilePicture) {
            $stmt = $this->db->prepare("UPDATE users SET name = ?, bio = ?, profile_picture = ? WHERE id = ?");
            $stmt->execute([$name, $bio, $profilePicture, $userId]);
        } else {
            $stmt = $this->db->prepare("UPDATE users SET name = ?, bio = ? WHERE id = ?");
            $stmt->execute([$name, $bio, $userId]);
        }
        return true;
    }
    
    // Get all users
    public function getAllUsers($limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("SELECT id, name, email, bio, profile_picture, points, average_rating, total_reviews, created_at FROM users WHERE role = 'user' AND is_active = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    // Search users by skill
    public function searchBySkill($skillTitle) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.*, s.title as skill_title 
            FROM users u 
            INNER JOIN skills s ON u.id = s.user_id 
            WHERE s.title LIKE ? AND s.is_active = 1 AND u.is_active = 1
        ");
        $stmt->execute(["%$skillTitle%"]);
        return $stmt->fetchAll();
    }

    // Search users by name, email, bio, or skill title
    public function search($query, $limit = 50, $offset = 0) {
        $term = "%$query%";
        $stmt = $this->db->prepare("SELECT DISTINCT u.*, 
            (SELECT COUNT(*) FROM skills s WHERE s.user_id = u.id AND s.is_active = 1) AS skills_count
            FROM users u
            LEFT JOIN skills s ON u.id = s.user_id
            WHERE u.role = 'user' AND u.is_active = 1
            AND (
                u.name LIKE ? OR 
                u.email LIKE ? OR 
                u.bio LIKE ? OR 
                s.title LIKE ?
            )
            ORDER BY u.name ASC
            LIMIT ? OFFSET ?");
        $stmt->execute([$term, $term, $term, $term, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    // Update points
    public function updatePoints($userId, $amount, $type) {
        if ($type === 'earned' || $type === 'bonus') {
            $stmt = $this->db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        } else {
            $stmt = $this->db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        }
        $stmt->execute([$amount, $userId]);
        
        // Refresh session points
        if ($userId == getUserId()) {
            $_SESSION['user_points'] = $this->getUserById($userId)['points'];
        }
        return true;
    }
    
    // Get user stats
    public function getUserStats($userId) {
        $stats = [
            'skills_offered' => 0,
            'skills_wanted' => 0,
            'sessions_completed' => 0,
            'total_earned' => 0,
            'total_spent' => 0
        ];
        
        // Count skills offered
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM skills WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $stats['skills_offered'] = $stmt->fetch()['count'];
        
        // Count skills wanted
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM skill_requests WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $stats['skills_wanted'] = $stmt->fetch()['count'];
        
        // Count completed sessions
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM bookings WHERE (learner_id = ? OR teacher_id = ?) AND status = 'completed'");
        $stmt->execute([$userId, $userId]);
        $stats['sessions_completed'] = $stmt->fetch()['count'];
        
        // Total earned
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM point_transactions WHERE user_id = ? AND type = 'earned'");
        $stmt->execute([$userId]);
        $stats['total_earned'] = $stmt->fetch()['total'];
        
        // Total spent
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM point_transactions WHERE user_id = ? AND type = 'spent'");
        $stmt->execute([$userId]);
        $stats['total_spent'] = $stmt->fetch()['total'];
        
        return $stats;
    }
    
    // Logout
    public function logout() {
        session_destroy();
        redirect('index.php');
    }
}