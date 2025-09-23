<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLease;
use App\Models\AssetMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('asset_module');
    }

    /**
     * Display asset reports dashboard
     */
    public function index()
    {
        $tenant = app('tenant');
        
        if (!$tenant->isAssetManagementEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('Asset Management module is not enabled'));
        }

        // Get summary statistics
        $totalAssets = Asset::where('tenant_id', $tenant->id)->count();
        $totalValue = Asset::where('tenant_id', $tenant->id)->sum('purchase_value');
        $activeLeases = AssetLease::where('tenant_id', $tenant->id)
                                 ->where('status', 'active')
                                 ->count();
        $overdueMaintenance = AssetMaintenance::where('tenant_id', $tenant->id)
                                             ->where('status', 'scheduled')
                                             ->where('scheduled_date', '<', now())
                                             ->count();

        return view('backend.asset_management.reports.index', compact(
            'totalAssets', 'totalValue', 'activeLeases', 'overdueMaintenance'
        ));
    }

    /**
     * Asset valuation report
     */
    public function valuation(Request $request)
    {
        $tenant = app('tenant');
        $date = $request->get('date', now()->toDateString());
        
        $assets = Asset::where('tenant_id', $tenant->id)
                      ->with(['category', 'activeLeases'])
                      ->get()
                      ->map(function ($asset) use ($date) {
                          $asset->current_value = $this->calculateCurrentValue($asset, $date);
                          $asset->depreciation = $asset->purchase_value - $asset->current_value;
                          return $asset;
                      });

        $totalPurchaseValue = $assets->sum('purchase_value');
        $totalCurrentValue = $assets->sum('current_value');
        $totalDepreciation = $totalPurchaseValue - $totalCurrentValue;

        return view('backend.asset_management.reports.valuation', compact(
            'assets', 'totalPurchaseValue', 'totalCurrentValue', 'totalDepreciation', 'date'
        ));
    }

    /**
     * Profit and Loss report for assets
     */
    public function profitLoss(Request $request)
    {
        $tenant = app('tenant');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        // Revenue from leases
        $leaseRevenue = AssetLease::where('tenant_id', $tenant->id)
                                 ->where('status', 'completed')
                                 ->whereBetween('end_date', [$startDate, $endDate])
                                 ->sum('total_amount');

        // Maintenance costs
        $maintenanceCosts = AssetMaintenance::where('tenant_id', $tenant->id)
                                           ->where('status', 'completed')
                                           ->whereBetween('completed_date', [$startDate, $endDate])
                                           ->sum('cost');

        // Depreciation costs
        $depreciationCosts = $this->calculateDepreciationForPeriod($tenant->id, $startDate, $endDate);

        // Other asset-related expenses (can be extended)
        $otherExpenses = 0; // Placeholder for future expenses

        $grossProfit = $leaseRevenue - $maintenanceCosts - $depreciationCosts - $otherExpenses;

        // Get detailed breakdown by category
        $categoryBreakdown = $this->getCategoryBreakdown($tenant->id, $startDate, $endDate);

        return view('backend.asset_management.reports.profit_loss', compact(
            'leaseRevenue', 'maintenanceCosts', 'depreciationCosts', 'otherExpenses',
            'grossProfit', 'categoryBreakdown', 'startDate', 'endDate'
        ));
    }

    /**
     * Lease performance report
     */
    public function leasePerformance(Request $request)
    {
        $tenant = app('tenant');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $leases = AssetLease::where('tenant_id', $tenant->id)
                           ->whereBetween('start_date', [$startDate, $endDate])
                           ->with(['asset.category', 'member'])
                           ->get();

        $performanceMetrics = [
            'total_leases' => $leases->count(),
            'completed_leases' => $leases->where('status', 'completed')->count(),
            'active_leases' => $leases->where('status', 'active')->count(),
            'cancelled_leases' => $leases->where('status', 'cancelled')->count(),
            'total_revenue' => $leases->where('status', 'completed')->sum('total_amount'),
            'average_lease_duration' => $leases->where('status', 'completed')->avg(function($lease) {
                return Carbon::parse($lease->start_date)->diffInDays(Carbon::parse($lease->end_date));
            }),
        ];

        return view('backend.asset_management.reports.lease_performance', compact(
            'leases', 'performanceMetrics', 'startDate', 'endDate'
        ));
    }

    /**
     * Maintenance report
     */
    public function maintenance(Request $request)
    {
        $tenant = app('tenant');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $maintenanceRecords = AssetMaintenance::where('tenant_id', $tenant->id)
                                             ->whereBetween('scheduled_date', [$startDate, $endDate])
                                             ->with(['asset.category'])
                                             ->get();

        $maintenanceStats = [
            'total_scheduled' => $maintenanceRecords->where('status', 'scheduled')->count(),
            'total_completed' => $maintenanceRecords->where('status', 'completed')->count(),
            'total_in_progress' => $maintenanceRecords->where('status', 'in_progress')->count(),
            'total_cancelled' => $maintenanceRecords->where('status', 'cancelled')->count(),
            'total_cost' => $maintenanceRecords->where('status', 'completed')->sum('cost'),
            'overdue_count' => $maintenanceRecords->where('status', 'scheduled')
                                                 ->where('scheduled_date', '<', now())
                                                 ->count(),
        ];

        return view('backend.asset_management.reports.maintenance', compact(
            'maintenanceRecords', 'maintenanceStats', 'startDate', 'endDate'
        ));
    }

    /**
     * Asset utilization report
     */
    public function utilization(Request $request)
    {
        $tenant = app('tenant');
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $assets = Asset::where('tenant_id', $tenant->id)
                      ->where('is_leasable', true)
                      ->with(['category', 'leases' => function($query) use ($startDate, $endDate) {
                          $query->whereBetween('start_date', [$startDate, $endDate]);
                      }])
                      ->get();

        $reportData = $assets->map(function ($asset) use ($startDate, $endDate) {
            $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            $leasedDays = $asset->leases->sum(function($lease) use ($startDate, $endDate) {
                $leaseStart = max(Carbon::parse($lease->start_date), Carbon::parse($startDate));
                $leaseEnd = min(Carbon::parse($lease->end_date), Carbon::parse($endDate));
                return $leaseStart->diffInDays($leaseEnd) + 1;
            });
            
            $utilizationRate = $totalDays > 0 ? ($leasedDays / $totalDays) * 100 : 0;
            $revenue = $asset->leases->sum('total_amount');
            
            return [
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'category' => $asset->category->name,
                'utilization_rate' => $utilizationRate,
                'total_revenue' => $revenue,
            ];
        });

        $utilizationStats = [
            'total_leasable_assets' => $assets->count(),
            'average_utilization' => $reportData->avg('utilization_rate'),
            'total_active_leases' => AssetLease::where('tenant_id', $tenant->id)
                                            ->where('status', 'active')
                                            ->whereBetween('start_date', [$startDate, $endDate])
                                            ->count(),
            'top_performers' => $reportData->sortByDesc('utilization_rate')->take(3)->values(),
            'underperformers' => $reportData->where('utilization_rate', '<', 50)->sortBy('utilization_rate')->take(3)->values(),
        ];

        return view('backend.asset_management.reports.utilization', compact(
            'reportData', 'utilizationStats', 'startDate', 'endDate'
        ));
    }

    /**
     * Calculate current value of an asset based on depreciation
     */
    private function calculateCurrentValue($asset, $date)
    {
        if (!$asset->purchase_date || !$asset->depreciation_method) {
            return $asset->purchase_value;
        }

        $purchaseDate = Carbon::parse($asset->purchase_date);
        $currentDate = Carbon::parse($date);
        $yearsOwned = $purchaseDate->diffInYears($currentDate);

        switch ($asset->depreciation_method) {
            case 'straight_line':
                $annualDepreciation = $asset->purchase_value / $asset->useful_life;
                $totalDepreciation = $annualDepreciation * $yearsOwned;
                break;
            case 'declining_balance':
                $rate = 2 / $asset->useful_life; // Double declining balance
                $totalDepreciation = $asset->purchase_value * (1 - pow(1 - $rate, $yearsOwned));
                break;
            default:
                $totalDepreciation = 0;
        }

        $currentValue = $asset->purchase_value - $totalDepreciation;
        return max($currentValue, $asset->salvage_value ?? 0);
    }

    /**
     * Calculate depreciation for a specific period
     */
    private function calculateDepreciationForPeriod($tenantId, $startDate, $endDate)
    {
        $assets = Asset::where('tenant_id', $tenantId)
                      ->whereNotNull('purchase_date')
                      ->whereNotNull('depreciation_method')
                      ->get();

        $totalDepreciation = 0;
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        foreach ($assets as $asset) {
            $purchaseDate = Carbon::parse($asset->purchase_date);
            
            // Skip if asset was purchased after the period
            if ($purchaseDate->gt($end)) {
                continue;
            }

            $depreciationStart = $purchaseDate->gt($start) ? $purchaseDate : $start;
            $depreciationEnd = $end;
            
            $months = $depreciationStart->diffInMonths($depreciationEnd);
            
            switch ($asset->depreciation_method) {
                case 'straight_line':
                    $monthlyDepreciation = $asset->purchase_value / ($asset->useful_life * 12);
                    $totalDepreciation += $monthlyDepreciation * $months;
                    break;
                case 'declining_balance':
                    $rate = 2 / $asset->useful_life;
                    $monthlyRate = $rate / 12;
                    $totalDepreciation += $asset->purchase_value * (1 - pow(1 - $monthlyRate, $months));
                    break;
            }
        }

        return $totalDepreciation;
    }

    /**
     * Get category breakdown for P&L report
     */
    private function getCategoryBreakdown($tenantId, $startDate, $endDate)
    {
        $categories = AssetCategory::where('tenant_id', $tenantId)->get();
        $breakdown = [];

        foreach ($categories as $category) {
            $assets = Asset::where('tenant_id', $tenantId)
                          ->where('category_id', $category->id)
                          ->get();

            $revenue = AssetLease::where('tenant_id', $tenantId)
                               ->where('status', 'completed')
                               ->whereBetween('end_date', [$startDate, $endDate])
                               ->whereHas('asset', function($query) use ($category) {
                                   $query->where('category_id', $category->id);
                               })
                               ->sum('total_amount');

            $maintenance = AssetMaintenance::where('tenant_id', $tenantId)
                                          ->where('status', 'completed')
                                          ->whereBetween('completed_date', [$startDate, $endDate])
                                          ->whereHas('asset', function($query) use ($category) {
                                              $query->where('category_id', $category->id);
                                          })
                                          ->sum('cost');

            $depreciation = $this->calculateDepreciationForPeriod($tenantId, $startDate, $endDate);

            $breakdown[] = [
                'category' => $category,
                'revenue' => $revenue,
                'maintenance' => $maintenance,
                'depreciation' => $depreciation,
                'profit' => $revenue - $maintenance - $depreciation,
            ];
        }

        return $breakdown;
    }
}
