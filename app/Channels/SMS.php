<?php

namespace App\Channels;

use App\Utilities\TextMessage;
use Illuminate\Notifications\Notification;

class SMS {
    /**
     * @param $notifiable
     * @param Notification $notification
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function send($notifiable, Notification $notification) {
        $message = $notification->toSMS($notifiable);

        try {
            $sms = new TextMessage();
            $result = $sms->send($message->getRecipient(), $message->getContent());
            
            if (!$result) {
                \Log::warning('SMS delivery failed', [
                    'recipient' => $message->getRecipient(),
                    'notification_type' => get_class($notification),
                    'notifiable_id' => $notifiable->id ?? 'unknown'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('SMS Channel Exception', [
                'error' => $e->getMessage(),
                'recipient' => $message->getRecipient(),
                'notification_type' => get_class($notification),
                'notifiable_id' => $notifiable->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}