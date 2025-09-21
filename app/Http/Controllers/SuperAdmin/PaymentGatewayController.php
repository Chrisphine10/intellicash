<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Currency;
use Illuminate\Http\Request;
use App\Models\PaymentGateway;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayController extends Controller {

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
        $paymentgateways = PaymentGateway::online()->get();
        return view('backend.super_admin.payment_gateway.list', compact('paymentgateways'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        return view('backend.super_admin.payment_gateway.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'   => 'required',
            'slug'   => 'required',
            'image'  => 'nullable|image',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.payment_gateways.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $image = '';
        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/backend/images/gateways/", $image);
        }

        $parameters = array();
        if (!empty($request->parameter_name)) {
            $i = 0;
            foreach ($request->parameter_name as $parameter) {
                $parameters[$parameter] = $request->parameter_value[$i] != null ? $request->parameter_value[$i] : '';
            }
        }

        $paymentgateway                       = new PaymentGateway();
        $paymentgateway->name                 = $request->input('name');
        $paymentgateway->slug                 = $request->input('slug');
        $paymentgateway->image                = $image;
        $paymentgateway->status               = $request->input('status');
        $paymentgateway->parameters           = json_encode($parameters);
        $paymentgateway->supported_currencies = $request->input('supported_currencies');
        $paymentgateway->extra                = $request->input('extra');

        $paymentgateway->save();

        if (!$request->ajax()) {
            return redirect()->route('admin.payment_gateways.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $paymentgateway, 'table' => '#payment_gateways_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $alert_col      = 'col-lg-8 offset-lg-2';
        $paymentgateway = PaymentGateway::online()->find($id);
        return view('backend.super_admin.payment_gateway.edit', compact('paymentgateway', 'id', 'alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {

        $rules    = ['status' => 'required'];
        $messages = [];
        foreach ($request->parameter_value as $key => $val) {
            if ($key == 'status') {continue;}
            $rules['parameter_value.' . $key]                     = "required_if:status,1";
            $messages['parameter_value.' . $key . '.required_if'] = ucwords(str_replace("_", " ", $key)) . ' ' . _lang("is required");
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.payment_gateways.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $paymentgateway = PaymentGateway::online()->find($id);

        $parameters = array();
        if (!empty($paymentgateway->parameters)) {
            $i = 0;
            foreach ($paymentgateway->parameters as $parameter => $value) {
                $parameters[$parameter] = $request->parameter_value[$parameter] != null ? $request->parameter_value[$parameter] : '';
            }
        }

        $paymentgateway->status        = $request->input('status');
        $paymentgateway->parameters    = json_encode($parameters);

        $paymentgateway->save();

        if (!$request->ajax()) {
            return redirect()->route('admin.payment_gateways.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $paymentgateway, 'table' => '#payment_gateways_table']);
        }

    }

}