<?php
namespace App\Cronjobs;

use App\Models\LoanRepayment;
use App\Notifications\UpcommingLoanRepayment;
use Carbon\Carbon;
use Exception;

class UpcommingLoanNotification {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $upcommingRepayments = LoanRepayment::withoutGlobalScopes()
            ->where('repayment_date', '<=', Carbon::now()->addDays(3))
            ->where('repayment_date', '>=', Carbon::now())
            ->where('status', 0)
            ->whereNull('upcomming_notification')
            ->limit(10)
            ->get();

        foreach ($upcommingRepayments as $upcommingRepayment) {
            try {
                $upcommingRepayment->loan->borrower->notify(new UpcommingLoanRepayment($upcommingRepayment, $upcommingRepayment->loan->tenant_id));
                $upcommingRepayment->upcomming_notification = now();
                $upcommingRepayment->save();
            } catch (Exception $e) {}
        }

    }

}