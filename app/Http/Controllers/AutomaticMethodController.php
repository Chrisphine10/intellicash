<?php
namespace App\Http\Controllers;

use App\Models\AutomaticGateway;
use App\Models\ChargeLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AutomaticMethodController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $alert_col      = 'col-lg-8 offset-lg-2';
        $tenantId        = request()->tenant->id;
        $paymentGateways = AutomaticGateway::withOutGlobalScopes()
            ->whereNull('tenant_id')
            ->with(['tenantGateway' => function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }])
            ->get()
            ->map(function ($gateway) {
                return (object) [
                    'id'                   => $gateway->tenantGateway->id ?? $gateway->id,
                    'name'                 => $gateway->tenantGateway->name ?? $gateway->name,
                    'slug'                 => $gateway->tenantGateway->slug ?? $gateway->slug,
                    'image'                => $gateway->tenantGateway->image ?? $gateway->image,
                    'status'               => $gateway->tenantGateway->status ?? 0,
                    'is_crypto'            => $gateway->tenantGateway->is_crypto ?? $gateway->is_crypto,
                    'parameters'           => $gateway->tenantGateway->parameters ?? $gateway->parameters,
                    'supported_currencies' => $gateway->tenantGateway->supported_currencies ?? $gateway->supported_currencies,
                    'currency'             => $gateway->tenantGateway->currency ?? null,
                ];
            });
        return view('backend.admin.automatic_method.list', compact('paymentGateways', 'alert_col'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $alert_col      = 'col-lg-8 offset-lg-2';
        $paymentgateway = AutomaticGateway::withOutGlobalScopes()
            ->whereRaw('(tenant_id = ? OR tenant_id IS NULL)', request()->tenant->id)
            ->where('id', $id)
            ->first();

        return view('backend.admin.automatic_method.edit', compact('paymentgateway', 'id', 'alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $tenant, $id) {
        $validator = Validator::make($request->all(), [
            'name'          => 'required',
            'image'         => 'nullable|image',
            'status'        => 'required',
            'exchange_rate' => 'required_if:status,1',
        ], [
            'exchange_rate.required_if' => _lang('Exchange rate is required'),
        ]);

        if ($validator->fails()) {
            return redirect()->route('payment_gateways.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/backend/images/gateways/", $image);
        }

        DB::beginTransaction();

        $defaultGateway = AutomaticGateway::withoutGlobalScopes()->find($id);

        $paymentgateway = $defaultGateway;
        if ($defaultGateway->tenant_id != request()->tenant->id) {
            $paymentgateway            = $paymentgateway->replicate();
            $paymentgateway->tenant_id = request()->tenant->id;
        }

        $parameters = [];
        if (! empty($paymentgateway->parameters)) {
            $i = 0;
            foreach ($paymentgateway->parameters as $parameter => $value) {
                $parameters[$parameter] = $request->parameter_value[$parameter] != null ? $request->parameter_value[$parameter] : '';
            }
        }

        $paymentgateway->name = $request->input('name');
        if ($request->hasfile('image')) {
            $paymentgateway->image = $image;
        }
        $paymentgateway->status        = $request->input('status');
        $paymentgateway->currency      = $request->input('currency');
        $paymentgateway->parameters    = json_encode($parameters);
        $paymentgateway->exchange_rate = $request->exchange_rate;

        $paymentgateway->save();

        //Store charge and limits
        $paymentgateway->chargeLimits()->whereNotIn('id', $request->limit_id)->delete();

        if ($request->has('minimum_amount')) {
            foreach ($request->minimum_amount as $key => $value) {

                if (isset($request->limit_id[$key])) {
                    $chargeLimits = ChargeLimit::firstOrNew(['id' => $request->limit_id[$key]]);
                } else {
                    $chargeLimits = new ChargeLimit();
                }

                $chargeLimits->minimum_amount       = $request->minimum_amount[$key];
                $chargeLimits->maximum_amount       = $request->maximum_amount[$key];
                $chargeLimits->fixed_charge         = $request->fixed_charge[$key];
                $chargeLimits->charge_in_percentage = $request->percent_charge[$key];
                $chargeLimits->gateway_id           = $paymentgateway->id;
                $chargeLimits->gateway_type         = get_class($paymentgateway);
                $chargeLimits->save();
            }
        }

        DB::commit();

        return redirect()->route('automatic_methods.index')->with('success', _lang('Updated Successfully'));

    }

}