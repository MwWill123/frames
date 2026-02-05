<?php
// 1. CONFIGURAÇÃO DOS HEADERS (Ocorre antes de tudo)
header("Access-Control-Allow-Origin: https://frames-silk.vercel.app");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// 2. TRATAMENTO DO PREFLIGHT (OPTIONS)
// Se o navegador perguntar "posso enviar dados?", respondemos "sim" e encerramos.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 3. INCLUSÃO DO BANCO DE DADOS
require_once __DIR__ . '/../config/database.php';
// ATENÇÃO: O arquivo database.php precisa criar uma variável de conexão.
// Vou assumir que o nome da variável criada lá é $database ou $pdo.
// Se for diferente, ajuste na linha da execução lá embaixo.

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
            
            // Ajuste para PostgreSQL (RETURNING id) ou MySQL (LAST_INSERT_ID) conforme seu banco
            $stmt = $this->db->prepare("
                INSERT INTO users (email, password_hash, role, is_verified) 
                VALUES (?, ?, ?, FALSE)
            ");
            $stmt->execute([$email, $passwordHash, strtoupper($role)]);
            
            // Detectando ID (compatível com PDO genérico)
            $userId = $this->db->lastInsertId();
            
            if ($displayName) {
                $profileTable = ($role === 'EDITOR') ? 'editor_profiles' : 'user_profiles';
                $stmt = $this->db->prepare("INSERT INTO $profileTable (user_id, display_name) VALUES (?, ?)");
                $stmt->execute([$userId, $displayName]);
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Debug: return ['success' => false, 'message' => $e->getMessage()];
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
            // Removi "AND is_active = TRUE" temporariamente para testar, caso você não tenha ativado o user ainda
            
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + $this->token_expiry);
            
            $stmt = $this->db->prepare("UPDATE users SET api_token = ?, token_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            $stmt = $this->db->prepare("SELECT id AS user_id, email, role FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'token' => $token, 'data' => $userData];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    // ... (Mantenha o método verifyToken aqui se precisar) ...
}

// 4. EXECUÇÃO DA LÓGICA (Isso estava faltando no seu arquivo original)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebe o JSON cru
    $input = file_get_contents("php://input");
    $data = json_decode($input);

    // Verifica se os dados chegaram
    if (!isset($data->email) || !isset($data->password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        exit;
    }

    // Instancia o Auth. 
    // NOTA: $database deve vir do arquivo config/database.php incluído acima.
    // Se o seu database.php chama a variável de $conn ou $pdo, altere abaixo:
    if (isset($database)) {
        $auth = new Auth($database);
    } elseif (isset($pdo)) {
        $auth = new Auth($pdo);
    } elseif (isset($conn)) {
        $auth = new Auth($conn);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database connection variable not found']);
        exit;
    }

    // Executa o login
    $result = $auth->login($data->email, $data->password);
    echo json_encode($result);
}
?>