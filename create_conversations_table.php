<?php
require_once 'config.php';
require_once 'database.php';

echo "=== Создание таблицы conversations ===\n";

try {
    $db = Database::getInstance();
    
    // Создаем таблицу conversations
    $sql = "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        user_id BIGINT NOT NULL,
        message_text TEXT NOT NULL,
        message_type ENUM('admin', 'tenant') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $db->getPdo()->exec($sql);
    echo "✅ Таблица conversations создана\n";
    
    // Проверяем
    $stmt = $db->getPdo()->query("SHOW TABLES LIKE 'conversations'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✅ Таблица conversations существует в базе данных\n";
    } else {
        echo "❌ Таблица conversations не создана\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>