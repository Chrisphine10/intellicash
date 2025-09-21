<?php

namespace App\Notifications;

use App\Channels\SmsMessage;
use App\Models\EmailTemplate;
use App\Utilities\Overrider;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VslaMeetingReminder extends Notification
{
    use Queueable;

    private $vslaSettings;
    private $meetingDate;
    private $template;
    private $replace = [];

    /**
     * Create a new notification instance.
     */
    public function __construct($vslaSettings, $meetingDate)
    {
        $this->vslaSettings = $vslaSettings;
        $this->meetingDate = $meetingDate;
        
        Overrider::load("Settings");
        $this->template = EmailTemplate::where('slug', 'VSLA_MEETING_REMINDER')
            ->where('tenant_id', $vslaSettings->tenant_id)
            ->first();

        $this->replace['memberName'] = '';
        $this->replace['meetingDate'] = $meetingDate->format('l, F j, Y');
        $this->replace['meetingTime'] = $vslaSettings->getFormattedMeetingTime();
        $this->replace['meetingDays'] = $vslaSettings->getMeetingDaysString();
        $this->replace['vslaName'] = $vslaSettings->tenant->name ?? 'VSLA Group';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        $channels = [];
        
        // Add email if template exists and is enabled
        if ($this->template != null && $this->template->email_status == 1) {
            $channels[] = 'mail';
        }
        
        // Add SMS if template exists and is enabled
        if ($this->template != null && $this->template->sms_status == 1) {
            $channels[] = \App\Channels\SMS::class;
        }
        
        // Always add database notification
        $channels[] = 'database';
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $this->replace['memberName'] = $notifiable->name;
        
        if ($this->template) {
            $message = processShortCode($this->template->email_body, $this->replace);
            $subject = processShortCode($this->template->subject, $this->replace);
            
            return (new MailMessage)
                ->subject($subject)
                ->view('email.vsla-meeting-reminder', [
                    'message' => $message,
                    'memberName' => $this->replace['memberName'],
                    'meetingDate' => $this->replace['meetingDate'],
                    'meetingTime' => $this->replace['meetingTime'],
                    'meetingDays' => $this->replace['meetingDays'],
                    'vslaName' => $this->replace['vslaName']
                ]);
        }
        
        // Fallback message if no template
        return (new MailMessage)
            ->subject('VSLA Meeting Reminder')
            ->line('Dear ' . $notifiable->name . ',')
            ->line('This is a reminder that your VSLA meeting is scheduled for:')
            ->line('Date: ' . $this->replace['meetingDate'])
            ->line('Time: ' . $this->replace['meetingTime'])
            ->line('Please ensure you attend the meeting on time.')
            ->line('Thank you!');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSMS($notifiable)
    {
        $this->replace['memberName'] = $notifiable->name;
        
        if ($this->template) {
            $message = processShortCode($this->template->sms_body, $this->replace);
        } else {
            // Fallback SMS message
            $message = "VSLA Meeting Reminder: " . $this->replace['meetingDate'] . " at " . $this->replace['meetingTime'] . ". Please attend on time.";
        }

        return (new SmsMessage())
            ->setContent($message)
            ->setRecipient($notifiable->country_code . $notifiable->mobile);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        $this->replace['memberName'] = $notifiable->name;
        
        if ($this->template) {
            $message = processShortCode($this->template->notification_body, $this->replace);
            return ['subject' => $this->template->subject, 'message' => $message];
        }
        
        return [
            'subject' => 'VSLA Meeting Reminder',
            'message' => 'VSLA meeting scheduled for ' . $this->replace['meetingDate'] . ' at ' . $this->replace['meetingTime']
        ];
    }
}
