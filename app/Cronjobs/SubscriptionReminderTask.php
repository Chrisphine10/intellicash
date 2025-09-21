<?php

namespace App\Cronjobs;

use Carbon\Carbon;
use App\Models\User;
use App\Notifications\SubscriptionReminder;

class SubscriptionReminderTask {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $date = Carbon::now()->addDays(7);
        $users = User::where('membership_type', 'member')
            ->whereDate('valid_to', '<=', $date)
            ->where('s_email_send_at', null)
            ->limit(10)
            ->get();

        foreach ($users as $user) {
            try {
                $user->notify(new SubscriptionReminder($user));
                $user->s_email_send_at = now();
                $user->save();
            } catch (\Exception $e) {
                // Nothing
            }
        }
    }

}