<?php

namespace App\Notifications;

use App\Models\Election;
use App\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ElectionResultsNotification extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)
            ->subject(_lang('Election Results: :title', ['title' => $this->election->title]))
            ->greeting(_lang('Hello :name', ['name' => $notifiable->name]))
            ->line(_lang('The election results are now available.'))
            ->line(_lang('Election Title: :title', ['title' => $this->election->title]));

        if ($this->election->type === 'referendum') {
            $yesVotes = $this->election->results()->where('choice', 'yes')->sum('total_votes');
            $noVotes = $this->election->results()->where('choice', 'no')->sum('total_votes');
            $totalVotes = $yesVotes + $noVotes;
            
            $mail->line(_lang('Referendum Results:'))
                 ->line(_lang('Yes: :count votes (:percentage%)', [
                     'count' => $yesVotes,
                     'percentage' => $totalVotes > 0 ? round(($yesVotes / $totalVotes) * 100, 2) : 0
                 ]))
                 ->line(_lang('No: :count votes (:percentage%)', [
                     'count' => $noVotes,
                     'percentage' => $totalVotes > 0 ? round(($noVotes / $totalVotes) * 100, 2) : 0
                 ]));
        } else {
            $winners = $this->election->results()->winners()->with('candidate.member')->get();
            $mail->line(_lang('Election Winners:'));
            foreach ($winners as $result) {
                $mail->line(_lang('- :name: :votes votes (:percentage%)', [
                    'name' => $result->candidate->name,
                    'votes' => $result->total_votes,
                    'percentage' => $result->percentage
                ]));
            }
        }

        $mail->action(_lang('View Full Results'), route('voting.elections.results', $this->election->id))
             ->line(_lang('Thank you for participating in the democratic process.'));

        return $mail;
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => _lang('Election Results: :title', ['title' => $this->election->title]),
            'message' => _lang('The election results are now available.'),
            'election_id' => $this->election->id,
            'type' => 'election_results',
        ];
    }

    public function toSMS($notifiable)
    {
        $message = _lang('Election results available: :title. Check your portal for details.', [
            'title' => $this->election->title
        ]);

        return (new SmsMessage())
            ->setRecipient($notifiable->mobile)
            ->setContent($message);
    }
}
