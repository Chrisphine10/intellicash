<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Models\Member;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.auth:tenant');
    }

    /**
     * Get all transactions with filtering
     */
    public function index(Request $request)
    {
        $query = Transaction::where('tenant_id', $request->attributes->get('tenant_id'));

        // Apply filters
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        if ($request->has('account_id')) {
            $query->where('savings_account_id', $request->account_id);
        }

        if ($request->has('type')) {
            $query->where('type', ucfirst($request->type));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('trans_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('trans_date', '<=', $request->date_to);
        }

        if ($request->has('amount_min')) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->has('amount_max')) {
            $query->where('amount', '<=', $request->amount_max);
        }

        $transactions = $query->with([
                'member:id,first_name,last_name,member_no',
                'savingsAccount.savings_type:id,name',
                'createdBy:id,name'
            ])
            ->orderBy('trans_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    /**
     * Get transaction details
     */
    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('tenant_id', $request->attributes->get('tenant_id'))
                                ->with([
                                    'member:id,first_name,last_name,member_no,email,mobile',
                                    'savingsAccount.savings_type:id,name',
                                    'createdBy:id,name',
                                    'loan:id,loan_id,applied_amount'
                                ])
                                ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'transaction_date' => $transaction->trans_date,
                'member' => [
                    'id' => $transaction->member->id,
                    'name' => $transaction->member->name,
                    'member_no' => $transaction->member->member_no,
                    'email' => $transaction->member->email,
                    'mobile' => $transaction->member->mobile,
                ],
                'account' => [
                    'id' => $transaction->savings_account_id,
                    'account_number' => $transaction->savingsAccount->account_number,
                    'savings_type' => $transaction->savingsAccount->savings_type->name,
                ],
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'dr_cr' => $transaction->dr_cr,
                'method' => $transaction->method,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'note' => $transaction->note,
                'reference' => $transaction->reference,
                'created_by' => $transaction->createdBy->name,
                'created_at' => $transaction->created_at,
            ]
        ]);
    }

    /**
     * Get bank transactions
     */
    public function getBankTransactions(Request $request)
    {
        $query = BankTransaction::where('tenant_id', $request->attributes->get('tenant_id'));

        // Apply filters
        if ($request->has('bank_account_id')) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('trans_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('trans_date', '<=', $request->date_to);
        }

        $transactions = $query->with(['bankAccount:id,account_name,account_number'])
                            ->orderBy('trans_date', 'desc')
                            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    /**
     * Get transaction summary
     */
    public function getSummary(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        $query = Transaction::where('tenant_id', $tenantId);

        if ($request->has('date_from')) {
            $query->whereDate('trans_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('trans_date', '<=', $request->date_to);
        }

        $transactions = $query->get();

        $summary = [
            'total_transactions' => $transactions->count(),
            'total_deposits' => $transactions->where('dr_cr', 'cr')->sum('amount'),
            'total_withdrawals' => $transactions->where('dr_cr', 'dr')->sum('amount'),
            'net_amount' => $transactions->where('dr_cr', 'cr')->sum('amount') - 
                           $transactions->where('dr_cr', 'dr')->sum('amount'),
            'by_type' => $transactions->groupBy('type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'deposits' => $group->where('dr_cr', 'cr')->sum('amount'),
                    'withdrawals' => $group->where('dr_cr', 'dr')->sum('amount'),
                ];
            }),
            'by_status' => $transactions->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get VSLA transactions
     */
    public function getVslaTransactions(Request $request)
    {
        $query = \App\Models\VslaTransaction::where('tenant_id', $request->attributes->get('tenant_id'));

        // Apply filters
        if ($request->has('meeting_id')) {
            $query->where('meeting_id', $request->meeting_id);
        }

        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->with([
                'meeting:id,meeting_date,meeting_number',
                'member:id,first_name,last_name,member_no',
                'transaction:id,amount,dr_cr',
                'bankAccount:id,account_name'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }
}
