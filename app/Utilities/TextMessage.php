<?php
namespace App\Utilities;

use Textmagic\Services\TextmagicRestClient;
use Twilio\Rest\Client;

class TextMessage {

    public function send($to, $message) {
        if ($to < 8 || $to == null) {
            return;
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
        curl_close($curl);
    }

}