<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller {

    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    public function export_borrowers_report(Request $request) {
        $date1 = $request->date1;
        $date2 = $request->date2;
        $status = $request->status;
        $gender = $request->gender;

        $data = Member::with(['loans' => function($query) use ($date1, $date2, $status) {
            $query->when($status, function($q, $status) {
                return $q->where('status', $status);
            })
            ->whereRaw("date(created_at) >= '$date1' AND date(created_at) <= '$date2'");
        }])
        ->when($gender, function($query, $gender) {
            return $query->where('gender', $gender);
        })
        ->whereHas('loans')
        ->orderBy('id', 'desc')
        ->get();

        $filename = 'borrowers_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'Member No',
                'Name',
                'Gender',
                'Email',
                'Mobile',
                'Total Loans',
                'Active Loans',
                'Total Borrowed',
                'Total Paid',
                'Outstanding'
            ]);

            foreach ($data as $borrower) {
                fputcsv($file, [
                    $borrower->member_no,
                    $borrower->first_name . ' ' . $borrower->last_name,
                    $borrower->gender,
                    $borrower->email,
                    $borrower->mobile,
                    $borrower->loans->count(),
                    $borrower->loans->where('status', 1)->count(),
                    $borrower->loans->sum('applied_amount'),
                    $borrower->loans->sum('total_paid'),
                    $borrower->loans->sum('applied_amount') - $borrower->loans->sum('total_paid')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_loan_arrears_aging_report(Request $request) {
        $data = LoanRepayment::with(['loan.borrower', 'loan.loan_product'])
            ->where('status', 0)
            ->whereRaw('repayment_date < CURDATE()')
            ->selectRaw('*, DATEDIFF(CURDATE(), repayment_date) as days_overdue')
            ->orderBy('days_overdue', 'desc')
            ->get();

        $filename = 'loan_arrears_aging_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Loan ID',
                'Borrower',
                'Loan Product',
                'Repayment Date',
                'Days Overdue',
                'Principal Due',
                'Interest Due',
                'Penalty Due',
                'Total Due'
            ]);

            foreach ($data as $repayment) {
                fputcsv($file, [
                    $repayment->loan->loan_id,
                    $repayment->loan->borrower->first_name . ' ' . $repayment->loan->borrower->last_name,
                    $repayment->loan->loan_product->name,
                    $repayment->repayment_date,
                    $repayment->days_overdue,
                    $repayment->principal_amount,
                    $repayment->interest,
                    $repayment->penalty,
                    $repayment->amount_to_pay
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_collections_report(Request $request) {
        $date1 = $request->date1;
        $date2 = $request->date2;
        $collector_id = $request->collector_id;

        $data = LoanPayment::with(['loan.borrower', 'loan.created_by', 'member'])
            ->when($collector_id, function($query, $collector_id) {
                return $query->whereHas('loan', function($q) use ($collector_id) {
                    return $q->where('created_user_id', $collector_id);
                });
            })
            ->whereRaw("date(paid_at) >= '$date1' AND date(paid_at) <= '$date2'")
            ->orderBy('paid_at', 'desc')
            ->get();

        $filename = 'collections_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Payment Date',
                'Loan ID',
                'Borrower',
                'Principal Paid',
                'Interest Paid',
                'Penalty Paid',
                'Total Paid',
                'Collector'
            ]);

            foreach ($data as $payment) {
                fputcsv($file, [
                    $payment->paid_at,
                    $payment->loan->loan_id,
                    $payment->loan->borrower->first_name . ' ' . $payment->loan->borrower->last_name,
                    $payment->repayment_amount,
                    $payment->interest,
                    $payment->late_penalties,
                    $payment->total_amount,
                    $payment->loan->created_by->name ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_disbursement_report(Request $request) {
        $date1 = $request->date1;
        $date2 = $request->date2;
        $loan_product_id = $request->loan_product_id;

        $data = Loan::with(['borrower', 'loan_product', 'currency'])
            ->when($loan_product_id, function($query, $loan_product_id) {
                return $query->where('loan_product_id', $loan_product_id);
            })
            ->whereNotNull('release_date')
            ->where('status', 1)
            ->whereRaw("date(release_date) >= '$date1' AND date(release_date) <= '$date2'")
            ->orderBy('release_date', 'desc')
            ->get();

        $filename = 'disbursement_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Release Date',
                'Loan ID',
                'Borrower',
                'Loan Product',
                'Currency',
                'Disbursed Amount',
                'Interest Rate',
                'Term',
                'Status'
            ]);

            foreach ($data as $loan) {
                fputcsv($file, [
                    $loan->release_date,
                    $loan->loan_id,
                    $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                    $loan->loan_product->name,
                    $loan->currency->name,
                    $loan->applied_amount,
                    $loan->loan_product->interest_rate,
                    $loan->loan_product->term . ' ' . $loan->loan_product->term_period,
                    $loan->status == 1 ? 'Active' : ($loan->status == 2 ? 'Fully Paid' : ($loan->status == 3 ? 'Default' : 'Pending'))
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_fees_report(Request $request) {
        $date1 = $request->date1;
        $date2 = $request->date2;

        $data = LoanPayment::with(['loan.borrower'])
            ->selectRaw('*, (interest + late_penalties) as total_fees')
            ->whereRaw("date(paid_at) >= '$date1' AND date(paid_at) <= '$date2'")
            ->orderBy('paid_at', 'desc')
            ->get();

        $filename = 'fees_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Payment Date',
                'Loan ID',
                'Borrower',
                'Interest Collected',
                'Penalty Collected',
                'Total Fees'
            ]);

            foreach ($data as $payment) {
                fputcsv($file, [
                    $payment->paid_at,
                    $payment->loan->loan_id,
                    $payment->loan->borrower->first_name . ' ' . $payment->loan->borrower->last_name,
                    $payment->interest,
                    $payment->late_penalties,
                    $payment->total_fees
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_outstanding_report(Request $request) {
        $data = Loan::with(['borrower', 'loan_product', 'currency'])
            ->where('status', 1)
            ->selectRaw('*, (applied_amount - total_paid) as outstanding_amount')
            ->orderBy('outstanding_amount', 'desc')
            ->get();

        $filename = 'outstanding_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Loan ID',
                'Borrower',
                'Loan Product',
                'Currency',
                'Applied Amount',
                'Total Paid',
                'Outstanding Amount',
                'Release Date'
            ]);

            foreach ($data as $loan) {
                fputcsv($file, [
                    $loan->loan_id,
                    $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                    $loan->loan_product->name,
                    $loan->currency->name,
                    $loan->applied_amount,
                    $loan->total_paid,
                    $loan->outstanding_amount,
                    $loan->release_date
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_portfolio_at_risk_report(Request $request) {
        $data = LoanRepayment::with(['loan.borrower', 'loan.loan_product'])
            ->where('status', 0)
            ->whereRaw('repayment_date < CURDATE()')
            ->selectRaw('*, DATEDIFF(CURDATE(), repayment_date) as days_overdue')
            ->orderBy('days_overdue', 'desc')
            ->get();

        $filename = 'portfolio_at_risk_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Loan ID',
                'Borrower',
                'Loan Product',
                'Repayment Date',
                'Days Overdue',
                'Principal Due',
                'Interest Due',
                'Penalty Due',
                'Total Due',
                'Risk Level'
            ]);

            foreach ($data as $repayment) {
                $risk_level = $repayment->days_overdue > 90 ? 'High Risk' : 
                             ($repayment->days_overdue > 30 ? 'Medium Risk' : 'Low Risk');
                
                fputcsv($file, [
                    $repayment->loan->loan_id,
                    $repayment->loan->borrower->first_name . ' ' . $repayment->loan->borrower->last_name,
                    $repayment->loan->loan_product->name,
                    $repayment->repayment_date,
                    $repayment->days_overdue,
                    $repayment->principal_amount,
                    $repayment->interest,
                    $repayment->penalty,
                    $repayment->amount_to_pay,
                    $risk_level
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_loan_officer_report(Request $request) {
        $date1 = $request->date1;
        $date2 = $request->date2;
        $officer_id = $request->officer_id;

        $data = Loan::with(['borrower', 'loan_product', 'created_by'])
            ->when($officer_id, function($query, $officer_id) {
                return $query->where('created_user_id', $officer_id);
            })
            ->whereRaw("date(created_at) >= '$date1' AND date(created_at) <= '$date2'")
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'loan_officer_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Created Date',
                'Loan ID',
                'Borrower',
                'Loan Product',
                'Applied Amount',
                'Status',
                'Loan Officer'
            ]);

            foreach ($data as $loan) {
                fputcsv($file, [
                    $loan->created_at,
                    $loan->loan_id,
                    $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                    $loan->loan_product->name,
                    $loan->applied_amount,
                    $loan->status == 1 ? 'Active' : ($loan->status == 2 ? 'Fully Paid' : ($loan->status == 3 ? 'Default' : 'Pending')),
                    $loan->created_by->name ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function export_loan_products_report(Request $request) {
        $data = LoanProduct::withCount(['loans'])
            ->get()
            ->map(function($product) {
                $loans = $product->loans;
                $product->total_loans = $loans->count();
                $product->total_disbursed = $loans->sum('applied_amount');
                $product->total_collected = $loans->sum('total_paid');
                $product->avg_loan_size = $loans->avg('applied_amount');
                return $product;
            });

        $filename = 'loan_products_report_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'Product Name',
                'Interest Rate',
                'Term',
                'Min Amount',
                'Max Amount',
                'Total Loans',
                'Total Disbursed',
                'Total Collected',
                'Avg Loan Size',
                'Status'
            ]);

            foreach ($data as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->interest_rate,
                    $product->term . ' ' . $product->term_period,
                    $product->minimum_amount,
                    $product->maximum_amount,
                    $product->loans_count,
                    $product->total_disbursed,
                    $product->total_collected,
                    $product->avg_loan_size,
                    $product->status == 1 ? 'Active' : 'Inactive'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

}
