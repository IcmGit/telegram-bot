<?php
require_once 'config.php';
require_once 'vendors/telegram-api.php';
require_once 'database.php';
require_once 'handlers/tenant_handler.php';
require_once 'handlers/admin_handler.php';

// Для хостинга меняем режим в config.php на 'webhook'
if (MODE !== 'webhook') {
    die("Установите MODE = 'webhook' в config.php");
}

class BotWebhook {
    private $api;
    private $db;
    private $tenantHandler;
    private $adminHandler;
    private $admins;
    
    public function __construct($admins) {
        $this->admins = $admins;
        $this->api = new TelegramAPI(BOT_TOKEN);
        $this->db = Database::getInstance();
        $this->tenantHandler = new TenantHandler($this->db, $this->api, $this->admins);
        $this->adminHandler = new AdminHandler($this->db, $this->api, $this->tenantHandler);
    }
    
    public function processUpdate($update) {
        try {
            logMessage("Webhook получено обновление: " . json_encode($update));
            
            // Обработка сообщений
            if (isset($update['message'])) {
                $this->processMessage($update['message']);
            }
            
            // Обработка callback query
            if (isset($update['callback_query'])) {
                $this->processCallbackQuery($update['callback_query']);
            }
            
        } catch (Exception $e) {
            logMessage("❌ Ошибка в processUpdate: " . $e->getMessage());
            // НЕ выбрасываем исключение дальше, чтобы Telegram не считал вебхук упавшим
        }
    }
    
    private function processMessage($message) {
        try {
            $chat_id = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $first_name = $message['chat']['first_name'] ?? '';
            $photo = isset($message['photo']) ? end($message['photo'])['file_id'] : null;
            
            $is_admin = in_array($chat_id, $this->admins);
            $user_state = $this->db->getUserState($chat_id);
            
            // ВАЖНОЕ ИСПРАВЛЕНИЕ: Сначала проверяем фото
            if ($photo && $user_state && $user_state['state'] === 'waiting_photo') {
                $this->tenantHandler->handlePhoto($chat_id, $photo);
                return;
            }

            if ($is_admin && $user_state && $user_state['state'] === 'waiting_response') {
                $this->adminHandler->handleAdminResponse($chat_id, $text);
                return;
            }
            
            if ($user_state) {
                $this->processUserState($chat_id, $text, $photo, $user_state);
                return;
            }
            
            if ($text === '/start' || $text === '/newrequest') {
                $this->tenantHandler->handleStart($chat_id, $first_name);
            } elseif ($is_admin && $text === '/admin') {
                $this->adminHandler->showAdminPanel($chat_id);
            } else {
                $this->api->sendMessage($chat_id, "Используйте /start для создания новой заявки.");
            }
            
        } catch (Exception $e) {
            logMessage("❌ Ошибка в processMessage: " . $e->getMessage());
        }
    }
    
    private function processUserState($chat_id, $text, $photo, $user_state) {
        try {
            switch ($user_state['state']) {
                case 'waiting_name':
                    $this->tenantHandler->handleName($chat_id, $text);
                    break;
                case 'waiting_phone':
                    $this->tenantHandler->handlePhone($chat_id, $text);
                    break;
                case 'waiting_message':
                    $this->tenantHandler->handleMessage($chat_id, $text);
                    break;
                case 'waiting_photo':
                    if ($text === '🚀 Отправить без фото') {
                        $this->tenantHandler->handleNoPhoto($chat_id);
                    }
                    break;
            }
        } catch (Exception $e) {
            logMessage("❌ Ошибка в processUserState: " . $e->getMessage());
        }
    }
    
    private function processCallbackQuery($callback_query) {
        try {
            $callback_data = $callback_query['data'];
            $admin_id = $callback_query['from']['id'];
            $callback_query_id = $callback_query['id'];
            
            if (strpos($callback_data, 'respond_') === 0) {
                $this->adminHandler->handleRespondRequest($callback_data, $admin_id);
                $this->api->answerCallbackQuery($callback_query_id, "Подготовка ответа...");
            }
            
            logMessage("Обработан callback: {$callback_data} от пользователя {$admin_id}");
            
        } catch (Exception $e) {
            logMessage("❌ Ошибка в processCallbackQuery: " . $e->getMessage());
        }
    }
}

// Обработка входящего вебхука
try {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if ($update) {
        $bot = new BotWebhook($ADMINS);
        $bot->processUpdate($update);
    }

    // ВАЖНО: Всегда возвращаем 200 OK
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    // Даже при критической ошибке возвращаем 200
    logMessage("💥 Критическая ошибка вебхука: " . $e->getMessage());
    http_response_code(200);
    echo 'OK';
}
?>