<?php
require_once 'config.php';

echo "=== Полное пересоздание таблицы conversations ===\n";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Удаляем таблицу если существует
    $pdo->exec("DROP TABLE IF EXISTS conversations");
    echo "✅ Таблица conversations удалена\n";
    
    // Создаем таблицу заново с правильной структурой
    $sql = "CREATE TABLE conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        user_id BIGINT NOT NULL,
        message_text TEXT NOT NULL,
        message_type ENUM('admin', 'tenant') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "✅ Таблица conversations создана заново\n";
    
    // Проверяем структуру
    $stmt = $pdo->query("DESCRIBE conversations");
    $columns = $stmt->fetchAll();
    echo "Структура таблицы:\n";
    foreach ($columns as $column) {
        echo " - {$column['Field']} ({$column['Type']})\n";
    }
    
    // Тестируем вставку данных
    echo "\nТестируем вставку данных:\n";
    $test_sql = "INSERT INTO conversations (request_id, user_id, message_text, message_type) VALUES (11, 5190670030, 'Тестовое сообщение', 'admin')";
    $pdo->exec($test_sql);
    echo "✅ Тестовая запись добавлена\n";
    
    // Проверяем данные
    $stmt = $pdo->query("SELECT * FROM conversations");
    $messages = $stmt->fetchAll();
    echo "Записей в таблице: " . count($messages) . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>