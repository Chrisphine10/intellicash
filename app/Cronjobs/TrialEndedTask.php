<?php

namespace App\Cronjobs;

use App\Models\User;
use App\Notifications\TrialEndedNotification;

class TrialEndedTask {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $users = User::where('membership_type', 'trial')
            ->whereDate('valid_to', '<', date('Y-m-d'))
            ->where('t_email_send_at', null)
            ->limit(10)
            ->get();

        foreach ($users as $user) {
            try {
                $user->notify(new TrialEndedNotification($user));
                $user->t_email_send_at = now();
                $user->save();
            } catch (\Exception $e) {
                // Nothing
            }
        }
    }

}