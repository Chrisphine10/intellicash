<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('asset_module');
    }

    /**
     * Display a listing of asset categories
     */
    public function index()
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        $categories = AssetCategory::where('tenant_id', $tenant->id)
                                  ->withCount('assets')
                                  ->orderBy('name')
                                  ->get();

        return view('backend.asset_management.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category
     */
    public function create(Request $request, $tenant)
    {
        return view('backend.asset_management.categories.create');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request, $tenant)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,investment,leasable',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        
        try {
            AssetCategory::create([
                'tenant_id' => app('tenant')->id,
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'is_active' => $request->boolean('is_active', true),
            ]);

            DB::commit();
            
            return redirect()->route('asset-categories.index')->with('success', _lang('Asset category created successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while creating the category: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified category
     */
    public function show(Request $request, $tenant, $id)
    {
        $asset_category = AssetCategory::withoutGlobalScopes()->findOrFail($id);
        $asset_category->load(['assets' => function($query) {
            $query->with(['activeLeases.member'])->orderBy('name');
        }]);
        
        return view('backend.asset_management.categories.show', compact('asset_category'));
    }

    /**
     * Show the form for editing the specified category
     */
    public function edit(Request $request, $tenant, $id)
    {
        $asset_category = AssetCategory::withoutGlobalScopes()->findOrFail($id);
        return view('backend.asset_management.categories.edit', compact('asset_category'));
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, $tenant, $id)
    {
        $asset_category = AssetCategory::withoutGlobalScopes()->findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,investment,leasable',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        
        try {
            $asset_category->update([
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->type,
                'is_active' => $request->boolean('is_active'),
            ]);

            DB::commit();
            
            return redirect()->route('asset-categories.index')->with('success', _lang('Asset category updated successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while updating the category: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified category
     */
    public function destroy(Request $request, $tenant, $id)
    {
        $asset_category = AssetCategory::withoutGlobalScopes()->findOrFail($id);
        
        // Check if category has assets
        if ($asset_category->assets()->count() > 0) {
            return back()->with('error', _lang('Cannot delete category with existing assets'));
        }

        DB::beginTransaction();
        
        try {
            $asset_category->delete();

            DB::commit();
            
            return redirect()->route('asset-categories.index')->with('success', _lang('Asset category deleted successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while deleting the category: ') . $e->getMessage());
        }
    }

    /**
     * Toggle category status
     */
    public function toggleStatus(Request $request, $tenant, $id)
    {
        $asset_category = AssetCategory::withoutGlobalScopes()->findOrFail($id);
        
        DB::beginTransaction();
        
        try {
            $asset_category->update([
                'is_active' => !$asset_category->is_active
            ]);

            DB::commit();
            
            $status = $asset_category->is_active ? 'activated' : 'deactivated';
            return back()->with('success', _lang("Category {$status} successfully"));
            
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while updating the category: ') . $e->getMessage());
        }
    }
}
