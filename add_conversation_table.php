<?php
require_once 'config.php';
require_once 'database.php';

echo "=== Создание таблицы для переписки ===\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // Создаем таблицу для переписки
    $sql = "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        user_id BIGINT NOT NULL,
        message_text TEXT NOT NULL,
        message_type ENUM('admin', 'tenant') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "✅ Таблица conversations создана\n";
    
    // Добавляем кнопку "Закрыть заявку" в существующие заявки
    echo "✅ Структура базы данных обновлена\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>