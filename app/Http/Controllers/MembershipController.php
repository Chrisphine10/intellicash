<?php
namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MembershipController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());

        $this->middleware(function ($request, $next) {
            if (! auth()->check()){
                return redirect()->route('login');
            }
            $route_name = request()->route()->getName();

            if($route_name == 'membership.payment_gateways' && auth()->user()->tenant_owner != 1){
                return back()->with('error', _lang('Only tenant owner can access payment page'));
            }

            if (auth()->user()->user_type != 'admin' || auth()->user()->tenant_owner != 1) {
                return redirect()->route('login');
            }
            return $next($request);
        });
    }

    /** Show Active Subscription */
    public function index() {
        $alert_col = 'col-xl-8 offset-xl-2';
        $payments = request()->tenant->subscriptionPayments()->orderBy('id', 'desc')->paginate(10);
        $lastPayment = request()->tenant->subscriptionPayments()->orderBy('id', 'desc')->first();
        $package     = request()->tenant->package;
        return view('membership.index', compact('package', 'lastPayment', 'payments', 'alert_col'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function packages() {
        $packages = Package::active()->get();
        return view('membership.packages', compact('packages'));
    }

    public function choose_package(Request $request) {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $tenant                  = auth()->user()->tenant;
        $tenant->package_id      = $request->package_id;
        $tenant->valid_to        = null;
        $tenant->s_email_send_at = null;
        $tenant->save();

        return redirect()->route('dashboard.index', ['tenant' => $tenant->slug]);

    }

    public function payment_gateways(Request $request) {
        $pendingPayments = auth()->user()->tenant->subscriptionPayments()->where('status', 0)->get();
        if (count($pendingPayments) > 0) {
            $request->session()->forget('error');

            $alert_col = 'col-lg-10 offset-lg-1';
            return view('membership.pending_payment', compact('pendingPayments', 'alert_col'));
        }
        $payment_gateways = PaymentGateway::active()->get();
        return view('membership.payment_gateways', compact('payment_gateways'));
    }

    public function make_payment(Request $request, $slug) {
        $user = auth()->user();

        $gateway = PaymentGateway::where('slug', $slug)->first();
        $package = $user->tenant->package;

        if ($user->tenant->package_id == null) {
            return redirect()->route('membership.packages')->with('error', _lang("Please choose your package first"));
        }

        if($gateway->type == 0){
            $alert_col = 'col-lg-6 offset-lg-3';
            return view('membership.gateway.offline', compact('package', 'gateway', 'slug', 'alert_col'));
        }

        //Process Via Payment Gateway
        $paymentGateway = '\App\Http\Controllers\SubscriptionGateway\\' . $slug . '\\ProcessController';

        $data = $paymentGateway::process($user, $slug);
        $data = json_decode($data);

        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        if (isset($data->error)) {
            return back()->with('error', $data->error_message);
        }

        $alert_col = 'col-lg-6 offset-lg-3';
        return view($data->view, compact('data', 'package', 'gateway', 'slug', 'alert_col'));
    }

}