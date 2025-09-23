<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintController extends Controller {

    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    public function repayment_receipt($payment_id) {
        $payment = LoanPayment::with(['loan.borrower', 'loan.loan_product', 'loan.currency'])
            ->findOrFail($payment_id);

        return view('backend.admin.print.repayment_receipt', compact('payment'));
    }

    public function loan_statement($loan_id) {
        $loan = Loan::with(['borrower', 'loan_product', 'currency', 'payments'])
            ->findOrFail($loan_id);

        $repayments = LoanRepayment::where('loan_id', $loan_id)
            ->orderBy('repayment_date')
            ->get();

        return view('backend.admin.print.loan_statement', compact('loan', 'repayments'));
    }

    public function borrower_statement($member_id) {
        $member = \App\Models\Member::with(['loans.loan_product', 'loans.currency'])
            ->findOrFail($member_id);

        $loans = Loan::where('borrower_id', $member_id)
            ->with(['loan_product', 'currency', 'payments'])
            ->get();

        return view('backend.admin.print.borrower_statement', compact('member', 'loans'));
    }

    public function loan_schedule($loan_id) {
        $loan = Loan::with(['borrower', 'loan_product', 'currency'])
            ->findOrFail($loan_id);

        $repayments = LoanRepayment::where('loan_id', $loan_id)
            ->orderBy('repayment_date')
            ->get();

        return view('backend.admin.print.loan_schedule', compact('loan', 'repayments'));
    }

    public function savings_statement($account_id) {
        $account = SavingsAccount::with(['member', 'savings_type.currency'])
            ->findOrFail($account_id);

        $transactions = Transaction::where('savings_account_id', $account_id)
            ->where('status', 2)
            ->orderBy('trans_date', 'desc')
            ->get();

        return view('backend.admin.print.savings_statement', compact('account', 'transactions'));
    }

    public function savings_transaction_receipt($transaction_id) {
        $transaction = Transaction::with(['member', 'account.savings_type.currency'])
            ->findOrFail($transaction_id);

        return view('backend.admin.print.savings_transaction_receipt', compact('transaction'));
    }

    public function other_income_receipt($transaction_id) {
        $transaction = Transaction::with(['member', 'account.savings_type.currency'])
            ->findOrFail($transaction_id);

        return view('backend.admin.print.other_income_receipt', compact('transaction'));
    }

}
