<?php

namespace App\Notifications;

use App\Channels\SmsMessage;
use App\Models\EmailTemplate;
use App\Utilities\Overrider;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VslaRoleAssignmentNotification extends Notification
{
    use Queueable;

    private $roleAssignment;
    private $template;
    private $replace = [];

    /**
     * Create a new notification instance.
     */
    public function __construct($roleAssignment)
    {
        $this->roleAssignment = $roleAssignment;
        
        Overrider::load("Settings");
        $this->template = EmailTemplate::where('slug', 'VSLA_ROLE_ASSIGNMENT')
            ->where('tenant_id', $roleAssignment->tenant_id)
            ->first();

        $this->replace['memberName'] = '';
        $this->replace['roleName'] = ucfirst($roleAssignment->role);
        $this->replace['assignedBy'] = $roleAssignment->assignedBy->name ?? 'Administrator';
        $this->replace['assignedDate'] = $roleAssignment->assigned_at->format('F j, Y');
        $this->replace['notes'] = $roleAssignment->notes ?? '';
        $this->replace['vslaName'] = $roleAssignment->tenant->name ?? 'VSLA Group';
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
        $this->replace['memberName'] = $notifiable->first_name . ' ' . $notifiable->last_name;
        
        if ($this->template) {
            $message = processShortCode($this->template->email_body, $this->replace);
            return (new MailMessage)
                ->subject($this->template->subject)
                ->markdown('email.notification', ['message' => $message]);
        }
        
        // Fallback message if no template
        return (new MailMessage)
            ->subject('VSLA Role Assignment')
            ->line('Dear ' . $this->replace['memberName'] . ',')
            ->line('You have been assigned the role of ' . $this->replace['roleName'] . ' in your VSLA group.')
            ->line('Assigned by: ' . $this->replace['assignedBy'])
            ->line('Date: ' . $this->replace['assignedDate'])
            ->when($this->replace['notes'], function ($mail) {
                return $mail->line('Notes: ' . $this->replace['notes']);
            })
            ->line('Please fulfill your responsibilities as ' . $this->replace['roleName'] . '.')
            ->line('Thank you!');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSMS($notifiable)
    {
        $this->replace['memberName'] = $notifiable->first_name . ' ' . $notifiable->last_name;
        
        if ($this->template) {
            $message = processShortCode($this->template->sms_body, $this->replace);
        } else {
            // Fallback SMS message
            $message = "You have been assigned as " . $this->replace['roleName'] . " in your VSLA group. Please fulfill your responsibilities.";
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
        $this->replace['memberName'] = $notifiable->first_name . ' ' . $notifiable->last_name;
        
        if ($this->template) {
            $message = processShortCode($this->template->notification_body, $this->replace);
            return ['subject' => $this->template->subject, 'message' => $message];
        }
        
        return [
            'subject' => 'VSLA Role Assignment',
            'message' => 'You have been assigned the role of ' . $this->replace['roleName'] . ' in your VSLA group.'
        ];
    }
}
