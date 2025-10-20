<?php
require_once 'config.php';
require_once 'database.php';

echo "=== Тест базы данных ===\n";

try {
    $db = Database::getInstance();
    echo "✅ Подключение к БД успешно\n";
    
    // Проверяем таблицы
    $tables = $db->getPdo()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Таблицы: " . implode(', ', $tables) . "\n";
    
    // Тестируем сохранение состояния
    $test_user_id = 999999;
    $result = $db->saveUserState($test_user_id, 'waiting_name');
    echo "Сохранение состояния: " . ($result ? '✅' : '❌') . "\n";
    
    // Тестируем получение состояния
    $state = $db->getUserState($test_user_id);
    echo "Получение состояния: " . ($state ? $state['state'] : '❌ не найдено') . "\n";
    
    // Очищаем тестовые данные
    $db->deleteUserState($test_user_id);
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>