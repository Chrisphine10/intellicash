<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.auth:tenant');
    }

    /**
     * Process a payment transaction
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'account_id' => 'required|exists:savings_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:deposit,withdrawal,transfer',
            'method' => 'required|in:cash,bank_transfer,mobile_money,cheque',
            'description' => 'nullable|string|max:1000',
            'reference' => 'nullable|string|max:100',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'transaction_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $member = Member::find($request->member_id);
        $account = SavingsAccount::find($request->account_id);

        // Verify member and account belong to tenant
        if ($member->tenant_id !== $request->attributes->get('tenant_id') ||
            $account->tenant_id !== $request->attributes->get('tenant_id')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Member or account does not belong to your organization'
            ], 403);
        }

        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'tenant_id' => $request->attributes->get('tenant_id'),
                'trans_date' => $request->transaction_date ?? now(),
                'member_id' => $member->id,
                'savings_account_id' => $account->id,
                'amount' => $request->amount,
                'dr_cr' => $request->type === 'deposit' ? 'cr' : 'dr',
                'type' => ucfirst($request->type),
                'method' => $request->method,
                'status' => 2, // Approved
                'note' => $request->description,
                'description' => $request->description,
                'reference' => $request->reference,
                'created_user_id' => 1, // System user
                'branch_id' => $member->branch_id,
            ]);

            // If bank account is specified, create bank transaction
            if ($request->bank_account_id) {
                $bankAccount = BankAccount::find($request->bank_account_id);
                
                if ($bankAccount->tenant_id === $request->attributes->get('tenant_id')) {
                    BankTransaction::create([
                        'tenant_id' => $request->attributes->get('tenant_id'),
                        'trans_date' => $request->transaction_date ?? now(),
                        'bank_account_id' => $bankAccount->id,
                        'amount' => $request->amount,
                        'dr_cr' => $request->type === 'deposit' ? 'cr' : 'dr',
                        'type' => $request->type === 'deposit' ? 'deposit' : 'withdraw',
                        'status' => BankTransaction::STATUS_APPROVED,
                        'description' => $request->description,
                        'created_user_id' => 1,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'member_id' => $member->id,
                    'account_id' => $account->id,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'status' => 'completed',
                    'transaction_date' => $transaction->trans_date,
                    'reference' => $transaction->reference,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'error' => 'Payment processing failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for a member
     */
    public function getPaymentHistory(Request $request, $memberId)
    {
        $member = Member::findOrFail($memberId);

        if ($member->tenant_id !== $request->attributes->get('tenant_id')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Member does not belong to your organization'
            ], 403);
        }

        $query = Transaction::where('member_id', $memberId)
                          ->where('tenant_id', $request->attributes->get('tenant_id'));

        if ($request->has('account_id')) {
            $query->where('savings_account_id', $request->account_id);
        }

        if ($request->has('type')) {
            $query->where('type', ucfirst($request->type));
        }

        if ($request->has('date_from')) {
            $query->whereDate('trans_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('trans_date', '<=', $request->date_to);
        }

        $transactions = $query->with(['savingsAccount.savings_type'])
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
     * Get account balance
     */
    public function getAccountBalance(Request $request, $accountId)
    {
        $account = SavingsAccount::findOrFail($accountId);

        if ($account->tenant_id !== $request->attributes->get('tenant_id')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Account does not belong to your organization'
            ], 403);
        }

        $balance = get_account_balance($account->id, $account->member_id);

        return response()->json([
            'success' => true,
            'data' => [
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'member_id' => $account->member_id,
                'member_name' => $account->member->name,
                'savings_type' => $account->savings_type->name,
                'balance' => $balance,
                'currency' => 'KES', // Default currency
                'last_updated' => now(),
            ]
        ]);
    }

    /**
     * Transfer funds between accounts
     */
    public function transferFunds(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|exists:savings_accounts,id',
            'to_account_id' => 'required|exists:savings_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'reference' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $fromAccount = SavingsAccount::find($request->from_account_id);
        $toAccount = SavingsAccount::find($request->to_account_id);

        // Verify accounts belong to tenant
        if ($fromAccount->tenant_id !== $request->attributes->get('tenant_id') ||
            $toAccount->tenant_id !== $request->attributes->get('tenant_id')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Accounts do not belong to your organization'
            ], 403);
        }

        // Check sufficient balance
        $fromBalance = get_account_balance($fromAccount->id, $fromAccount->member_id);
        if ($fromBalance < $request->amount) {
            return response()->json([
                'error' => 'Insufficient funds',
                'message' => 'Insufficient balance in source account'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Create withdrawal transaction
            $withdrawalTransaction = Transaction::create([
                'tenant_id' => $request->attributes->get('tenant_id'),
                'trans_date' => now(),
                'member_id' => $fromAccount->member_id,
                'savings_account_id' => $fromAccount->id,
                'amount' => $request->amount,
                'dr_cr' => 'dr',
                'type' => 'Transfer',
                'method' => 'Internal',
                'status' => 2,
                'note' => $request->description,
                'description' => 'Transfer to ' . $toAccount->account_number,
                'reference' => $request->reference,
                'created_user_id' => 1,
                'branch_id' => $fromAccount->member->branch_id,
            ]);

            // Create deposit transaction
            $depositTransaction = Transaction::create([
                'tenant_id' => $request->attributes->get('tenant_id'),
                'trans_date' => now(),
                'member_id' => $toAccount->member_id,
                'savings_account_id' => $toAccount->id,
                'amount' => $request->amount,
                'dr_cr' => 'cr',
                'type' => 'Transfer',
                'method' => 'Internal',
                'status' => 2,
                'note' => $request->description,
                'description' => 'Transfer from ' . $fromAccount->account_number,
                'reference' => $request->reference,
                'created_user_id' => 1,
                'branch_id' => $toAccount->member->branch_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'transfer_id' => $withdrawalTransaction->id,
                    'from_account' => $fromAccount->account_number,
                    'to_account' => $toAccount->account_number,
                    'amount' => $request->amount,
                    'status' => 'completed',
                    'transaction_date' => $withdrawalTransaction->trans_date,
                    'reference' => $request->reference,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'error' => 'Transfer failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
