<?php
namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller {

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
        $assets    = ['datatable'];
        $currencys = Currency::all();
        return view('backend.admin.currency.list', compact('currencys', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.currency.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'full_name'     => 'required|max:50',
            'name'          => 'required|min:3|max:3',
            'exchange_rate' => 'required|numeric',
            'base_currency' => 'required',
            'status'        => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('currency.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->input('base_currency') == '1') {
            $currency = Currency::where('base_currency', '1')->first();
            if ($currency) {
                $currency->base_currency = 0;
                $currency->save();
            }
        }

        $currency                = new Currency();
        $currency->full_name     = $request->input('full_name');
        $currency->name          = $request->input('name');
        $currency->exchange_rate = $request->input('base_currency') == 0 ? $request->input('exchange_rate') : 1;
        $currency->base_currency = $request->input('base_currency');
        $currency->status        = $request->input('status');

        $currency->save();

        //Prefix Output
        $currency->base_currency = $currency->base_currency == '1' ? show_status(_lang('Yes'), 'success') : show_status(_lang('No'), 'danger');
        $currency->status        = status($currency->status);

        if (! $request->ajax()) {
            return redirect()->route('currency.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $currency, 'table' => '#currency_table']);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $currency = Currency::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.currency.modal.view', compact('currency', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $currency = Currency::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.currency.modal.edit', compact('currency', 'id'));
        }
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
            'full_name'     => 'required|max:50',
            'name'          => 'required|min:3|max:3',
            'exchange_rate' => 'required|numeric',
            'base_currency' => 'required',
            'status'        => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('currency.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->input('base_currency') == '1') {
            $base_currency = Currency::where('base_currency', '1')->first();
            if ($base_currency) {
                $base_currency->base_currency = 0;
                $base_currency->save();
            }
        } else {
            $base_currency = Currency::where('base_currency', '1')->where('id', '!=', $id)->first();
            if (! $base_currency) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => _lang('Please create a base currency before updating this currency !')]);
                } else {
                    return redirect()->route('currency.index')->with('error', _lang('Please create a base currency before updating this currency !'));
                }
            }
        }

        $currency                = Currency::find($id);
        $currency->full_name     = $request->input('full_name');
        $currency->name          = $request->input('name');
        $currency->exchange_rate = $request->input('base_currency') == 0 ? $request->input('exchange_rate') : 1;
        $currency->base_currency = $request->input('base_currency');
        $currency->status        = $request->input('status');

        $currency->save();

        //Prefix Output
        $currency->base_currency = $currency->base_currency == '1' ? show_status(_lang('Yes'), 'success') : show_status(_lang('No'), 'danger');
        $currency->status        = status($currency->status);

        if (! $request->ajax()) {
            return redirect()->route('currency.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $currency, 'table' => '#currency_table']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $currency = Currency::find($id);
        if ($currency->base_currency == 1) {
            return redirect()->route('currency.index')->with('error', _lang('You can not remove base currency !'));
        }
        $currency->delete();
        return redirect()->route('currency.index')->with('success', _lang('Deleted Successfully'));
    }
}