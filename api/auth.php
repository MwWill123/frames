<?php
setCorsHeaders();

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    private $token_expiry = 86400; // 24 horas
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function register($email, $password, $role = 'CLIENT', $displayName = null) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            if (strlen($password) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            $this->db->beginTransaction();
            
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, role, is_verified) 
                VALUES (?, ?, ?, FALSE)
                RETURNING id
            ");
            $stmt->execute([$email, $passwordHash, strtoupper($role)]);
            $userId = $stmt->fetchColumn();
            
            if ($displayName) {
                $profileTable = ($role === 'EDITOR') ? 'editor_profiles' : 'user_profiles';
                $stmt = $this->db->prepare("INSERT INTO $profileTable (user_id, display_name) VALUES (?, ?)");
                $stmt->execute([$userId, $displayName]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ? AND is_active = TRUE");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + $this->token_expiry);
            
            $stmt = $this->db->prepare("UPDATE users SET api_token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            $stmt = $this->db->prepare("SELECT id AS user_id, email, role FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            return ['success' => true, 'token' => $token, 'data' => $userData];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    public function verifyToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT id AS user_id, email, role 
                FROM users 
                WHERE api_token = ? AND token_expires_at > NOW() AND is_active = TRUE
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or expired token'];
            }
            
            return ['success' => true, 'data' => (object)$user];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Token verification failed'];
        }
    }
}
?>