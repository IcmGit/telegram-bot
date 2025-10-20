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

// ДИАГНОСТИКА: Логируем факт запуска вебхука
logMessage("=== Webhook вызван ===");

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

        logMessage("BotWebhook инициализирован");
    }
    
    public function processUpdate($update) {
        try {
            logMessage("Webhook получено обновление: " . json_encode($update));
            
            // ДИАГНОСТИКА: Логируем тип обновления
            if (isset($update['message'])) {
                $chat_id = $update['message']['chat']['id'];
                $text = $update['message']['text'] ?? '';
                logMessage("Тип: message, chat_id: {$chat_id}, text: {$text}");
            } elseif (isset($update['callback_query'])) {
                $chat_id = $update['callback_query']['from']['id'];
                $data = $update['callback_query']['data'] ?? '';
                logMessage("Тип: callback_query, chat_id: {$chat_id}, data: {$data}");
            } else {
                logMessage("Тип: unknown, update: " . json_encode($update));
            }

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
            
            logMessage("Обработка сообщения от {$chat_id}: {$text}");
            
            $is_admin = in_array($chat_id, $this->admins);
            $user_state = $this->db->getUserState($chat_id);
            
            // ВАЖНОЕ ИСПРАВЛЕНИЕ: Сначала проверяем фото
            if ($photo && $user_state && $user_state['state'] === 'waiting_photo') {
                logMessage("Обработка фото от {$chat_id}");
                $this->tenantHandler->handlePhoto($chat_id, $photo);
                return;
            }

            // Обработка ответов администратора
            if ($is_admin && $user_state && $user_state['state'] === 'waiting_response') {
                logMessage("Обработка ответа администратора от {$chat_id}");
                $this->adminHandler->handleAdminResponse($chat_id, $text);
                return;
            }
            
            // Обработка состояний пользователя (создание заявки)
            if ($user_state) {
                logMessage("Обработка состояния пользователя {$chat_id}: {$user_state['state']}");
                $this->processUserState($chat_id, $text, $photo, $user_state);
                return;
            }
            
            // Обработка ответов пользователя на сообщения администратора
            if (!$is_admin) {
                // Проверяем есть ли у пользователя активная заявка
                $active_request = $this->db->getActiveRequestByUser($chat_id);
                if ($active_request && !in_array($text, ['/start', '/newrequest'])) {
                    logMessage("👤 Пользователь {$chat_id} отвечает по активной заявке #{$active_request['id']}");
                    
                    // ВЫЗЫВАЕМ handleTenantReply напрямую
                    $this->tenantHandler->handleTenantReply($chat_id, $text);
                    return; // Важно: завершаем обработку после вызова
                }
            }
            
            // Обработка команд
            if ($text === '/start' || $text === '/newrequest') {
                logMessage("Запуск handleStart для {$chat_id}");
                $this->tenantHandler->handleStart($chat_id, $first_name);
            } elseif ($is_admin && $text === '/admin') {
                logMessage("Запуск showAdminPanel для администратора {$chat_id}");
                $this->adminHandler->showAdminPanel($chat_id);
            } else {
                logMessage("Отправка стандартного ответа {$chat_id}");
                $this->api->sendMessage($chat_id, "Используйте /start для создания новой заявки.");
            }
            
        } catch (Exception $e) {
            logMessage("❌ Ошибка в processMessage: " . $e->getMessage());
        }
    }
    
    private function processUserState($chat_id, $text, $photo, $user_state) {
        try {
            logMessage("processUserState: {$chat_id} -> {$user_state['state']}");
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
            
            logMessage("🔘 Обработка callback: {$callback_data} от администратора {$admin_id}");
            
            if (strpos($callback_data, 'respond_') === 0) {
                logMessage("📝 Администратор {$admin_id} нажал 'Ответить' по заявке {$callback_data}");
                
                $request_id = str_replace('respond_', '', $callback_data);
                $this->db->saveUserState($admin_id, 'waiting_response', ['request_id' => $request_id]);
                
                $this->adminHandler->handleRespondRequest($callback_data, $admin_id);
                $this->api->answerCallbackQuery($callback_query_id, "Подготовка ответа...");
                
            } elseif (strpos($callback_data, 'close_') === 0) {
                logMessage("🔒 Администратор {$admin_id} нажал 'Закрыть заявку' {$callback_data}");
                
                $this->adminHandler->handleCloseRequest($callback_data, $admin_id);
                $this->api->answerCallbackQuery($callback_query_id, "Закрытие заявки...");
                
                // Очищаем состояние администратора при закрытии заявки
                $this->db->deleteUserState($admin_id);
            }
            
        } catch (Exception $e) {
            logMessage("❌ Ошибка в processCallbackQuery: " . $e->getMessage());
        }
    }
}

// Обработка входящего вебхука
$input = file_get_contents('php://input');
$update = json_decode($input, true);

logMessage("Получен input: " . substr($input, 0, 500)); // Логируем первые 500 символов

if ($update) {
    try {
        $bot = new BotWebhook($ADMINS);
        $bot->processUpdate($update);
    } catch (Exception $e) {
        logMessage("💥 Критическая ошибка при создании бота: " . $e->getMessage());
    }
} else {
    logMessage("❌ Не удалось декодировать update из input");
}

// ВАЖНО: Всегда возвращаем 200 OK
http_response_code(200);
echo 'OK';

?>