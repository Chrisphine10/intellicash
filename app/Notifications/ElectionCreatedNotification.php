<?php

namespace App\Notifications;

use App\Models\Election;
use App\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ElectionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $election;

    public function __construct(Election $election)
    {
        $this->election = $election;
    }

    public function via($notifiable)
    {
        $channels = ['mail', 'database'];
        
        // Add SMS if mobile number is available
        if ($notifiable->mobile) {
            $channels[] = \App\Channels\SMS::class;
        }
        
        return $channels;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(_lang('New Election: :title', ['title' => $this->election->title]))
            ->greeting(_lang('Hello :name', ['name' => $notifiable->name]))
            ->line(_lang('A new election has been created in your VSLA group.'))
            ->line(_lang('Election Title: :title', ['title' => $this->election->title]))
            ->line(_lang('Description: :description', ['description' => $this->election->description]))
            ->line(_lang('Voting Period: :start to :end', [
                'start' => $this->election->start_date->format('M d, Y H:i'),
                'end' => $this->election->end_date->format('M d, Y H:i')
            ]))
            ->action(_lang('View Election'), route('voting.elections.show', $this->election->id))
            ->line(_lang('Please participate in this important decision for your group.'));
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => _lang('New Election: :title', ['title' => $this->election->title]),
            'message' => _lang('A new election has been created. Voting period: :start to :end', [
                'start' => $this->election->start_date->format('M d, Y H:i'),
                'end' => $this->election->end_date->format('M d, Y H:i')
            ]),
            'election_id' => $this->election->id,
            'type' => 'election_created',
        ];
    }

    public function toSMS($notifiable)
    {
        $message = _lang('New election: :title. Voting period: :start to :end. Please participate!', [
            'title' => $this->election->title,
            'start' => $this->election->start_date->format('M d, Y H:i'),
            'end' => $this->election->end_date->format('M d, Y H:i')
        ]);

        return (new SmsMessage())
            ->setRecipient($notifiable->mobile)
            ->setContent($message);
    }
}
