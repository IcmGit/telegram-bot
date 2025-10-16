<?php
class TenantHandler {
    private $db;
    private $api;
    private $admins;
    
    public function __construct($db, $api, $admins) {
        $this->db = $db;
        $this->api = $api;
        $this->admins = $admins;
    }
    
    public function handleStart($chat_id, $first_name = '') {
        $this->db->saveUserState($chat_id, 'waiting_name');
        
        $message = "👋 Добро пожаловать" . ($first_name ? ", {$first_name}!" : "!") . "\n\n";
        $message .= "Оставьте заявку для администрации бизнес-центра.\n\n";
        $message .= "Пожалуйста, введите ваше имя:";
        
        $this->api->sendMessage($chat_id, $message);
        logMessage("Пользователь {$chat_id} начал создание заявки");
    }
    
    public function handleName($chat_id, $name) {
        $this->db->saveUserState($chat_id, 'waiting_phone', ['name' => $name]);
        $this->api->sendMessage($chat_id, "📞 Теперь введите ваш номер телефона:");
    }
    
    public function handlePhone($chat_id, $phone) {
        $state = $this->db->getUserState($chat_id);
        $user_data = $state['state_data'] ?? [];
        $user_data['phone'] = $phone;
        
        $this->db->saveUserState($chat_id, 'waiting_message', $user_data);
        $this->api->sendMessage($chat_id, "💬 Опишите вашу проблему или вопрос:");
    }
    
    public function handleMessage($chat_id, $message_text) {
        $state = $this->db->getUserState($chat_id);
        $user_data = $state['state_data'] ?? [];
        $user_data['message'] = $message_text;
        
        $this->db->saveUserState($chat_id, 'waiting_photo', $user_data);
        
        $keyboard = [
            'keyboard' => [[['text' => '🚀 Отправить без фото']]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        $this->api->sendMessage($chat_id, 
            "📷 Вы можете прикрепить фото к заявке. Просто отправьте фото как обычное сообщение 📎.\n\n" .
            "Либо нажмите кнопку \"🚀 Отправить без фото\" ниже:",
            json_encode($keyboard)
        );
    }
    
    public function handlePhoto($chat_id, $photo_id) {
        global $ADMINS;
        
        $state = $this->db->getUserState($chat_id);
        $user_data = $state['state_data'] ?? [];
        
        // Сохраняем заявку и получаем номер заявки (id)
        $request_id = $this->db->saveRequest(
            $chat_id,
            $user_data['name'],
            $user_data['phone'],
            $user_data['message'],
            $photo_id
        );
        
        // Отправляем заявку администраторам
        $this->sendRequestToAdmins($request_id, $user_data, $photo_id);
        
        // Очищаем состояние
        $this->db->deleteUserState($chat_id);
        
        // Отправляем подтверждение
        $this->api->sendMessage($chat_id,
            "✅ Спасибо за вашу заявку #{$request_id}! Мы уже работаем по вашему вопросу.",
            json_encode(['remove_keyboard' => true])
        );
        
        logMessage("Заявка #{$request_id} создана с фото");
    }
    
    public function handleNoPhoto($chat_id) {
        global $ADMINS;
        
        $state = $this->db->getUserState($chat_id);
        $user_data = $state['state_data'] ?? [];
        
        // Сохраняем заявку и получаем номер заявки (id)
        $request_id = $this->db->saveRequest(
            $chat_id,
            $user_data['name'],
            $user_data['phone'],
            $user_data['message'],
            null
        );
        
        // Отправляем заявку администраторам
        $this->sendRequestToAdmins($request_id, $user_data, null);
        
        // Очищаем состояние
        $this->db->deleteUserState($chat_id);
        
        $this->api->sendMessage($chat_id,
            "✅ Спасибо за вашу заявку #{$request_id}! Мы уже работаем по вашему вопросу.",
            json_encode(['remove_keyboard' => true])
        );
        
        logMessage("Заявка #{$request_id} создана без фото");
    }
    
    private function sendRequestToAdmins($request_id, $user_data, $photo_id) {
        $message = "📨 *Новая заявка #{$request_id}*\n\n" .
                  "👤 *Имя:* {$user_data['name']}\n" .
                  "📞 *Телефон:* {$user_data['phone']}\n" .
                  "💬 *Сообщение:* {$user_data['message']}\n\n" .
                  "🕒 *Время подачи:* " . date('d.m.Y H:i:s');
        
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '💬 Ответить арендатору', 'callback_data' => "respond_{$request_id}"]
            ]]
        ];
        
        foreach ($this->admins as $admin_id) {
            if ($photo_id) {
                $this->api->sendPhoto($admin_id, $photo_id, $message, json_encode($keyboard));
            } else {
                $this->api->sendMessage($admin_id, $message, json_encode($keyboard));
            }
            logMessage("Заявка #{$request_id} отправлена администратору {$admin_id}");
        }
    }
    
    public function sendResponseToTenant($request_id, $response, $admin_id) {
        $request = $this->db->getRequest($request_id);
        if (!$request) {
            return false;
        }
        
        // Обновляем заявку в базе данных
        $this->db->updateRequestResponse($request_id, $response, $admin_id);
        
        // Отправляем ответ арендатору
        $this->api->sendMessage($request['user_id'],
            "📩 *Ответ от администратора по вашей заявке #{$request_id}:*\n\n" .
            "{$response}\n\n" .
            "——\n" .
            "Если у вас есть дополнительные вопросы, оставьте новую заявку через команду /start"
        );
        
        logMessage("Ответ на заявку #{$request_id} отправлен арендатору {$request['user_id']}");
        return true;
    }
}
?>