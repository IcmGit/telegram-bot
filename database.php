<?php
class Database {
    private $pdo;
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    
    public function createTables() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT,
                user_name VARCHAR(255),
                phone VARCHAR(50),
                message TEXT,
                photo VARCHAR(500),
                status VARCHAR(20) DEFAULT 'new',
                admin_response TEXT,
                admin_id BIGINT,
                responded_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS user_states (
                user_id BIGINT PRIMARY KEY,
                state VARCHAR(50),
                state_data TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($queries as $query) {
            try {
                $this->pdo->exec($query);
            } catch (PDOException $e) {
                logMessage("Ошибка создания таблицы: " . $e->getMessage());
            }
        }
        
        logMessage("Таблицы базы данных созданы/проверены");
    }
    
    // Нумерация заявок осуществляется через AUTO_INCREMENT поля id
    public function saveRequest($user_id, $user_name, $phone, $message, $photo = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO requests (user_id, user_name, phone, message, photo) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $user_name, $phone, $message, $photo]);
        $request_id = $this->pdo->lastInsertId();
        
        logMessage("Создана заявка #{$request_id} от пользователя {$user_name}");
        return $request_id;
    }
    
    public function getRequest($request_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$request_id]);
        return $stmt->fetch();
    }
    
    public function updateRequestResponse($request_id, $response, $admin_id) {
    // Проверяем существование столбца admin_id
    $columns = $this->pdo->query("SHOW COLUMNS FROM requests LIKE 'admin_id'")->fetch();
    
    if ($columns) {
        // Столбец admin_id существует
        $stmt = $this->pdo->prepare("
            UPDATE requests 
            SET admin_response = ?, admin_id = ?, responded_at = CURRENT_TIMESTAMP, status = 'completed' 
            WHERE id = ?
        ");
        $result = $stmt->execute([$response, $admin_id, $request_id]);
    } else {
        // Столбец admin_id не существует - используем без него
        $stmt = $this->pdo->prepare("
            UPDATE requests 
            SET admin_response = ?, responded_at = CURRENT_TIMESTAMP, status = 'completed' 
            WHERE id = ?
        ");
        $result = $stmt->execute([$response, $request_id]);
    }
    
    if ($result) {
        logMessage("Заявка #{$request_id} отмечена как выполненная администратором {$admin_id}");
    } else {
        logMessage("❌ Ошибка обновления заявки #{$request_id}");
    }
    
    return $result;
}
    
    // Методы для работы с состояниями пользователей (для Long Polling)
    public function saveUserState($user_id, $state, $state_data = null) {
        $data_json = $state_data ? json_encode($state_data) : null;
        $stmt = $this->pdo->prepare("
            INSERT INTO user_states (user_id, state, state_data) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE state = ?, state_data = ?, updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$user_id, $state, $data_json, $state, $data_json]);
    }
    
    public function getUserState($user_id) {
        $stmt = $this->pdo->prepare("SELECT state, state_data FROM user_states WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        if ($result && $result['state_data']) {
            $result['state_data'] = json_decode($result['state_data'], true);
        }
        
        return $result;
    }
    
    public function deleteUserState($user_id) {
        $stmt = $this->pdo->prepare("DELETE FROM user_states WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    }
}
?>