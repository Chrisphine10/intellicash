<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLease;
use App\Models\AssetMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetManagementDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('asset_module');
    }

    /**
     * Display the asset management dashboard
     */
    public function index()
    {
        $tenant = app('tenant');
        
        // Overview statistics
        $totalAssets = Asset::where('tenant_id', $tenant->id)->count();
        $activeLeases = AssetLease::where('tenant_id', $tenant->id)->active()->count();
        $pendingMaintenance = AssetMaintenance::where('tenant_id', $tenant->id)
                                             ->where('status', 'scheduled')
                                             ->count();
        $totalValue = Asset::where('tenant_id', $tenant->id)->sum('current_value');

        // Category statistics
        $categoryStats = AssetCategory::where('tenant_id', $tenant->id)
                                    ->withCount(['assets'])
                                    ->with(['assets' => function($query) {
                                        $query->select('category_id', 'current_value', 'is_leasable');
                                    }])
                                    ->get()
                                    ->map(function($category) {
                                        $category->total_value = $category->assets->sum('current_value');
                                        $category->leasable_count = $category->assets->where('is_leasable', true)->count();
                                        return $category;
                                    });

        // Recent assets
        $recentAssets = Asset::where('tenant_id', $tenant->id)
                           ->with('category')
                           ->orderBy('created_at', 'desc')
                           ->limit(5)
                           ->get();

        // Recent leases
        $recentLeases = AssetLease::where('tenant_id', $tenant->id)
                                ->with(['asset', 'member'])
                                ->active()
                                ->orderBy('created_at', 'desc')
                                ->limit(5)
                                ->get();

        return view('backend.asset_management.dashboard', compact(
            'totalAssets',
            'activeLeases', 
            'pendingMaintenance',
            'totalValue',
            'categoryStats',
            'recentAssets',
            'recentLeases'
        ));
    }
}
