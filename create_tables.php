<?php
require_once 'config.php';
require_once 'database.php';

echo "=== Создание таблиц в базе данных ===\n";

try {
    $db = Database::getInstance();
    
    // Создаем таблицы
    $db->createTables();
    
    echo "✅ Таблицы успешно созданы!\n";
    
    // Проверяем
    $tables = $db->getPdo()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Существующие таблицы: " . implode(', ', $tables) . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>