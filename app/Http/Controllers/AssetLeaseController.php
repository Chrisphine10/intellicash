<?php

namespace App\Http\Controllers;

use App\Models\AssetLease;
use App\Models\Asset;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetLeaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('asset_module');
    }

    /**
     * Display a listing of asset leases
     */
    public function index(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        $query = AssetLease::with(['asset.category', 'member', 'createdBy'])
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
                $q->where('lease_number', 'like', "%{$search}%")
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

        $leases = $query->orderBy('created_at', 'desc')->paginate(20);
        
        $members = Member::where('tenant_id', $tenant->id)->active()->get();
        $assets = Asset::where('tenant_id', $tenant->id)->leasable()->get();

        return view('backend.asset_management.leases.index', compact('leases', 'members', 'assets'));
    }

    /**
     * Show the form for creating a new lease
     */
    public function create()
    {
        $tenant = app('tenant');
        $assets = Asset::where('tenant_id', $tenant->id)->availableForLease()->with('category')->get();
        $members = Member::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.leases.create', compact('assets', 'members', 'tenant'));
    }

    /**
     * Store a newly created lease
     */
    public function store(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'member_id' => 'required|exists:members,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'deposit_amount' => 'nullable|numeric|min:0',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $asset = Asset::findOrFail($request->asset_id);
        
        if (!$asset->isAvailableForLease()) {
            return back()->with('error', _lang('This asset is not available for lease'));
        }

        DB::beginTransaction();
        
        try {
            $lease = AssetLease::create([
                'tenant_id' => app('tenant')->id,
                'asset_id' => $request->asset_id,
                'member_id' => $request->member_id,
                'lease_number' => AssetLease::generateLeaseNumber(app('tenant')->id),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'daily_rate' => $asset->lease_rate,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'terms_conditions' => $request->terms_conditions,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Calculate total amount if end date is provided
            if ($request->end_date) {
                $lease->calculateTotalAmount();
            }

            DB::commit();
            
            return redirect()->route('asset-leases.show', $lease)->with('success', _lang('Asset lease created successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while creating the lease: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified lease
     */
    public function show(AssetLease $lease)
    {
        $lease->load(['asset.category', 'member', 'createdBy']);
        
        return view('backend.asset_management.leases.show', compact('lease'));
    }

    /**
     * Show the form for editing the specified lease
     */
    public function edit(AssetLease $lease)
    {
        if ($lease->status !== 'active') {
            return back()->with('error', _lang('Only active leases can be edited'));
        }

        $tenant = app('tenant');
        $members = Member::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.leases.edit', compact('lease', 'members', 'tenant'));
    }

    /**
     * Update the specified lease
     */
    public function update(Request $request, AssetLease $lease)
    {
        if ($lease->status !== 'active') {
            return back()->with('error', _lang('Only active leases can be edited'));
        }

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'deposit_amount' => 'nullable|numeric|min:0',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            $lease->update([
                'member_id' => $request->member_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'deposit_amount' => $request->deposit_amount ?? 0,
                'terms_conditions' => $request->terms_conditions,
                'notes' => $request->notes,
            ]);

            // Recalculate total amount if end date is provided
            if ($request->end_date) {
                $lease->calculateTotalAmount();
            }

            DB::commit();
            
            return redirect()->route('asset-leases.show', $lease)->with('success', _lang('Asset lease updated successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while updating the lease: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified lease
     */
    public function destroy(AssetLease $lease)
    {
        if ($lease->status === 'completed') {
            return back()->with('error', _lang('Cannot delete completed leases'));
        }

        DB::beginTransaction();
        
        try {
            $lease->delete();

            DB::commit();
            
            return redirect()->route('asset-leases.index')->with('success', _lang('Asset lease deleted successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while deleting the lease: ') . $e->getMessage());
        }
    }

    /**
     * Complete a lease
     */
    public function complete(AssetLease $lease)
    {
        if ($lease->status !== 'active') {
            return back()->with('error', _lang('Only active leases can be completed'));
        }

        DB::beginTransaction();
        
        try {
            $lease->markAsCompleted();

            DB::commit();
            
            return back()->with('success', _lang('Lease completed successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while completing the lease: ') . $e->getMessage());
        }
    }

    /**
     * Cancel a lease
     */
    public function cancel(AssetLease $lease)
    {
        if ($lease->status !== 'active') {
            return back()->with('error', _lang('Only active leases can be cancelled'));
        }

        DB::beginTransaction();
        
        try {
            $lease->cancel();

            DB::commit();
            
            return back()->with('success', _lang('Lease cancelled successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while cancelling the lease: ') . $e->getMessage());
        }
    }

    /**
     * Mark lease as overdue
     */
    public function markOverdue(AssetLease $lease)
    {
        if ($lease->status !== 'active') {
            return back()->with('error', _lang('Only active leases can be marked as overdue'));
        }

        DB::beginTransaction();
        
        try {
            $lease->markAsOverdue();

            DB::commit();
            
            return back()->with('success', _lang('Lease marked as overdue'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while marking the lease as overdue: ') . $e->getMessage());
        }
    }
}
