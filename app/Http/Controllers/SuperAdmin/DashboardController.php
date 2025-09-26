<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index() {
        $data           = [];
        $data['assets'] = ['datatable'];

        $tenantStats = Tenant::select(
            DB::raw('COUNT(*) as total_tenant'),
            DB::raw('IFNULL(SUM(CASE WHEN membership_type = "trial" THEN 1 ELSE 0 END),0) as trial_tenant'),
            DB::raw('IFNULL(SUM(CASE WHEN membership_type = "member" THEN 1 ELSE 0 END),0) as paid_tenant'),
            DB::raw('IFNULL(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END),0) as active_tenant'),
            DB::raw('IFNULL(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END),0) as new_tenant'),
            DB::raw('IFNULL(SUM(CASE WHEN membership_type = "member" AND valid_to < CURDATE() THEN 1 ELSE 0 END),0) as expired_tenant')
        )->first();

        $data['total_tenant']   = $tenantStats->total_tenant;
        $data['trial_tenant']   = $tenantStats->trial_tenant;
        $data['paid_tenant']    = $tenantStats->paid_tenant;
        $data['active_tenant']  = $tenantStats->active_tenant;
        $data['new_tenant']     = $tenantStats->new_tenant;
        $data['expired_tenant'] = $tenantStats->expired_tenant;

        $data['current_month_income'] = SubscriptionPayment::where('status', 1)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->sum('amount');
        $data['offline_payment_request'] = SubscriptionPayment::where('status', 0)->count();

        $data['newTenants'] = Tenant::with('package')
            ->where('package_id', '!=', null)
            ->where('membership_type', '!=', null)
            ->orderBy("tenants.created_at", "desc")
            ->limit(10)
            ->get();

        return view("backend.super_admin.dashboard-superadmin", $data);
    }

    public function json_package_wise_subscription() {
        if (auth()->user()->user_type != 'superadmin') {
            return null;
        }
        $tenants = Tenant::selectRaw('package_id, COUNT(id) as subscribed')
            ->with('package')
            ->where('package_id', '!=', null)
            ->groupBy('package_id')
            ->get();

        $package    = [];
        $colors     = [];
        $subscribed = [];

        $flatColors = ["#00C9A7", "#3DAB74", "#4285F4", "#A855F7", "#2D2D2D",
            "#0FA3B1", "#4CAF50", "#1E88E5", "#8E24AA", "#212121",
            "#FFD166", "#FF6F61", "#EF233C", "#FF9F1C", "#E63946", "#D62828"];

        foreach ($tenants as $tenant) {
            array_push($package, $tenant->package->name . ' (' . ucwords($tenant->package->package_type) . ')');
            if (!empty($flatColors)) {
                $index = array_rand($flatColors);
                $color = $flatColors[$index];
                unset($flatColors[$index]);
            } else {
                $color = sprintf("#%06X", mt_rand(0, 0xFFFFFF));
            }
        
            array_push($colors, $color);
            array_push($subscribed, (double) $tenant->subscribed);
        }

        return response()->json([
            'package' => $package, 
            'subscribed' => $subscribed, 
            'colors' => $colors
        ]);
    }

    public function json_yearly_revenue() {
        if (auth()->user()->user_type != 'superadmin') {
            return null;
        }

        $months               = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $subscriptionPayments = SubscriptionPayment::selectRaw('MONTH(created_at) as td, SUM(subscription_payments.amount) as amount')
            ->whereRaw('YEAR(created_at) = ?', date('Y'))
            ->groupBy('td')
            ->get();

        $transactions = [];

        foreach ($subscriptionPayments as $subscriptionPayment) {
            $transactions[$subscriptionPayment->td] = $subscriptionPayment->amount;
        }

        return response()->json([
            'month' => $months, 
            'transactions' => $transactions
        ]);
    }

    public function json_yearly_signup() {
        if (auth()->user()->user_type != 'superadmin') {
            return null;
        }

        $months  = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $tenants = Tenant::selectRaw('MONTH(created_at) as td, COUNT(id) as total')
            ->whereRaw('YEAR(created_at) = ?', date('Y'))
            ->groupBy('td')
            ->get();

        $signups = [];

        foreach ($tenants as $tenant) {
            $signups[$tenant->td] = $tenant->total;
        }

        return response()->json([
            'month' => $months, 
            'signups' => $signups
        ]);

    }

}
