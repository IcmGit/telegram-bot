<?php
require_once 'config.php';
require_once 'vendors/telegram-api.php';
require_once 'database.php';
require_once 'handlers/tenant_handler.php';
require_once 'handlers/admin_handler.php';

class BotPolling {
    private $api;
    private $db;
    private $tenantHandler;
    private $adminHandler;
    private $last_update_id;
    private $admins;
    
    public function __construct($admins) {
        $this->admins = $admins;
        $this->api = new TelegramAPI(BOT_TOKEN);
        $this->db = Database::getInstance();
        $this->tenantHandler = new TenantHandler($this->db, $this->api, $this->admins);
        $this->adminHandler = new AdminHandler($this->db, $this->api, $this->tenantHandler);
        $this->loadLastUpdateId();
    }
    
    private function loadLastUpdateId() {
        if (file_exists(LAST_UPDATE_ID_FILE)) {
            $this->last_update_id = (int)file_get_contents(LAST_UPDATE_ID_FILE);
        } else {
            $this->last_update_id = 0;
        }
    }
    
    private function saveLastUpdateId($update_id) {
        $this->last_update_id = $update_id;
        file_put_contents(LAST_UPDATE_ID_FILE, $update_id);
    }
    
    public function run() {
        logMessage("Бот запущен в режиме Long Polling");
        echo "Бот запущен...\n";
        
        while (true) {
            try {
                $updates = $this->api->getUpdates($this->last_update_id + 1, 100, POLLING_TIMEOUT);
                
                if ($updates && isset($updates['result'])) {
                    foreach ($updates['result'] as $update) {
                        $this->processUpdate($update);
                        $this->saveLastUpdateId($update['update_id']);
                    }
                }
                
                // Небольшая пауза между запросами
                sleep(1);
                
            } catch (Exception $e) {
                logMessage("Ошибка в основном цикле: " . $e->getMessage());
                sleep(5); // Пауза при ошибке
            }
        }
    }
    
    private function processUpdate($update) {
        logMessage("Получено обновление: " . json_encode($update));
        
        // Обработка сообщений
        if (isset($update['message'])) {
            $this->processMessage($update['message']);
        }
        
        // Обработка callback query (нажатия на кнопки)
        if (isset($update['callback_query'])) {
            $this->processCallbackQuery($update['callback_query']);
        }
    }
    
    private function processMessage($message) {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $first_name = $message['chat']['first_name'] ?? '';
        $photo = isset($message['photo']) ? end($message['photo'])['file_id'] : null;
        
        // Проверяем, является ли пользователь администратором
        $is_admin = in_array($chat_id, $this->admins);
        
        // Получаем текущее состояние пользователя
        $user_state = $this->db->getUserState($chat_id);
        
         // ВАЖНОЕ ИСПРАВЛЕНИЕ: Сначала проверяем фото
        if ($photo && $user_state && $user_state['state'] === 'waiting_photo') {
            $this->tenantHandler->handlePhoto($chat_id, $photo);
            return;
        }
        
        // Обработка состояний администратора
        if ($is_admin && $user_state && $user_state['state'] === 'waiting_response') {
            $this->adminHandler->handleAdminResponse($chat_id, $text);
            return;
        }
        
        // Обработка состояний арендатора
        if ($user_state) {
            $this->processUserState($chat_id, $text, $photo, $user_state);
            return;
        }
        
        // Обработка команд
        if ($text === '/start' || $text === '/newrequest') {
            $this->tenantHandler->handleStart($chat_id, $first_name);
        } elseif ($is_admin && $text === '/admin') {
            $this->adminHandler->showAdminPanel($chat_id);
        } else {
            $this->api->sendMessage($chat_id, 
                "Используйте /start для создания новой заявки."
            );
        }
    }
    
    private function processUserState($chat_id, $text, $photo, $user_state) {
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
    }
    
    private function processCallbackQuery($callback_query) {
        $callback_data = $callback_query['data'];
        $admin_id = $callback_query['from']['id'];
        $callback_query_id = $callback_query['id'];
        
        if (strpos($callback_data, 'respond_') === 0) {
            $this->adminHandler->handleRespondRequest($callback_data, $admin_id);
            $this->api->answerCallbackQuery($callback_query_id, "Подготовка ответа...");
        }
        
        logMessage("Обработан callback: {$callback_data} от пользователя {$admin_id}");
    }
}

// Запуск бота
if (php_sapi_name() === 'cli') {
    $bot = new BotPolling($ADMINS);
    $bot->run();
} else {
    echo "Этот скрипт должен запускаться из командной строки.";
}
?>