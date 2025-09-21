<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\MemberController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public API routes (no authentication required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

// API Authentication routes (require web authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/generate-tenant-credentials', [AuthController::class, 'generateTenantCredentials']);
        Route::post('/generate-member-credentials', [AuthController::class, 'generateMemberCredentials']);
        Route::get('/api-keys', [AuthController::class, 'listApiKeys']);
        Route::get('/api-keys/{id}', [AuthController::class, 'getApiKeyDetails']);
        Route::post('/api-keys/{id}/revoke', [AuthController::class, 'revokeApiKey']);
        Route::post('/api-keys/{id}/regenerate-secret', [AuthController::class, 'regenerateSecret']);
    });
});

// Protected API routes (require API authentication)
Route::middleware(['api.auth:tenant'])->group(function () {
    
    // Member Management
    Route::prefix('members')->group(function () {
        Route::get('/', [MemberController::class, 'index']);
        Route::post('/', [MemberController::class, 'store']);
        Route::get('/{id}', [MemberController::class, 'show']);
        Route::get('/{id}/savings-accounts', [MemberController::class, 'getSavingsAccounts']);
        Route::get('/{id}/loans', [MemberController::class, 'getLoans']);
        Route::get('/{id}/transactions', [MemberController::class, 'getTransactionHistory']);
        Route::get('/{id}/vsla-info', [MemberController::class, 'getVslaInfo']);
    });

    // Payment Processing
    Route::prefix('payments')->group(function () {
        Route::post('/process', [PaymentController::class, 'processPayment']);
        Route::post('/transfer', [PaymentController::class, 'transferFunds']);
        Route::get('/history/{memberId}', [PaymentController::class, 'getPaymentHistory']);
        Route::get('/balance/{accountId}', [PaymentController::class, 'getAccountBalance']);
    });

    // Transaction Management
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::get('/summary', [TransactionController::class, 'getSummary']);
        Route::get('/bank-transactions', [TransactionController::class, 'getBankTransactions']);
        Route::get('/vsla-transactions', [TransactionController::class, 'getVslaTransactions']);
    });

    // Bank Account Management
    Route::prefix('bank-accounts')->group(function () {
        Route::get('/', function (Request $request) {
            $accounts = \App\Models\BankAccount::where('tenant_id', $request->attributes->get('tenant_id'))
                                             ->with('currency')
                                             ->get();
            
            return response()->json([
                'success' => true,
                'data' => $accounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                        'account_number' => $account->account_number,
                        'bank_name' => $account->bank_name,
                        'current_balance' => $account->current_balance,
                        'available_balance' => $account->available_balance,
                        'currency' => $account->currency->name,
                        'is_active' => $account->is_active,
                    ];
                })
            ]);
        });
        
        Route::get('/{id}', function (Request $request, $id) {
            $account = \App\Models\BankAccount::where('tenant_id', $request->attributes->get('tenant_id'))
                                            ->with('currency')
                                            ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $account->id,
                    'account_name' => $account->account_name,
                    'account_number' => $account->account_number,
                    'bank_name' => $account->bank_name,
                    'opening_balance' => $account->opening_balance,
                    'current_balance' => $account->current_balance,
                    'available_balance' => $account->available_balance,
                    'blocked_balance' => $account->blocked_balance,
                    'currency' => $account->currency->name,
                    'is_active' => $account->is_active,
                    'allow_negative_balance' => $account->allow_negative_balance,
                    'minimum_balance' => $account->minimum_balance,
                    'maximum_balance' => $account->maximum_balance,
                    'opening_date' => $account->opening_date,
                    'last_balance_update' => $account->last_balance_update,
                ]
            ]);
        });
    });

    // VSLA Management
    Route::prefix('vsla')->group(function () {
        Route::get('/meetings', function (Request $request) {
            $meetings = \App\Models\VslaMeeting::where('tenant_id', $request->attributes->get('tenant_id'))
                                             ->with(['createdUser:id,name'])
                                             ->orderBy('meeting_date', 'desc')
                                             ->paginate($request->get('per_page', 20));
            
            return response()->json([
                'success' => true,
                'data' => $meetings->items(),
                'pagination' => [
                    'current_page' => $meetings->currentPage(),
                    'last_page' => $meetings->lastPage(),
                    'per_page' => $meetings->perPage(),
                    'total' => $meetings->total(),
                ]
            ]);
        });
        
        Route::get('/settings', function (Request $request) {
            $settings = \App\Models\VslaSetting::where('tenant_id', $request->attributes->get('tenant_id'))
                                             ->first();
            
            if (!$settings) {
                return response()->json([
                    'error' => 'VSLA settings not found',
                    'message' => 'VSLA module is not configured for this organization'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'share_amount' => $settings->share_amount,
                    'penalty_amount' => $settings->penalty_amount,
                    'welfare_amount' => $settings->welfare_amount,
                    'meeting_frequency' => $settings->meeting_frequency,
                    'meeting_time' => $settings->getFormattedMeetingTime(),
                    'meeting_days' => $settings->getMeetingDaysString(),
                    'auto_approve_loans' => $settings->auto_approve_loans,
                    'max_loan_amount' => $settings->max_loan_amount,
                    'max_loan_duration_days' => $settings->max_loan_duration_days,
                ]
            ]);
        });
    });

    // Reports and Analytics
    Route::prefix('reports')->group(function () {
        Route::get('/financial-summary', function (Request $request) {
            $tenantId = $request->attributes->get('tenant_id');
            
            $summary = [
                'total_members' => \App\Models\Member::where('tenant_id', $tenantId)->count(),
                'active_members' => \App\Models\Member::where('tenant_id', $tenantId)->where('status', 1)->count(),
                'total_savings' => \App\Models\Transaction::where('tenant_id', $tenantId)
                                                         ->where('dr_cr', 'cr')
                                                         ->sum('amount'),
                'total_loans' => \App\Models\Loan::where('tenant_id', $tenantId)->sum('applied_amount'),
                'outstanding_loans' => \App\Models\Loan::where('tenant_id', $tenantId)
                                                      ->where('status', 2)
                                                      ->get()
                                                      ->sum(function ($loan) {
                                                          return $loan->total_payable - $loan->total_paid;
                                                      }),
                'bank_accounts' => \App\Models\BankAccount::where('tenant_id', $tenantId)->count(),
                'total_bank_balance' => \App\Models\BankAccount::where('tenant_id', $tenantId)->sum('current_balance'),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        });
    });
});

