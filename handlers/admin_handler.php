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
        $state = $this->db->getUserState($admin_id);
        
        if (!$state || $state['state'] !== 'waiting_response') {
            return false;
        }
        
        $request_id = $state['state_data']['request_id'] ?? null;
        
        if (!$request_id) {
            $this->api->sendMessage($admin_id, "❌ Ошибка: не найден ID заявки");
            return false;
        }
        
        // Отправляем ответ арендатору
        $success = $this->tenantHandler->sendResponseToTenant($request_id, $response_text, $admin_id);
        
        // Сбрасываем состояние администратора
        $this->db->deleteUserState($admin_id);
        
        if ($success) {
            $this->api->sendMessage($admin_id, "✅ Ответ успешно отправлен арендатору");
        } else {
            $this->api->sendMessage($admin_id, "❌ Ошибка при отправке ответа");
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
}
?>