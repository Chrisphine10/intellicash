<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawRequest;
use App\Services\PaymentMethodService;
use App\Notifications\WithdrawMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalRequestController extends Controller
{
    protected $paymentMethodService;

    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display pending withdrawal requests
     */
    public function index()
    {
        $withdrawRequests = WithdrawRequest::with(['member', 'account.savings_type', 'transaction'])
            ->where('status', 0) // Pending
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('backend.admin.withdrawal_requests.index', compact('withdrawRequests'));
    }

    /**
     * Show withdrawal request details
     */
    public function show($id)
    {
        $withdrawRequest = WithdrawRequest::with(['member', 'account.savings_type', 'transaction'])
            ->findOrFail($id);

        // Decode requirements to get payment method details
        $requirements = json_decode($withdrawRequest->requirements, true);
        $paymentMethod = null;
        
        if (isset($requirements['payment_method_id'])) {
            $paymentMethod = \App\Models\BankAccount::find($requirements['payment_method_id']);
        }

        return view('backend.admin.withdrawal_requests.show', compact('withdrawRequest', 'paymentMethod', 'requirements'));
    }

    /**
     * Approve withdrawal request
     */
    public function approve(Request $request, $id)
    {
        $withdrawRequest = WithdrawRequest::with(['member', 'account', 'transaction'])
            ->findOrFail($id);

        if ($withdrawRequest->status != 0) {
            return back()->with('error', 'This withdrawal request has already been processed');
        }

        DB::beginTransaction();

        try {
            // Decode requirements to get payment method details
            $requirements = json_decode($withdrawRequest->requirements, true);
            $paymentMethodId = $requirements['payment_method_id'] ?? null;
            $recipientDetails = $requirements['recipient_details'] ?? [];

            if (!$paymentMethodId) {
                throw new \Exception('Payment method information not found');
            }

            // Process withdrawal through payment method service
            $paymentResult = $this->paymentMethodService->processWithdrawal($withdrawRequest, $recipientDetails);

            if ($paymentResult['success']) {
                // Update withdrawal request status
                $withdrawRequest->status = 2; // Approved and completed
                $withdrawRequest->save();

                // Update transaction status
                $withdrawRequest->transaction->status = 2; // Completed
                $withdrawRequest->transaction->transaction_details = json_encode($paymentResult['data']);
                $withdrawRequest->transaction->save();

                // Send notification to member
                try {
                    $withdrawRequest->member->notify(new WithdrawMoney($withdrawRequest->transaction));
                } catch (\Exception $e) {
                    Log::error('Withdrawal approval notification failed: ' . $e->getMessage());
                }

                DB::commit();

                return redirect()->route('admin.withdrawal_requests.index')
                    ->with('success', 'Withdrawal request approved and processed successfully');
            } else {
                throw new \Exception($paymentResult['message']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Withdrawal approval error: ' . $e->getMessage());
            return back()->with('error', 'Failed to approve withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Reject withdrawal request
     */
    public function reject(Request $request, $id)
    {
        $withdrawRequest = WithdrawRequest::with(['transaction'])
            ->findOrFail($id);

        if ($withdrawRequest->status != 0) {
            return back()->with('error', 'This withdrawal request has already been processed');
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
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

            DB::commit();

            return redirect()->route('admin.withdrawal_requests.index')
                ->with('success', 'Withdrawal request rejected successfully');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Withdrawal rejection error: ' . $e->getMessage());
            return back()->with('error', 'Failed to reject withdrawal: ' . $e->getMessage());
        }
    }

    /**
     * Get withdrawal request statistics
     */
    public function statistics()
    {
        $stats = [
            'pending' => WithdrawRequest::where('status', 0)->count(),
            'approved' => WithdrawRequest::where('status', 2)->count(),
            'rejected' => WithdrawRequest::where('status', 3)->count(),
            'total_amount_pending' => WithdrawRequest::where('status', 0)->sum('amount'),
            'total_amount_approved' => WithdrawRequest::where('status', 2)->sum('amount'),
        ];

        return response()->json($stats);
    }
}
