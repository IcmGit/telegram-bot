<?php
require_once 'config.php';
require_once 'database.php';

echo "=== Детальная диагностика таблицы requests ===\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 1. Проверяем структуру таблицы
    echo "1. Структура таблицы requests:\n";
    $stmt = $pdo->query("DESCRIBE requests");
    $columns = $stmt->fetchAll();
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }
    
    // 2. Проверяем данные в таблице
    echo "\n2. Данные в таблице requests:\n";
    $stmt = $pdo->query("SELECT id, user_id, user_name, status FROM requests ORDER BY id DESC LIMIT 5");
    $requests = $stmt->fetchAll();
    
    foreach ($requests as $request) {
        echo "   - Заявка #{$request['id']}: user_id={$request['user_id']}, user_name='{$request['user_name']}', status='{$request['status']}'\n";
    }
    
    // 3. Тестируем метод getActiveRequestByUser
    echo "\n3. Тестируем метод getActiveRequestByUser:\n";
    if (!empty($requests)) {
        $test_user_id = $requests[0]['user_id'];
        $result = $db->getActiveRequestByUser($test_user_id);
        echo "   - Результат для user_id={$test_user_id}: " . ($result ? "НАЙДЕНА #{$result['id']}" : "НЕ НАЙДЕНА") . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>