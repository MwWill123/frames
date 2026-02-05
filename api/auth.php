<?php
/**
 * Authentication System Simplificado (sem JWT - para testes)
 * FRAMES Platform
 */

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    private $secret_key = "mude_isso_para_algo_forte_em_producao_123456789"; // Mude para algo único!
    private $token_expiry = 86400; // 24 horas
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Register new user
     */
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
            
            // Profile creation (mesmo do original - truncado, complete se precisar)
            if ($role === 'EDITOR' && $displayName) {
                $stmt = $this->db->prepare("
                    INSERT INTO editor_profiles (user_id, display_name, primary_software, specialties, editor_level)
                    VALUES (?, ?, 'PREMIERE_PRO', ARRAY['VLOG','YOUTUBE'], 'PRO')
                ");
                $stmt->execute([$userId, $displayName]);
            } elseif ($displayName) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_profiles (user_id, display_name)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $displayName]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Login user - gera api_token simples
     */
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ? AND is_active = TRUE");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Gera token aleatório
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + $this->token_expiry);
            
            $stmt = $this->db->prepare("UPDATE users SET api_token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // Busca dados do user para retornar
            $stmt = $this->db->prepare("SELECT id AS user_id, email, role FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            return [
                'success' => true,
                'token' => $token,
                'data' => $userData
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    /**
     * Verify token simples
     */
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
            error_log("Verify token error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Token verification failed'];
        }
    }
}

// Processa requests no auth.php (adapte do seu código original - provavelmente você tem um switch para actions)
header('Content-Type: application/json');
setCorsHeaders();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$db = getDatabase();
$auth = new Auth($db);

if ($action === 'register') {
    $result = $auth->register($input['email'] ?? '', $input['password'] ?? '', $input['role'] ?? 'CLIENT', $input['displayName'] ?? null);
    echo json_encode($result);
} elseif ($action === 'login') {
    $result = $auth->login($input['email'] ?? '', $input['password'] ?? '');
    echo json_encode($result);
} elseif ($action === 'verify_token') {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $result = $auth->verifyToken($token);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>