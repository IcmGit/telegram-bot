<?php
require_once 'config.php';
require_once 'vendors/telegram-api.php';

$api = new TelegramAPI(BOT_TOKEN);

// URL вашего вебхука на Beget (после подключения домена и SSL)
$webhook_url = 'https://icmbot.store/telegram-bot/bot_webhook.php';

// Устанавливаем вебхук
$result = $api->setWebhook($webhook_url);

if ($result && $result['ok']) {
    echo "✅ Вебхук успешно установлен: " . $webhook_url . "\n";
    
    // Проверяем информацию о вебхуке
    $info = $api->getWebhookInfo();
    echo "Информация о вебхуке:\n";
    print_r($info);
} else {
    echo "❌ Ошибка установки вебхука: " . ($result['description'] ?? 'Unknown error') . "\n";
}
?>