<?php
namespace App\Http\Controllers\SubscriptionGateway\Offline;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProcessController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        date_default_timezone_set(get_timezone());
    }

    /**
     * Callback function from Payment Gateway
     *
     * @return \Illuminate\Http\Response
     **/
    public function callback(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $user    = auth()->user();
        $package = $user->tenant->package;
        $gateway = PaymentGateway::where('slug', $request->slug)->first();

        if (! $gateway) {
            return redirect()->route('membership.payment_gateways')->with('error', _lang('Payment Gateway not found !'));
        }

        $customValidation = generate_custom_field_validation_2($gateway->parameters);
        $validator        = Validator::make($request->all(), $customValidation['rules'], $customValidation['messages']);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // Store custom field data
        $requirements = store_custom_field_data_2($gateway->parameters);

        $subscriptionpayment                  = new SubscriptionPayment();
        $subscriptionpayment->tenant_id       = $user->tenant->id;
        $subscriptionpayment->order_id        = uniqid();
        $subscriptionpayment->payment_method  = $gateway->name;
        $subscriptionpayment->package_id      = $user->tenant->package_id;
        $subscriptionpayment->amount          = $package->cost - (($package->discount / 100) * $package->cost);
        $subscriptionpayment->status          = 0;
        $subscriptionpayment->extra           = json_encode($requirements);
        $subscriptionpayment->created_user_id = auth()->id();

        $subscriptionpayment->save();

        return redirect()->route('membership.payment_gateways')->with('success', _lang('Thank You! Your Payment is under review. You will be notified once your payment is approved.'));

    }

}