<?php

namespace App\Http\Controllers;

use App\Models\AssetMaintenance;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetMaintenanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('asset_module');
    }

    /**
     * Display a listing of asset maintenance records
     */
    public function index(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        $query = AssetMaintenance::with(['asset.category', 'createdBy'])
                                ->where('tenant_id', $tenant->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by maintenance type
        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->maintenance_type);
        }

        // Filter by asset
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('scheduled_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('scheduled_date', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('asset', function($assetQuery) use ($search) {
                      $assetQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('asset_code', 'like', "%{$search}%");
                  });
            });
        }

        $maintenance = $query->orderBy('scheduled_date', 'desc')->paginate(20);
        
        $assets = Asset::where('tenant_id', $tenant->id)->active()->get();
        $maintenanceTypes = AssetMaintenance::getMaintenanceTypes();

        return view('backend.asset_management.maintenance.index', compact('maintenance', 'assets', 'maintenanceTypes'));
    }

    /**
     * Show the form for creating a new maintenance record
     */
    public function create()
    {
        $tenant = app('tenant');
        $assets = Asset::where('tenant_id', $tenant->id)->active()->get();
        $maintenanceTypes = AssetMaintenance::getMaintenanceTypes();
        
        return view('backend.asset_management.maintenance.create', compact('assets', 'maintenanceTypes'));
    }

    /**
     * Store a newly created maintenance record
     */
    public function store(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'maintenance_type' => 'required|in:scheduled,emergency,repair,inspection,cleaning,upgrade',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            AssetMaintenance::create([
                'tenant_id' => app('tenant')->id,
                'asset_id' => $request->asset_id,
                'maintenance_type' => $request->maintenance_type,
                'title' => $request->title,
                'description' => $request->description,
                'scheduled_date' => $request->scheduled_date,
                'cost' => $request->cost ?? 0,
                'notes' => $request->notes,
                'status' => 'scheduled',
                'created_by' => auth()->id(),
            ]);

            DB::commit();
            
            return redirect()->route('asset-maintenance.index')->with('success', _lang('Maintenance record created successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while creating the maintenance record: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified maintenance record
     */
    public function show(AssetMaintenance $maintenance)
    {
        $maintenance->load(['asset.category', 'createdBy']);
        
        return view('backend.asset_management.maintenance.show', compact('maintenance'));
    }

    /**
     * Show the form for editing the specified maintenance record
     */
    public function edit(AssetMaintenance $maintenance)
    {
        if ($maintenance->status === 'completed') {
            return back()->with('error', _lang('Completed maintenance records cannot be edited'));
        }

        $tenant = app('tenant');
        $assets = Asset::where('tenant_id', $tenant->id)->active()->get();
        $maintenanceTypes = AssetMaintenance::getMaintenanceTypes();
        
        return view('backend.asset_management.maintenance.edit', compact('maintenance', 'assets', 'maintenanceTypes'));
    }

    /**
     * Update the specified maintenance record
     */
    public function update(Request $request, AssetMaintenance $maintenance)
    {
        if ($maintenance->status === 'completed') {
            return back()->with('error', _lang('Completed maintenance records cannot be edited'));
        }

        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'maintenance_type' => 'required|in:scheduled,emergency,repair,inspection,cleaning,upgrade',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_date' => 'required|date',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        
        try {
            $maintenance->update([
                'asset_id' => $request->asset_id,
                'maintenance_type' => $request->maintenance_type,
                'title' => $request->title,
                'description' => $request->description,
                'scheduled_date' => $request->scheduled_date,
                'cost' => $request->cost ?? 0,
                'notes' => $request->notes,
            ]);

            DB::commit();
            
            return redirect()->route('asset-maintenance.index')->with('success', _lang('Maintenance record updated successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while updating the maintenance record: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified maintenance record
     */
    public function destroy(AssetMaintenance $maintenance)
    {
        if ($maintenance->status === 'completed') {
            return back()->with('error', _lang('Completed maintenance records cannot be deleted'));
        }

        DB::beginTransaction();
        
        try {
            $maintenance->delete();

            DB::commit();
            
            return redirect()->route('asset-maintenance.index')->with('success', _lang('Maintenance record deleted successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while deleting the maintenance record: ') . $e->getMessage());
        }
    }

    /**
     * Mark maintenance as in progress
     */
    public function markInProgress(AssetMaintenance $maintenance)
    {
        if ($maintenance->status !== 'scheduled') {
            return back()->with('error', _lang('Only scheduled maintenance can be marked as in progress'));
        }

        DB::beginTransaction();
        
        try {
            $maintenance->markAsInProgress();

            DB::commit();
            
            return back()->with('success', _lang('Maintenance marked as in progress'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while updating the maintenance status: ') . $e->getMessage());
        }
    }

    /**
     * Complete maintenance
     */
    public function complete(Request $request, AssetMaintenance $maintenance)
    {
        if ($maintenance->status === 'completed') {
            return back()->with('error', _lang('Maintenance is already completed'));
        }

        $request->validate([
            'notes' => 'nullable|string',
            'performed_by' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        
        try {
            $maintenance->markAsCompleted(
                $request->notes,
                $request->performed_by
            );

            if ($request->filled('cost')) {
                $maintenance->update(['cost' => $request->cost]);
            }

            DB::commit();
            
            return back()->with('success', _lang('Maintenance completed successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while completing the maintenance: ') . $e->getMessage());
        }
    }

    /**
     * Cancel maintenance
     */
    public function cancel(AssetMaintenance $maintenance)
    {
        if ($maintenance->status === 'completed') {
            return back()->with('error', _lang('Completed maintenance cannot be cancelled'));
        }

        DB::beginTransaction();
        
        try {
            $maintenance->cancel();

            DB::commit();
            
            return back()->with('success', _lang('Maintenance cancelled successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while cancelling the maintenance: ') . $e->getMessage());
        }
    }

    /**
     * Show overdue maintenance
     */
    public function overdue()
    {
        $tenant = app('tenant');
        
        $maintenance = AssetMaintenance::with(['asset.category', 'createdBy'])
                                     ->where('tenant_id', $tenant->id)
                                     ->overdue()
                                     ->orderBy('scheduled_date')
                                     ->get();

        return view('backend.asset_management.maintenance.overdue', compact('maintenance'));
    }
}
