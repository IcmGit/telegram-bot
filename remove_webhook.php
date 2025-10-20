<?php
require_once 'config.php';
require_once 'vendors/telegram-api.php';

$api = new TelegramAPI(BOT_TOKEN);
$result = $api->deleteWebhook();

if ($result && $result['ok']) {
    echo "✅ Вебхук удален\n";
} else {
    echo "❌ Ошибка удаления вебхука: " . ($result['description'] ?? 'Unknown error') . "\n";
}
?>