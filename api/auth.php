<?php
// Configurações de CORS
header("Access-Control-Allow-Origin: https://frames-silk.vercel.app");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    private $token_expiry = 86400; // 24 horas
    
    public function __construct($database) {
        $this->db = $database;
    }

    // --- NOVO MÉTODO: VERIFY TOKEN ---
    public function verifyToken($token) {
        try {
            // Limpa o prefixo "Bearer " se existir
            $token = str_replace('Bearer ', '', $token);

            $stmt = $this->db->prepare("SELECT id, role, token_expires_at FROM users WHERE api_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Verifica expiração
            if (strtotime($user['token_expires_at']) < time()) {
                return false;
            }

            return $user; // Retorna array com id e role
        } catch (Exception $e) {
            return false;
        }
    }
    // ---------------------------------
    
    public function register($email, $password, $role = 'CLIENT', $displayName = null) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Email inválido'];
            
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) return ['success' => false, 'message' => 'Email já registrado'];
            
            if (strlen($password) < 8) return ['success' => false, 'message' => 'Senha muito curta (mín 8)'];
            
            $this->db->beginTransaction();
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Inserção
            $stmt = $this->db->prepare("INSERT INTO users (email, password_hash, role, is_verified) VALUES (?, ?, ?, FALSE)");
            $stmt->execute([$email, $passwordHash, strtoupper($role)]);
            
            // Pega ID (compatível com PGSQL/MySQL)
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $userId = $stmt->fetchColumn();
            
            if ($displayName) {
                $profileTable = ($role === 'EDITOR') ? 'editor_profiles' : 'user_profiles';
                $stmt = $this->db->prepare("INSERT INTO $profileTable (user_id, display_name) VALUES (?, ?)");
                $stmt->execute([$userId, $displayName]);
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Registro realizado com sucesso'];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'message' => 'Erro no registro: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Credenciais inválidas'];
            }
            
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + $this->token_expiry);
            
            $stmt = $this->db->prepare("UPDATE users SET api_token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // Retornar dados limpos
            return [
                'success' => true, 
                'token' => $token, 
                'data' => ['user_id' => $user['id'], 'email' => $email, 'role' => $user['role']]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }
}

// --- PROTEÇÃO DE EXECUÇÃO ---
// Só executa o código abaixo se o arquivo for chamado DIRETAMENTE pela URL.
// Se for incluído via 'require' (pelo projects.php), isso aqui é ignorado.
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input);

        if (!isset($data->email) || !isset($data->password)) {
            echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
            exit;
        }

        try {
            $dbConnection = getDatabase();
            $auth = new Auth($dbConnection);
            $action = $data->action ?? 'login';

            if ($action === 'login') {
                $result = $auth->login($data->email, $data->password);
                if ($result['success']) {
                    $role = strtoupper($result['data']['role']);
                    $redirectMap = ['ADMIN' => '/admin/dashboard.html', 'EDITOR' => '/editor/dashboard.html', 'CLIENT' => '/client/dashboard.html'];
                    $result['redirect'] = $redirectMap[$role] ?? '/client/dashboard.html';
                    $result['user'] = $result['data'];
                }
                echo json_encode($result);
            } elseif ($action === 'register') {
                echo json_encode($auth->register($data->email, $data->password, $data->role ?? 'CLIENT', $data->displayName ?? null));
            } else {
                echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro interno']);
        }
    }
}
?>