<?php
// Skill Class - handles skill listings and requests

class Skill {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Create new skill listing
    public function create($userId, $title, $description, $category, $skillLevel, $pointsRequired) {
        $stmt = $this->db->prepare("INSERT INTO skills (user_id, title, description, category, skill_level, points_required) VALUES (?, ?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$userId, $title, $description, $category, $skillLevel, $pointsRequired]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get skill by ID
    public function getSkillById($id) {
        $stmt = $this->db->prepare("
            SELECT s.*, u.name as teacher_name, u.profile_picture, u.average_rating, u.points as teacher_points 
            FROM skills s 
            INNER JOIN users u ON s.user_id = u.id 
            WHERE s.id = ? AND s.is_active = 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Get all skills with pagination
    public function getAllSkills($category = null, $level = null, $limit = 20, $offset = 0) {
        $sql = "SELECT s.*, u.name as teacher_name, u.profile_picture, u.average_rating 
                FROM skills s 
                INNER JOIN users u ON s.user_id = u.id 
                WHERE s.is_active = 1 AND u.is_active = 1";
        $params = [];
        
        if ($category) {
            $sql .= " AND s.category = ?";
            $params[] = $category;
        }
        
        if ($level) {
            $sql .= " AND s.skill_level = ?";
            $params[] = $level;
        }
        
        $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Get skills by user
    public function getUserSkills($userId) {
        $stmt = $this->db->prepare("SELECT * FROM skills WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    // Update skill
    public function update($skillId, $userId, $title, $description, $category, $skillLevel, $pointsRequired) {
        $stmt = $this->db->prepare("UPDATE skills SET title = ?, description = ?, category = ?, skill_level = ?, points_required = ? WHERE id = ? AND user_id = ?");
        
        try {
            $stmt->execute([$title, $description, $category, $skillLevel, $pointsRequired, $skillId, $userId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Delete skill
    public function delete($skillId, $userId) {
        $stmt = $this->db->prepare("UPDATE skills SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$skillId, $userId]);
        return true;
    }
    
    // Search skills
    public function search($query, $category = null) {
        $sql = "
            SELECT s.*, u.name as teacher_name, u.profile_picture, u.average_rating 
            FROM skills s 
            INNER JOIN users u ON s.user_id = u.id 
            WHERE s.is_active = 1 AND u.is_active = 1 
            AND (s.title LIKE ? OR s.description LIKE ? OR u.name LIKE ?)
        ";
        $params = ["%$query%", "%$query%", "%$query%"];
        
        if ($category) {
            $sql .= " AND s.category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Get categories
    public function getCategories() {
        $stmt = $this->db->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // Get skills by category
    public function getByCategory($category, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT s.*, u.name as teacher_name, u.profile_picture, u.average_rating 
            FROM skills s 
            INNER JOIN users u ON s.user_id = u.id 
            WHERE s.category = ? AND s.is_active = 1 AND u.is_active = 1 
            ORDER BY s.created_at DESC LIMIT ?
        ");
        $stmt->execute([$category, $limit]);
        return $stmt->fetchAll();
    }
    
    // Get featured skills (top rated teachers)
    public function getFeaturedSkills($limit = 6) {
        $stmt = $this->db->query("
            SELECT s.*, u.name as teacher_name, u.profile_picture, u.average_rating 
            FROM skills s 
            INNER JOIN users u ON s.user_id = u.id 
            WHERE s.is_active = 1 AND u.is_active = 1 AND u.average_rating > 0
            ORDER BY u.average_rating DESC, s.created_at DESC LIMIT $limit
        ");
        return $stmt->fetchAll();
    }
}