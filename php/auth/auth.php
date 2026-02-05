<?php
/**
 * Authentication System with RBAC
 * FRAMES Platform - PostgreSQL Version
 */

require_once __DIR__ . '/../config/config.php';  // ✅ Usar SEU config.php atualizado

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Auth {
    private $db;
    private $secret_key;
    private $issuer = "frames.com";
    private $audience = "frames.com";
    private $token_expiry = 86400; // 24 hours
    
    public function __construct() {
        $this->db = getDB();  // ✅ Usar getDB() do seu config.php
        $this->secret_key = defined('JWT_SECRET') ? JWT_SECRET : "SUA_CHAVE_SECRETA_AQUI_MUDAR_NO_SUPABASE";
    }
    
    /**
     * Register new user (PostgreSQL)
     */
    public function register($email, $password, $role = 'CLIENT', $displayName = null) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Formato de email inválido'];
            }
            
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email já cadastrado'];
            }
            
            // Validate password
            if (strlen($password) < 8) {
                return ['success' => false, 'message' => 'Senha deve ter pelo menos 8 caracteres'];
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Get role_id from roles table
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = :role");
            $stmt->execute([':role' => strtoupper($role)]);
            $roleId = $stmt->fetchColumn();
            
            if (!$roleId) {
                $roleId = 3; // Default to CLIENT
            }
            
            // Insert user with PostgreSQL syntax
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, role_id, is_verified, is_active, created_at) 
                VALUES (:email, :password, :role_id, FALSE, TRUE, CURRENT_TIMESTAMP)
                RETURNING id, uuid, email
            ");
            $stmt->execute([
                ':email' => $email,
                ':password' => $passwordHash,
                ':role_id' => $roleId
            ]);
            
            $user = $stmt->fetch();
            
            // Create user profile
            if (strtoupper($role) === 'EDITOR') {
                $stmt = $this->db->prepare("
                    INSERT INTO editor_profiles (user_id, display_name, primary_software, specialties, editor_level, created_at)
                    VALUES (:user_id, :display_name, 'PREMIERE_PRO', ARRAY['VLOG']::varchar[], 'JUNIOR', CURRENT_TIMESTAMP)
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO user_profiles (user_id, display_name, created_at)
                    VALUES (:user_id, :display_name, CURRENT_TIMESTAMP)
                ");
            }
            
            $stmt->execute([
                ':user_id' => $user['id'],
                ':display_name' => $displayName ?? 'Novo Usuário'
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Cadastro realizado com sucesso!',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $role
                ]
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro no cadastro: ' . $e->getMessage()];
        }
    }
    
    /**
     * Login user (PostgreSQL)
     */
    public function login($email, $password) {
        try {
            // Get user from database
            $stmt = $this->db->prepare("
                SELECT u.id, u.uuid, u.email, u.password_hash, r.role_name, u.is_verified, u.is_active
                FROM users u 
                JOIN roles r ON u.role_id = r.id
                WHERE u.email = :email AND u.deleted_at IS NULL
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Credenciais inválidas'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Credenciais inválidas'];
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Conta inativa. Contate o suporte.'];
            }
            
            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':id' => $user['id']]);
            
            // Generate JWT token
            $token = $this->generateToken($user);
            
            // Get user profile
            $profile = $this->getUserProfile($user['id'], $user['role_name']);
            
            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'uuid' => $user['uuid'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                    'is_verified' => $user['is_verified'],
                    'profile' => $profile
                ],
                'redirect' => $this->getRedirectUrl($user['role_name'])
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro no login: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate JWT token
     */
    private function generateToken($user) {
        $issuedAt = time();
        $expiry = $issuedAt + $this->token_expiry;
        
        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $issuedAt,
            'exp' => $expiry,
            'data' => [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role_name'],
                'uuid' => $user['uuid']
            ]
        ];
        
        return JWT::encode($payload, $this->secret_key, 'HS256');
    }
    
    /**
     * Verify JWT token
     */
    public function verifyToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));
            return [
                'success' => true,
                'data' => (array)$decoded->data
            ];
        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Token inválido ou expirado'
            ];
        }
    }
    
    /**
     * Get user profile based on role
     */
    private function getUserProfile($userId, $role) {
        try {
            if ($role === 'EDITOR') {
                $stmt = $this->db->prepare("
                    SELECT 
                        display_name,
                        avatar_url,
                        bio,
                        primary_software,
                        editor_level,
                        specialties,
                        hourly_rate,
                        average_rating,
                        total_projects_completed,
                        is_verified,
                        available_for_hire
                    FROM editor_profiles 
                    WHERE user_id = :user_id
                ");
            } else {
                $stmt = $this->db->prepare("
                    SELECT 
                        display_name,
                        avatar_url,
                        bio,
                        phone,
                        country
                    FROM user_profiles 
                    WHERE user_id = :user_id
                ");
            }
            
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetch() ?: ['display_name' => 'Usuário'];
            
        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            return ['display_name' => 'Usuário'];
        }
    }
    
    /**
     * Get redirect URL based on role
     */
    private function getRedirectUrl($role) {
        switch ($role) {
            case 'ADMIN':
                return '/admin/dashboard.html';
            case 'EDITOR':
                return '/editor/dashboard.html';
            case 'CLIENT':
                return '/client/dashboard.html';
            default:
                return '/';
        }
    }
}

// ==================== API ENDPOINT ====================

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle actual request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    $auth = new Auth();
    $response = [];
    
    switch ($action) {
        case 'login':
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $response = $auth->login($email, $password);
            break;
            
        case 'register':
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'CLIENT';
            $displayName = $data['displayName'] ?? '';
            $response = $auth->register($email, $password, $role, $displayName);
            break;
            
        case 'verify_token':
            $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
            $response = $auth->verifyToken($token);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Ação inválida'];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>