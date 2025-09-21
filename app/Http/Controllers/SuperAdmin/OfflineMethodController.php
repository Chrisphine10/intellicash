<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Validator;

class OfflineMethodController extends Controller {

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
        $assets          = ['datatable'];
        $paymentGateways = PaymentGateway::offline()->orderBy('name')->get();
        return view('backend.super_admin.offline_methods.list', compact('paymentGateways', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $alert_col = "col-lg-10 offset-lg-1";
        $assets    = ['summernote'];
        return view('backend.super_admin.offline_methods.create', compact('alert_col', 'assets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'         => 'required',
            'image'        => 'nullable|image|max:2048',
            'status'       => 'required',
            'instructions' => '',
            'field_name.*' => 'required',
            'field_type.*' => 'required',
            'max_size.*'   => 'required|numeric',
        ], [
            'field_name.*.required' => _lang('Field name is required'),
            'field_type.*.required' => _lang('File type is required'),
            'max_size.*.required'   => _lang('Max size is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.offline_methods.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $form_fields = [];
        if ($request->has('field_name')) {
            for ($i = 0; $i < count($request->field_name); $i++) {
                $form_field                             = [];
                $form_field['field_label']              = $request->field_name[$i];
                $form_field['field_name']               = strtolower(str_replace(' ', '_', xss_clean($request->field_name[$i])));
                $form_field['field_type']               = $request->field_type[$i];
                $form_field['validation']               = $request->validation[$i];
                $form_field['max_size']                 = $request->max_size[$i];
                $form_fields[$form_field['field_name']] = $form_field;
            }
        }

        $image = 'default-gateway.png';
        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $gateway               = new PaymentGateway();
        $gateway->name         = $request->input('name');
        $gateway->slug         = strtolower(str_replace(' ', '_', $request->input('name')));
        $gateway->type         = 0;
        $gateway->image        = $image;
        $gateway->status       = $request->input('status');
        $gateway->status       = $request->input('status');
        $gateway->parameters   = json_encode($form_fields);
        $gateway->instructions = $request->input('instructions');

        $gateway->save();

        if (! $request->ajax()) {
            return redirect()->route('admin.offline_methods.index')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $paymentGateway, 'table' => '#affiliate_payout_methods_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $paymentGateway = PaymentGateway::offline()->find($id);
        $alert_col      = "col-lg-10 offset-lg-1";
        $assets         = ['summernote'];
        return view('backend.super_admin.offline_methods.edit', compact('paymentGateway', 'id', 'alert_col', 'assets'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'name'         => 'required',
            'image'        => 'nullable|image|max:2048',
            'status'       => 'required',
            'instructions' => '',
            'field_name.*' => 'required',
            'field_type.*' => 'required',
            'max_size.*'   => 'required|numeric',
        ], [
            'field_name.*.required' => _lang('Field name is required'),
            'field_type.*.required' => _lang('File type is required'),
            'max_size.*.required'   => _lang('Max size is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.offline_methods.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $form_fields = [];
        if ($request->has('field_name')) {
            for ($i = 0; $i < count($request->field_name); $i++) {
                $form_field                             = [];
                $form_field['field_label']              = $request->field_name[$i];
                $form_field['field_name']               = strtolower(str_replace(' ', '_', xss_clean($request->field_name[$i])));
                $form_field['field_type']               = $request->field_type[$i];
                $form_field['validation']               = $request->validation[$i];
                $form_field['max_size']                 = $request->max_size[$i];
                $form_fields[$form_field['field_name']] = $form_field;
            }
        }

        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $gateway       = PaymentGateway::offline()->find($id);
        $gateway->name = $request->input('name');
        if ($gateway->slug == '') {
            $gateway->slug = strtolower(str_replace(' ', '_', $request->input('name')));
        }
        if ($request->hasfile('image')) {
            $gateway->image = $image;
        }
        $gateway->status       = $request->input('status');
        $gateway->parameters   = json_encode($form_fields);
        $gateway->instructions = $request->input('instructions');
        $gateway->save();

        if (! $request->ajax()) {
            return redirect()->route('admin.offline_methods.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $paymentGateway, 'table' => '#affiliate_payout_methods_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $paymentGateway = PaymentGateway::offline()->find($id);
        $paymentGateway->delete();
        return redirect()->route('admin.offline_methods.index')->with('success', _lang('Deleted Successfully'));
    }

}