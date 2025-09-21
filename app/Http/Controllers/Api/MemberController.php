<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('api.auth:tenant');
    }

    /**
     * Get all members with filtering
     */
    public function index(Request $request)
    {
        $query = Member::where('tenant_id', $request->attributes->get('tenant_id'));

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('member_no', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $members = $query->with(['branch:id,name'])
                        ->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $members->items(),
            'pagination' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ]
        ]);
    }

    /**
     * Get member details
     */
    public function show(Request $request, $id)
    {
        $member = Member::where('tenant_id', $request->attributes->get('tenant_id'))
                       ->with(['branch:id,name', 'user:id,name,email'])
                       ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'name' => $member->name,
                'member_no' => $member->member_no,
                'email' => $member->email,
                'mobile' => $member->mobile,
                'country_code' => $member->country_code,
                'gender' => $member->gender,
                'business_name' => $member->business_name,
                'address' => $member->address,
                'city' => $member->city,
                'state' => $member->state,
                'zip' => $member->zip,
                'status' => $member->status,
                'branch' => $member->branch,
                'user' => $member->user,
                'created_at' => $member->created_at,
                'updated_at' => $member->updated_at,
            ]
        ]);
    }

    /**
     * Get member's savings accounts
     */
    public function getSavingsAccounts(Request $request, $id)
    {
        $member = Member::where('tenant_id', $request->attributes->get('tenant_id'))
                       ->findOrFail($id);

        $accounts = SavingsAccount::where('member_id', $member->id)
                                 ->where('tenant_id', $request->attributes->get('tenant_id'))
                                 ->with(['savings_type:id,name'])
                                 ->get();

        $accountsWithBalance = $accounts->map(function ($account) use ($member) {
            $balance = get_account_balance($account->id, $member->id);
            
            return [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'savings_type' => $account->savings_type->name,
                'status' => $account->status,
                'opening_balance' => $account->opening_balance,
                'current_balance' => $balance,
                'created_at' => $account->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $accountsWithBalance
        ]);
    }

    /**
     * Get member's loans
     */
    public function getLoans(Request $request, $id)
    {
        $member = Member::where('tenant_id', $request->attributes->get('tenant_id'))
                       ->findOrFail($id);

        $loans = Loan::where('borrower_id', $member->id)
                    ->where('tenant_id', $request->attributes->get('tenant_id'))
                    ->with(['loan_product:id,name,interest_rate', 'currency:id,name'])
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'data' => $loans->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'loan_id' => $loan->loan_id,
                    'loan_product' => $loan->loan_product->name,
                    'applied_amount' => $loan->applied_amount,
                    'total_payable' => $loan->total_payable,
                    'total_paid' => $loan->total_paid,
                    'outstanding_amount' => $loan->total_payable - $loan->total_paid,
                    'interest_rate' => $loan->loan_product->interest_rate,
                    'currency' => $loan->currency->name,
                    'status' => $loan->status,
                    'first_payment_date' => $loan->first_payment_date,
                    'release_date' => $loan->release_date,
                    'created_at' => $loan->created_at,
                ];
            })
        ]);
    }

    /**
     * Get member's transaction history
     */
    public function getTransactionHistory(Request $request, $id)
    {
        $member = Member::where('tenant_id', $request->attributes->get('tenant_id'))
                       ->findOrFail($id);

        $query = \App\Models\Transaction::where('member_id', $member->id)
                                      ->where('tenant_id', $request->attributes->get('tenant_id'));

        // Apply filters
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

        $transactions = $query->with(['savingsAccount.savings_type:id,name'])
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
     * Get member's VSLA information
     */
    public function getVslaInfo(Request $request, $id)
    {
        $member = Member::where('tenant_id', $request->attributes->get('tenant_id'))
                       ->findOrFail($id);

        $vslaRoles = $member->getVslaRoles();
        $vslaTransactions = \App\Models\VslaTransaction::where('member_id', $member->id)
                                                     ->where('tenant_id', $request->attributes->get('tenant_id'))
                                                     ->with(['meeting:id,meeting_date,meeting_number'])
                                                     ->orderBy('created_at', 'desc')
                                                     ->limit(10)
                                                     ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'member_id' => $member->id,
                'member_name' => $member->name,
                'vsla_roles' => $vslaRoles,
                'is_chairperson' => $member->isVslaChairperson(),
                'is_treasurer' => $member->isVslaTreasurer(),
                'is_secretary' => $member->isVslaSecretary(),
                'recent_vsla_transactions' => $vslaTransactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'transaction_type' => $transaction->transaction_type,
                        'amount' => $transaction->amount,
                        'description' => $transaction->description,
                        'status' => $transaction->status,
                        'meeting' => [
                            'id' => $transaction->meeting->id,
                            'meeting_number' => $transaction->meeting->meeting_number,
                            'meeting_date' => $transaction->meeting->meeting_date,
                        ],
                        'created_at' => $transaction->created_at,
                    ];
                })
            ]
        ]);
    }

    /**
     * Create a new member
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'nullable|email|max:100',
            'mobile' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'gender' => 'nullable|string|in:male,female,other',
            'business_name' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:191',
            'state' => 'nullable|string|max:191',
            'zip' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $member = Member::create([
            'tenant_id' => $request->attributes->get('tenant_id'),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'country_code' => $request->country_code,
            'gender' => $request->gender,
            'business_name' => $request->business_name,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
            'branch_id' => $request->branch_id,
            'status' => $request->status ?? 1,
            'member_no' => $this->generateMemberNumber(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'name' => $member->name,
                'email' => $member->email,
                'mobile' => $member->mobile,
                'status' => $member->status,
            ]
        ], 201);
    }

    /**
     * Generate unique member number
     */
    private function generateMemberNumber()
    {
        $prefix = 'MEM';
        $lastMember = Member::where('tenant_id', request()->attributes->get('tenant_id'))
                           ->orderBy('id', 'desc')
                           ->first();
        
        $nextNumber = $lastMember ? (int)substr($lastMember->member_no, 3) + 1 : 1;
        
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
