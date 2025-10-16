<?php
// Режим работы: polling или webhook
define('MODE', 'polling'); // Измените на 'webhook' для хостинга

// Конфигурация бота
define('BOT_TOKEN', '8206710817:AAGD3ry8bGgTTVm50Y0wcgAQrqEp9RHmB0g');
define('BOT_USERNAME', 'Icmchatbot');

// Список администраторов (будет заполнен после получения chat_id)
$ADMINS = [
    // Добавьте chat_id администраторов после получения через get_chat_id.php
    // 123456789,
    // 987654321
    5190670030,
    5732331495
];

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_bot');
define('DB_USER', 'root');
define('DB_PASS', '');

// Настройки Long Polling
define('POLLING_TIMEOUT', 30); // Таймаут в секундах
define('LAST_UPDATE_ID_FILE', 'last_update_id.txt');

// Состояния пользователей (хранятся в памяти для polling)
$user_states = [];
$admin_states = [];

// Включить логирование
define('ENABLE_LOGGING', true);
function logMessage($message) {
    if (ENABLE_LOGGING) {
        file_put_contents('bot.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }
}
?>