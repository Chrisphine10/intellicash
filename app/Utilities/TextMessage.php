<?php
namespace App\Utilities;

use Textmagic\Services\TextmagicRestClient;
use Twilio\Rest\Client;

class TextMessage {

    public function send($to, $message) {
        // Enhanced phone number validation
        if (!$this->validatePhoneNumber($to)) {
            \Log::warning('Invalid phone number provided for SMS', ['phone' => $to]);
            return false;
        }
        
        // Sanitize message content
        $message = $this->sanitizeMessage($message);
        
        // Check rate limiting
        if (!$this->checkRateLimit($to)) {
            \Log::warning('SMS rate limit exceeded', ['phone' => $to]);
            return false;
        }

        if (app()->bound('tenant')) {
            if (get_tenant_option('sms_gateway') == 'twilio') {
                $this->twilio($to, $message);
            } else if (get_tenant_option('sms_gateway') == 'textmagic') {
                $this->textMagic($to, $message);
            } else if (get_tenant_option('sms_gateway') == 'nexmo') {
                $this->nexmo($to, $message);
            } else if (get_tenant_option('sms_gateway') == 'infobip') {
                $this->infobip($to, $message);
            } else if (get_tenant_option('sms_gateway') == 'africas_talking') {
                $this->africasTalking($to, $message);
            }
        } else {
            if (get_option('sms_gateway') == 'twilio') {
                $this->twilio($to, $message);
            } else if (get_option('sms_gateway') == 'textmagic') {
                $this->textMagic($to, $message);
            } else if (get_option('sms_gateway') == 'nexmo') {
                $this->nexmo($to, $message);
            } else if (get_option('sms_gateway') == 'infobip') {
                $this->infobip($to, $message);
            } else if (get_option('sms_gateway') == 'africas_talking') {
                $this->africasTalking($to, $message);
            }
        }
    }

    public function twilio($to, $message) {
        if (app()->bound('tenant')) {
            $account_sid   = get_tenant_option('twilio_account_sid');
            $auth_token    = get_tenant_option('twilio_auth_token');
            $twilio_number = get_tenant_option('twilio_number');
        } else {
            $account_sid   = get_option('twilio_account_sid');
            $auth_token    = get_option('twilio_auth_token');
            $twilio_number = get_option('twilio_number');
        }

        $client = new Client($account_sid, $auth_token);
        try {
            $client->messages->create('+' . $to,
                ['from' => $twilio_number, 'body' => $message]);
        } catch (\Exception $e) {}
    }

    public function textMagic($to, $message) {
        if (app()->bound('tenant')) {
            $text_magic_username = get_tenant_option('textmagic_username');
            $textmagic_api_key   = get_tenant_option('textmagic_api');
        } else {
            $text_magic_username = get_option('textmagic_username');
            $textmagic_api_key   = get_option('textmagic_api_key');
        }

        $client = new TextmagicRestClient($text_magic_username, $textmagic_api_key);
        try {
            $response = $client->messages->create(
                [
                    'text'   => $message,
                    'phones' => $to,
                ]
            );
        } catch (\Exception $e) {}
    }

    public function nexmo($to, $message) {
        if (app()->bound('tenant')) {
            $nexmo_api_key    = get_tenant_option('nexmo_api_key');
            $nexmo_api_secret = get_tenant_option('nexmo_api_secret');
            $fromName         = get_tenant_option('company_name');
        } else {
            $nexmo_api_key    = get_option('nexmo_api_key');
            $nexmo_api_secret = get_option('nexmo_api_secret');
            $fromName         = get_option('company_name');
        }

        $setup    = new \Vonage\Client\Credentials\Basic($nexmo_api_key, $nexmo_api_secret);
        $client   = new \Vonage\Client($setup);
        $response = $client->sms()->send(
            new \Vonage\SMS\Message\SMS($to, $fromName, $message)
        );
        $message = $response->current();
    }

    public function infobip($to, $message) {
        if (app()->bound('tenant')) {
            $infobip_api_key      = get_tenant_option('infobip_api_key');
            $infobip_api_base_url = get_tenant_option('infobip_api_base_url');
            $fromName             = get_tenant_option('company_name');
        } else {
            $infobip_api_key      = get_option('infobip_api_key');
            $infobip_api_base_url = get_option('infobip_api_base_url');
            $fromName             = get_option('company_name');
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://' . preg_replace("(^https?://)", "", $infobip_api_base_url) . '/sms/2/text/advanced',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => '{"messages":[{"destinations":[{"to":"' . $to . '"}],"from":"' . $fromName . '","text":"' . $message . '"}]}',
            CURLOPT_HTTPHEADER     => [
                "Authorization: App $infobip_api_key",
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
    }

    public function africasTalking($to, $message) {
        if (app()->bound('tenant')) {
            $africas_talking_username = get_tenant_option('africas_talking_username');
            $africas_talking_api_key  = get_tenant_option('africas_talking_api_key');
            $africas_talking_sender_id = get_tenant_option('africas_talking_sender_id');
        } else {
            $africas_talking_username = get_option('africas_talking_username');
            $africas_talking_api_key  = get_option('africas_talking_api_key');
            $africas_talking_sender_id = get_option('africas_talking_sender_id');
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.africastalking.com/version1/messaging',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query([
                'username' => $africas_talking_username,
                'to' => $to,
                'message' => $message,
                'from' => $africas_talking_sender_id
            ]),
            CURLOPT_HTTPHEADER     => [
                "apiKey: $africas_talking_api_key",
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        // Enhanced error handling and logging
        if ($error) {
            \Log::error('Africa\'s Talking SMS Error', [
                'error' => $error,
                'phone' => $to,
                'message_length' => strlen($message)
            ]);
            return false;
        }
        
        if ($httpCode !== 200) {
            \Log::error('Africa\'s Talking SMS HTTP Error', [
                'http_code' => $httpCode,
                'response' => $response,
                'phone' => $to
            ]);
            return false;
        }
        
        \Log::info('Africa\'s Talking SMS sent successfully', [
            'phone' => $to,
            'message_length' => strlen($message),
            'response' => $response
        ]);
        
        return true;
    }
    
    /**
     * Validate phone number format and security
     */
    private function validatePhoneNumber($phone) {
        if (empty($phone) || strlen($phone) < 8) {
            return false;
        }
        
        // Remove any non-numeric characters except + at the beginning
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        
        // Check for suspicious patterns
        if (preg_match('/^(\+?1?[0-9]{10,15})$/', $cleaned)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Sanitize SMS message content
     */
    private function sanitizeMessage($message) {
        // Remove potential XSS and injection attempts
        $message = strip_tags($message);
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Limit message length
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }
        
        return $message;
    }
    
    /**
     * Check SMS rate limiting
     */
    private function checkRateLimit($phone) {
        $key = 'sms_rate_limit_' . md5($phone);
        $attempts = \Cache::get($key, 0);
        
        // Allow max 5 SMS per phone per hour
        if ($attempts >= 5) {
            return false;
        }
        
        \Cache::put($key, $attempts + 1, 3600); // 1 hour
        return true;
    }

}