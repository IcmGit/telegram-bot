<?php
class TelegramAPI {
    private $token;
    private $api_url;
    
    public function __construct($token) {
        $this->token = $token;
        $this->api_url = "https://api.telegram.org/bot{$token}/";
    }
    
    public function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'Markdown') {
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => $parse_mode
        ];
        
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        
        return $this->request('sendMessage', $data);
    }
    
    public function sendPhoto($chat_id, $photo, $caption = null, $reply_markup = null) {
        $data = [
            'chat_id' => $chat_id,
            'photo' => $photo
        ];
        
        if ($caption) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'Markdown';
        }
        
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        
        return $this->request('sendPhoto', $data);
    }
    
    public function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
        $data = [
            'callback_query_id' => $callback_query_id,
            'show_alert' => $show_alert
        ];
        
        if ($text) {
            $data['text'] = $text;
        }
        
        return $this->request('answerCallbackQuery', $data);
    }
    
    public function getUpdates($offset = null, $limit = 100, $timeout = 30) {
        $data = [
            'timeout' => $timeout,
            'limit' => $limit
        ];
        
        if ($offset !== null) {
            $data['offset'] = $offset;
        }
        
        return $this->request('getUpdates', $data);
    }
    
    public function setWebhook($url) {
        return $this->request('setWebhook', ['url' => $url]);
    }
    
    public function deleteWebhook() {
        return $this->request('deleteWebhook');
    }
    
    public function getWebhookInfo() {
        return $this->request('getWebhookInfo');
    }
    
    public function getMe() {
        return $this->request('getMe');
    }
    
    private function request($method, $data = []) {
        $url = $this->api_url . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            logMessage("cURL Error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($response === false) {
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['ok']) || !$result['ok']) {
            logMessage("API Error: " . $response);
            return false;
        }
        
        return $result;
    }
}
?>