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
        
        $this->middleware(function ($request, $next) {
            $route_name = request()->route()->getName();
            if (in_array($route_name, ['assets.store', 'asset_categories.store'])) {
                if (has_limit('assets', 'account_limit') <= 0) {
                    if ($request->ajax()) {
                        return response()->json(['result' => 'error', 'message' => _lang('Sorry, Your have reached your limit ! You can update your subscription plan to increase your limit.')]);
                    }
                    return back()->with('error', _lang('Sorry, Your have reached your limit ! You can update your subscription plan to increase your limit.'));
                }
            }

            return $next($request);
        });
    }

    /**
     * Resolve tenant from slug if it's a string
     */
    private function resolveTenant($tenant)
    {
        if (is_string($tenant)) {
            return \App\Models\Tenant::where('slug', $tenant)->firstOrFail();
        }
        return $tenant;
    }

    /**
     * Display a listing of assets
     */
public function index(Request $request, $tenant)
    {
        $this->authorize('viewAny', Asset::class);
        
        $tenant = $this->resolveTenant($tenant);
        
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
    public function create(Request $request, $tenant)
    {
        $this->authorize('create', Asset::class);
        
        $tenant = $this->resolveTenant($tenant);
        
        $categories = AssetCategory::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.assets.create', compact('categories'));
    }

    /**
     * Store a newly created asset
     */
    public function store(Request $request, $tenant)
    {
        $this->authorize('create', Asset::class);
        
        $tenant = $this->resolveTenant($tenant);
        
        $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255|min:2',
            'description' => 'nullable|string|max:1000',
            'purchase_value' => 'required|numeric|min:0|max:999999999.99',
            'current_value' => 'nullable|numeric|min:0|max:999999999.99',
            'purchase_date' => 'required|date|before_or_equal:today',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'location' => 'nullable|string|max:255',
            'is_leasable' => 'boolean',
            'lease_rate' => 'nullable|numeric|min:0|max:99999.99',
            'lease_rate_type' => 'nullable|in:daily,weekly,monthly',
            'notes' => 'nullable|string|max:2000',
            'payment_method' => 'required|in:bank_transfer,cash,credit',
            'bank_account_id' => 'required_if:payment_method,bank_transfer|exists:bank_accounts,id',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
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
                'supplier_name' => $request->supplier_name,
                'invoice_number' => $request->invoice_number,
                'payment_method' => $request->payment_method,
                'bank_account_id' => $request->bank_account_id,
            ]);

            // Create financial transaction based on payment method
            $this->createAssetPurchaseTransaction($asset, $request);

            DB::commit();
            
            return redirect()->route('assets.index')->with('success', _lang('Asset created successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Asset creation failed', [
                'user_id' => auth()->id(),
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'request_data' => $request->except(['_token'])
            ]);
            return back()->with('error', _lang('An error occurred while creating the asset. Please try again.'));
        }
    }

    /**
     * Display the specified asset
     */
    public function show(Request $request, $tenant, $id)
    {
        $tenant = $this->resolveTenant($tenant);
        
        $asset = Asset::where('id', $id)
                     ->where('tenant_id', $tenant->id)
                     ->firstOrFail();
        
        $this->authorize('view', $asset);
        
        $asset->load(['category', 'leases.member', 'maintenance']);
        
        // Check if this is an AJAX request for modal content
        if (request()->ajax()) {
            return view('backend.asset_management.assets.modal.show', compact('asset'));
        }
        
        return view('backend.asset_management.assets.show', compact('asset'));
    }

    /**
     * Show the form for editing the specified asset
     */
    public function edit(Request $request, $tenant, $id)
    {
        $tenant = $this->resolveTenant($tenant);
        
        $asset = Asset::where('id', $id)
                     ->where('tenant_id', $tenant->id)
                     ->firstOrFail();
        
        $this->authorize('update', $asset);
        
        $categories = AssetCategory::where('tenant_id', $tenant->id)->active()->get();
        
        // Check if this is an AJAX request for modal content
        if (request()->ajax()) {
            return view('backend.asset_management.assets.modal.edit', compact('asset', 'categories'));
        }
        
        return view('backend.asset_management.assets.edit', compact('asset', 'categories'));
    }

    /**
     * Update the specified asset
     */
    public function update(Request $request, $tenant, $id)
    {
        $tenant = $this->resolveTenant($tenant);
        
        $asset = Asset::where('id', $id)
                     ->where('tenant_id', $tenant->id)
                     ->firstOrFail();
        
        $this->authorize('update', $asset);
        
        $request->validate([
            'category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255|min:2',
            'description' => 'nullable|string|max:1000',
            'purchase_value' => 'required|numeric|min:0|max:999999999.99',
            'current_value' => 'nullable|numeric|min:0|max:999999999.99',
            'purchase_date' => 'required|date|before_or_equal:today',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,maintenance,disposed',
            'is_leasable' => 'boolean',
            'lease_rate' => 'nullable|numeric|min:0|max:99999.99',
            'lease_rate_type' => 'nullable|in:daily,weekly,monthly',
            'notes' => 'nullable|string|max:2000',
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
            \Log::error('Asset update failed', [
                'user_id' => auth()->id(),
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
                'request_data' => $request->except(['_token'])
            ]);
            return back()->with('error', _lang('An error occurred while updating the asset. Please try again.'));
        }
    }

    /**
     * Remove the specified asset
     */
    public function destroy(Request $request, $tenant, $id)
    {
        $tenant = $this->resolveTenant($tenant);
        
        $asset = Asset::where('id', $id)
                     ->where('tenant_id', $tenant->id)
                     ->firstOrFail();
        
        $this->authorize('delete', $asset);
        
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
    public function availableForLease(Request $request, $tenant)
    {
        $this->authorize('viewAny', Asset::class);
        
        $tenant = $this->resolveTenant($tenant);
        
        $assets = Asset::with(['category'])
                      ->where('tenant_id', $tenant->id)
                      ->availableForLease()
                      ->get();

        return view('backend.asset_management.assets.available', compact('assets'));
    }

    /**
     * Show lease form for an asset
     */
    public function leaseForm(Request $request, $tenant, $id)
    {
        $tenant = $this->resolveTenant($tenant);
        
        $asset = Asset::where('id', $id)
                     ->where('tenant_id', $tenant->id)
                     ->firstOrFail();
        
        $this->authorize('lease', $asset);
        
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
    public function createLease(Request $request, $tenant, $id)
    {
        $tenant = $this->resolveTenant($tenant);
        
        $asset = Asset::where('id', $id)
                     ->where('tenant_id', $tenant->id)
                     ->firstOrFail();
        
        $this->authorize('lease', $asset);
        
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date|before:today+1year',
            'deposit_amount' => 'nullable|numeric|min:0|max:999999.99',
            'terms_conditions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:1000',
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
     * Show asset sales form
     */
    public function sell($asset)
    {
        $tenant = app('tenant');
        
        // Handle both model binding and manual resolution
        if (is_string($asset) || is_numeric($asset)) {
            $asset = Asset::where('id', $asset)
                         ->where('tenant_id', $tenant->id)
                         ->firstOrFail();
        } else {
            // Ensure the asset belongs to the current tenant
            if ($asset->tenant_id !== $tenant->id) {
                abort(404);
            }
        }
        
        $this->authorize('update', $asset);
        
        $bankAccounts = BankAccount::where('tenant_id', $tenant->id)->active()->get();
        
        return view('backend.asset_management.assets.sell', compact('asset', 'bankAccounts'));
    }

    /**
     * Process asset sale
     */
    public function processSale(Request $request, $asset)
    {
        $tenant = app('tenant');
        
        // Handle both model binding and manual resolution
        if (is_string($asset) || is_numeric($asset)) {
            $asset = Asset::where('id', $asset)
                         ->where('tenant_id', $tenant->id)
                         ->firstOrFail();
        } else {
            // Ensure the asset belongs to the current tenant
            if ($asset->tenant_id !== $tenant->id) {
                abort(404);
            }
        }

        $this->authorize('update', $asset);

        $request->validate([
            'sale_price' => 'required|numeric|min:0|max:999999999.99',
            'sale_date' => 'required|date|before_or_equal:today',
            'buyer_name' => 'required|string|max:255|min:2',
            'payment_method' => 'required|in:bank_transfer,cash',
            'bank_account_id' => 'required_if:payment_method,bank_transfer|exists:bank_accounts,id',
            'sale_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            // Update asset status to sold
            $asset->update([
                'status' => 'sold',
                'sale_price' => $request->sale_price,
                'sale_date' => $request->sale_date,
                'buyer_name' => $request->buyer_name,
                'sale_reason' => $request->sale_reason,
            ]);

            // Create financial transaction for asset sale
            $this->createAssetSaleTransaction($asset, $request);

            DB::commit();
            
            return redirect()->route('assets.index')->with('success', _lang('Asset sold successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while selling the asset: ') . $e->getMessage());
        }
    }

    /**
     * Generate unique asset code
     */
    private function generateAssetCode($tenantId, $categoryId)
    {
        return DB::transaction(function() use ($tenantId, $categoryId) {
            $category = AssetCategory::find($categoryId);
            $prefix = strtoupper(substr($category->name, 0, 3)) . '-';
            
            $lastAsset = Asset::where('tenant_id', $tenantId)
                              ->where('asset_code', 'like', $prefix . '%')
                              ->lockForUpdate()
                              ->orderBy('id', 'desc')
                              ->first();

            if ($lastAsset) {
                $lastNumber = (int) str_replace($prefix, '', $lastAsset->asset_code);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Create financial transaction for asset purchase
     */
    private function createAssetPurchaseTransaction($asset, $request)
    {
        $description = "Asset Purchase: {$asset->name} ({$asset->asset_code})";
        if ($request->supplier_name) {
            $description .= " - Supplier: {$request->supplier_name}";
        }
        if ($request->invoice_number) {
            $description .= " - Invoice: {$request->invoice_number}";
        }

        switch ($request->payment_method) {
            case 'bank_transfer':
                // Create bank transaction (debit)
                \App\Models\BankTransaction::create([
                    'tenant_id' => $asset->tenant_id,
                    'bank_account_id' => $request->bank_account_id,
                    'trans_date' => $request->purchase_date,
                    'amount' => $request->purchase_value,
                    'dr_cr' => 'dr',
                    'type' => 'Asset Purchase',
                    'method' => 'Bank Transfer',
                    'status' => 1,
                    'description' => $description,
                    'created_user_id' => auth()->id(),
                ]);
                break;

            case 'cash':
                // Create cash transaction (debit from cash account)
                // This would require a cash account or general ledger entry
                // For now, we'll create a bank transaction with a "Cash" bank account
                $cashAccount = \App\Models\BankAccount::where('tenant_id', $asset->tenant_id)
                    ->where('bank_name', 'Cash Account')
                    ->first();
                
                if (!$cashAccount) {
                    // Create a cash account if it doesn't exist
                    $cashAccount = \App\Models\BankAccount::create([
                        'tenant_id' => $asset->tenant_id,
                        'bank_name' => 'Cash Account',
                        'account_name' => 'Cash in Hand',
                        'account_number' => 'CASH-001',
                        'opening_date' => now()->format('Y-m-d'),
                        'opening_balance' => 0,
                        'currency_id' => \App\Models\Currency::where('tenant_id', $asset->tenant_id)->first()->id,
                    ]);
                }

                \App\Models\BankTransaction::create([
                    'tenant_id' => $asset->tenant_id,
                    'bank_account_id' => $cashAccount->id,
                    'trans_date' => $request->purchase_date,
                    'amount' => $request->purchase_value,
                    'dr_cr' => 'dr',
                    'type' => 'Asset Purchase',
                    'method' => 'Cash',
                    'status' => 1,
                    'description' => $description,
                    'created_user_id' => auth()->id(),
                ]);
                break;

            case 'credit':
                // Credit payments are not supported until proper accounts payable system is implemented
                throw new \Exception('Credit payments are not currently supported. Please use bank transfer or cash payment.');
                break;
        }
    }

    /**
     * Create financial transaction for asset sale
     */
    private function createAssetSaleTransaction($asset, $request)
    {
        $description = "Asset Sale: {$asset->name} ({$asset->asset_code}) - Buyer: {$request->buyer_name}";

        switch ($request->payment_method) {
            case 'bank_transfer':
                // Create bank transaction (credit)
                \App\Models\BankTransaction::create([
                    'tenant_id' => $asset->tenant_id,
                    'bank_account_id' => $request->bank_account_id,
                    'trans_date' => $request->sale_date,
                    'amount' => $request->sale_price,
                    'dr_cr' => 'cr',
                    'type' => 'Asset Sale',
                    'method' => 'Bank Transfer',
                    'status' => 1,
                    'description' => $description,
                    'created_user_id' => auth()->id(),
                ]);
                break;

            case 'cash':
                // Create cash transaction (credit to cash account)
                $cashAccount = \App\Models\BankAccount::where('tenant_id', $asset->tenant_id)
                    ->where('bank_name', 'Cash Account')
                    ->first();
                
                if (!$cashAccount) {
                    // Create a cash account if it doesn't exist
                    $cashAccount = \App\Models\BankAccount::create([
                        'tenant_id' => $asset->tenant_id,
                        'bank_name' => 'Cash Account',
                        'account_name' => 'Cash in Hand',
                        'account_number' => 'CASH-001',
                        'opening_date' => now()->format('Y-m-d'),
                        'opening_balance' => 0,
                        'currency_id' => \App\Models\Currency::where('tenant_id', $asset->tenant_id)->first()->id,
                    ]);
                }

                \App\Models\BankTransaction::create([
                    'tenant_id' => $asset->tenant_id,
                    'bank_account_id' => $cashAccount->id,
                    'trans_date' => $request->sale_date,
                    'amount' => $request->sale_price,
                    'dr_cr' => 'cr',
                    'type' => 'Asset Sale',
                    'method' => 'Cash',
                    'status' => 1,
                    'description' => $description,
                    'created_user_id' => auth()->id(),
                ]);
                break;
        }
    }
}
