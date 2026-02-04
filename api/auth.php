<?php
/**
 * Authentication System with RBAC
 * FRAMES Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For JWT library

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Auth {
    private $db;
    private $secret_key = "YOUR_SECRET_KEY_HERE_CHANGE_IN_PRODUCTION"; // Change this!
    private $issuer = "frames.com";
    private $audience = "frames.com";
    private $token_expiry = 86400; // 24 hours
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Register new user
     */
    public function register($email, $password, $role = 'CLIENT', $displayName = null) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Validate password strength
            if (strlen($password) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, role, is_verified) 
                VALUES (?, ?, ?, FALSE)
                RETURNING id
            ");
            $stmt->execute([$email, $passwordHash, strtoupper($role)]);
            $userId = $stmt->fetchColumn();
            
            // Create user profile based on role
            if ($role === 'EDITOR') {
                $stmt = $this->db->prepare("
                    INSERT INTO editor_profiles (user_id, display_name, primary_software, specialties, editor_level)
                    VALUES (?, ?, 'PREMIERE_PRO', ARRAY['VLOG'], 'JUNIOR')
                ");
                $stmt->execute([$userId, $displayName ?? 'New Editor']);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO user_profiles (user_id, display_name)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $displayName ?? 'User']);
            }
            
            $this->db->commit();
            
            // Send verification email (implement this)
            $this->sendVerificationEmail($email, $userId);
            
            return [
                'success' => true,
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        try {
            // Get user from database
            $stmt = $this->db->prepare("
                SELECT id, email, password_hash, role, is_verified, is_active
                FROM users 
                WHERE email = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is inactive. Please contact support.'];
            }
            
            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Generate JWT token
            $token = $this->generateToken($user);
            
            // Get user profile based on role
            $profile = $this->getUserProfile($user['id'], $user['role']);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'is_verified' => $user['is_verified'],
                    'profile' => $profile
                ],
                'redirect' => $this->getRedirectUrl($user['role'])
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
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
                'role' => $user['role']
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
                'data' => $decoded->data
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid or expired token'
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
                    WHERE user_id = ?
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
                    WHERE user_id = ?
                ");
            }
            
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get redirect URL based on role (RBAC)
     */
    private function getRedirectUrl($role) {
        switch ($role) {
            case 'ADMIN':
                return '/admin/dashboard';
            case 'EDITOR':
                return '/editor/dashboard';
            case 'CLIENT':
                return '/client/dashboard';
            default:
                return '/';
        }
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($userId, $permission) {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Permission matrix
        $permissions = [
            'ADMIN' => ['*'], // Admin has all permissions
            'EDITOR' => [
                'portfolio.create',
                'portfolio.edit',
                'portfolio.delete',
                'project.view',
                'project.accept',
                'project.deliver',
                'proposal.create',
                'message.send'
            ],
            'CLIENT' => [
                'project.create',
                'project.edit',
                'project.delete',
                'payment.create',
                'review.create',
                'message.send'
            ]
        ];
        
        $userRole = $user['role'];
        
        // Admin has all permissions
        if ($userRole === 'ADMIN') {
            return true;
        }
        
        // Check if role has specific permission
        return in_array($permission, $permissions[$userRole] ?? []);
    }
    
    /**
     * OAuth Login (Google/Apple)
     */
    public function oauthLogin($provider, $oauthData) {
        try {
            // Verify OAuth token with provider
            $userData = $this->verifyOAuthToken($provider, $oauthData);
            
            if (!$userData) {
                return ['success' => false, 'message' => 'OAuth verification failed'];
            }
            
            // Check if user exists
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$userData['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // User exists, login
                $token = $this->generateToken($user);
                $profile = $this->getUserProfile($user['id'], $user['role']);
                
                return [
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'profile' => $profile
                    ],
                    'redirect' => $this->getRedirectUrl($user['role'])
                ];
            } else {
                // New user, register
                $tempPassword = bin2hex(random_bytes(16));
                return $this->register(
                    $userData['email'],
                    $tempPassword,
                    'CLIENT',
                    $userData['name']
                );
            }
            
        } catch (Exception $e) {
            error_log("OAuth login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'OAuth login failed'];
        }
    }
    
    /**
     * Verify OAuth token with provider
     */
    private function verifyOAuthToken($provider, $token) {
        // Implement actual OAuth verification here
        // This is a placeholder
        switch ($provider) {
            case 'google':
                // Verify Google token
                break;
            case 'apple':
                // Verify Apple token
                break;
        }
        
        return null; // Return user data from OAuth provider
    }
    
    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $userId) {
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        
        // Store token in database (you'll need to create a table for this)
        $stmt = $this->db->prepare("
            INSERT INTO email_verifications (user_id, token, expires_at)
            VALUES (?, ?, NOW() + INTERVAL '24 hours')
        ");
        $stmt->execute([$userId, $token]);
        
        // Send email (implement with your email service)
        $verificationLink = "https://frames.com/verify-email?token=" . $token;
        
        // TODO: Integrate with email service (SendGrid, AWS SES, etc.)
        error_log("Verification email sent to {$email}: {$verificationLink}");
    }
    
    /**
     * Verify email
     */
    public function verifyEmail($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id FROM email_verifications 
                WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
            ");
            $stmt->execute([$token]);
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$verification) {
                return ['success' => false, 'message' => 'Invalid or expired verification link'];
            }
            
            // Update user
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                UPDATE users 
                SET is_verified = TRUE, email_verified_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$verification['user_id']]);
            
            $stmt = $this->db->prepare("
                UPDATE email_verifications 
                SET used_at = NOW() 
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Email verified successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Email verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // Don't reveal if email exists
                return ['success' => true, 'message' => 'If email exists, reset link has been sent'];
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            
            $stmt = $this->db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, NOW() + INTERVAL '1 hour')
            ");
            $stmt->execute([$user['id'], $token]);
            
            // Send reset email
            $resetLink = "https://frames.com/reset-password?token=" . $token;
            
            // TODO: Integrate with email service
            error_log("Password reset email sent to {$email}: {$resetLink}");
            
            return ['success' => true, 'message' => 'Password reset link sent'];
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Request failed'];
        }
    }
    
    /**
     * Reset password
     */
    public function resetPassword($token, $newPassword) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_id FROM password_resets 
                WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset) {
                return ['success' => false, 'message' => 'Invalid or expired reset link'];
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            $this->db->beginTransaction();
            
            // Update password
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $reset['user_id']]);
            
            // Mark reset as used
            $stmt = $this->db->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Password reset successful'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Reset failed'];
        }
    }
}

// Middleware function to protect routes
function requireAuth($requiredRole = null) {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }
    
    $db = getDatabase();
    $auth = new Auth($db);
    $result = $auth->verifyToken($token);
    
    if (!$result['success']) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    // Check role if specified
    if ($requiredRole && $result['data']->role !== $requiredRole) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }
    
    return $result['data'];
}
?>
