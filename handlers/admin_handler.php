<?php
class AdminHandler {
    private $db;
    private $api;
    private $tenantHandler;
    
    public function __construct($db, $api, $tenantHandler) {
        $this->db = $db;
        $this->api = $api;
        $this->tenantHandler = $tenantHandler;
    }
    
    public function handleRespondRequest($callback_data, $admin_id) {
        $request_id = str_replace('respond_', '', $callback_data);
        $request = $this->db->getRequest($request_id);
        
        if (!$request) {
            $this->api->sendMessage($admin_id, "❌ Заявка не найдена");
            return;
        }
        
        // Сохраняем состояние администратора - ожидаем ответ
        $this->db->saveUserState($admin_id, 'waiting_response', ['request_id' => $request_id]);
        
        $message = "✍️ Введите ответ для арендатора *{$request['user_name']}* по заявке #{$request_id}:\n\n";
        $message .= "*Текст заявки:* {$request['message']}";
        
        if ($request['phone']) {
            $message .= "\n*Телефон:* {$request['phone']}";
        }
        
        $this->api->sendMessage($admin_id, $message);
        logMessage("Администратор {$admin_id} начал ответ на заявку #{$request_id}");
    }
    
    public function handleAdminResponse($admin_id, $response_text) {
        logMessage("📨 Администратор {$admin_id} отправляет ответ: {$response_text}");
        
        // Получаем состояние администратора
        $admin_state = $this->db->getUserState($admin_id);
        
        if (!$admin_state || $admin_state['state'] !== 'waiting_response') {
            $this->api->sendMessage($admin_id, 
                "❌ Сначала выберите заявку для ответа, нажав кнопку '💬 Ответить'"
            );
            return false;
        }
        
        $request_id = $admin_state['state_data']['request_id'] ?? null;
        
        if (!$request_id) {
            $this->api->sendMessage($admin_id, "❌ Ошибка: не найден ID заявки");
            return false;
        }
        
        $request = $this->db->getRequest($request_id);
        
        if (!$request) {
            $this->api->sendMessage($admin_id, "❌ Заявка не найдена");
            return false;
        }
        
        $tenant_id = $request['user_id'];
        
        // Сохраняем сообщение администратора в переписку
        $this->db->saveConversationMessage($request_id, $admin_id, $response_text, 'admin');
        
        // Отправляем ответ арендатору
        $message = "📩 *Ответ администратора по заявке #{$request_id}:*\n\n" .
                "{$response_text}\n\n" .
                "——\n" .
                "Вы можете ответить на это сообщение - просто напишите текст ниже.";
        
        $success = $this->api->sendMessage($tenant_id, $message);
        
        if ($success) {
            $this->api->sendMessage($admin_id, 
                "✅ Ответ отправлен арендатору\n\n" .
                "Заявка остается активной. Пользователь может продолжить диалог."
            );
            
            logMessage("✅ Ответ администратора {$admin_id} отправлен пользователю {$tenant_id}");
            
            // НЕ очищаем состояние администратора - он может отправлять еще ответы
            // Состояние очистится только при закрытии заявки или таймауте
            
        } else {
            $this->api->sendMessage($admin_id, "❌ Ошибка при отправке ответа");
            logMessage("❌ Ошибка отправки ответа пользователю {$tenant_id}");
        }
        
        return $success;
    }
    
    public function showAdminPanel($admin_id) {
        // Можно добавить статистику и т.д.
        $message = "👑 *Панель администратора*\n\n";
        $message .= "Для ответа на заявки используйте кнопку 'Ответить арендатору' под каждой заявкой.\n\n";
        $message .= "Бот работает в режиме приема заявок от арендаторов.";
        
        $this->api->sendMessage($admin_id, $message);
    }

    public function handleCloseRequest($callback_data, $admin_id) {
        $request_id = str_replace('close_', '', $callback_data);
        
        logMessage("🔒 Администратор {$admin_id} закрывает заявку #{$request_id}");
        
        $request = $this->db->getRequest($request_id);
        
        if (!$request) {
            $this->api->sendMessage($admin_id, "❌ Заявка не найдена");
            return false;
        }
        
        // Закрываем заявку
        $this->db->closeRequest($request_id);
        
        // Уведомляем пользователя
        $this->api->sendMessage($request['user_id'],
            "🔒 *Заявка #{$request_id} закрыта*\n\n" .
            "Администратор закрыл вашу заявку. Если у вас возникнут новые вопросы, " .
            "создайте новую заявку через команду /start"
        );
        
        $this->api->sendMessage($admin_id, "✅ Заявка #{$request_id} закрыта");
        
        logMessage("✅ Заявка #{$request_id} закрыта администратором {$admin_id}");
        
        return true;
    }

}
?>