// Member-specific API routes (require member API authentication)
Route::middleware(['api.auth:member'])->group(function () {
    Route::prefix('member')->group(function () {
        Route::get('/profile', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');
            $member = \App\Models\Member::where('tenant_id', $apiKey->tenant_id)
                                      ->where('id', $request->get('member_id'))
                                      ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'member_no' => $member->member_no,
                    'email' => $member->email,
                    'mobile' => $member->mobile,
                    'status' => $member->status,
                ]
            ]);
        });
        
        Route::get('/accounts', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');
            $member = \App\Models\Member::where('tenant_id', $apiKey->tenant_id)
                                      ->where('id', $request->get('member_id'))
                                      ->firstOrFail();
            
            $accounts = \App\Models\SavingsAccount::where('member_id', $member->id)
                                                 ->where('tenant_id', $apiKey->tenant_id)
                                                 ->with(['savings_type:id,name'])
                                                 ->get();
            
            $accountsWithBalance = $accounts->map(function ($account) use ($member) {
                $balance = get_account_balance($account->id, $member->id);
                
                return [
                    'id' => $account->id,
                    'account_number' => $account->account_number,
                    'savings_type' => $account->savings_type->name,
                    'balance' => $balance,
                    'status' => $account->status,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $accountsWithBalance
            ]);
        });
        
        Route::get('/transactions', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');
            $member = \App\Models\Member::where('tenant_id', $apiKey->tenant_id)
                                      ->where('id', $request->get('member_id'))
                                      ->firstOrFail();
            
            $query = \App\Models\Transaction::where('member_id', $member->id)
                                          ->where('tenant_id', $apiKey->tenant_id);
            
            if ($request->has('account_id')) {
                $query->where('savings_account_id', $request->account_id);
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
        });
    });
});
