<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLease;
use App\Models\AssetMaintenance;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('asset_module');
    }

    /**
     * Display a listing of assets
     */
    public function index(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        $query = Asset::with(['category', 'activeLeases.member'])
                     ->where('tenant_id', $tenant->id);

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by leasable
        if ($request->filled('is_leasable')) {
            $query->where('is_leasable', $request->boolean('is_leasable'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('created_at', 'desc')->paginate(20);
        $categories = AssetCategory::where('tenant_id', $tenant->id)->active()->get();

        return view('backend.asset_management.assets.index', compact('assets', 'categories'));
    }

    /**
     * Show the form for creating a new asset
     */
    public function create()
    {
        $tenant = app('tenant');
        $categories = AssetCategory::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.assets.create', compact('categories'));
    }

    /**
     * Store a newly created asset
     */
    public function store(Request $request)
    {
        $tenant = app('tenant');
        
        $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'purchase_value' => 'required|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'purchase_date' => 'required|date',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'location' => 'nullable|string|max:255',
            'is_leasable' => 'boolean',
            'lease_rate' => 'nullable|numeric|min:0',
            'lease_rate_type' => 'nullable|in:daily,weekly,monthly',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            // Generate unique asset code
            $assetCode = $this->generateAssetCode($tenant->id, $request->category_id);
            
            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'asset_code' => $assetCode,
                'description' => $request->description,
                'purchase_value' => $request->purchase_value,
                'current_value' => $request->current_value ?? $request->purchase_value,
                'purchase_date' => $request->purchase_date,
                'warranty_expiry' => $request->warranty_expiry,
                'location' => $request->location,
                'is_leasable' => $request->boolean('is_leasable'),
                'lease_rate' => $request->is_leasable ? $request->lease_rate : null,
                'lease_rate_type' => $request->is_leasable ? $request->lease_rate_type : 'daily',
                'notes' => $request->notes,
                'status' => 'active',
            ]);

            DB::commit();
            
            return redirect()->route('assets.index')->with('success', _lang('Asset created successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while creating the asset: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified asset
     */
    public function show(Asset $asset)
    {
        $asset->load(['category', 'leases.member', 'maintenance']);
        
        return view('backend.asset_management.assets.show', compact('asset'));
    }

    /**
     * Show the form for editing the specified asset
     */
    public function edit(Asset $asset)
    {
        $tenant = app('tenant');
        $categories = AssetCategory::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.assets.edit', compact('asset', 'categories'));
    }

    /**
     * Update the specified asset
     */
    public function update(Request $request, Asset $asset)
    {
        $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'purchase_value' => 'required|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'purchase_date' => 'required|date',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,maintenance,disposed',
            'is_leasable' => 'boolean',
            'lease_rate' => 'nullable|numeric|min:0',
            'lease_rate_type' => 'nullable|in:daily,weekly,monthly',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            $asset->update([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'purchase_value' => $request->purchase_value,
                'current_value' => $request->current_value,
                'purchase_date' => $request->purchase_date,
                'warranty_expiry' => $request->warranty_expiry,
                'location' => $request->location,
                'status' => $request->status,
                'is_leasable' => $request->boolean('is_leasable'),
                'lease_rate' => $request->is_leasable ? $request->lease_rate : null,
                'lease_rate_type' => $request->is_leasable ? $request->lease_rate_type : 'daily',
                'notes' => $request->notes,
            ]);

            DB::commit();
            
            return redirect()->route('assets.index')->with('success', _lang('Asset updated successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while updating the asset: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified asset
     */
    public function destroy(Asset $asset)
    {
        // Check if asset has active leases
        if ($asset->activeLeases()->count() > 0) {
            return back()->with('error', _lang('Cannot delete asset with active leases'));
        }

        DB::beginTransaction();
        
        try {
            // Delete related records
            $asset->leases()->delete();
            $asset->maintenance()->delete();
            $asset->delete();

            DB::commit();
            
            return redirect()->route('assets.index')->with('success', _lang('Asset deleted successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while deleting the asset: ') . $e->getMessage());
        }
    }

    /**
     * Show available assets for lease
     */
    public function availableForLease()
    {
        $tenant = app('tenant');
        
        $assets = Asset::with(['category'])
                      ->where('tenant_id', $tenant->id)
                      ->availableForLease()
                      ->get();

        return view('backend.asset_management.assets.available', compact('assets'));
    }

    /**
     * Show lease form for an asset
     */
    public function leaseForm(Asset $asset)
    {
        if (!$asset->isAvailableForLease()) {
            return back()->with('error', _lang('This asset is not available for lease'));
        }

        $tenant = app('tenant');
        $members = Member::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.assets.lease', compact('asset', 'members'));
    }

    /**
     * Create a new lease
     */
    public function createLease(Request $request, Asset $asset)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'deposit_amount' => 'nullable|numeric|min:0',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (!$asset->isAvailableForLease()) {
            return back()->with('error', _lang('This asset is not available for lease'));
        }

        DB::beginTransaction();
        
        try {
            $lease = AssetLease::create([
                'tenant_id' => app('tenant')->id,
                'asset_id' => $asset->id,
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
     * Generate unique asset code
     */
    private function generateAssetCode($tenantId, $categoryId)
    {
        $category = AssetCategory::find($categoryId);
        $prefix = strtoupper(substr($category->name, 0, 3)) . '-';
        
        $lastAsset = Asset::where('tenant_id', $tenantId)
                          ->where('asset_code', 'like', $prefix . '%')
                          ->orderBy('id', 'desc')
                          ->first();

        if ($lastAsset) {
            $lastNumber = (int) str_replace($prefix, '', $lastAsset->asset_code);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
