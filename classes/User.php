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
    
    // Login user — step 1: verify credentials, generate OTP, send email
    // Returns: false (bad credentials) | 'otp_sent' (OTP emailed) | 'otp_unavailable' (mail failed, log in directly)
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Check if OTP columns exist
        $hasOtp = false;
        try {
            $s = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='otp_code'");
            $s->execute();
            $hasOtp = (bool)$s->fetchColumn();
        } catch (PDOException $e) {}

        if (!$hasOtp) {
            // OTP columns not migrated yet — use session-only OTP (no DB storage)
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            require_once __DIR__ . '/../config/mail.php';
            $subject = APP_NAME . ' — Your login code';
            $html    = "
            <div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;border:1px solid #e0e0e0;border-radius:8px'>
                <h2 style='color:#0d6efd'>" . APP_NAME . "</h2>
                <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                <p>Your login verification code is:</p>
                <div style='font-size:2.5rem;font-weight:bold;letter-spacing:12px;text-align:center;
                            background:#f0f4ff;padding:20px;border-radius:8px;margin:24px 0;color:#0d6efd'>
                    $otp
                </div>
                <p style='color:#666'>This code expires in <strong>10 minutes</strong>.</p>
            </div>";

            $result = sendMail($user['email'], $user['name'], $subject, $html);

            // Store OTP in session since DB columns don't exist yet
            $_SESSION['otp_session_code']    = $otp;
            $_SESSION['otp_session_expires'] = time() + 600;
            $_SESSION['otp_user_data']       = $user; // store full user for createSession later

            if ($result !== true) {
                $_SESSION['otp_fallback_code'] = $otp;
                $_SESSION['otp_mail_error']    = $result;
            }

            $_SESSION['otp_user_id']  = $user['id'];
            $_SESSION['otp_redirect'] = $_GET['redirect'] ?? '';
            return 'otp_sent';
        }

        // Generate 6-digit OTP, valid for 10 minutes
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600);

        $this->db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")
                 ->execute([$otp, $expires, $user['id']]);

        // Send OTP email
        require_once __DIR__ . '/../config/mail.php';

        $subject = APP_NAME . ' — Your login code';
        $html    = "
        <div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;border:1px solid #e0e0e0;border-radius:8px'>
            <h2 style='color:#0d6efd;margin-bottom:8px'>" . APP_NAME . "</h2>
            <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
            <p>Your login verification code is:</p>
            <div style='font-size:2.5rem;font-weight:bold;letter-spacing:12px;text-align:center;
                        background:#f0f4ff;padding:20px;border-radius:8px;margin:24px 0;color:#0d6efd'>
                $otp
            </div>
            <p style='color:#666'>This code expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
            <p style='color:#999;font-size:0.85rem'>If you didn't try to log in, you can safely ignore this email.</p>
        </div>";

        $result = sendMail($user['email'], $user['name'], $subject, $html);

        if ($result !== true) {
            // Email failed or not configured — store OTP in session so verify-otp.php can show it
            error_log("OTP email failed for user {$user['id']}: $result");
            $_SESSION['otp_fallback_code'] = $otp;
            $_SESSION['otp_mail_error']    = $result; // 'not_configured' or actual error
        }

        // Always go to OTP page — never skip it
        $_SESSION['otp_user_id']  = $user['id'];
        $_SESSION['otp_redirect'] = $_GET['redirect'] ?? '';

        return 'otp_sent';
    }

    // Verify OTP — step 2: check code, complete login
    public function verifyOtp(int $userId, string $code): bool {
        // Check session-based OTP first (used when DB columns not yet migrated)
        if (!empty($_SESSION['otp_session_code'])) {
            $valid = $_SESSION['otp_session_code'] === $code
                  && time() < ($_SESSION['otp_session_expires'] ?? 0);

            if ($valid && !empty($_SESSION['otp_user_data'])) {
                $user = $_SESSION['otp_user_data'];
                unset($_SESSION['otp_session_code'], $_SESSION['otp_session_expires'], $_SESSION['otp_user_data']);
                $this->createSession($user);
                return true;
            }
            return false;
        }

        // Normal DB-based OTP check
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > NOW() AND is_active = 1"
        );
        $stmt->execute([$userId, $code]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        // Clear OTP
        $this->db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
                 ->execute([$userId]);

        $this->createSession($user);
        return true;
    }

    // Create the authenticated session
    private function createSession(array $user): void {
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_role']   = $user['role'];
        $_SESSION['user_points'] = $user['points'];
        // Clear any pending OTP state
        unset($_SESSION['otp_user_id'], $_SESSION['otp_redirect']);
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