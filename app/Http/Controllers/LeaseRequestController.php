<?php

namespace App\Http\Controllers;

use App\Models\LeaseRequest;
use App\Models\Asset;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\AssetLease;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeaseRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of lease requests for admin
     */
    public function index(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        $query = LeaseRequest::with(['asset.category', 'member', 'paymentAccount', 'processedBy'])
                          ->where('tenant_id', $tenant->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by member
        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        // Filter by asset
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhereHas('member', function($memberQuery) use ($search) {
                      $memberQuery->where('first_name', 'like', "%{$search}%")
                                 ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('asset', function($assetQuery) use ($search) {
                      $assetQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('asset_code', 'like', "%{$search}%");
                  });
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);
        
        $members = Member::where('tenant_id', $tenant->id)->active()->get();
        $assets = Asset::where('tenant_id', $tenant->id)->leasable()->get();

        return view('backend.asset_management.lease_requests.index', compact('requests', 'members', 'assets'));
    }

    /**
     * Display lease requests for a specific member
     */
    public function memberRequests(Request $request)
    {
        $member = auth()->user()->member;
        
        if (!$member) {
            return redirect()->route('dashboard.index')->with('error', _lang('Member not found'));
        }

        $query = LeaseRequest::with(['asset.category', 'paymentAccount', 'processedBy'])
                          ->where('member_id', $member->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('backend.customer.lease_requests.index', compact('requests'));
    }

    /**
     * Show the form for creating a new lease request
     */
    public function create()
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        $member = auth()->user()->member;
        
        if (!$member) {
            return redirect()->route('dashboard.index')->with('error', _lang('Member not found'));
        }

        $assets = Asset::where('tenant_id', $tenant->id)->availableForLease()->with('category')->get();
        $accounts = SavingsAccount::where('member_id', $member->id)->with('savings_type')->get();
        
        return view('backend.customer.lease_requests.create', compact('assets', 'accounts', 'member'));
    }

    /**
     * Store a newly created lease request
     */
    public function store(Request $request)
    {
        $member = auth()->user()->member;
        
        if (!$member) {
            return back()->with('error', _lang('Member not found'));
        }

        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'start_date' => 'required|date|after_or_equal:today',
            'requested_days' => 'required|integer|min:1|max:365',
            'deposit_amount' => 'nullable|numeric|min:0',
            'payment_account_id' => 'required|exists:savings_accounts,id',
            'reason' => 'required|string|max:1000',
            'terms_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $asset = Asset::findOrFail($request->asset_id);
        
        if (!$asset->isAvailableForLease()) {
            return back()->with('error', _lang('This asset is not available for lease'));
        }

        // Verify the payment account belongs to the member
        $paymentAccount = SavingsAccount::where('id', $request->payment_account_id)
                                      ->where('member_id', $member->id)
                                      ->first();

        if (!$paymentAccount) {
            return back()->with('error', _lang('Invalid payment account'));
        }

        // Calculate end date
        $startDate = \Carbon\Carbon::parse($request->start_date);
        $endDate = $startDate->copy()->addDays($request->requested_days);

        // Calculate total amount
        $totalAmount = $request->requested_days * $asset->lease_rate;

        DB::beginTransaction();
        
        try {
            $leaseRequest = LeaseRequest::create([
                'tenant_id' => app('tenant')->id,
                'member_id' => $member->id,
                'asset_id' => $request->asset_id,
                'request_number' => LeaseRequest::generateRequestNumber(app('tenant')->id),
                'start_date' => $request->start_date,
                'end_date' => $endDate,
                'requested_days' => $request->requested_days,
                'daily_rate' => $asset->lease_rate,
                'total_amount' => $totalAmount,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'payment_account_id' => $request->payment_account_id,
                'reason' => $request->reason,
                'terms_accepted' => true,
                'created_user_id' => auth()->id(),
            ]);

            DB::commit();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang('Lease request submitted successfully'), 'redirect' => route('lease-requests.member.index')]);
            } else {
                return redirect()->route('lease-requests.member.index')->with('success', _lang('Lease request submitted successfully'));
            }
            
        } catch (\Exception $e) {
            DB::rollback();
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while submitting the request: ') . $e->getMessage()]);
            } else {
                return back()->with('error', _lang('An error occurred while submitting the request: ') . $e->getMessage());
            }
        }
    }

    /**
     * Display the specified lease request
     */
    public function show(LeaseRequest $leaseRequest)
    {
        $leaseRequest->load(['asset.category', 'member', 'paymentAccount.savings_type', 'processedBy']);
        
        return view('backend.asset_management.lease_requests.show', compact('leaseRequest'));
    }

    /**
     * Show member's lease request details
     */
    public function memberShow(LeaseRequest $leaseRequest)
    {
        $member = auth()->user()->member;
        
        if ($leaseRequest->member_id !== $member->id) {
            return redirect()->route('lease-requests.member.index')->with('error', _lang('Unauthorized access'));
        }

        $leaseRequest->load(['asset.category', 'paymentAccount.savings_type', 'processedBy']);
        
        return view('backend.customer.lease_requests.show', compact('leaseRequest'));
    }

    /**
     * Approve a lease request
     */
    public function approve(Request $request, LeaseRequest $leaseRequest)
    {
        if (!$leaseRequest->canBeApproved()) {
            return back()->with('error', _lang('This lease request cannot be approved'));
        }

        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        DB::beginTransaction();
        
        try {
            // Check if member has sufficient balance
            $requiredAmount = $leaseRequest->total_amount + $leaseRequest->deposit_amount;
            $accountBalance = get_account_balance($leaseRequest->payment_account_id, $leaseRequest->member_id);
            
            if ($accountBalance < $requiredAmount) {
                return back()->with('error', _lang('Insufficient balance in the selected payment account'));
            }

            // Approve the request
            $leaseRequest->approve(auth()->id(), $request->admin_notes);

            // Create the actual lease
            $lease = $leaseRequest->convertToLease();

            // Process payment
            $this->processPayment($leaseRequest, $lease);

            DB::commit();
            
            return redirect()->route('lease-requests.show', $leaseRequest)->with('success', _lang('Lease request approved and payment processed successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while approving the request: ') . $e->getMessage());
        }
    }

    /**
     * Reject a lease request
     */
    public function reject(Request $request, LeaseRequest $leaseRequest)
    {
        if (!$leaseRequest->canBeProcessed()) {
            return back()->with('error', _lang('This lease request cannot be processed'));
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        DB::beginTransaction();
        
        try {
            $leaseRequest->reject(auth()->id(), $request->rejection_reason, $request->admin_notes);

            DB::commit();
            
            return redirect()->route('lease-requests.show', $leaseRequest)->with('success', _lang('Lease request rejected'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while rejecting the request: ') . $e->getMessage());
        }
    }

    /**
     * Process payment for approved lease request
     */
    private function processPayment(LeaseRequest $leaseRequest, AssetLease $lease)
    {
        $totalAmount = $leaseRequest->total_amount + $leaseRequest->deposit_amount;
        
        // Create debit transaction for member's account
        Transaction::create([
            'tenant_id' => $leaseRequest->tenant_id,
            'trans_date' => now()->toDateString(),
            'member_id' => $leaseRequest->member_id,
            'savings_account_id' => $leaseRequest->payment_account_id,
            'amount' => $totalAmount,
            'dr_cr' => 'dr',
            'type' => 'lease_payment',
            'method' => 'account_transfer',
            'status' => 1,
            'note' => "Lease payment for asset: {$leaseRequest->asset->name}",
            'description' => "Lease payment - Request #{$leaseRequest->request_number}",
            'created_user_id' => auth()->id(),
        ]);

        // Create credit transaction for organization (if needed)
        // This would depend on your organization's accounting structure
    }

    /**
     * Get available assets for lease request (AJAX)
     */
    public function getAvailableAssets(Request $request)
    {
        $tenant = app('tenant');
        $assets = Asset::where('tenant_id', $tenant->id)
                      ->availableForLease()
                      ->with('category')
                      ->get();

        return response()->json($assets);
    }

    /**
     * Get asset details for lease request (AJAX)
     */
    public function getAssetDetails(Request $request)
    {
        $asset = Asset::find($request->asset_id);
        
        if (!$asset) {
            return response()->json(['error' => 'Asset not found'], 404);
        }

        return response()->json([
            'daily_rate' => $asset->lease_rate,
            'description' => $asset->description,
            'category' => $asset->category->name ?? 'N/A',
        ]);
    }
}
