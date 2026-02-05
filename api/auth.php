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

// 4. EXECUÇÃO DA LÓGICA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebe o JSON cru do frontend
    $input = file_get_contents("php://input");
    $data = json_decode($input);

    // Verifica se os dados mínimos chegaram
    if (!isset($data->email) || !isset($data->password)) {
        echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
        exit;
    }

    // Inicializa a conexão usando a função definida em database.php ou config.php
    try {
        // Seu arquivo database.php define getDatabase() e config.php define getDB()
        // Ambos retornam a conexão PDO necessária.
        $dbConnection = getDatabase(); 
        
        if (!$dbConnection) {
            throw new Exception("Falha ao obter instância do banco de dados.");
        }

        $auth = new Auth($dbConnection);

        // Verifica qual ação o login.js enviou (login ou register)
        $action = isset($data->action) ? $data->action : 'login';

        if ($action === 'login') {
            $result = $auth->login($data->email, $data->password);
            
            // Adiciona o redirecionamento esperado pelo seu login.js
            if ($result['success']) {
                $role = strtoupper($result['data']['role']);
                $redirectMap = [
                    'ADMIN' => '/admin/dashboard.html',
                    'EDITOR' => '/editor/dashboard.html',
                    'CLIENT' => '/client/dashboard.html'
                ];
                $result['redirect'] = $redirectMap[$role] ?? '/client/dashboard.html';
                $result['user'] = $result['data']; // Compatibilidade com login.js:115
            }
            
            echo json_encode($result);
        } elseif ($action === 'register') {
            $result = $auth->register(
                $data->email, 
                $data->password, 
                $data->role ?? 'CLIENT', 
                $data->displayName ?? null
            );
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
    }
    exit;
}
?>