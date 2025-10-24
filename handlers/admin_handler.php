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
        try {
            logMessage("📨 Администратор {$admin_id} отправляет ответ: {$response_text}");
            
            // Получаем состояние администратора
            $admin_state = $this->db->getUserState($admin_id);
            
            if (!$admin_state || $admin_state['state'] !== 'waiting_response') {
                logMessage("❌ У администратора {$admin_id} нет состояния waiting_response");
                $this->api->sendMessage($admin_id, 
                    "❌ Сначала выберите заявку для ответа, нажав кнопку '💬 Ответить'"
                );
                return false;
            }
            
            $request_id = $admin_state['state_data']['request_id'] ?? null;
            
            if (!$request_id) {
                logMessage("❌ Ошибка: не найден ID заявки в состоянии администратора");
                $this->api->sendMessage($admin_id, "❌ Ошибка: не найден ID заявки");
                return false;
            }
            
            logMessage("🔍 Поиск заявки #{$request_id}");
            $request = $this->db->getRequest($request_id);
            
            if (!$request) {
                logMessage("❌ Заявка #{$request_id} не найдена");
                $this->api->sendMessage($admin_id, "❌ Заявка не найдена");
                return false;
            }
            
            $tenant_id = $request['user_id'];
            logMessage("👤 Отправка ответа пользователю {$tenant_id}");
            
            // ВКЛЮЧАЕМ СОХРАНЕНИЕ В ПЕРЕПИСКУ
            logMessage("💾 Сохранение сообщения в переписку...");
            $save_result = $this->db->saveConversationMessage($request_id, $admin_id, $response_text, 'admin');
            
            if (!$save_result) {
                logMessage("❌ Не удалось сохранить сообщение в переписку");
                // НЕ прерываем отправку - продолжаем без сохранения в переписку
                $this->api->sendMessage($admin_id, "⚠️ Сообщение отправлено, но не сохранено в историю");
            } else {
                logMessage("✅ Сообщение сохранено в переписку");
            }
            
            // Отправляем ответ арендатору
            $message = "📩 *Ответ по заявке #{$request_id}:*\n" .
                    "{$response_text}\n\n";
            
            logMessage("✉️ Отправка сообщения пользователю {$tenant_id}");
            $success = $this->api->sendMessage($tenant_id, $message);
            
            if ($success) {
                logMessage("✅ Ответ успешно отправлен пользователю {$tenant_id}");
                $this->api->sendMessage($admin_id, 
                    "✅ Ответ отправлен арендатору\n\n" .
                    "Заявка остается активной. Пользователь может продолжить диалог."
                );
            } else {
                logMessage("❌ Ошибка отправки ответа пользователю {$tenant_id}");
                $this->api->sendMessage($admin_id, "❌ Ошибка при отправке ответа");
            }
            
            return $success;
            
        } catch (Exception $e) {
            logMessage("💥 Критическая ошибка в handleAdminResponse: " . $e->getMessage());
            $this->api->sendMessage($admin_id, "❌ Произошла ошибка при отправке ответа");
            return false;
        }
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
            "🔒 *Заявка #{$request_id} закрыта*\n" .
            "Для новой заявки нажмите /start"
        );
        
        $this->api->sendMessage($admin_id, "✅ Заявка #{$request_id} закрыта");
        
        logMessage("✅ Заявка #{$request_id} закрыта администратором {$admin_id}");
        
        return true;
    }

}
?>