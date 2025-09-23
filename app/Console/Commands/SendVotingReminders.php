<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\Member;
use App\Notifications\VotingReminderNotification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendVotingReminders extends Command
{
    protected $signature = 'voting:send-reminders';
    protected $description = 'Send voting reminders for elections ending soon';

    public function handle()
    {
        $this->info('Starting voting reminder process...');

        // Find elections ending in the next 24 hours that are still active
        $elections = Election::where('status', 'active')
            ->where('end_date', '>', now())
            ->where('end_date', '<=', now()->addHours(24))
            ->get();

        $remindersSent = 0;

        foreach ($elections as $election) {
            $this->info("Processing election: {$election->title}");

            // Get all members who haven't voted yet
            $votedMemberIds = $election->votes()->pluck('member_id')->toArray();
            
            $members = Member::where('tenant_id', $election->tenant_id)
                ->where('status', 1)
                ->whereNotIn('id', $votedMemberIds)
                ->get();

            foreach ($members as $member) {
                if ($member->user) {
                    $hoursRemaining = now()->diffInHours($election->end_date, false);
                    
                    $member->user->notify(
                        new VotingReminderNotification($election, $hoursRemaining)
                    );
                    
                    $remindersSent++;
                }
            }

            $this->info("Sent reminders to {$members->count()} members for election: {$election->title}");
        }

        $this->info("Voting reminder process completed. Sent {$remindersSent} reminders.");
    }
}
