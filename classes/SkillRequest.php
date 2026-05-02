<?php
// SkillRequest Class - handles skill requests (what users want to learn)

class SkillRequest {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create skill request
    public function create($userId, $title, $description, $category, $skillLevel) {
        $stmt = $this->db->prepare("INSERT INTO skill_requests (user_id, title, description, category, skill_level) VALUES (?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$userId, $title, $description, $category, $skillLevel]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get skill request by ID
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT sr.*, u.name as user_name, u.profile_picture 
            FROM skill_requests sr 
            INNER JOIN users u ON sr.user_id = u.id 
            WHERE sr.id = ? AND sr.is_active = 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Get user's skill requests
    public function getUserRequests($userId) {
        $stmt = $this->db->prepare("SELECT * FROM skill_requests WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    // Get all skill requests with pagination
    public function getAllRequests($category = null, $limit = 20, $offset = 0) {
        $sql = "
            SELECT sr.*, u.name as user_name, u.profile_picture 
            FROM skill_requests sr 
            INNER JOIN users u ON sr.user_id = u.id 
            WHERE sr.is_active = 1 AND u.is_active = 1
        ";
        $params = [];
        
        if ($category) {
            $sql .= " AND sr.category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY sr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Update skill request
    public function update($requestId, $userId, $title, $description, $category, $skillLevel) {
        $stmt = $this->db->prepare("UPDATE skill_requests SET title = ?, description = ?, category = ?, skill_level = ? WHERE id = ? AND user_id = ?");
        
        try {
            $stmt->execute([$title, $description, $category, $skillLevel, $requestId, $userId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Delete skill request
    public function delete($requestId, $userId) {
        $stmt = $this->db->prepare("UPDATE skill_requests SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$requestId, $userId]);
        return true;
    }
    
    // Search skill requests
    public function search($query, $category = null) {
        $sql = "
            SELECT sr.*, u.name as user_name, u.profile_picture 
            FROM skill_requests sr 
            INNER JOIN users u ON sr.user_id = u.id 
            WHERE sr.is_active = 1 AND u.is_active = 1 
            AND (sr.title LIKE ? OR sr.description LIKE ?)
        ";
        $params = ["%$query%", "%$query%"];
        
        if ($category) {
            $sql .= " AND sr.category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY sr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Find matching skills for a request
    public function findMatchingSkills($requestId) {
        $request = $this->getById($requestId);
        
        if (!$request) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT s.*, u.name as teacher_name, u.profile_picture, u.average_rating 
            FROM skills s 
            INNER JOIN users u ON s.user_id = u.id 
            WHERE s.is_active = 1 AND u.is_active = 1 
            AND s.category = ? 
            AND s.skill_level = ?
            AND s.user_id != ?
            AND (s.title LIKE ? OR s.description LIKE ?)
            ORDER BY u.average_rating DESC
        ");
        
        $searchTerm = "%{$request['title']}%";
        $stmt->execute([$request['category'], $request['skill_level'], $request['user_id'], $searchTerm, $searchTerm]);
        return $stmt->fetchAll();
    }
}