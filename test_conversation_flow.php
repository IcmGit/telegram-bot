<?php
require_once 'config.php';
require_once 'database.php';
require_once 'handlers/tenant_handler.php';

echo "=== Тест переписки пользователя ===\n";

try {
    $db = Database::getInstance();
    
    // 1. Проверяем метод getRequestByUser
    echo "1. Тест getRequestByUser:\n";
    $test_user_id = 8439366025;
    $request = $db->getRequestByUser($test_user_id);
    echo "   - Результат: " . ($request ? "✅ Заявка #{$request['id']} найдена" : "❌ Не найдена") . "\n";
    
    // 2. Проверяем сохранение сообщения пользователя
    echo "2. Тест сохранения сообщения пользователя:\n";
    if ($request) {
        $result = $db->saveConversationMessage($request['id'], $test_user_id, "Тестовое сообщение от пользователя", "tenant");
        echo "   - Сохранение: " . ($result ? "✅ УСПЕХ" : "❌ ОШИБКА") . "\n";
    }
    
    // 3. Проверяем данные в переписке
    echo "3. Данные в переписке:\n";
    if ($request) {
        $messages = $db->getConversation($request['id']);
        echo "   - Сообщений: " . count($messages) . "\n";
        foreach ($messages as $msg) {
            echo "   - #{$msg['id']}: {$msg['message_type']} - " . substr($msg['message_text'], 0, 30) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>