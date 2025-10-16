<?php
require_once 'config.php';
require_once 'database.php';

echo "Установка базы данных для Telegram бота...\n";

try {
    $db = Database::getInstance();
    $db->createTables();
    echo "✅ База данных успешно настроена!\n";
    echo "✅ Таблицы созданы/проверены\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

// Проверка соединения с Telegram API
require_once 'vendors/telegram-api.php';
$api = new TelegramAPI(BOT_TOKEN);
$me = $api->getMe();

if ($me && isset($me['result'])) {
    $bot_name = $me['result']['first_name'];
    echo "✅ Бот {$bot_name} доступен\n";
} else {
    echo "❌ Ошибка подключения к боту. Проверьте BOT_TOKEN в config.php\n";
}

echo "\nСледующие шалы:\n";
echo "1. Запустите get_chat_id.php для получения chat_id администраторов\n";
echo "2. Добавьте chat_id в config.php в массив \$ADMINS\n";
echo "3. Запустите бота: php bot_polling.php\n";
?>