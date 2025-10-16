<?php
require_once 'config.php';
require_once 'vendors/telegram-api.php';

echo "Скрипт для получения chat_id\n";
echo "============================\n\n";

$api = new TelegramAPI(BOT_TOKEN);

// Получаем информацию о боте
$me = $api->getMe();
if (!$me || !isset($me['result'])) {
    die("❌ Не удалось подключиться к боту. Проверьте BOT_TOKEN в config.php\n");
}

$bot_name = $me['result']['first_name'];
echo "Бот: {$bot_name} (@{$me['result']['username']})\n\n";

echo "Инструкция:\n";
echo "1. Напишите любое сообщение боту в Telegram\n";
echo "2. Скрипт покажет ваш chat_id\n";
echo "3. Нажмите Ctrl+C для остановки\n\n";

echo "Ожидание сообщений...\n";

$last_update_id = 0;

while (true) {
    $updates = $api->getUpdates($last_update_id + 1, 10, 10);
    
    if ($updates && isset($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $last_update_id = $update['update_id'];
            
            if (isset($update['message'])) {
                $message = $update['message'];
                $chat_id = $message['chat']['id'];
                $first_name = $message['chat']['first_name'] ?? 'Неизвестно';
                $username = $message['chat']['username'] ?? 'Не указан';
                $text = $message['text'] ?? '(без текста)';
                
                echo "\n=== НОВОЕ СООБЩЕНИЕ ===\n";
                echo "Chat ID: {$chat_id}\n";
                echo "Имя: {$first_name}\n";
                echo "Username: @{$username}\n";
                echo "Текст: {$text}\n";
                echo "=======================\n\n";
                
                // Отправляем ответ с chat_id
                $api->sendMessage($chat_id, 
                    "Ваш chat_id: `{$chat_id}`\n\n" .
                    "Добавьте этот ID в файл config.php в массив \$ADMINS",
                    null, 'Markdown'
                );
            }
        }
    }
    
    sleep(1);
}
?>