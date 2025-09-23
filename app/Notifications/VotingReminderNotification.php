<?php

namespace App\Notifications;

use App\Models\Election;
use App\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VotingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $election;
    protected $hoursRemaining;

    public function __construct(Election $election, $hoursRemaining = 24)
    {
        $this->election = $election;
        $this->hoursRemaining = $hoursRemaining;
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
            ->subject(_lang('Voting Reminder: :title', ['title' => $this->election->title]))
            ->greeting(_lang('Hello :name', ['name' => $notifiable->name]))
            ->line(_lang('This is a reminder that voting is still open for an important election in your VSLA group.'))
            ->line(_lang('Election Title: :title', ['title' => $this->election->title]))
            ->line(_lang('Time Remaining: :hours hours', ['hours' => $this->hoursRemaining]))
            ->line(_lang('Voting Ends: :end', ['end' => $this->election->end_date->format('M d, Y H:i')]))
            ->action(_lang('Vote Now'), route('voting.elections.vote', $this->election->id))
            ->line(_lang('Please participate in this important decision for your group.'));
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => _lang('Voting Reminder: :title', ['title' => $this->election->title]),
            'message' => _lang('Voting ends in :hours hours. Please vote now!', ['hours' => $this->hoursRemaining]),
            'election_id' => $this->election->id,
            'type' => 'voting_reminder',
        ];
    }

    public function toSMS($notifiable)
    {
        $message = _lang('Voting reminder: :title ends in :hours hours. Vote now!', [
            'title' => $this->election->title,
            'hours' => $this->hoursRemaining
        ]);

        return (new SmsMessage())
            ->setRecipient($notifiable->mobile)
            ->setContent($message);
    }
}
