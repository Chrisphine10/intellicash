<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawRequest;
use App\Models\BankAccount;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalRequestController extends Controller
{
    protected $paymentMethodService;

    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
        
        // Apply comprehensive authorization middleware
        $this->middleware('auth');
        $this->middleware('admin.access');
        $this->middleware('transaction.auth:withdrawals.view')->only(['index', 'show']);
        $this->middleware('transaction.auth:withdrawals.approve')->only(['approve']);
        $this->middleware('transaction.auth:withdrawals.reject')->only(['reject']);
        $this->middleware('transaction.auth:withdrawals.stats')->only(['statistics']);
    }

    /**
     * Display a listing of withdrawal requests
     */
    public function index()
    {
        $withdrawRequests = WithdrawRequest::with(['member', 'account', 'method'])
            ->where('tenant_id', request()->tenant->id)
            ->where('status', 0) // Only pending requests
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('backend.admin.withdrawal_requests.index', compact('withdrawRequests'));
    }

    /**
     * Display the specified withdrawal request
     */
    public function show($id)
    {
        $withdrawRequest = WithdrawRequest::with(['member', 'account', 'method', 'transaction'])
            ->where('id', $id)
            ->where('tenant_id', request()->tenant->id)
            ->firstOrFail();

        return view('backend.admin.withdrawal_requests.show', compact('withdrawRequest'));
    }

    /**
     * Approve withdrawal request
     */
    public function approve(Request $request, $id)
    {
        // Validate admin permissions
        if (!has_permission('withdrawals.approve')) {
            \Log::warning('Unauthorized withdrawal approval attempt', [
                'user_id' => auth()->id(),
                'withdrawal_id' => $id,
                'ip_address' => $request->ip(),
                'tenant_id' => request()->tenant->id
            ]);
            return back()->with('error', 'Insufficient permissions to approve withdrawals');
        }

        // Use database transaction with pessimistic locking
        try {
            return DB::transaction(function() use ($request, $id) {
                // Lock the withdrawal request to prevent concurrent processing
                $withdrawRequest = WithdrawRequest::with(['member', 'account', 'transaction'])
                    ->where('id', $id)
                    ->where('tenant_id', request()->tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($withdrawRequest->status != 0) {
                    throw new \Exception('This withdrawal request has already been processed');
                }

                // Log approval attempt
                \Log::info('Withdrawal request approval initiated', [
                    'withdraw_request_id' => $id,
                    'admin_user_id' => auth()->id(),
                    'member_id' => $withdrawRequest->member_id,
                    'amount' => $withdrawRequest->amount,
                    'ip_address' => $request->ip(),
                    'tenant_id' => request()->tenant->id
                ]);

                // Decode requirements to get payment method details
                $requirements = json_decode($withdrawRequest->requirements, true);
                $paymentMethodId = $requirements['payment_method_id'] ?? null;
                $recipientDetails = $requirements['recipient_details'] ?? [];

                if ($paymentMethodId) {
                    // Process via payment method service
                    $paymentResult = $this->paymentMethodService->processWithdrawal(
                        $paymentMethodId,
                        $withdrawRequest->amount,
                        $recipientDetails
                    );

                    if ($paymentResult['success']) {
                        // Update withdrawal request status
                        $withdrawRequest->status = 2; // Approved
                        $withdrawRequest->save();

                        // Update transaction status
                        $withdrawRequest->transaction->status = 2; // Completed
                        $withdrawRequest->transaction->transaction_details = json_encode($paymentResult['data']);
                        $withdrawRequest->transaction->save();

                        // Send notification to member
                        try {
                            $withdrawRequest->member->notify(new \App\Notifications\WithdrawMoney($withdrawRequest->transaction));
                        } catch (\Exception $e) {
                            \Log::error('Withdrawal approval notification failed: ' . $e->getMessage());
                        }

                        // Log successful approval
                        \Log::info('Withdrawal request approved successfully', [
                            'withdraw_request_id' => $id,
                            'admin_user_id' => auth()->id(),
                            'member_id' => $withdrawRequest->member_id,
                            'amount' => $withdrawRequest->amount,
                            'payment_result' => $paymentResult
                        ]);

                        return redirect()->route('admin.withdrawal_requests.index')
                            ->with('success', 'Withdrawal request approved and processed successfully');
                    } else {
                        throw new \Exception($paymentResult['message']);
                    }
                } else {
                    // Manual approval for traditional withdrawal methods
                    $withdrawRequest->status = 2; // Approved
                    $withdrawRequest->save();

                    $withdrawRequest->transaction->status = 2; // Completed
                    $withdrawRequest->transaction->save();

                    // Send notification to member
                    try {
                        $withdrawRequest->member->notify(new \App\Notifications\WithdrawMoney($withdrawRequest->transaction));
                    } catch (\Exception $e) {
                        \Log::error('Withdrawal approval notification failed: ' . $e->getMessage());
                    }

                    return redirect()->route('admin.withdrawal_requests.index')
                        ->with('success', 'Withdrawal request approved successfully');
                }
            }, 5); // 5 second timeout for transaction
        } catch (\Exception $e) {
            \Log::error('Withdrawal approval error', [
                'withdraw_request_id' => $id,
                'admin_user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'tenant_id' => request()->tenant->id
            ]);
            return back()->with('error', 'Failed to approve withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Reject withdrawal request
     */
    public function reject(Request $request, $id)
    {
        // Validate admin permissions
        if (!has_permission('withdrawals.reject')) {
            \Log::warning('Unauthorized withdrawal rejection attempt', [
                'user_id' => auth()->id(),
                'withdrawal_id' => $id,
                'ip_address' => $request->ip(),
                'tenant_id' => request()->tenant->id
            ]);
            return back()->with('error', 'Insufficient permissions to reject withdrawals');
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500|regex:/^[a-zA-Z0-9\s.,!?-]+$/',
            'admin_notes' => 'nullable|string|max:1000',
            'approval_level' => 'required|in:standard,manager,director',
            'risk_assessment' => 'required|in:low,medium,high'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Use database transaction with pessimistic locking
        try {
            return DB::transaction(function() use ($request, $id) {
                // Lock the withdrawal request to prevent concurrent processing
                $withdrawRequest = WithdrawRequest::with(['transaction'])
                    ->where('id', $id)
                    ->where('tenant_id', request()->tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($withdrawRequest->status != 0) {
                    throw new \Exception('This withdrawal request has already been processed');
                }

                // Log rejection attempt
                \Log::info('Withdrawal request rejection initiated', [
                    'withdraw_request_id' => $id,
                    'admin_user_id' => auth()->id(),
                    'member_id' => $withdrawRequest->member_id,
                    'amount' => $withdrawRequest->amount,
                    'rejection_reason' => $request->rejection_reason,
                    'ip_address' => $request->ip(),
                    'tenant_id' => request()->tenant->id
                ]);

                // Update withdrawal request status
                $withdrawRequest->status = 3; // Rejected
                $withdrawRequest->requirements = json_encode(array_merge(
                    json_decode($withdrawRequest->requirements, true) ?? [],
                    ['rejection_reason' => $request->rejection_reason]
                ));
                $withdrawRequest->save();

                // Update transaction status
                $withdrawRequest->transaction->status = 3; // Rejected
                $withdrawRequest->transaction->description .= ' - Rejected: ' . $request->rejection_reason;
                $withdrawRequest->transaction->save();

                // Log successful rejection
                \Log::info('Withdrawal request rejected successfully', [
                    'withdraw_request_id' => $id,
                    'admin_user_id' => auth()->id(),
                    'member_id' => $withdrawRequest->member_id,
                    'amount' => $withdrawRequest->amount,
                    'rejection_reason' => $request->rejection_reason
                ]);

                return redirect()->route('admin.withdrawal_requests.index')
                    ->with('success', 'Withdrawal request rejected successfully');
            }, 5); // 5 second timeout for transaction
        } catch (\Exception $e) {
            \Log::error('Withdrawal rejection error', [
                'withdraw_request_id' => $id,
                'admin_user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'tenant_id' => request()->tenant->id
            ]);
            return back()->with('error', 'Failed to reject withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Get withdrawal request statistics
     */
    public function statistics()
    {
        $tenantId = request()->tenant->id;

        $stats = [
            'pending' => WithdrawRequest::where('tenant_id', $tenantId)->where('status', 0)->count(),
            'approved' => WithdrawRequest::where('tenant_id', $tenantId)->where('status', 2)->count(),
            'rejected' => WithdrawRequest::where('tenant_id', $tenantId)->where('status', 3)->count(),
            'total_amount_pending' => WithdrawRequest::where('tenant_id', $tenantId)->where('status', 0)->sum('amount'),
            'total_amount_approved' => WithdrawRequest::where('tenant_id', $tenantId)->where('status', 2)->sum('amount'),
        ];

        return response()->json($stats);
    }
}