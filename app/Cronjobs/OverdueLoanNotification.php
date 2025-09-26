<?php
namespace App\Cronjobs;

use App\Models\LoanRepayment;
use App\Notifications\OverdueLoanPayment;
use Exception;

class OverdueLoanNotification {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $date          = date('Y-m-d');
        $dueRepayments = LoanRepayment::withoutGlobalScopes()
            ->selectRaw('loan_id, MAX(repayment_date) as repayment_date, COUNT(id) as total_due_repayment, SUM(principal_amount) as total_due')
            ->with('loan.currency')
            ->where('repayment_date', '<', $date)
            ->where('status', 0)
            ->whereNull('overdue_notification')
            ->groupBy('loan_id')
            ->limit(10)
            ->get();

        foreach ($dueRepayments as $dueRepayment) {
            try {
                $dueRepayment->loan->borrower->notify(new OverdueLoanPayment($dueRepayment, $dueRepayment->loan->tenant_id));
                if ($dueRepayment->total_due_repayment > 0) {
                    LoanRepayment::withoutGlobalScopes()
                        ->where('loan_id', $dueRepayment->loan_id)
                        ->where('repayment_date', '<', $date)
                        ->where('status', 0)
                        ->whereNull('overdue_notification')
                        ->update(['overdue_notification' => now()]);
                } else {
                    $dueRepayment->overdue_notification = now();
                    $dueRepayment->save();
                }
            } catch (Exception $e) {}
        }

    }

}