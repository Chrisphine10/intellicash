<?php

use App\Cronjobs\TrialEndedTask;
use Illuminate\Support\Facades\Schedule;
use App\Cronjobs\SubscriptionReminderTask;
use App\Cronjobs\VslaMeetingReminderTask;


Schedule::call(new TrialEndedTask)->hourly();
Schedule::call(new SubscriptionReminderTask)->hourlyAt(10);
Schedule::call(new \App\Cronjobs\YearlyMaintenanceFeePosting)->hourly();
Schedule::call(new \App\Cronjobs\OverdueLoanNotification)->everyThirtyMinutes();
Schedule::call(new \App\Cronjobs\UpcommingLoanNotification)->everyTenMinutes();
Schedule::call(new VslaMeetingReminderTask)->everyThirtyMinutes();

Schedule::call(function () {
    update_option("cornjob_runs_at", now());
});